<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Tests\Fixtures\Order;
use Tests\TestCase;
use Wnikk\LaravelAccessRules\Facades\Access;

/**
 * The tools of 3.x behind the screens: the editor of conditions, "why?", health and XACML.
 */
class ToolsTest extends TestCase
{
    public function test_the_vocabulary_names_what_a_condition_may_read(): void
    {
        $data = $this->api('GET', '/conditions/vocabulary')->assertOk()->json('data');

        $this->assertSame(['order', 'item'], array_keys($data['resources']));
        $this->assertSame(Order::class, $data['resources']['order']['model']);
        $this->assertEqualsCanonicalizing(['id', 'cost', 'status', 'locked'], $data['resources']['order']['columns']);
        $this->assertSame(['items' => 'HasMany'], $data['resources']['order']['relations']);
        $this->assertContains('user.tenant', $data['user']);
        $this->assertContains('env.weekday', $data['env']);
        $this->assertContains('isAuthor()', $data['functions']['author']);
    }

    public function test_a_condition_is_checked_without_being_saved(): void
    {
        $this->api('POST', '/conditions/check', ['when' => 'order.cost > 100 and exists(order.items, price > 5)', 'resource' => 'order'])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.text', 'order.cost > 100 && exists(order.items, price > 5)')
            ->assertJsonPath('data.aggregates', true);

        $this->api('POST', '/conditions/check', ['when' => 'order.cost > 100', 'resource' => 'order'])->assertJsonPath('data.aggregates', false);

        $this->api('POST', '/conditions/check', ['when' => 'order.nothing.here > 1', 'resource' => 'order'])
            ->assertStatus(422)->assertJsonPath('code', 'condition');

        $this->api('POST', '/conditions/check', ['when' => '', 'resource' => 'order'])->assertOk()->assertJsonPath('data.text', null);
    }

    public function test_why_a_check_answers_what_it_answers(): void
    {
        $ids = $this->seedAccess();

        $report = $this->api('GET', '/explain?owner='.$ids['user'].'&ability=orders.view&record=order:3')->assertOk()->json('data');

        $this->assertFalse($report['decision']);
        $this->assertSame('record', $report['asked']);
        $this->assertSame(['prohibit', 'order.locked == true', true], [$report['entries'][0]['effect'], $report['entries'][0]['condition'], $report['entries'][0]['decisive']]);
        $this->assertSame('Role manager (Managers)', $report['entries'][0]['from']);

        $this->api('GET', '/explain?owner='.$ids['user'].'&ability=orders.view&record=order')->assertOk()->assertJsonPath('data.asked', 'class')->assertJsonPath('data.decision', true);
        $this->api('GET', '/explain?owner='.$ids['user'].'&ability=orders.view')->assertOk()->assertJsonPath('data.decision', null);
        $this->api('GET', '/explain?owner='.$ids['user'].'&ability=orders.view&record=invoice:1')->assertStatus(422);
        $this->api('GET', '/explain?owner='.$ids['user'].'&ability=orders.view&record=order:99')->assertNotFound();
    }

    public function test_health_reports_what_a_migration_broke_and_can_fix_types(): void
    {
        $this->seedAccess();

        $this->api('GET', '/health')->assertOk()->assertJsonPath('data.problems', [])->assertJsonPath('data.write', true);

        Schema::table('orders', fn ($table) => $table->renameColumn('cost', 'total'));

        $problems = $this->api('GET', '/health')->assertOk()->json('data.problems');
        $this->assertCount(1, $problems);
        $this->assertSame(['permission', 'condition'], [$problems[0]['subject'], $problems[0]['code']]);
        $this->assertStringContainsString('"cost" is not a column', $problems[0]['problem']);

        $this->api('POST', '/health/fix')->assertOk()->assertJsonPath('data.fixed', 0);
        $this->api('POST', '/cache/flush')->assertOk();
    }

    public function test_xacml_export_check_and_import_from_the_browser(): void
    {
        $ids = $this->seedAccess();

        $document = $this->get('/access-control/xacml/export')->assertOk()->assertHeader('Content-Type', 'application/xml')->streamedContent();
        $this->assertStringStartsWith('<?xml', $document);
        $this->assertStringContainsString('urn:wnikk:access:manifest', $document, 'one file: the manifest set travels inside');

        $upload = fn () => UploadedFile::fake()->createWithContent('access.xml', $document);

        // Unchanged: every change is "same"
        $plan = $this->api('POST', '/xacml/check', ['policy' => $upload()])->assertOk()->json('data');
        $this->assertSame([], $plan['errors']);
        $this->assertNotNull($plan['exported_at'], 'the plan says when the document was exported');
        $this->assertSame(['same'], array_values(array_unique(array_column($plan['changes'], 'action'))));

        // Changed in between: the plan says what differs, and nothing is written by a check
        Access::for('Role', 'manager')->removeAllow('orders.view');
        Access::for('Role', 'manager')->allow('orders.view', when: 'order.cost > 999');

        $plan    = $this->api('POST', '/xacml/check', ['policy' => $upload()])->assertOk()->json('data');
        $differs = collect($plan['changes'])->firstWhere('action', 'differs');
        $this->assertSame(['order.cost > 100', 'order.cost > 999'], [$differs['document'], $differs['database']]);
        $this->assertFalse(Access::for('App\Models\User', 7)->can('orders.view', Order::find(1)) ?? false);

        // Without "replace" what differs stays; with it the document wins
        $this->api('POST', '/xacml/import', ['policy' => $upload()])->assertOk()->assertJsonPath('data.applied.replaced', 0);
        $this->api('POST', '/xacml/import', ['policy' => $upload(), 'replace' => 1])->assertOk()->assertJsonPath('data.applied.replaced', 1);
        $this->assertTrue(Access::for('App\Models\User', 7)->can('orders.view', Order::find(1)));

        $this->api('POST', '/xacml/check', [])->assertStatus(422)->assertJsonValidationErrors('policy');
        // A document the core refuses is a report with errors and no writes, not a crash
        $refused = $this->api('POST', '/xacml/check', ['policy' => UploadedFile::fake()->createWithContent('x.xml', '<!DOCTYPE x [<!ENTITY e "e">]><a/>')])->assertOk()->json('data');
        $this->assertNotSame([], $refused['errors']);
        $this->assertFalse($refused['written']);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\Fixtures\Order;
use Tests\TestCase;
use Wnikk\LaravelAccessRules\Facades\Access;

/**
 * The permissions screen: every rule with the rows that reach the owner, the label of a rule
 * read by the five steps of the core, and writing one row at a time.
 */
class PermissionsTest extends TestCase
{
    public function test_the_matrix_shows_own_and_inherited_rows_with_conditions_and_a_label(): void
    {
        $ids = $this->seedAccess();
        Access::for('App\Models\User', 7)->allow('orders.view', when: 'order.cost > 400');

        $data = $this->api('GET', '/owners/'.$ids['user'].'/permissions')->assertOk()->json('data');
        $this->assertSame('Ann', $data['owner']['title']);

        $rules = array_column($data['list'], null, 'guard_name');
        $view  = $rules['orders.view'];

        // Strongest first: own permit, then the prohibition and the permit of the role
        $this->assertSame(
            [['allow', 'order.cost > 400', true, null], ['deny', 'order.locked == true', false, 'Managers'], ['allow', 'order.cost > 100', false, 'Managers']],
            array_map(static fn (array $e): array => [$e['effect'], $e['when'], $e['own'], $e['from']], $view['entries']),
        );
        $this->assertSame(['conditional', true], [$view['summary']['state'], $view['summary']['own']]);

        // An option-bearing rule is granted value by value: the label says so, the rows say csv
        $this->assertSame(['options', false], [$rules['orders.export']['summary']['state'], $rules['orders.export']['summary']['own']]);
        $this->assertSame([['allow', 'csv']], array_map(static fn (array $e): array => [$e['effect'], $e['option']], $rules['orders.export']['entries']));

        $this->assertSame([], $rules['orders']['entries']);
        $this->assertNull($rules['orders']['summary']['state']);
    }

    public function test_the_label_follows_the_five_steps_of_the_core(): void
    {
        $ids = $this->seedAccess();
        Access::newRule('news.view');
        $label = fn () => collect($this->api('GET', '/owners/'.$ids['user'].'/permissions')->json('data.list'))->firstWhere('guard_name', 'news.view')['summary'];

        Access::for('Role', 'manager')->allow('news.view');
        $this->assertSame(['state' => 'allowed', 'own' => false], $label());

        Access::for('Role', 'manager')->deny('news.view');
        $this->assertSame(['state' => 'forbidden', 'own' => false], $label());

        Access::for('App\Models\User', 7)->allow('news.view');
        $this->assertSame(['state' => 'allowed', 'own' => true], $label());

        Access::for('App\Models\User', 7)->deny('news.view');
        $this->assertSame(['state' => 'forbidden', 'own' => true], $label());

        // The label agrees with the core, which is the promise behind it
        $this->assertFalse(Access::for('App\Models\User', 7)->can('news.view'));
    }

    public function test_a_permit_and_a_prohibition_are_written_and_removed_apart(): void
    {
        $ids = $this->seedAccess();
        $ann = fn () => Access::for('App\Models\User', 7);

        $this->api('POST', '/owners/'.$ids['user'].'/permissions', ['rule' => $ids['rule'], 'effect' => 'allow', 'when' => 'order.cost > 400'])->assertOk();
        $this->api('POST', '/owners/'.$ids['user'].'/permissions', ['rule' => $ids['rule'], 'effect' => 'deny', 'when' => "order.status == 'paid'"])->assertOk();

        $this->assertSame([1, 3], Order::query()->allowedTo('orders.view', $ann())->orderBy('id')->pluck('id')->all(), 'the own permit brings the locked order back, the own prohibition hides the paid one');

        // The same row again replaces its condition instead of failing as a duplicate
        $this->api('POST', '/owners/'.$ids['user'].'/permissions', ['rule' => $ids['rule'], 'effect' => 'allow', 'when' => 'order.cost > 800'])->assertOk();
        $this->assertSame('order.cost > 800', collect($this->api('GET', '/owners/'.$ids['user'].'/permissions')->json('data.list'))->firstWhere('guard_name', 'orders.view')['entries'][1]['when'], 'the own prohibition stands above it');
        $this->assertSame([1, 3], Order::query()->allowedTo('orders.view', $ann())->orderBy('id')->pluck('id')->all(), 'order 1 through the role, order 3 through the new own permit');

        $this->api('DELETE', '/owners/'.$ids['user'].'/permissions', ['rule' => $ids['rule'], 'effect' => 'allow'])->assertOk();
        $this->assertSame([1], Order::query()->allowedTo('orders.view', $ann())->pluck('id')->all(), 'the prohibition of the owner and the rows of the role remain');

        $this->api('DELETE', '/owners/'.$ids['user'].'/permissions', ['rule' => $ids['rule'], 'effect' => 'allow'])->assertNotFound();
    }

    public function test_refusals_of_the_core_reach_the_screen_by_code(): void
    {
        $ids    = $this->seedAccess();
        $export = collect($this->api('GET', '/rules')->json('data.list'))->firstWhere('guard_name', 'orders.export')['id'];

        $this->api('POST', '/owners/'.$ids['user'].'/permissions', ['rule' => $export, 'effect' => 'allow', 'option' => 'xml'])
            ->assertStatus(422)->assertJsonPath('code', 'invalid_option');

        $this->api('POST', '/owners/'.$ids['user'].'/permissions', ['rule' => $ids['rule'], 'effect' => 'allow', 'when' => 'order.nothing.here == 1'])
            ->assertStatus(422)->assertJsonPath('code', 'condition');

        $this->api('POST', '/owners/'.$ids['user'].'/permissions', ['rule' => $export, 'effect' => 'allow', 'option' => 'pdf'])->assertOk();
        $this->assertTrue(Access::for('App\Models\User', 7)->can('orders.export.pdf'));
    }

    /**
     * A plain permit next to a prohibition with a condition: the prohibition is the stronger
     * step and wins for the records it is true for, so the rule depends on the record. Reading
     * the plain row first would say "allowed" and hide the locked orders.
     */
    public function test_a_conditional_row_of_the_stronger_step_makes_the_rule_depend_on_the_record(): void
    {
        $ids   = $this->seedAccess();
        $label = fn () => collect($this->api('GET', '/owners/'.$ids['role'].'/permissions')->json('data.list'))->firstWhere('guard_name', 'orders.view')['summary']['state'];

        Access::for('Role', 'manager')->removeAllow('orders.view');
        Access::for('Role', 'manager')->allow('orders.view');
        $this->assertSame('conditional', $label(), 'own prohibition with a condition over a plain own permit');

        Access::for('Role', 'manager')->removeDeny('orders.view');
        $this->assertSame('allowed', $label());

        Access::for('Role', 'manager')->deny('orders.view');
        $this->assertSame('forbidden', $label(), 'a plain prohibition of the same step decides');
    }

    /**
     * With rule_tree_inheritance on, a row on "reports" reaches "reports.sales" in a check. The
     * matrix shows that row under the rule it reaches, marked with the rule it sits on, so the
     * label agrees with the check; the card counts the row once.
     */
    public function test_a_row_reaches_the_rules_below_it_when_the_tree_inherits(): void
    {
        $ids     = $this->seedAccess();
        $reports = Access::newRule('reports', 'Reports');
        Access::newRule('reports.sales', 'Sales', null, $reports);
        Access::for('Role', 'manager')->allow('reports');

        $sales = fn () => collect($this->api('GET', '/owners/'.$ids['user'].'/permissions')->json('data.list'))->firstWhere('guard_name', 'reports.sales');

        $this->assertSame([], $sales()['entries'], 'without the option the tree groups and passes nothing');
        $this->assertNull($sales()['summary']['state']);

        config(['access.rule_tree_inheritance' => true]);
        $row = $sales();
        $this->assertSame(['allowed', false], [$row['summary']['state'], $row['summary']['own']]);
        $this->assertSame(['tree', 'reports', 'allow', false], [$row['entries'][0]['via'], $row['entries'][0]['via_rule'], $row['entries'][0]['effect'], $row['entries'][0]['own']]);

        $counts = $this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->json('data.permissions');
        $this->assertSame(4, $counts['inherited'], 'the row on reports counts once');
        config(['access.rule_tree_inheritance' => false]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Wnikk\LaravelAccessRules\Facades\Access;

/**
 * The rules screen: the list with origins, creating a custom rule, what a rule of code lets an
 * administrator change, and the two refusals of deleting.
 */
class RulesTest extends TestCase
{
    public function test_the_list_shows_every_rule_with_its_origin_condition_and_holders(): void
    {
        $this->seedAccess();

        $data = $this->api('GET', '/rules')->assertOk()->json('data');

        $this->assertSame(['order', 'item'], $data['resources']);
        $this->assertTrue($data['write']);

        $rules = array_column($data['list'], null, 'guard_name');
        $this->assertEqualsCanonicalizing(['orders', 'orders.view', 'orders.export', 'news.edit.sport'], array_keys($rules));
        $this->assertSame(['code', true, 'order', 2], [$rules['orders.view']['origin'], $rules['orders.view']['managed'], $rules['orders.view']['resource'], $rules['orders.view']['holders_count']]);
        $this->assertSame(['custom', false], [$rules['news.edit.sport']['origin'], $rules['news.edit.sport']['managed']]);
        $this->assertSame('required|in:csv,pdf', $rules['orders.export']['options']);
    }

    public function test_a_rule_created_from_the_panel_is_custom_and_may_carry_a_condition(): void
    {
        $this->seedAccess();

        $this->api('POST', '/rules', ['guard_name' => 'orders.archive', 'title' => 'Archive', 'resource' => 'order', 'when' => 'not order.locked'])
            ->assertOk()->assertJsonPath('ok', true);

        $rule = collect($this->api('GET', '/rules')->json('data.list'))->firstWhere('guard_name', 'orders.archive');
        $this->assertSame(['custom', '!(order.locked == true)'], [$rule['origin'], $rule['when']]);

        // A mistake in the condition is refused with the words of the core, nothing is created
        $this->api('POST', '/rules', ['guard_name' => 'orders.broken', 'resource' => 'order', 'when' => 'order.nothing.here > 1'])
            ->assertStatus(422)->assertJsonPath('code', 'condition')->assertJsonPath('ok', false);
        $this->assertNull(collect($this->api('GET', '/rules')->json('data.list'))->firstWhere('guard_name', 'orders.broken'));

        // and so is a duplicate name, an unknown resource, and an option spec that does not compile
        $this->api('POST', '/rules', ['guard_name' => 'orders.view'])->assertStatus(422)->assertJsonValidationErrors('guard_name');
        $this->api('POST', '/rules', ['guard_name' => 'x', 'resource' => 'invoice'])->assertStatus(422)->assertJsonValidationErrors('resource');
        $this->api('POST', '/rules', ['guard_name' => 'x', 'options' => 'no_such_rule:1'])->assertStatus(422)->assertJsonValidationErrors('options');
    }

    public function test_a_rule_of_code_may_change_how_it_reads_and_nothing_else(): void
    {
        $ids = $this->seedAccess();
        $all = fn () => array_column($this->api('GET', '/rules')->json('data.list'), null, 'guard_name');

        // Title, description, options and the place in the tree are open; unchanged fields are not sent to the core
        $this->api('PUT', '/rules/'.$ids['rule'], ['guard_name' => 'orders.view', 'title' => 'See orders', 'description' => 'Everything about viewing', 'resource' => 'order', 'parent_id' => 0])
            ->assertOk();
        $this->assertSame('See orders', $all()['orders.view']['title']);

        // The place in the tree is how the list reads, so it is open for a rule of code too
        $this->api('PUT', '/rules/'.$ids['rule'], ['guard_name' => 'orders.view', 'title' => 'See orders', 'description' => 'Everything about viewing', 'resource' => 'order', 'parent_id' => 0])->assertOk();
        $this->assertSame(0, $all()['orders.view']['parent_id']);
        $this->api('PUT', '/rules/'.$ids['rule'], ['guard_name' => 'orders.view', 'title' => 'See orders', 'description' => 'Everything about viewing', 'resource' => 'order', 'parent_id' => $all()['orders']['id']])->assertOk();
        $this->assertSame($all()['orders']['id'], $all()['orders.view']['parent_id']);

        // unless the tree decides what a permission covers: then the place is the code's, like the name
        config(['access.rule_tree_inheritance' => true]);
        $this->api('PUT', '/rules/'.$ids['rule'], ['guard_name' => 'orders.view', 'title' => 'See orders', 'description' => 'Everything about viewing', 'resource' => 'order', 'parent_id' => 0])
            ->assertStatus(403)->assertJsonPath('code', 'rule_managed_by_code')->assertJsonPath('errors.core.0', fn (string $why) => str_contains($why, 'rule_tree_inheritance'));
        config(['access.rule_tree_inheritance' => false]);

        // The name is not: the core refuses, and the refusal names the reason by code
        $this->api('PUT', '/rules/'.$ids['rule'], ['guard_name' => 'orders.see', 'title' => 'See orders'])
            ->assertStatus(403)->assertJsonPath('code', 'rule_managed_by_code');
        $this->assertArrayHasKey('orders.view', $all());

        // A custom rule is open completely, including its place in the tree
        $custom = $all()['news.edit.sport']['id'];
        $parent = $all()['orders']['id'];
        $this->api('PUT', '/rules/'.$custom, ['guard_name' => 'news.edit.football', 'parent_id' => $parent])->assertOk();
        $this->assertSame($parent, $all()['news.edit.football']['parent_id']);

        // but not inside its own branch
        $this->api('PUT', '/rules/'.$parent, ['guard_name' => 'orders', 'parent_id' => $custom])->assertStatus(422)->assertJsonValidationErrors('parent_id');
    }

    public function test_deleting_is_refused_for_code_and_for_a_rule_somebody_holds(): void
    {
        $ids = $this->seedAccess();
        $all = fn () => array_column($this->api('GET', '/rules')->json('data.list'), null, 'guard_name');

        $this->api('DELETE', '/rules/'.$ids['rule'])->assertStatus(403)->assertJsonPath('code', 'rule_managed_by_code');

        $custom = $all()['news.edit.sport']['id'];
        Access::for('Role', 'manager')->allow('news.edit.sport');

        // Held: refused, and the answer lists who holds it, so the screen can send the administrator there
        $response = $this->api('DELETE', '/rules/'.$custom)->assertStatus(409)->assertJsonPath('code', 'rule_in_use');
        $this->assertSame(['Managers'], array_column($response->json('data.rows'), 'title'));

        $this->api('GET', '/rules/'.$custom.'/holders')->assertOk()->assertJsonPath('data.rows.0.effect', 'allow')->assertJsonPath('data.meta.total', 1);

        Access::for('Role', 'manager')->removeAllow('news.edit.sport');
        $this->api('DELETE', '/rules/'.$custom)->assertOk();
        $this->assertArrayNotHasKey('news.edit.sport', $all());
    }

    public function test_a_read_only_screen_refuses_every_write(): void
    {
        $ids = $this->seedAccess();
        config(['accessUi.screens.rules.write' => false]);

        $this->api('GET', '/rules')->assertOk()->assertJsonPath('data.write', false);
        $this->api('POST', '/rules', ['guard_name' => 'x'])->assertForbidden();
        $this->api('PUT', '/rules/'.$ids['rule'], ['guard_name' => 'orders.view'])->assertForbidden();
        $this->api('DELETE', '/rules/'.$ids['rule'])->assertForbidden();
    }
}

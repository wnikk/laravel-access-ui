<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;
use Wnikk\LaravelAccessRules\Contracts\Owner as OwnerContract;
use Wnikk\LaravelAccessRules\Facades\Access;

/**
 * Inheritance from either end, the widget's numbers, and the picker behind long lists.
 */
class InheritTest extends TestCase
{
    public function test_parents_of_an_owner_with_indirect_ones_marked_and_the_counts_of_the_widget(): void
    {
        $ids = $this->seedAccess();
        Access::for('Role', 'staff')->create('Staff');
        Access::for('Role', 'staff')->allow('orders.export', 'pdf');
        Access::for('Role', 'manager')->inheritFrom('Role', 'staff');
        Access::for('App\Models\User', 7)->deny('orders.export', 'csv');

        $data = $this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->assertOk()->json('data');

        $this->assertSame('parents', $data['direction']);
        $this->assertSame([['Managers', true, null], ['Staff', false, $ids['role']]], array_map(static fn (array $r): array => [$r['title'], $r['direct'], $r['through']], $data['list']));
        $this->assertSame(['own' => 1, 'inherited' => 4, 'conditional' => 2, 'forbidden' => 2], $data['permissions']);

        // What may still be assigned: assignable entities, minus what is linked already
        $this->assertSame(['assignable', false], [$data['available']['scope'], $data['available']['truncated']]);
        $this->assertSame(['Staff'], array_column($data['available']['list'], 'title'));
    }

    public function test_children_of_a_role_draw_candidates_from_every_owner(): void
    {
        $ids = $this->seedAccess();
        Access::for('Team', 1)->create('North');

        $data = $this->api('GET', '/owners/'.$ids['role'].'/inherit?direction=children')->assertOk()->json('data');

        $this->assertSame(['Ann'], array_column($data['list'], 'title'));
        $this->assertSame('all', $data['available']['scope']);
        $this->assertSame(['North'], array_column($data['available']['list'], 'title'));
        $this->assertArrayNotHasKey('permissions', $data);
    }

    public function test_assigning_and_removing_go_through_the_core(): void
    {
        $ids   = $this->seedAccess();
        $staff = Access::for('Role', 'staff')->create('Staff');
        Access::for('Role', 'staff')->allow('orders.export', 'pdf');

        $this->api('POST', '/owners/'.$ids['user'].'/inherit', ['direction' => 'parents', 'target' => $staff->getKey()])->assertOk();
        $this->assertTrue(Access::for('App\Models\User', 7)->can('orders.export.pdf'));

        $this->api('POST', '/owners/'.$ids['user'].'/inherit', ['direction' => 'parents', 'target' => $staff->getKey()])->assertStatus(409);
        $this->api('POST', '/owners/'.$ids['user'].'/inherit', ['direction' => 'parents', 'target' => $ids['user']])->assertStatus(422);
        $this->api('POST', '/owners/'.$ids['user'].'/inherit', ['direction' => 'parents', 'target' => 999])->assertNotFound();

        // A loop is the core's refusal, by code
        $this->api('POST', '/owners/'.$staff->getKey().'/inherit', ['direction' => 'parents', 'target' => $ids['role']])->assertOk();
        $this->api('POST', '/owners/'.$ids['role'].'/inherit', ['direction' => 'parents', 'target' => $staff->getKey()])->assertStatus(422)->assertJsonPath('code', 'inheritance_loop');

        $link = collect($this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->json('data.list'))->firstWhere('title', 'Staff')['inheritance_id'];
        $this->api('DELETE', '/owners/'.$ids['user'].'/inherit/'.$link)->assertOk();
        $this->assertNull(Access::for('App\Models\User', 7)->can('orders.export.pdf'));

        // A link of somebody else cannot be removed through this owner
        $other = collect($this->api('GET', '/owners/'.$staff->getKey().'/inherit?direction=parents')->json('data.list'))->firstWhere('title', 'Managers')['inheritance_id'];
        $this->api('DELETE', '/owners/'.$ids['user'].'/inherit/'.$other)->assertNotFound();
    }

    public function test_only_assignable_types_may_be_handed_out_as_parents(): void
    {
        $ids  = $this->seedAccess();
        $team = Access::for('Team', 1)->create('North');

        $this->api('POST', '/owners/'.$ids['user'].'/inherit', ['direction' => 'parents', 'target' => $team->getKey()])->assertStatus(422);

        // From the other end anybody may receive: a team inherits from the role
        $this->api('POST', '/owners/'.$ids['role'].'/inherit', ['direction' => 'children', 'target' => $team->getKey()])->assertOk();
    }

    public function test_a_long_list_is_searched_through_the_picker(): void
    {
        $ids = $this->seedAccess();
        config(['accessUi.picker.inline_limit' => 2]);

        foreach (['a', 'b', 'c'] as $name) {
            Access::for('Role', $name)->create('Role '.$name);
        }

        $available = $this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->json('data.available');
        $this->assertSame([true, [], 3], [$available['truncated'], $available['list'], $available['total']]);

        $this->api('GET', '/pick?scope=assignable&search=Role&limit=2')->assertOk()->assertJsonPath('data.meta.total', 3)->assertJsonPath('data.meta.last_page', 2);
        $this->api('GET', '/pick?scope=assignable&exclude='.$ids['role'].',x,0')->assertOk()->assertJsonPath('data.meta.total', 3);
        $this->api('GET', '/pick?scope=all')->assertOk()->assertJsonPath('data.meta.total', 5);
        $this->api('GET', '/pick?scope=listed')->assertOk()->assertJsonPath('data.meta.total', 5);
    }

    /**
     * The card has a policy of its own on top of the inheritance screen: config widget.write and
     * widget.ability, checked for the owner of the card. The routes are shared with the screen,
     * so the server tells the two apart by the direction: giving this owner a source, or taking
     * one away, is the card; adding or removing an heir is the panel.
     */
    public function test_the_card_can_be_read_only_while_the_panel_still_assigns(): void
    {
        $ids = $this->seedAccess();
        Access::for('Role', 'staff')->create('Staff');
        $staff = Access::for('Role', 'staff')->record();
        config(['accessUi.widget.write' => false]);

        $this->assertFalse($this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->assertOk()->json('data.write'), 'the card shows and offers no buttons');
        $this->assertTrue($this->api('GET', '/owners/'.$ids['role'].'/inherit?direction=children')->assertOk()->json('data.write'), 'the panel still writes');

        $this->api('POST', '/owners/'.$ids['user'].'/inherit', ['direction' => 'parents', 'target' => $staff->getKey()])->assertForbidden();
        $this->api('POST', '/owners/'.$staff->getKey().'/inherit', ['direction' => 'children', 'target' => $ids['user']])->assertOk();

        $link = collect($this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->json('data.list'))->firstWhere('title', 'Staff')['inheritance_id'];
        $this->api('DELETE', '/owners/'.$ids['user'].'/inherit/'.$link)->assertForbidden();
        $this->api('DELETE', '/owners/'.$staff->getKey().'/inherit/'.$link)->assertOk();
    }

    public function test_the_ability_of_the_card_receives_the_owner(): void
    {
        $ids = $this->seedAccess();
        config(['accessUi.widget.ability' => 'assign-access']);

        // Whoever asks may assign to user 7 and to nobody else.
        Gate::define('assign-access', fn (?Authenticatable $user, OwnerContract $owner): bool => (string) $owner->original_id === '7');

        $this->assertTrue($this->api('GET', '/owners/'.$ids['user'].'/inherit?direction=parents')->json('data.write'));
        $this->assertFalse($this->api('GET', '/owners/'.$ids['role'].'/inherit?direction=parents')->json('data.write'));
        $this->api('POST', '/owners/'.$ids['role'].'/inherit', ['direction' => 'parents', 'target' => $ids['user']])->assertForbidden();
    }
}

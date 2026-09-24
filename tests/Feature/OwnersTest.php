<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Wnikk\LaravelAccessRules\Facades\Access;

/**
 * The owners screen: who is listed and why, what the panel may create, renaming, deleting
 * through the core, and who is affected by a change.
 */
class OwnersTest extends TestCase
{
    public function test_configured_types_and_anybody_holding_a_permission_are_listed(): void
    {
        $this->seedAccess();

        // A team is not an entity of the panel. It is listed once it holds something, marked unmanaged.
        Access::for('Team', 1)->create('North');
        Access::for('Team', 2)->create('South');
        Access::for('Team', 2)->allow('orders.view');

        $rows = array_column($this->api('GET', '/owners')->assertOk()->json('data.rows'), null, 'title');

        $this->assertSame(['Ann', 'Managers', 'South'], array_keys($rows));
        $this->assertSame(['role', true, 3, 0, 1], [$rows['Managers']['entity'], $rows['Managers']['managed'], $rows['Managers']['permissions_count'], $rows['Managers']['sources_count'], $rows['Managers']['inheritors_count']]);
        $this->assertSame([null, false, true, 'Team'], [$rows['South']['entity'], $rows['South']['managed'], $rows['South']['tenant'], $rows['South']['type_label']]);
        $this->assertSame(1, $rows['Ann']['sources_count']);

        $this->api('GET', '/owners?entity=role')->assertOk()->assertJsonPath('data.rows.0.title', 'Managers')->assertJsonPath('data.meta.total', 1);
        $this->api('GET', '/owners?search=ann')->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.rows.0.title', 'Ann');
        $this->api('GET', '/owners?entity=nowhere')->assertNotFound();
    }

    public function test_the_guest_owner_is_marked(): void
    {
        Access::for('Role', 'guest')->create('Guests');

        $this->api('GET', '/owners')->assertOk()->assertJsonPath('data.rows.0.guest', true);
    }

    public function test_the_panel_creates_what_config_lets_it_create(): void
    {
        $this->api('POST', '/owners', ['entity' => 'role', 'original_id' => 'editor', 'name' => 'Editors'])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('Editors', Access::for('Role', 'editor')->record()->name);

        $this->api('POST', '/owners', ['entity' => 'role', 'original_id' => 'editor'])->assertStatus(409);
        $this->api('POST', '/owners', ['entity' => 'user', 'original_id' => '9'])->assertForbidden();
        $this->api('POST', '/owners', ['entity' => 'nowhere', 'original_id' => 'x'])->assertNotFound();
    }

    public function test_renaming_changes_the_name_and_nothing_else(): void
    {
        $ids = $this->seedAccess();

        $this->api('PUT', '/owners/'.$ids['role'], ['name' => 'Sales managers'])->assertOk();

        $role = Access::for('Role', 'manager')->record();
        $this->assertSame(['manager', 'Sales managers'], [$role->original_id, $role->name]);
        $this->assertTrue(Access::for('App\Models\User', 7)->can('orders.export.csv'));
    }

    public function test_deleting_goes_through_the_core_and_takes_links_and_permissions_along(): void
    {
        $ids = $this->seedAccess();
        $this->assertTrue(Access::for('App\Models\User', 7)->can('orders.export.csv'));

        $this->api('DELETE', '/owners/'.$ids['role'])->assertOk()->assertJsonPath('message', 'Managers deleted');

        $this->assertNull(Access::for('Role', 'manager')->record());
        $this->assertNull(Access::for('App\Models\User', 7)->can('orders.export.csv'), 'what the role held is gone from its heirs');
        $this->api('GET', '/owners/'.$ids['role'].'/permissions')->assertNotFound();
    }

    public function test_heirs_are_everyone_that_inherits_at_any_depth(): void
    {
        $ids = $this->seedAccess();
        Access::for('Role', 'chief')->create('Chiefs');
        Access::for('Role', 'chief')->inheritFrom('Role', 'manager');
        Access::for('Group', 'g')->create('Group G');
        Access::for('Group', 'g')->inheritFrom('Role', 'chief');

        $titles = array_column($this->api('GET', '/owners/'.$ids['role'].'/heirs')->assertOk()->json('data.rows'), 'title');

        $this->assertEqualsCanonicalizing(['Ann', 'Chiefs', 'Group G'], $titles);
    }
}

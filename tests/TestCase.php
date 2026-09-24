<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Tests\Fixtures\Item;
use Tests\Fixtures\Order;
use Wnikk\LaravelAccessRules\AccessRules;
use Wnikk\LaravelAccessRules\AccessRulesServiceProvider;
use Wnikk\LaravelAccessRules\Facades\Access;
use Wnikk\LaravelAccessRules\Models\RuleOrigin;
use Wnikk\LaravelAccessUi\AccessUiServiceProvider;

/**
 * Base of every test: an application with the core and this package loaded, tables of the core in
 * place, and the panel registered under a prefix behind an "admin" gate.
 *
 * Tests treat src/ as a black box. They call the routes the way the bundled JavaScript does and
 * assert the JSON an application can observe, or the rows the core writes. A test written against
 * a controller proves the controller does what it does; a test against a route fails when the
 * promise of the documentation is broken, whichever class broke it.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Whether the request that follows may open the panel. Tests flip it to prove the gate is checked. */
    protected bool $admin = true;

    /** The middleware of the group. A test empties it and refreshes the application to prove nothing registers. */
    protected array $middleware = ['web', 'can:manage-access'];

    protected function setUp(): void
    {
        AccessRules::resetCacheState();
        parent::setUp();

        // Nullable on purpose: the tests run signed out, and Gate skips a callback that cannot take a guest.
        Gate::define('manage-access', fn (?Authenticatable $user = null): bool => $this->admin);
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('access', require __DIR__.'/../vendor/wnikk/laravel-access-rules/config/access.php');
        $app['config']->set('access.owner_types', ['App\Models\User', 'Role', 'Group', 'Team']);
        $app['config']->set('access.resources', ['order' => Order::class, 'item' => Item::class]);
        $app['config']->set('access.tenant_types', ['Team']);
        $app['config']->set('access.guest', ['type' => 'Role', 'id' => 'guest']);
        $app['config']->set('auth.providers.users.model', 'App\Models\User');

        $app['config']->set('accessUi', require __DIR__.'/../config/accessUi.php');
        $app['config']->set('accessUi.routes.prefix', 'access-control');
        $app['config']->set('accessUi.routes.middleware', $this->middleware);
    }

    /**
     * Tables of the core, created inside the transaction of the test as the core's own suite does.
     */
    protected function afterRefreshingDatabase(): void
    {
        (require __DIR__.'/../vendor/wnikk/laravel-access-rules/database/migrations/create_access_rules_tables.php.stub')->up();

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->integer('cost');
            $table->string('status');
            $table->boolean('locked')->default(false);
        });
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->integer('order_id');
            $table->integer('price');
        });
    }

    /**
     * A rule, a role that holds it, and an owner that inherits from the role: the smallest set every
     * screen has something to show for.
     *
     * @return array{rule:int, role:int, user:int}
     */
    protected function seedAccess(): array
    {
        Access::newRule('orders', 'Orders');
        $rule = Access::newRule('orders.view', 'View orders', 'Everything about viewing', null, null, 'order');
        Access::newRule('orders.export', 'Export', options: 'required|in:csv,pdf', resource: 'order');
        Access::newRule('news.edit.sport', 'Edit sport news', origin: RuleOrigin::Custom);

        Access::for('Role', 'manager')->create('Managers');
        Access::for('Role', 'manager')->allow('orders.view', when: 'order.cost > 100');
        Access::for('Role', 'manager')->deny('orders.view', when: 'order.locked');
        Access::for('Role', 'manager')->allow('orders.export', 'csv');

        Access::for('App\Models\User', 7)->create('Ann');
        Access::for('App\Models\User', 7)->inheritFrom('Role', 'manager');

        Order::query()->insert([
            ['id' => 1, 'cost' => 150, 'status' => 'draft', 'locked' => false],
            ['id' => 2, 'cost' => 50, 'status' => 'paid', 'locked' => false],
            ['id' => 3, 'cost' => 900, 'status' => 'draft', 'locked' => true],
        ]);

        return [
            'rule' => (int) $rule,
            'role' => (int) Access::for('Role', 'manager')->record()->getKey(),
            'user' => (int) Access::for('App\Models\User', 7)->record()->getKey(),
        ];
    }

    protected function getPackageProviders($app): array
    {
        return [AccessRulesServiceProvider::class, AccessUiServiceProvider::class];
    }

    /**
     * A request the way the bundle sends it: JSON in, JSON out, no CSRF (the "web" group of testbench
     * runs without the token check).
     */
    protected function api(string $method, string $path, array $data = []): TestResponse
    {
        return $this->json($method, '/access-control'.$path, $data);
    }
}

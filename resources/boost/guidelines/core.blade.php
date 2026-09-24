## Laravel Access UI (wnikk/laravel-access-ui)

The administration panel of `wnikk/laravel-access-rules` 3.x: rules, owners, permissions with conditions, inheritance, the explanation of one check, a health check of stored conditions, XACML export and import, and one card for a page of the application that shows an account. One script and one stylesheet published into `public/vendor/accessui`, nothing from a CDN.

### Conventions

- The panel registers no routes until `config/accessUi.php` names both `routes.prefix` and `routes.middleware`. The middleware must include what marks an administrator, for example `['web', 'auth', 'can:manage-access']`. `['web', 'auth']` alone lets every signed-in account grant itself everything.
- Rules that code checks by name are created in migrations or seeders with `Access::newRule()`, never through the panel. The panel creates rules with the origin `custom`, for names code builds at run time. It may change the title, place and options of a rule of code; it refuses to rename it, change its condition or delete it.
- After an upgrade of this package run `php artisan vendor:publish --tag=accessUi-assets --force`. The bundle is published into `public/`, not served from `vendor/`.
- An owner is addressed by the id of its row in the owner table of the core. A page gets it from `$user->getOwner()->id`. The panel never loads a model of the application to find an owner.
- The card goes on a page that already shows an account: `@accessUiWidget(['owner' => $user])`. It renders nothing while the routes are off, so the line is safe in every environment.
- Screens are switched in config `screens.<name>` with `enabled`, `write` and `ability`. `'write' => false` on `rules` is the setting for an application whose vocabulary lives in migrations.
- Every CSS class is prefixed `wacu-`. Override colours through custom properties on `.wacu-root`; never edit the published bundle.

@verbatim
<code-snippet name="Turning the panel on" lang="php">
// config/accessUi.php
'routes' => [
    'prefix'     => 'access-control',
    'middleware' => ['web', 'auth', 'can:manage-access'],
],
'layout' => ['view' => 'layouts.admin', 'section' => 'content', 'title' => 'Access control'],

// app/Providers/AppServiceProvider.php
Gate::define('manage-access', fn (User $user): bool => $user->is_admin);
</code-snippet>
@endverbatim

@verbatim
<code-snippet name="The card on a user page" lang="blade">
@accessUiWidget(['owner' => $user])
@accessUiWidget(['owner_id' => 17, 'title' => 'Roles', 'compact' => true])
</code-snippet>
@endverbatim

Activate the `access-ui-integration` skill for layouts and themes, translations, narrowing a screen to fewer people, the JSON routes and their envelope, and the upgrade from 2.x.

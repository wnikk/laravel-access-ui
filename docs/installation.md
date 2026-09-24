---
title: Installation
weight: 1
---

# Installation

Version 3 of the panel needs PHP 8.4, Laravel 13 and `wnikk/laravel-access-rules` 3.3 or newer, installed and migrated. For an older application stay on 2.0: `composer require wnikk/laravel-access-ui:^2.0`.

1. Install the package:

    ```bash
    composer require wnikk/laravel-access-ui
    ```

   The service provider registers itself. With package discovery turned off, add `Wnikk\LaravelAccessUi\AccessUiServiceProvider::class` to `bootstrap/providers.php`.

2. Publish the configuration and the bundle:

    ```bash
    php artisan vendor:publish --tag=accessUi-config
    php artisan vendor:publish --tag=accessUi-assets
    ```

   The second command copies `accessUi.js` and `accessUi.css` to `public/vendor/accessui`. Without them the panel page loads and mounts nothing. Run it again with `--force` after every upgrade, and set `assets.version` in config so browsers fetch the new files.

   Two more tags exist for what you may want to change: `accessUi-views` (the standalone page, the embedded page, the card) and `accessUi-lang` (the translations of refusals of the core).

3. Turn the routes on. Nothing is registered until both keys of `routes` are set:

    ```php
    'routes' => [
        'prefix'     => 'access-control',
        'middleware' => ['web', 'auth', 'can:manage-access'],
    ],
    ```

   `['web', 'auth']` alone lets every signed-in account grant itself every permission of the system. Name what marks an administrator in your application:

   - a Gate: `Gate::define('manage-access', fn (User $user): bool => $user->is_admin)` and `can:manage-access`
   - a rule of the core: `can:system.access.manage`, as long as somebody holds it before you rely on it
   - your own middleware: `admin`

4. Open `/access-control`. The panel renders on a bare page of its own. Name a layout of yours in `layout.view` and it renders inside your admin area, see [Configuration](configuration.md).

## A first check

1. Rules: the rules your migrations created are listed with the mark "code".
2. Owners: create a role. It appears in the list. Open its permissions.
3. Allow a rule, reload the page: the state stays "Allowed".
4. Inheritance: let a user inherit from the role.
5. Why?: pick that user and the rule. The table shows the permit of the role and the decision "permitted".
6. In code: `$user->can('the.rule')` answers true.

Step 6 is the one that counts. The core resolves what the panel wrote.

## Assets through your own pipeline

Set `assets.inject` to false and load the two files of `dist/` yourself, or import `vendor/wnikk/laravel-access-ui/resources/js/accessUi.js` from source in your Vite build. The mount script on the page waits up to five seconds for `window.accessUi`.

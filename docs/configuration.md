---
title: Configuration
weight: 2
---

# Configuration

Everything is in `config/accessUi.php`. The defaults show every screen and write nothing anywhere until the routes are on.

## Routes

```php
'routes' => [
    'prefix'     => env('ACCESS_UI_PREFIX'),   // 'access-control'; null keeps the routes off
    'middleware' => [],                        // ['web', 'auth', 'can:manage-access']; empty keeps the routes off
    'domain'     => env('ACCESS_UI_DOMAIN'),   // optional host
    'as'         => 'accessUi.',               // route-name prefix
],
```

Both `prefix` and `middleware` are required before anything registers. The routes hand out permissions, so an install that nobody configured exposes nothing rather than something unguarded. See [Installation](installation.md) for what the middleware has to name.

## Layout and theme

```php
'layout' => [
    'view'    => null,              // 'layouts.admin' renders the panel inside your layout
    'section' => 'content',         // the section the layout yields
    'title'   => 'Access control',  // reaches the layout as $title
],

'theme' => 'auto',                  // 'auto' | 'light' | 'dark'
```

With `view` null the panel serves a bare page: no CSS framework, no font, nothing from the network. Name a layout and the layout only has to yield the section; the stylesheet and the script come with the panel.

`auto` follows `prefers-color-scheme`. `light` and `dark` put `wacu-light` or `wacu-dark` on the mount point. The stylesheet honours these classes on any ancestor too, so an application that toggles its theme by a class on `<html>` can leave this at `auto`.

Colours, radius and fonts are custom properties on `.wacu-root`:

```css
.wacu-root {
    --wacu-accent: #6d28d9;
    --wacu-radius: 2px;
    --wacu-font: "Inter", sans-serif;
}
```

The full list with its dark counterparts is at the top of `resources/css/accessUi.css`.

## Assets

```php
'assets' => [
    'base'    => '/vendor/accessui',   // where vendor:publish --tag=accessUi-assets put the files
    'inject'  => true,                 // false when your own pipeline loads the bundle
    'version' => null,                 // appended as ?v= to bust caches after an upgrade
],
```

## Entities

An owner is anything that holds permissions. The core keeps users, roles and groups alike as rows of one table, told apart by a type derived from a label. An entity here is that label, a name to show, and two decisions.

```php
'entities' => [
    'role' => [
        'type'       => 'Role',    // must also be in config/access.php, owner_types
        'label'      => 'Roles',
        'single'     => 'Role',
        'create'     => true,      // the panel may add rows of this type by hand
        'assignable' => true,      // may be handed out as a source of rights; the card offers these
    ],
    'user' => [
        'type'       => null,      // the class from auth.providers.users.model, used as a string
        'label'      => 'Users',
        'single'     => 'User',
        'create'     => false,     // the row of a user appears when the application first touches them
        'assignable' => false,
    ],
],
```

Two things happen whatever this list says:

- any owner that holds a permission or a prohibition is listed on the owners and inheritance screens, marked as unlisted when its type is not configured. A permit granted straight to one account would otherwise be invisible on a screen of roles, and an administrator who cannot see it cannot revoke it;
- any owner in the table may be added as an inheritor. Who receives rights is not decided by this list.

Renaming and deleting are offered on every listed row. Deleting the owner row of a user takes away the permissions and the links of that account; the user of the application stays.

An entity whose type is missing from `owner_types` is dropped and named at the top of the panel with the reason, so one mistyped label costs that entity and not the screen.

With `'entities' => []` the panel lists exactly the owners that hold something.

Tenants (`access.tenant_types`) and the guest owner (`access.guest`) of the core are marked in the lists.

## Screens

```php
'screens' => [
    'rules'       => ['enabled' => true, 'write' => true, 'ability' => null],
    'owners'      => ['enabled' => true, 'write' => true, 'ability' => null],
    'permissions' => ['enabled' => true, 'write' => true, 'ability' => null],
    'inherit'     => ['enabled' => true, 'write' => true, 'ability' => null],
    'explain'     => ['enabled' => true, 'write' => true, 'ability' => null],
    'health'      => ['enabled' => true, 'write' => true, 'ability' => null],
    'xacml'       => ['enabled' => true, 'write' => true, 'ability' => null],
],
```

A screen that is off is hidden and its routes answer 403. `write` false hides the buttons and refuses every write on that screen. `ability` names a Gate ability checked on top of the route middleware for writes.

All seven are on: whoever passed the middleware of the group administers access. Name an ability to narrow one screen to fewer people, for example `xacml` to those who may import policies.

`rules` deserves a thought. Rules are the vocabulary the application checks against, so a renamed rule stops matching the code that asks for it. `'write' => false` keeps the list browsable while rules change only through migrations. Rules that come with code are protected either way: the panel may reword them and edit their options, and refuses to rename or delete them.

## The assignment card

```php
'widget' => [
    'write'   => true,   // false: every card only shows
    'ability' => null,   // a Gate ability checked with the owner of the card
],
```

What the card on a user page may change, on top of the inheritance screen it uses: see [The assignment card](widget.md).

## Translations

The interface is English, and the bundle takes other languages two ways. Before the bundle loads, a page may put messages on `window.accessUiMessages`, keyed by locale; the bundle reads them once and picks the locale of the application:

```html
<script>window.accessUiMessages = { ru: { "rules.title": "Правила", "permissions.allow": "Разрешить" } };</script>
```

After it loaded, `accessUi.addMessages('ru', {...})` adds or corrects strings. Keys are in `resources/js/lang/en.json`, English is the fallback key by key. Refusals of the core are translated on the server: `vendor:publish --tag=accessUi-lang`, then `lang/vendor/accessUi/<locale>/errors.php`; messages of the controllers go through `__()` and `lang/<locale>.json` of the application.

## Pickers

```php
'picker' => [
    'per_page'     => 15,    // rows per page of every paged list
    'inline_limit' => 100,   // a list no longer than this travels with the screen, saving a request
],
```

## XACML

```php
'xacml' => [
    'max_upload_kb' => 10240,   // the largest policy an upload may carry
],
```

The core refuses DOCTYPE and never reads the network, so the size is the one bound left to the panel.

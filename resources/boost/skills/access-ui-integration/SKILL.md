---
name: access-ui-integration
description: Install and integrate wnikk/laravel-access-ui, the administration panel of wnikk/laravel-access-rules 3.x - routes and the admin gate, layout and theme, the seven screens and their switches, the assignment card on a user page, translations, the JSON routes and their envelope, upgrade from 2.x.
---

# Laravel Access UI Integration

## When to use this skill

Use it when the task is about the administration of access in an application that has `wnikk/laravel-access-rules` installed: installing the panel, protecting it, rendering it inside an existing admin layout, putting the assignment card on a user page, switching a screen off or making it read-only, translating it, calling its JSON routes from a page of the application, or upgrading from the 2.0 panel.

Version 3 needs PHP 8.4, Laravel 13 and `wnikk/laravel-access-rules` 3.2.4 or newer.

## Mental model

The panel is a client of the core. It writes through `Access`, `Access::for()`, `RuleCatalog` and `Xacml`, and asks `explain()` for a decision. It reads the tables of the core for lists, and it knows nothing about the models of the application: owners are rows of the owner table, addressed by id.

Screens, all on by default:

| Screen | What it does |
|---|---|
| Rules | the names code checks against; origin `code`, `custom` or `import`; resource, options, a condition of the rule |
| Owners | paged, searched rows of the owner table; from a row, its permissions |
| Permissions | for one owner, rule by rule: the permits and prohibitions that reach it, each with a condition; Allow, Forbid, With condition |
| Inheritance | who inherits from whom, from either end |
| Why? | one check explained: every permission that took part, the one that decided, the values its condition read |
| Health | what `acr:lint` finds; "fix", and a cache button |
| XACML | download the export; upload a document and see what it would change before importing |

Priority of permissions, from the weakest: inherited permit, inherited prohibition, own permit, own prohibition. A row with a condition applies to the records the condition is true for. The permissions screen labels a rule by these steps; the "Why?" screen answers for one record.

## Install checklist

```bash
composer require wnikk/laravel-access-ui
php artisan vendor:publish --tag=accessUi-config
php artisan vendor:publish --tag=accessUi-assets      # public/vendor/accessui; repeat with --force after every upgrade
```

```php
// config/accessUi.php: nothing registers until both are set
'routes' => [
    'prefix'     => 'access-control',
    'middleware' => ['web', 'auth', 'can:manage-access'],
],
```

```php
// the gate the middleware names; any marker of an administrator the application has will do
Gate::define('manage-access', fn (User $user): bool => $user->is_admin);
```

Then open `/access-control`. Check the result in code: grant a rule through the panel and assert `$user->can('the.rule')`.

## Layout and theme

```php
'layout' => ['view' => 'layouts.admin', 'section' => 'content', 'title' => 'Access control'],
'theme'  => 'auto',   // 'light' | 'dark'; the classes wacu-light and wacu-dark also work on any ancestor
```

The layout only has to `@yield` the section; the panel brings its own CSS and JS. With `layout.view` null the panel serves a bare page of its own.

Restyle through custom properties, no build step:

```css
.wacu-root { --wacu-accent: #6d28d9; --wacu-radius: 2px; --wacu-font: "Inter", sans-serif; }
```

Assets through your own pipeline: set `assets.inject` to false and load `dist/accessUi.js` and `dist/accessUi.css` yourself, or import `resources/js/accessUi.js` from source. `window.accessUi` has to exist within five seconds of the page loading.

## Entities

```php
'entities' => [
    'role' => ['type' => 'Role', 'label' => 'Roles', 'single' => 'Role', 'create' => true,  'assignable' => true],
    'user' => ['type' => null,   'label' => 'Users', 'single' => 'User', 'create' => false, 'assignable' => false],
],
```

`type` must be listed in `config/access.php` under `owner_types`; null means the class from `auth.providers.users.model`, used as a string. `create` lets the panel add rows of that type by hand. `assignable` lets the type be handed out as a source of rights; that is what the card offers. Owners of any type that hold a permission are listed regardless, marked as unlisted.

## Screens: who may do what

```php
'screens' => [
    'rules'  => ['enabled' => true, 'write' => false, 'ability' => null],             // browsable, changes only through migrations
    'xacml'  => ['enabled' => true, 'write' => true,  'ability' => 'import-policies'], // a Gate ability on top of the middleware
    'health' => ['enabled' => false],
],
```

A screen that is off is hidden and its routes answer 403. `write` false hides the buttons and refuses writes. `ability` is checked with `Gate::allows()` for writes.

## The card on a user page

```blade
@accessUiWidget(['owner' => $user])                       {{-- anything with getOwner(), the trait of the core --}}
@accessUiWidget(['owner_id' => 17, 'title' => 'Roles', 'compact' => true])
@accessUiAssets                                           {{-- in a layout, optional: the widget emits the tags itself when nobody did --}}
```

The card lists what the account inherits from, offers what may still be assigned, and shows four numbers: inherited rows, own rows, rows that depend on the record, prohibitions. It renders nothing while the routes are off or the inheritance screen is disabled.

Outside Blade: `accessUi.widget('#el', payload)`, where `payload` is what `AccessUi::widgetPayload($owner, $options)` returns: the four routes of the card, the entities it may hand out, locale, theme, token, `write`.

What the card may change is decided on the server, per owner: config `widget.write` (false: every card only shows) and `widget.ability`, a Gate ability that receives the owner of the card, `Gate::define('assign-access', fn (User $user, Owner $owner) => ...)`. `@accessUiWidget(['owner' => $user, 'write' => false])` hides the buttons of one card; the config is the guard.

## Translations

Put `window.accessUiMessages = { de: { 'rules.title': 'Regeln' } }` in a script before the bundle and the first render is translated; `accessUi.addMessages(locale, {...})` after it adds or corrects strings. Keys are in `resources/js/lang/en.json`, English is the fallback key by key. Messages of the controllers go through `__()`, so `lang/<locale>.json` of the application covers them. Refusals of the core are translated on the server: publish `--tag=accessUi-lang` and edit `lang/vendor/accessUi/<locale>/errors.php`.

## The JSON routes

Names carry the prefix `accessUi.` (config `routes.as`). `{owner}` is an owner id, `{id}` a rule id, `{link}` an inheritance row id.

| Method | Path | Body or query |
|---|---|---|
| GET | `rules` | |
| POST | `rules` | `guard_name, parent_id, title, description, options, resource, when` |
| PUT | `rules/{id}` | same; a rule of code accepts `title, description, parent_id, options` only |
| DELETE | `rules/{id}` | refused with `rule_in_use` while somebody holds it |
| GET | `rules/{id}/holders` | `page, limit` |
| GET | `owners` | `entity, search, page, limit` |
| POST | `owners` | `entity, original_id, name` |
| PUT | `owners/{owner}` | `name` |
| DELETE | `owners/{owner}` | permissions and links of the row go with it |
| GET | `owners/{owner}/heirs` | `page, limit` |
| GET | `owners/{owner}/permissions` | |
| POST | `owners/{owner}/permissions` | `rule, effect: allow\|deny, option, when` |
| DELETE | `owners/{owner}/permissions` | `rule, effect, option` |
| GET | `owners/{owner}/inherit` | `direction: parents\|children` |
| POST | `owners/{owner}/inherit` | `direction, target` |
| DELETE | `owners/{owner}/inherit/{link}` | |
| GET | `pick` | `scope: assignable\|all\|listed, search, exclude, page, limit` |
| GET | `conditions/vocabulary` | |
| POST | `conditions/check` | `when, resource` |
| GET | `explain` | `owner, ability, record` (`order:4`, `order`, or empty) |
| GET | `health` | |
| POST | `health/fix` | |
| POST | `cache/flush` | |
| GET | `xacml/export` | the policy document |
| POST | `xacml/check` | multipart `policy`; `replace, partial` |
| POST | `xacml/import` | same |

Every JSON answer is one envelope:

```json
{ "ok": true,  "message": "Saved", "data": { }, "reload": null }
{ "ok": false, "message": "The rule is held by owners.", "code": "rule_in_use", "errors": { "core": ["..."] }, "data": { "holders": [] } }
```

A validation failure is Laravel's own 422 body. Codes of the core and their status: `rule_in_use` and `duplicate_permission` 409, `inheritance_loop`, `invalid_option` and `condition` 422, `rule_managed_by_code` 403, `owner_not_found` and `rule_not_found` 404.

## Rules of code and rules of the panel

Code creates its rules in migrations (`Access::newRule('orders.view', ...)`), and the panel shows them with the mark "code": title, description, place in the tree and options may be edited, the name, the model and the condition may not, and delete is refused. A rule created in the panel is `custom`, for abilities whose names code builds at run time (`can('news.edit.'.$category->slug)`). Rules that 2.x had soft deleted are listed as `deprecated__<name>` behind the filter "leftovers of 2.x".

## Conditions in the panel

The editor is a text field with autocompletion from `config('access.resources')`: `order.` lists the columns and the relations of that model, `user.` and `env.` their attributes, and the functions of the core. While the administrator types, `POST conditions/check` runs `Cond::compile()` of the core, and the message of a refusal is shown under the field; the Save button stays disabled. Relations a condition reads must declare a return type on the model. The language itself is documented by the core: see the skill `access-rules-development`.

## XACML

"Download export" streams `Xacml::export()`, one XML file. Uploading a document runs `Xacml::check()` and shows the plan: every rule, owner, permission and link marked create, same, differs or only in the database, with the two versions side by side for what differs. Import executes that plan. `replace` also brings what differs to the document; `partial` writes what converts although something did not, which can drop a prohibition. Both are boxes with a warning, off by default.

## Upgrade from 2.0

- `composer require wnikk/laravel-access-ui:^3.0`, then `vendor:publish --tag=accessUi-assets --force`.
- `config/accessUi.php` of 2.0 stays valid; add `screens.explain`, `screens.health` and `screens.xacml` when you want to narrow them.
- Routes changed: restore and `?force=1` are gone; permissions are written with `POST owners/{owner}/permissions` and removed with `DELETE` on the same path. Pages that called the 2.0 routes need the table above.
- Pressing Allow no longer removes a prohibition of the same rule; the two are rows of their own.
- Translations added with `addMessages()` need the keys of `resources/js/lang/en.json` of 3.0.

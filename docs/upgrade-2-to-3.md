---
title: Upgrade from 2.0 to 3.0
weight: 8
---

# Upgrade from 2.0 to 3.0

3.0 is written for `wnikk/laravel-access-rules` 3.x. Upgrade the core first: [its guide](https://github.com/wnikk/laravel-access-rules/blob/main/docs/upgrade-2-to-3.md) covers the tables and the config of the core.

## Steps

1. PHP 8.4, Laravel 13 and the core 3.3 or newer are required.
2. `composer require wnikk/laravel-access-ui:^3.0`.
3. Publish the bundle again, the files changed:
   ```bash
   php artisan vendor:publish --tag=accessUi-assets --force
   ```
   Set `assets.version` in config, or browsers keep the old files.
4. `config/accessUi.php` of 2.0 stays valid. Three screens are new and on by default; add them to `screens` when you want to narrow them:
   ```php
   'explain' => ['enabled' => true, 'write' => true, 'ability' => null],
   'health'  => ['enabled' => true, 'write' => true, 'ability' => null],
   'xacml'   => ['enabled' => true, 'write' => true, 'ability' => null],
   ```
   and `'xacml' => ['max_upload_kb' => 10240]` for the size of an upload.
5. Views published with `accessUi-views` and translations added with `addMessages()` were written for 2.0. Publish the views again; check your keys against `resources/js/lang/en.json` of 3.0, most of the keys of 2.0 survive and many are new.

## What changed in behaviour

- **Allow and Forbid are independent.** 2.0 turned a prohibition into a permit when Allow was pressed. In 3.0 a permit and a prohibition of one rule are two rows, each with a condition of its own, and each is removed on its own with the cross on its chip.
- **Rules are deleted for good.** Deactivate and restore are gone with the soft delete of the core. Delete is refused while somebody holds the rule, and the holders are shown. Rules that 2.x had soft deleted are listed as `deprecated__<name>` behind the filter "leftovers of 2.x".
- **Rules of code are protected.** A rule created by a migration may be reworded and have its options edited. The panel refuses to rename or delete it. Rules created in the panel are `custom`.
- **The cache is not flushed by the panel.** The core turns it over on every change. The button on the health screen is for rows changed with plain SQL.
- **The card counts four numbers** instead of three: inherited, own, depending on the record, prohibitions.

## Routes

Pages of your own that called the routes of 2.0 need these changes:

| 2.0 | 3.0 |
|---|---|
| `POST rules/{id}/restore` | removed |
| `DELETE rules/{id}?force=1` | `DELETE rules/{id}`, refused with 409 `rule_in_use` while held |
| `PUT owners/{owner}/permissions/{rule}` with `permission: allow\|deny\|remove` | `POST owners/{owner}/permissions` with `rule, effect, option, when`; `DELETE owners/{owner}/permissions` with `rule, effect, option` |
| | new: `rules/{id}/holders`, `owners/{owner}/heirs`, `conditions/*`, `explain`, `health*`, `cache/flush`, `xacml/*` |

The envelope of a failure gained `code`, the code of the exception of the core. The rest of the envelope is as in 2.0: [Routes and the envelope](routes.md).

## Removed

- `Support\RuleSpec` and `permissionSummary()` of `AccessUi`: the core compiles the spec and the summary of a rule comes from its rows.
- `AccessUi::flushCache()`.
- The jQuery branch of `httpUi.js`; the bundle is an ES module built as an IIFE, as before.

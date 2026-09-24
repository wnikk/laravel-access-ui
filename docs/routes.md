---
title: Routes and the envelope
weight: 7
---

# Routes and the envelope

For a page of your own that talks to the panel. The routes exist only when `routes.prefix` and `routes.middleware` are set. Names carry the prefix of `routes.as`, `accessUi.` by default. `{owner}` is an owner id, `{id}` a rule id, `{link}` the id of an inheritance row.

| Method | Path | Name | Body or query |
|---|---|---|---|
| GET | `/` | `index` | the page |
| GET | `rules` | `rules.index` | |
| POST | `rules` | `rules.store` | `guard_name, parent_id, title, description, options, resource, when` |
| PUT | `rules/{id}` | `rules.update` | the same; a rule of code accepts `title, description, parent_id, options` |
| DELETE | `rules/{id}` | `rules.destroy` | refused while somebody holds the rule, `data.holders` lists them |
| GET | `rules/{id}/holders` | `rules.holders` | `page, limit` |
| GET | `owners` | `owners.index` | `entity, search, page, limit` |
| POST | `owners` | `owners.store` | `entity, original_id, name` |
| PUT | `owners/{owner}` | `owners.update` | `name` |
| DELETE | `owners/{owner}` | `owners.destroy` | |
| GET | `owners/{owner}/heirs` | `owners.heirs` | `page, limit` |
| GET | `owners/{owner}/permissions` | `permissions.index` | |
| POST | `owners/{owner}/permissions` | `permissions.store` | `rule, effect: allow\|deny, option, when` |
| DELETE | `owners/{owner}/permissions` | `permissions.destroy` | `rule, effect, option` |
| GET | `owners/{owner}/inherit` | `inherit.index` | `direction: parents\|children` |
| POST | `owners/{owner}/inherit` | `inherit.store` | `direction, target` |
| DELETE | `owners/{owner}/inherit/{link}` | `inherit.destroy` | |
| GET | `pick` | `pick` | `scope: assignable\|all\|listed, search, exclude, page, limit` |
| GET | `conditions/vocabulary` | `conditions.vocabulary` | |
| POST | `conditions/check` | `conditions.check` | `when, resource` |
| GET | `explain` | `explain` | `owner, ability, record` |
| GET | `health` | `health.index` | |
| POST | `health/fix` | `health.fix` | |
| POST | `cache/flush` | `cache.flush` | |
| GET | `xacml/export` | `xacml.export` | the policy document |
| POST | `xacml/check` | `xacml.check` | multipart `policy`; `replace, partial` |
| POST | `xacml/import` | `xacml.import` | the same |

Paged lists answer `rows` and `meta: { current_page, last_page, per_page, total }`. Every list carries `write`, whether the current request may change anything on that screen.

## The envelope

```json
{ "ok": true,  "message": "Saved", "data": { }, "reload": null }
{ "ok": false, "message": "The rule is held by owners. Take their permissions away first.", "code": "rule_in_use", "errors": { "core": ["..."] }, "data": { "holders": [] } }
```

`reload` is `true` to reload the page or a URL to go to. A validation failure is the 422 body of Laravel itself, which has the same shape, so `$request->validate()` is used as usual.

A refusal of the core answers with the code of its exception, a translated meaning in `message` and the words of the core under `errors.core`:

| Code | Status |
|---|---|
| `rule_in_use`, `duplicate_permission` | 409 |
| `inheritance_loop`, `invalid_option`, `condition` | 422 |
| `rule_managed_by_code` | 403 |
| `owner_not_found`, `rule_not_found` | 404 |

Translations of these messages: `php artisan vendor:publish --tag=accessUi-lang`, then `lang/vendor/accessUi/<locale>/errors.php`.

## Screens that are off

A route of a screen with `enabled` false answers 403 with a plain message, and so does a write on a screen with `write` false or an `ability` the Gate denies. The markup is never the only thing in the way.

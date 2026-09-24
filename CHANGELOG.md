# Changelog

All notable changes to `laravel-access-ui` will be documented in this file

## 3.0.0 - 2026-09-24

Rewritten for `wnikk/laravel-access-rules` 3.2+, Laravel 13+ and PHP 8.4+. The configuration of 2.0 stays valid, the routes and the bundle changed: [upgrade guide](docs/upgrade-2-to-3.md).

- Permissions with conditions: a permit and a prohibition of one rule are two rows, each with its own condition; the window of a rule lists the rows of the owner to change or delete and adds new ones
- Forms show what cannot change as facts and what can as fields
- Every screen and window is a history entry: the back button works, and a hash such as `#!/rules/edit/12` opens an editor straight away
- Editor of conditions with autocompletion from the models and a check by the compiler of the core before saving
- Rules carry their origin: a rule of code keeps its name, model and condition, its title, description, place and options are open; rules of the panel are `custom`; a rule is deleted for good and refused while somebody holds it, the holders are shown
- Rules take a resource and a condition of their own
- Screen "Why?": every permission that took part in one check, the one that decided, the values its condition read
- Screen "Health": what `acr:lint` finds, "fix" and a cache button
- Screen "XACML": download the export as one XML file, see what a document would change before importing it, `replace` and `partial` behind warnings
- Owners are paged and searched; tenants and the guest are marked; "who inherits from this" for every owner
- Widget counts own, inherited, conditional and forbidden rows; its own policy `widget.write` and `widget.ability`, a Gate ability that receives the owner; `'write' => false` on the directive; the page gets only what the card uses
- Translations may be registered before the bundle loads, on `window.accessUiMessages`
- Refusals of the core answer with their code, a translated meaning and the words of the core
- Tests through the routes on SQLite, PostgreSQL and MySQL; CI compares `dist/` with a fresh build
- Laravel Boost guideline and skill, `docs/llms.txt`, `AGENTS.md`
- Vue 3.5 and Vite 7; `httpUi.js` keeps `fetch` only
- **Removed:** restore of a rule, `?force=1`, `PUT owners/{owner}/permissions/{rule}`, the deactivate state of rules

## 2.0.0 - 2026-09-18

- Rewritten front end and configuration; one bundle without a CSS framework
- Any owner type, screens with `enabled`, `write` and `ability`, the assignment widget

## 1.0.0 - 2024-04-29

- First release: rules, roles, permissions and inheritance for `laravel-access-rules` 1.x, Bootstrap and UIkit themes

# AGENTS.md

Instructions for coding agents that work **on this package**. Agents that work on an application which uses the panel get their instructions from `resources/boost/` through Laravel Boost.

## What this is

`wnikk/laravel-access-ui`, version 3: the administration panel of `wnikk/laravel-access-rules` 3.x. Seven screens and one card for a page of the host application, shipped as one script and one stylesheet. The panel decides nothing about access. It calls the core and draws the answer.

## Commands

```bash
composer install                              # the core comes from ../laravel-access-rules, a path repository, in development and CI
./vendor/bin/phpunit                          # tests/Feature through the routes, SQLite in memory
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_DATABASE=acu_test DB_USERNAME=postgres ./vendor/bin/phpunit
DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=acu_test DB_USERNAME=root ./vendor/bin/phpunit
../laravel-access-rules/vendor/bin/pint --test --config pint.json src/Some/File.php   # only files you created or edited, never the whole project
npm install && npm run build                  # rebuilds dist/, which is committed; no lock file, CI only checks that the sources build
```

Run the suite on SQLite and PostgreSQL after a change to a query (`Support/OwnerReader`, `AccessUi::searchOwners`). After a change under `resources/js` or `resources/vue`, rebuild `dist/` and commit it, then open the panel on a stand in a browser: the tests never load the bundle, CI does not compare `dist/` with a build, and a broken transport shows no error on the server (decision U12 in `_dev/04-decisions.md`).

## Layout

```
src/
  AccessUi.php                     configuration turned into questions: routes, screens, entities, how an owner is presented, the bootstrap payload
  AccessUiServiceProvider.php      route group, Blade directives, publishing
  Http/Controllers/
    BaseController.php             the envelope, the screen check in callAction(), the translation of a refusal of the core
    Panel, Rules, Owners, Permissions, Inherit, Picker, Conditions, Explain, Health, Xacml
  Support/
    Errors.php                     code of AccessRulesException to HTTP status and translation key
    OwnerReader.php                permissions(), sources() and heirs() of the core shaped for the screens: grouped by rule, the label of a rule, counts
    RuleTree.php                   the tree of rules for the parent picker
    Vocabulary.php                 what a condition may name, for the editor
routes/access-ui.php               every route; nothing registers without prefix and middleware
config/accessUi.php
resources/
  views/                           standalone page, embedded page, assets, boot, widget
  lang/en/errors.php               translations of the codes of the core
  js/accessUi.js                   the global: init(), widget(), addMessages(), setDefaults()
  js/libs/                         api.js (envelope, attempt()), httpUi.js (transport, loader), i18n.js, tree.js, useInheritance.js
  js/lang/en.json                  every string of the interface
  vue/App.vue, Widget.vue          the shell of the panel and the card
  vue/screens/                     one file per screen
  vue/ui/                          Modal, Picker, Toasts, Icon, ConditionEditor
  css/accessUi.css                 tokens under .wacu-root, light and dark
  boost/                           guidelines and a skill for agents inside applications; keep them true to the code
dist/                              the built bundle, committed
docs/                              user documentation; docs/llms.txt is its map for agents
tests/Feature                      one test per promise of the documentation, through the routes
```

## Rules of this repository

- **The panel holds no logic of access.** Writes go through `Access`, `Access::for()`, `RuleCatalog` and `Xacml`; a decision comes from `explain()`; what an owner holds comes from `permissions()`, `sources()` and `heirs()` of the core, and `OwnerReader` only shapes it for the screens. The panel reads no table of the core.
- **Nothing names a class of `src/Protected` of the core.** When a screen needs what the core does not give publicly, ask for it in the core, as `Cond::compile()` and `permissions()` were added.
- **The panel knows no model of the application.** Owners are addressed by the id of their row. The one exception is `ExplainController`, which loads the model an owner stands for, because conditions read `user.` from it.
- **Routes stay off** until `routes.prefix` and `routes.middleware` are both set. Every screen has `enabled`, `write` and `ability`; `BaseController::callAction()` checks them, so a new action is guarded by being on the controller.
- **Tests go through the routes** and assert JSON or the rows of the core. No test constructs a controller.
- **Comments and PHPDoc are English and carry decisions**: what was chosen, what was rejected and why, what breaks otherwise. No restating of signatures.
- **Prose is plain**, in comments, `docs/` and this file: no dashes as pauses, no decorative adverbs, no metaphors, no closing one-liners, no "not X, it is Y" unless X was an alternative that was rejected. Name who does what: "the panel refuses", "you run".
- Every CSS class is prefixed `wacu-` and every selector sits under `.wacu-root`. No CDN, no web font, nothing from the network at run time.
- Documentation is part of the change: `docs/`, `resources/boost/`, `CHANGELOG.md` (short phrases), `resources/js/lang/en.json`.
- Commits and tags are made by the owner. Do not commit, push or touch the index.

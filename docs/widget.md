---
title: The assignment card
weight: 4
---

# The assignment card

One card for a page that already shows an account: what this account inherits from, what may still be assigned, a remove button per row, and how much it ends up holding.

![The card on a user page](art/widget.png)

```blade
@accessUiWidget(['owner' => $user])
@accessUiWidget(['owner' => $user->getOwner()->id])
@accessUiWidget(['owner_id' => 17])
```

The three lines mean the same. The first is duck-typed on `getOwner()`, the method of the trait `HasPermissions` of the core, so the panel needs no knowledge of your classes. `getOwner()` creates the owner row when there is none, which is right here: a user has none until something is granted, and mounting the card is the moment somebody is about to.

Options beyond the owner: `title` for the heading, which defaults to the name of the assignable entity when there is one; `compact` for a denser card; `write` false for a card that only shows.

## Who may change what the card shows

The card writes through the routes of the inheritance screen, and the server decides what it may write, per request and per owner:

```php
// config/accessUi.php
'widget' => [
    'write'   => true,             // false: every card only shows
    'ability' => 'assign-access',  // a Gate ability checked with the owner of the card
],
```

```php
Gate::define('assign-access', fn (User $user, Owner $owner): bool => $user->is_admin || $owner->original_id === (string) $user->id);
```

The ability receives the owner the card is about, so the answer may depend on who asks and about whom: an administrator assigns to anyone, a team lead only within the team, a user sees their own card and changes nothing. The server refuses a request that gives an owner a source or takes one away when the policy says no, whichever page sent it; the inheritance screen of the panel, which adds and removes heirs, follows its own switch. `'write' => false` on the directive hides the buttons of one card and is a choice of the page, not the guard.

## What travels to the page

The card gets what it uses and nothing else: its four routes, the entities that may be handed out, the locale, the theme, the token, and whether it may write. Nothing of the panel, no other route and no screen, is printed on a page of the application.

The card renders nothing while the routes are off or the inheritance screen is disabled, so the line is safe on a page of an installation that does not use the panel.

## The numbers

| | |
|---|---|
| inherited | rows that reach the account through what it inherits from |
| own | rows granted to the account itself; shown when there are any |
| depend on the record | rows with a condition, whichever way they came; whether one applies is decided per record |
| prohibitions | rows that take a permission away |

Numbers and no names: which rules those are is the job of the permissions screen. The gap between the figures is what a user page needs. Own rows on an account with nothing assigned mean somebody hand-tuned it. "No roles" and "no rights" are different statements.

## Assets

The card emits the bundle tags itself when the page has not. A layout can emit them once with `@accessUiAssets`; the card then stays quiet. Loading the bundle twice would mount two copies of everything, so whichever call comes first wins.

## Outside Blade

```html
<div id="user-access"></div>

<script>
    accessUi.widget('#user-access', Object.assign({ owner: 17 }, bootstrap));
</script>
```

`bootstrap` is what `app(\Wnikk\LaravelAccessUi\AccessUi::class)->bootstrapPayload()` returns: routes, screens, entities, locale and the CSRF token. `owner` is an owner id, never a user id.

## The routes behind it

Assigning a role is an inheritance link, so the card and the inheritance screen of the panel share three routes and differ in the direction they ask from:

```
GET    {prefix}/owners/{owner}/inherit?direction=parents    what this owner inherits from, with counts   the card
GET    {prefix}/owners/{owner}/inherit?direction=children   who inherits from this owner                 the panel
POST   {prefix}/owners/{owner}/inherit                      { "direction": "parents", "target": 4 }
DELETE {prefix}/owners/{owner}/inherit/{link}               one direct link
```

`direction=parents` offers only entities marked `assignable`; `children` offers every owner of the table. A list short enough for a dropdown travels inline as `available.list`; past `picker.inline_limit` it comes back empty with `truncated: true` and the client searches `{prefix}/pick`.

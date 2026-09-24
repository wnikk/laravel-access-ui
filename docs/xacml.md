---
title: XACML
weight: 6
---

# XACML

The core exports the whole set as an XACML 3.0 policy and imports one back, with a plan of what would change. The screen puts that in a browser.

![The plan of an import](art/panel-xacml.png)

## Download

"Download export" streams `Xacml::export()`: one XML file, written one owner at a time, so a large set does not become a large memory. What XACML has no place for, titles of rules, names of owners and inheritance, travels inside the document as a policy set no request reaches.

## Look before importing

Upload an export of this package or an XACML 3.0 policy of another system and press "What would it change?". The panel runs `Xacml::check()` and shows the plan. Nothing is written.

Every rule, owner, permission and link the document speaks about is one row of the plan:

| Action | Meaning |
|---|---|
| create | the document has it, the database does not |
| same | both agree; hidden until "show what is the same" |
| differs | both have it and disagree; the two versions sit side by side, the database on the left, the document on the right |
| only in database | the database has it, the document does not; an import never deletes it |

The heading of the plan carries the date of the export. When the database moved on after that date the core warns: a permission rewritten since is named, and a `create` that may be a row removed since is pointed at, because an import never deletes and cannot tell the two apart.

Errors of the document, with the address of the element, are listed above the table: a condition the core cannot express, a function it does not know, a rule the document names and never declares. Warnings sit below them.

## Import

Import executes the plan you looked at. Two choices change what is written, and both sit behind a box with its warning, off by default:

- **replace**: rows marked "differs" take the version of the document. The button counts what it is about to write.
- **partial**: the import writes what converts although something does not. The import is refused while the plan holds an error, unless this box is ticked, because a lost element may be a prohibition, and a lost prohibition is wider access.

The report after the import counts what was applied by kind.

## Size

`xacml.max_upload_kb` in config bounds the upload. The core refuses DOCTYPE and never reads the network. The format, what converts and what does not, is described by the core: [XACML](https://github.com/wnikk/laravel-access-rules/blob/main/docs/xacml.md).

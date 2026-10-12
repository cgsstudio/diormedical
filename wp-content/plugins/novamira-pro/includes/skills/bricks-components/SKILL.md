---
name: bricks-components
description: Companion of bricks-build-page for Bricks components (reusable element subtrees with editable properties) — when to use a component instead of a global class, reusing existing components with property values, creating one from a built subtree, adding or changing property definitions on an existing component without rebuilding it, and what breaks instances. Load when a pattern repeats across pages or the site already lists components.
---

# Components in Bricks

A **component** is a saved element subtree whose instances render the same
structure with per-instance **property** values (text, image, link, colour,
class…). A **global class** is a style bundle. Use a component when the
*structure* repeats (card with image + title + excerpt + CTA, pricing tier,
team member, CTA band); use a global class when only the *styling* repeats.

## Reuse first

`novamira/bricks-list-components` lists what exists (id, label, category,
root element, element count; `verbose: true` adds the tree and property
definitions). Place an instance with `novamira/bricks-apply-component`
(`cid`, target `post_id`/`area`, position, and `properties` keyed by
**property id** with the values for this instance). Properties without a
value fall back to the component's default.

## Create from a built subtree

Build the pattern once as normal elements, verify it renders, then
`novamira/bricks-create-component` from that subtree (copying it leaves the
source page untouched) or from an inline `elements` tree. Declare
`properties` for everything that differs per instance — each with a stable
id, label, type and a `default` — and connect them to the element settings
they drive. Keep the component's root a `block` (or `div`) so it can live in
any container.

Component body ids are conformed on the way in. Every non-root id must contain
exactly six characters; an instance also renders a longer child id under only
its first six, producing a selector the page never carries. Other lengths are
replaced with generated ids, property connections follow, and the rename is
reported in `warnings`. Send ids with exactly six characters, or omit them
entirely; read the stored body back with
`novamira/bricks-list-components` `verbose: true` when you need the ids the
component actually has.

## Extend an existing component

To add fields use `novamira/bricks-edit-component` with `patch.add_properties`
(appends; give each new property a `type` and a `default`, and for a `class`
property the `options` `{label, value: [class ids]}` — option ids are
generated); to change or remove fields resend the list with
`patch.properties`. Definitions are keyed by id, and instances that predate a
new property keep rendering with its `default` while instance overrides win.
**Never delete-and-recreate a component to add a field** and never re-point
pages to a new `cid`: that destroys per-instance values and history.

Patching `elements` also refreshes the CSS of every page that carries an
instance — the page compiles its rules from the component body, so without
that the pages keep the rules of the previous body. Those pages come back in
`affected_posts` with the per-page outcome — `regenerated`, `inline` on a site
that serves CSS inline, or `skipped(<reason>)` when the file could not be
written. Read it before telling the user the edit is done: a skipped page
still serves the previous body's CSS, and the warning names it and the call
that rewrites it.

After editing property definitions ask the user to reload any open builder
tab; a tab opened before the change would overwrite the new property on its
next save.

## Editing instances and deleting

- Per-instance changes are property values (`bricks-patch-elements` on the
  instance's `properties`), not edits to the component tree.
- `novamira/bricks-delete-component` refuses by default when instances exist
  and lists the affected pages; choose `orphan_instances: "inline"` to expand
  instances into plain elements, or `"remove"` to delete them — only after
  the user decides. The count is live usage: revisions, trashed posts and
  auto-drafts hold copies of the tree and are not counted.
- An instance is a childless `{id, cid}` element on purpose — its body comes
  from the component definition. It is not an empty band, and nothing reports
  it as one. Instances that ARE reported: one whose `cid` matches no stored
  component; one whose component body has no element carrying the component
  id; and one whose body has a root but nothing under it except layout
  wrappers. A component renders from its root only — the element carrying the
  component id — so a second element parented to 0, or one whose parent id is
  not in the body, is stored and not rendered. Those three are listed in
  `empty_structural_ids`.
- A body with MORE than one element carrying the component id is reported
  separately, as malformed. What such a body puts on the page is not
  predictable from the storage: it can render all of the body, part of it, or
  nothing, depending on the order and content of the duplicate rows. It is
  deliberately NOT in `empty_structural_ids`, because acting on that list
  means deleting the element and the content may be on the page. Repair the
  body so exactly one element carries the id, then check the page.
- Query filters must not live inside a component (instance id remapping
  breaks `filterQueryId`).

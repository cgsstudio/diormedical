---
name: bricksforge-integration
description: Activate when working with the Bricksforge add-on for Bricks Builder — building Pro Forms, enabling/disabling Bricksforge tools (Popups, Maintenance, Mega Menu, Email Designer, …) or custom elements, managing popups / Email Designer templates, or diagnosing why brf-* elements are missing from the builder. Covers the numeric-ID activation registry and the Pro Forms composition recipe.
---

# Bricksforge Integration

Bricksforge is a toolbox add-on for Bricks Builder, not a builder of its own.
Its custom elements (Pro Forms and friends) register as regular Bricks
elements, so **pages are built with the `novamira/bricks-*` abilities** — the
`novamira/bricksforge-*` abilities own what Bricks cannot see: the activation
registry, popup config, and the Email Designer templates.

## When to use

- The user mentions Bricksforge, Pro Forms, "brf-" elements, or Bricksforge
  tools (Popups, Mega Menu, Maintenance, Terminal, Backend Designer, …).
- The user wants a form on a Bricks site and Bricksforge is installed
  (`bricksforge-check-setup` → `active: true`).
- brf-* elements are unexpectedly missing from `bricks-list-elements`.

## When NOT to use

- Generic Bricks page building with no Bricksforge element involved.
- If `bricksforge-check-setup` reports `active: false`: Bricksforge loads
  ONLY when the Bricks theme (or a Bricks child theme) is the active theme.
  An activated plugin with another theme does nothing — report that instead
  of retrying.

## Start here

Always call `novamira/bricksforge-check-setup` first. It reports version and
floor, the activated tools and custom elements with readable names, and
actionable issues (fresh-install empty registry, activated tools the installed
Bricksforge does not provide, unknown IDs from a newer Bricksforge).

## Domain model

**Two separate numeric-ID spaces.** Bricksforge activates features by bare
numeric IDs, with no name registry of its own:

- **Tools** (feature modules) live in the `brf_activated_tools` option as a
  flat array of integers. Tool 5 = Popups, tool 11 = Form Submissions, tool
  13 = Email Designer.
- **Custom elements** live in `brf_activated_elements` as an array of
  `{id: N}` objects. Element 5 = the whole Pro Forms bundle. Element 3 =
  Popup Trigger.

The same number means different things in each space — never mix them. The
`bricksforge-list-tools` and `bricksforge-list-custom-elements` catalogs are
the authoritative ID→name maps; target writes by `slug` to stay readable.

**Tool catalog notes.**
- Not every tool exists in every Bricksforge install. `bricksforge-list-tools`
  flags each one with `available`; `bricksforge-enable-tool` rejects a tool with
  `available: false` (`bricksforge_tool_unavailable`) because Bricksforge would
  ignore its ID. Suggest updating Bricksforge instead.
- Slug `global-classes` (tool 10) is **Global Classes Backups**. The Global
  Classes management tool is `global-classes-manager` (tool 23).
- Animations are split across tools. `animations` (tool 1, Animation Controls)
  holds the per-element GSAP settings. `animation-panel` (tool 19) runs GSAP
  timelines, and `node-editor` (tool 20) runs node flows. When those two are
  available, `panel` (tool 6) only runs Events, so enabling `panel` alone does
  not bring timelines back. When they are not available, `panel` runs Events,
  timelines and node flows. Storage does not move with the tools: events and
  timelines are stored in the `brf_panel` option, node flows in
  `brf_panel_nodes`.
- `builder-customizer` (tool 21) covers builder roles/permissions, and
  `white-label` (tool 22) covers white labelling.

**Activation semantics.**
- An element ID not present in the registry means its brf-* elements do not
  register in Bricks at all: they vanish from `bricks-list-elements` and
  stop rendering on saved pages (the saved tree keeps them, so re-enabling
  restores rendering).
- Element 5 activates 42 elements in one flag: the form container
  (`brf-pro-forms`), the steps container, and every `brf-pro-forms-field-*`.
- Element 13 (API Query Builder Load More) auto-activates whenever tool 18
  (API Query Builder) is active, regardless of the registry.
- Fresh installs have an EMPTY element registry — Bricksforge registers
  nothing until elements are enabled.

## Abilities

| Ability | Use for |
|---|---|
| `bricksforge-check-setup` | Env report + issues. Run first. |
| `bricksforge-list-tools` | Tool catalog with IDs, slugs, active flags. |
| `bricksforge-enable-tool` / `bricksforge-disable-tool` | Toggle a feature module by id or slug. Disable keeps settings and data. |
| `bricksforge-list-custom-elements` | Element catalog with the Bricks element names each ID registers. |
| `bricksforge-enable-element` / `bricksforge-disable-element` | Toggle element availability in Bricks. Disable also drops per-element settings stored on that row (captcha/Stripe keys on id 5, Font Awesome kit on id 1) — same as the Bricksforge UI. |
| `bricksforge-list-popups` / `bricksforge-get-popup` | Popup configs (compact list / full config with never-fires warnings). |
| `bricksforge-create-popup` / `bricksforge-edit-popup` / `bricksforge-delete-popup` | Popup CRUD. Content = a Bricks template; behaviour = trigger/displayOn/animation config. |
| `bricksforge-list-email-templates` / `bricksforge-get-email-template` | Email Designer templates: which WordPress/WooCommerce emails each overrides, subject, status, theme. `get` adds the visual body only with `include_canvas`. |
| `bricksforge-edit-email-template` | Edit template METADATA (name, subject, overridden emails, theme) — not the visual body. |
| `bricksforge-enable-email-template` / `bricksforge-disable-email-template` | Turn a template's email override on/off. |
| `bricksforge-duplicate-email-template` / `bricksforge-delete-email-template` | Copy a design into a new (inactive, no-override) template / permanently remove one. |
| `bricksforge-list-email-themes` / `bricksforge-get-email-theme` | Read the saved Email Designer themes a template can bind to. |

## Workflows

### Turn on the Pro Forms surface

1. `bricksforge-check-setup` — if element 5 and tool 11 are already active,
   skip ahead.
2. `bricksforge-enable-element` `{slug: "pro-forms"}` — the 42 form elements
   register in Bricks from the next request.
3. Only if the form must store entries: `bricksforge-enable-tool`
   `{slug: "form-submissions"}`. The `create_submission` action is only offered
   while this tool is on. The `email` action does not need it.

### Build a contact form on a page

Pro Forms is a **nestable** Bricks element: the form is `brf-pro-forms` with
field elements as children (prefer this over the legacy `fields` repeater
control). Compose it with the Bricks content abilities:

1. Discover controls as needed: `bricks-list-elements` with
   `element: "brf-pro-forms"` (form actions, email settings) or a field name
   like `brf-pro-forms-field-text`.
2. Write the tree (`bricks-set-content` / `bricks-insert-content`):

```json
[
  {"id": "brform", "name": "brf-pro-forms", "parent": 0,
   "children": ["field1", "field2", "subm01"],
   "settings": {"actions": ["create_submission", "email"],
                 "emailSubject": "New contact"}},
  {"id": "field1", "name": "brf-pro-forms-field-text", "parent": "brform",
   "children": [], "settings": {"label": "Name", "id": "name1", "required": true}},
  {"id": "field2", "name": "brf-pro-forms-field-email", "parent": "brform",
   "children": [], "settings": {"label": "Email", "id": "email1", "required": true}},
  {"id": "subm01", "name": "brf-pro-forms-field-submit-button", "parent": "brform",
   "children": [], "settings": {"label": "Send"}}
]
```

Key points (render-verified):
- The submit button text control is `label`, not `text`.
- Give every field an explicit `id` setting (`name1`, `email1`) — Bricksforge
  keys the stored/emailed values by it.
- Field labels are HIDDEN by default: set `showLabels: true` on the
  `brf-pro-forms` container to render them, otherwise only the field `id`
  shows in the DOM (no visible label, no placeholder).
- `actions` picks what happens on submit: `create_submission` stores the row
  in the submissions table (needs the `form-submissions` tool), `email` sends
  mail (configure the `email*` controls), plus `redirect`, `webhook`,
  `post_create` (NOT `create_post`), `stripe`, and others. The `actions`
  control options of `brf-pro-forms` are the source of truth.
- Style with global classes / theme styles via the Bricks abilities, not
  per-element inline style settings.

**Choice fields (select / radio / checkbox).** Options are NOT free-form:
- `brf-pro-forms-field-select`: set `dataSourceSelect: "static"` (the control
  key is `dataSourceSelect`, NOT `dataSource`) and fill the `dataSourceCustom`
  repeater with `[{value, label}, …]` (or nest `brf-pro-forms-field-option`
  children).
- Radio / checkbox are per-option elements: a `brf-pro-forms-field-radio-wrapper`
  (or `-checkbox-wrapper`) parent holds the group label + `id`, with individual
  `brf-pro-forms-field-radio` / `-card-radio` / `-image-radio` children.
Discover control keys with `bricks-list-elements element=<name>` — note it
returns a full control set for `brf-pro-forms`, `-field-submit-button`, and
`-field-select`, but currently returns nothing for the plain field types
(`-field-text`/`-email`/`-textarea`/…); for those the field keys are the
documented `label`, `id`, `required`, plus `hasConditions`/`conditions`.

**Multi-step forms.** `brf-pro-forms-steps` is a standalone step INDICATOR, not
a container — do NOT nest fields inside it or they silently vanish. Real steps
are `brf-pro-forms-field-step` markers placed directly inside `brf-pro-forms`,
with `brf-pro-forms-field-next` / `-previous` navigation buttons between them.

**Conditional fields (show/hide logic).** Field visibility conditions are
plain element settings on the field, so set them with the Bricks content
abilities — there is no dedicated Bricksforge ability. On the field to
show/hide: set `hasConditions: true`, a `conditionsRelation` (`"and" | "or"`),
and a `conditions` repeater of rules. **Get the rule shape right — it is not
obvious:**

- `condition` is a fixed **source type**, NOT the source field's id. For field
  logic it is `"form_field"` (compare another field in this same form),
  `"storage_item"`, or `"global_variable"`.
- For `"form_field"`: **`value` holds the source field's `id` setting** (the
  field you are testing), **`value2` holds the value to compare against**, and
  `operator` is the comparison. The frontend matches the source field by
  `name="form-field-<value>"`, so that source field MUST carry an explicit `id`.
- `operator` ∈ `==`, `!=`, `>`, `>=`, `<`, `<=`, `contains`, `not_contains`,
  `contained_in`, `is_not_contained_in`, `starts_with`, `ends_with`, `empty`,
  `not_empty`, `exists`, `not_exists` (default `==`). `type` ∈ `string | number | array` (default `string`).

Render-verified example — a text field that appears only when the select
`reason1` equals `support`:

```json
{"id": "order1", "name": "brf-pro-forms-field-text", "parent": "brform",
 "settings": {"label": "Order number", "id": "order1",
   "hasConditions": true, "conditionsRelation": "and",
   "conditions": [
     {"condition": "form_field", "value": "reason1",
      "operator": "==", "value2": "support", "type": "string"}]}}
```

**Submit-button conditions** are NOT set on the submit-button element. They
are settings on the parent `brf-pro-forms` element: `submitButtonHasCondition:
true`, `submitButtonConditionsRelation` (`"and" | "or"`),
`submitButtonConditionAction` (`"disabled"` default, or `"hide"`), optional
`submitButtonConditionsAlternativeText`, and a `submitButtonConditions`
repeater. Each rule has these keys: `submitButtonCondition` (source: `option`,
`post_meta`, `storage_item`, `form_field`, `submission_count_reached`,
`submission_field`), `submitButtonConditionValue`,
`submitButtonConditionOperator` (the operators above),
`submitButtonConditionValue2`, `submitButtonConditionType`, and
`submitButtonConditionPostId` (for `post_meta`). They do not need the Element
Conditions tool (`conditional-logic`). That tool is for Bricks element
conditions, not Pro Forms. Confirm the keys with
`bricks-list-elements element=brf-pro-forms`.

**Bricksforge dynamic data.** Bricksforge registers its dynamic-data tags as a
standard Bricks provider (`bricksforge`), so they are already discoverable and
resolvable through the base Bricks abilities — NOT a Bricksforge ability. Turn
on the tool (`bricksforge-enable-tool slug dynamic-data`), then use
`novamira/bricks-list-dynamic-data` to see every `{...}` tag the `bricksforge`
provider exposes and `novamira/bricks-resolve-dynamic-data` to preview a value.
Use those tags in any element setting via the Bricks content abilities. (Don't
hardcode a tag list — it changes per Bricksforge version; list it at runtime.)

**Bricksforge per-element animations (Animation Controls, tool 1).** Like
conditions, these GSAP animation settings (`brf_animations_*` keys) live on the
element, not in a separate store. Set them via the Bricks content abilities
after enabling the `animations` tool, and discover the keys with
`bricks-list-elements`. There is no dedicated Bricksforge ability and nothing
to CRUD. Timelines built in the Bricksforge panel are a different feature
(`animation-panel`, or `panel` where `animation-panel` is unavailable) with no
ability coverage. BUT: these abilities write the
animation blind (server-side, no preview), and GSAP animations — especially
ScrollTrigger timing (start/end/scrub), easing, and staggers — are visual and
iterative. Simple animations (a fade, a slide) are fine to set this way; for
anything more, drive a browser MCP to load the page, watch the animation, and
iterate on the values, rather than guessing them blind.

### Create a popup

1. Content first: create or pick a Bricks template
   (`bricks-list-templates` / `bricks-create-template`, then build it with
   the Bricks content abilities).
2. `bricksforge-create-popup` with `template` and a `trigger`
   (`pageLoad` + optional `seconds`, `click` + `selector`,
   `scrollPosition` + px, or `customTrigger`). Defaults: `active: true`,
   `displayOn: entire_website`.
3. Heed the `warnings[]` — a popup missing template/trigger/displayOn or
   with the Popups tool off silently never fires. Only PUBLISHED Bricks
   templates are accepted (the popup endpoint renders them to anonymous
   visitors regardless of status).
4. Prefer a SINGLE page id in `page` targeting: with an array, the
   Bricksforge frontend script matches by substring on the joined ids, so
   `page: [123]` also fires on pages 1, 12, 23 — an upstream quirk.
5. To open it from a page element, add a `brf-popup-trigger` element with
   `settings: {type: "open", popup: "<popup id>"}` — BOTH are required, since
   the element defaults to a close-button (`brf-popup-close`) and only becomes
   an opener (`brf-popup-open` + `data-popup=<id>`) when `type` is `"open"`.
   Any element carrying class `brf-popup-open` and `data-popup=<id>` also works.

### Manage Email Designer templates

The Email Designer replaces WordPress/WooCommerce transactional emails (reset
password, new user, order emails, Bricks form notifications) with custom
designs. It has TWO layers:

- **A metadata subset — covered by abilities.** The abilities read and write
  each template's name, subject, overridden emails (`overrides` /
  `conditions`), on/off status, and theme binding. Nothing else about the
  template is editable here. Where Bricksforge keeps Email Designer
  revisions, only its editor save endpoint creates them; these abilities do
  not. Read a template before editing it if the user may want the old values
  back.
  1. `bricksforge-list-email-templates` — what exists, what each overrides,
     which are active. `tool_active: false` ⇒ none are live (enable the
     `email-designer` tool).
  2. `bricksforge-get-email-template` — one template's metadata; add
     `include_canvas: true` only to inspect/copy the raw design tree.
  3. `bricksforge-edit-email-template` — change name / subject / overridden
     emails / theme binding. Setting `conditions` REPLACES the overrides list.
     Each item's `type` must be the EXACT WordPress/WooCommerce mail-filter
     name — the plugin matches it with `===`, so an abbreviation silently
     never fires. Real names: `retrieve_password_notification_email`,
     `wp_new_user_notification_email` (+ `_admin`), `password_change_email`,
     `email_change_email`, `new_user_email_content`, `new_admin_email_content`,
     `comment_notification_text`, WooCommerce `woocommerce_new_order` etc. For
     Bricks form notifications: `{"type":"bricks_form","formId":"<id>"}` /
     `bricks_form_confirmation`, or `all_bricks_forms` /
     `all_bricks_forms_confirmations`. When unsure, copy the exact string from
     an existing template's `overrides`.
  4. `bricksforge-enable-` / `disable-email-template` — turn a template on/off
     (see the gotcha below: disabling alone may not restore the default
     email).
  5. `bricksforge-duplicate-email-template` — base a new email on an existing
     design; the copy starts inactive with cleared overrides.
  6. `bricksforge-list-email-themes` / `get-email-theme` — the themes a
     template can bind to (`use_theme` + `theme` id).
- **Editor-only — NOT via abilities.** The email BODY (the `canvas`) is a
  WYSIWYG design. This covers every canvas field the installed Bricksforge
  supports, including the preheader and per-element responsive overrides
  where present. Author or restyle it in a browser MCP or
  the Bricksforge Email Designer, then use these abilities to wire which
  emails it replaces, its subject, and whether it is live. Don't try to build
  the layout blind.

**Form field placeholders.** For Bricks / Pro Forms notification emails,
Bricksforge renders the subject, headers, and body as Twig with one variable
per submitted field: `{{brx_<field_id>}}`, with hyphens in the field id turned
into underscores (field `first-name` → `{{brx_first_name}}`). A repeater field
gives `brx_<id>` (an array of rows, to loop over) and `brx_<id>_text` (a
flattened one-line summary).

Gotchas:
- Bricksforge applies **one** template per email; they never both fire. When
  several templates list the same email, it uses the last matching one in the
  stored list (for a specific Bricks form, the first one with that `formId`).
  Keep `overrides` unique per email.
- Its lookup does not skip inactive templates while any template is active.
  So disabling a template that shares an email with an active template does
  not reliably restore the default mail. Also clear the disabled template's
  `conditions` (`[]`), or delete it.

### Toggle a Bricksforge feature

1. `bricksforge-list-tools` — see what is on.
2. `bricksforge-enable-tool` / `bricksforge-disable-tool` by slug
   (`popups`, `maintenance`, `mega-menu`, `conditional-logic`, …). Only tools
   with `available: true` can be enabled.
3. Disabling turns the feature off site-wide but keeps its settings/data;
   re-enabling restores it as it was.

## Gotchas

- **Tool 5 ≠ element 5.** Popups is tool 5; Pro Forms is element 5 (there is
  no `pro-forms` tool). Its submissions tool is 11, slug `form-submissions`.
  That tool powers form storage only, and this integration has no ability to
  read the stored entries. Always resolve through
  the catalogs (or use slugs).
- **Missing brf-* elements** almost always mean the element ID is not in the
  registry (fresh install, or someone disabled it) — check
  `bricksforge-list-custom-elements` before debugging Bricks.
- **`bricksforge-disable-element` loses that row's settings** (the upstream
  UI behaves the same). Warn the user when the target row carries keys
  (`settings_removed: true` in the output tells you it did).
- **Never write `brf_activated_tools` / `brf_activated_elements` by hand**
  (e.g. via a generic option ability): tools must stay flat ints and
  elements must stay `{id}` objects. Writing the wrong shape silently
  deactivates everything. The enable/disable abilities preserve the shapes.
- **Bricksforge popups are not Bricks-core popups.** Bricks itself has popup
  templates; the `bricksforge-*-popup` abilities manage the Bricksforge
  system (`brf_popups` + the popups tool). Don't mix the two when debugging.

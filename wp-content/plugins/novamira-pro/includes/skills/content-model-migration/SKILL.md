---
name: content-model-migration
description: Activate when migrating a field schema between content-modeling plugins — moving ACF field groups to Pods, Pods to ACPT, Meta Box to JetEngine, ASE to ACF, etc. Walks the agent through the four-phase workflow (audit → plan → execute → verify) using content-model-schema as the canonical intermediate format. Schemas only — stored values / postmeta / custom-table rows are explicitly out of scope. Trigger phrases: "migrate fields from X to Y", "move ACF field groups to Pods", "convert Meta Box to JetEngine", "audit what I'd lose moving from JE to ACF", "import this ACPT field group as ACF", "switch field plugin", "AI-assisted field schema migration". This skill turns the canonical vocabulary into a guided, confirmable migration — it does not claim one-click universal import/export.
---

# Content Model Migration

A workflow playbook for moving field schemas between the six supported content-modeling plugins (ACF, Pods, JetEngine, ASE, Meta Box, ACPT). Uses `content-model-schema` as the canonical intermediate format and treats migration as a four-phase, user-confirmed pipeline — never an autonomous one-shot.

## Scope

**Covered:**
- Field groups (boxes, meta groups, CFGroups — whatever the source plugin calls them).
- Fields, with type translation via the `content-model-schema` vocabulary.
- CPTs and taxonomies (when the user explicitly asks; not by default).
- Options pages, with `parent_slug` and `storage_key` distinctions.
- `targets[]` and `belongs[]` location rules — translated to the target plugin's convention.
- `content_types[]` (JetEngine CCT, Pods ACT with `storage: "table"`) when both source and target support them.

**Not covered (out of scope by design):**
- Stored values (`postmeta`, `termmeta`, `wp_options`, JetEngine custom-table rows, Pods ACT table rows). Value migration is a separate problem with plugin-specific storage layouts. Decline politely and direct the user to `WP_Query` + `update_post_meta` style scripts if they insist.
- Lossless conversion of:
  - Conditional logic / visibility rules (ACPT visibility, ACF conditional display).
  - Admin UI hints (column visibility, admin position, icons, JetEngine `filterable_in_admin`).
  - Bidirectional relationships (Pods `sister_id`).
  - Computed / virtual fields whose value is derived at read time.
  - Custom-table CCT semantics when moving to a plugin without `content_types[]` support (becomes a `post_types[]` entry — record-vs-post distinction is lost).
- Builder bindings (Elementor dynamic tags, Bricks dynamic data tokens that reference the source plugin's specific key format). These break on the new plugin and must be re-bound — flag this as a follow-up task, do not attempt to translate in-place.

## Mandatory workflow

Before any write, the agent must walk these phases in order. Skipping a phase is not allowed.

### Phase 0 — Activate the prerequisite skills

In order, in the same turn:

1. `novamira/skill-get slug=content-model-schema` — load the canonical vocabulary.
2. `novamira/skill-get slug=<source-plugin>-integration` — e.g. `acf-integration` if migrating from ACF.
3. `novamira/skill-get slug=<target-plugin>-integration` — e.g. `pods-integration` if migrating to Pods.

Without all three, the agent does not have the knowledge to translate. `skill-get` returns `not_found` only when the slug literally does not exist in the bundled skill index (typo or the skill was renamed) — that's a fatal error, halt. The skill being registered does NOT imply the underlying plugin is installed on the site: that check happens in Phase 1 below via the per-plugin `check-setup` ability. Do not conflate the two.

### Phase 1 — Setup check, both ends

Call check-setup on both plugins. **Both must report active.** Refuse to proceed otherwise — there is no point planning a migration toward an inactive target.

| Source plugin | Setup ability |
|---|---|
| ACF | `novamira/acf-check-setup` |
| Pods | `novamira/pods-check-setup` |
| JetEngine | `novamira/jetengine-check-setup` |
| ASE | `novamira/ase-check-setup` (also requires `pro_active: true`) |
| Meta Box | `novamira/metabox-check-setup` |
| ACPT | `novamira/acpt-check-setup` |

**Handle inactive-plugin null returns explicitly**: if a plugin is not active on the site, its `*-check-setup` ability is NOT registered in the WP Abilities API at all — `wp_get_ability('novamira/<plugin>-check-setup')` returns `null`, NOT a structured `{active: false}` response. Detect this with `if ( null === wp_get_ability(...) )` (or the equivalent in your wrapper) and treat as "plugin inactive — halt with a clear message instructing the user to activate the plugin first". Don't try to coerce a missing ability into a falsy structured response — it'll throw.

Record edition gates carefully. Each is a Phase-1 halt-condition if the target side doesn't satisfy it.

| Edition gate | Requirement | What to check in check-setup output |
|---|---|---|
| ACF Pro field types | `repeater`, `flexible_content`, `gallery`, `clone`, `oembed`, `google_map` | `acf-check-setup` does not currently expose `pro` directly — infer from `version` >= 6.0 AND presence of any Pro-only post-type/options-page abilities. Refuse to plan Pro types when uncertain. |
| **ACF Pro options pages** | Options pages = `acf_add_options_page` / `acf_add_options_sub_page` are **Pro-only**. The free `acf-create-options-page` call may accept the payload but the page never registers at runtime. | `acf-list-options-pages` returns empty after a "successful" create → confirms Free. Add `options_pages` to the canonical → ACF Free incompatibility list. |
| **ACF Pro internal CPTs / taxonomies** | First-class internal post-type / taxonomy editing is Pro 6.1+. | If `acf-list-post-types` is empty AND user expects a migration target, surface "ACF Pro required". |
| Pods ACT | `act_component_active` in `pods-check-setup` | Halt if false and source has `content_types[]` with `storage: "custom_table"`. |
| JetEngine CCT | `cct_module_active` in `jetengine-check-setup` | Halt if false and source has `content_types[]`. |
| **ASE Pro field groups + CCT** | BOTH `pro_active` **AND** `pro_licensed` must be true. `pro_active: true` just means the Pro code path is loaded; `pro_licensed: true` confirms the license is valid at runtime. A licensed-but-not-active site lets writes succeed but the entities never register. | `ase-check-setup` returns both booleans. Halt if either is false. |
| ASE Pro license-only (no field groups module) | `pro_active && pro_licensed && cfgroups_module_active` | Even with both pro flags, CFGroups module must be on. |

**Runtime coexistence — version-dependent AND edition-dependent**: some field plugins conflict at boot because they redeclare each other's core functions. Coexistence is NOT uniform across editions of the same plugin family.

| Pair | Status (2026) |
|---|---|
| ACF Free + Pods | **Coexist** — ACF Free's `get_field()` and Pods' compatibility-shim `get_field()` are guarded against each other. |
| **ACF Pro + Pods** | **Coexist at boot, fatal during a specific activation transition.** At cold boot both plugins load in alphabetical order: ACF Pro first declares `get_field()`, then Pods' compatibility shim (in `compatibility/acf.php`) sees `function_exists('get_field')` and skips its own declaration. No fatal. BUT: if you swap from ACF Free → ACF Pro on a request where Pods is already active, Pods has typically already declared its shim's `get_field()` earlier in the request lifecycle, and ACF Pro's unconditional declaration then collides → `Cannot redeclare get_field()` fatal at the activation moment. **Verified on ACF Pro 6.8.1 + Pods 3.3.8 in both directions.** The agent must: (a) when planning a migration on a site that already has both active, proceed normally; (b) when the user is about to swap ACF Free → ACF Pro and Pods is active, deactivate Pods first OR perform the swap via wp-cli `plugin deactivate pods && plugin install acf-pro --activate && plugin activate pods` so the requests are isolated. |
| ACF Pro + ACF Free | Mutually exclusive (same plugin slug, only one installed). |
| JetEngine + Pods | Recent versions coexist. Older versions had listing/relation hook collisions. |
| ACPT + ACF Pro | Co-exist, documented compatibility checks at boot — no fatal in 2026 stack. |
| ACPT + ACF Free | Co-exist. |
| Meta Box + (any other) | Co-exist. |
| ASE Pro + (any other) | Co-exist. |

**Activation-time vs boot-time** is the distinction to remember. At a cold WP boot, alphabetical plugin load order resolves the ACF Pro + Pods collision automatically (no fatal). At an activation-transition request — when one of the two is being activated and the other is already loaded mid-request — the collision can manifest. The collision is at PHP function-redeclaration time, not at ability time; if a real fatal happens, both plugins' check-setup abilities won't even register because the request never boots.

**Probe at runtime, don't assume.** After Phase 1 setup-checks succeed for both ends, the agent has implicitly confirmed both plugins booted on the same request. If a fatal would have happened, neither check-setup ability would have responded. Therefore the "fatal pair" scenario manifests as a check-setup TIMEOUT or PHP fatal, not as a successful response — handle it like any other Phase 1 failure (halt with the swap workflow below).

Historical pairs to be aware of (probe rather than assume):

- ACF + Pods: declared `get_field()` collision on older versions; resolved in recent releases.
- JetEngine + Pods: listing/relation hook collisions on older versions.
- ACPT + ACF: documented compatibility issues, generally version-specific.

If a fatal pair IS detected (Phase 1 fails despite both plugins being marked active), use the swap workflow:

1. With ONLY the SOURCE active, run Phase 2 (read source schema) and Phase 3 (translate to canonical). Snapshot the canonical JSON.
2. Deactivate the source. Activate the target.
3. Run target `check-setup`, Phase 4 (plan), Phase 5 (execute).
4. (Optional) Reactivate the source for Phase 6 verify — at this point the target side is written and you only need to read the canonical from the target.

Phrase this clearly to the user before starting: "<source> and <target> cannot run simultaneously on this site. Migration will require a brief moment with only the target active. Continue?"

### Phase 2 — Read the source schema

Use the source integration's read abilities to produce a complete picture. The per-plugin read surface is **not symmetric** — each plugin has its own ability names and `include_fields` semantics. Use this table:

| Source plugin | List field groups | Get field group (with fields) | List CPTs | Get CPT | List taxonomies | List options pages | CCT / ACT specific |
|---|---|---|---|---|---|---|---|
| ACF | `acf-list-fieldgroups` | `acf-get-fieldgroup-schema` (**fields always included**, no `include_fields` param) | `acf-list-post-types` | `acf-get-post-type` (**keyed by `key`, e.g. `post_type_book` — not by slug**) | `acf-list-taxonomies` | `acf-list-options-pages` | — |
| Pods | `pods-list-groups pod=<name>` (per-pod) | `pods-list-fields pod=<name>` + `pods-get-field` | `pods-list-pods` (filter `type=post_type`) | `pods-get-pod name=<slug>` (`include_fields=true`, `include_groups=true`) | filter `pods-list-pods type=taxonomy` | filter `pods-list-pods type=settings` | ACT: `pods-list-pods type=pod` (ACT pods register as `type=pod`); reuse `pods-get-pod` |
| JetEngine | `jetengine-list-meta-boxes` | `jetengine-get-meta-box id=<id>` (fields always included) | `jetengine-list-post-types` | `jetengine-get-post-type slug=<slug>` | n/a | `jetengine-list-options-pages` + `jetengine-get-options-page` | **CCT: `jetengine-list-ccts` + `jetengine-get-cct slug=<slug>`** (separate from meta-boxes — using `get-meta-box` on a CCT will fail) |
| ASE Pro | `ase-list-field-groups` | `ase-get-field-group id=<id> include_fields=true` | `ase-list-post-types` | `ase-get-post-type` | `ase-list-taxonomies` | — | — |
| Meta Box | `metabox-list-field-groups` | `metabox-get-field-group-schema key=<key> include_fields=true` | `metabox-list-post-types` | `metabox-get-post-type` | `metabox-list-taxonomies` | `metabox-list-settings-pages` + `metabox-get-settings-page` | — |
| ACPT | `acpt-list-field-groups` | `acpt-get-field-group-schema name=<name> include_fields=true` (returns 3-level: meta_boxes → fields) | `acpt-list-post-types` | `acpt-get-post-type` | `acpt-list-taxonomies` | `acpt-list-option-pages` + `acpt-get-option-page` | — |

The agent now has the full source picture in the source plugin's native shape.

### Phase 3 — Translate to canonical

Map every source entity into a `content-model-schema` JSON document. Use the per-plugin "Mapping guidance" section below for field-type and structural translations.

While translating:

- For every concept that does NOT fit the canonical vocabulary, append an entry to `warnings[]` with:
  - `path` — JSON-pointer-ish reference (e.g. `field_groups[0].fields[3]`).
  - `reason` — what is being dropped or transformed, in user-readable language.
  - `severity` — `structural` (semantics changed: bidirectional → unidirectional, custom-table → post-type, computed field → static) or `informational` (UI sugar dropped: admin column hint, icon, label-only difference).
- Never invent canonical types ad-hoc. If a source type has no canonical equivalent, model it with the closest match plus modifiers and warn about the gap.

### Phase 4 — Show the plan

Print to the user:

1. The canonical schema (compact summary if large — full body on request).
2. The migration plan: for each canonical entity, the target plugin write that will be performed (ability name + payload shape).
3. The full `warnings[]` list with severity.
4. Any structural warnings called out at the top of the message.

**The plan emitted in Phase 4 must already reflect Phase 5 routing decisions** — do not put `pods-create-cpt` in the plan only to swap to `pods-extend-builtin` in execute. Specifically:

- **Pods write-side: emit `pods-extend-builtin` in `migration_plan[]` whenever the target CPT slug already exists in WordPress** (registered by ACF Pro, ACPT, ASE, JetEngine, the source plugin itself, or any non-Pods source). Use `pods-create-cpt` only when the slug is genuinely new. Check existence with `post_type_exists('<slug>')` (PHP) or with the source plugin's `*-get-post-type` call. The same rule applies even when the source canonical does NOT include a `post_types[]` entry — i.e. when migrating only field groups but their `targets[]` reference a slug owned by another plugin.
- Same logic for taxonomies: prefer `pods-extend-taxonomy`-equivalent attachment paths over re-registering an already-registered taxonomy.

**Stop and request explicit user confirmation before any write.** If `warnings[]` contains entries with `severity: structural`, the confirmation must restate them ("Confermi anche queste perdite strutturali? <list>").

### Phase 5 — Execute (only after confirmation)

For each canonical entity, call the target plugin's `create-*` ability with the translated payload. In dependency order:

1. CPTs (if migrating; targets reference them via `targets[].kind: post_type`).
2. Taxonomies.
3. Content types (CCT / ACT) if both ends support them.
4. Options pages.
5. Field groups — write LAST so all referenced post types / taxonomies / options pages exist.

If any write fails, halt the pipeline. Do not auto-retry. Report which entity failed, the error, and the partial state to the user.

**Per-plugin write abilities and CPT-label parameter shapes** — the canonical `label_singular` / `label_plural` must be translated to each plugin's parameter convention. This is the most common source of write-side rejection:

| Target plugin | Create CPT ability | CPT label keys it expects |
|---|---|---|
| ACF | `acf-create-post-type` | `title` (slug-like), `labels: { singular: "Book", plural: "Books" }` — labels are NESTED under `labels` |
| Pods | `pods-create-cpt` | `name` (slug), `label_singular: "Book"`, `label_plural: "Books"` — flat top-level |
| JetEngine | `jetengine-create-post-type` | `general_settings: { name: "Books" }` (plural), `labels: { singular_name: "Book" }`; slug auto-derived from name if omitted |
| ASE Pro | `ase-create-post-type` | `name` (slug), `singular: "Book"`, `plural: "Books"` |
| Meta Box | `metabox-create-post-type` | `slug` (slug), `title: "Book"` (or alias `singular_name: "Book"`), `labels: { name: "Books", singular_name: "Book", menu_name: "Books", … }`. The plural is NESTED under `labels.name`, NOT a top-level key. The ability's `additionalProperties: false` rejects a top-level `name`. |
| ACPT | `acpt-create-post-type` | `name` (slug), `singular: "Book"`, `plural: "Books"` |

Same idea for field-group creation — each plugin has its own ability and payload skeleton. Read the ability description (`wp_get_ability(...)->get_input_schema()` or directly the file under `includes/abilities/<plugin>/`) before composing the payload. **Do not assume canonical key names match the target's keys.**

| Target plugin | Create field group | Create field |
|---|---|---|
| ACF | `acf-create-fieldgroup title=... location=[[{param,operator,value}]] fields=[...]` | (fields go into the field-group payload — no separate ability) |
| Pods | `pods-create-group pod=<name> name=...` | `pods-create-field pod=<name> group=<name> name=... type=...` (CALL group BEFORE fields) |
| JetEngine | `jetengine-create-meta-box id=... args={object_type,allowed_post_type} meta_fields=[...]` | (fields inline in `meta_fields`) |
| ASE Pro | `ase-create-field-group ...` | (fields inline in payload) |
| Meta Box | `metabox-create-field-group key=... title=... post_types=[...] fields=[...]` | (fields inline) |
| ACPT | `acpt-create-field-group name=... belongs=[{belongs_to, operator, find}] boxes=[{name, fields:[...]}]`. **`belongs_to` is an enum**: one of `customPostType` (NOT `post_type` and NOT `post`), `taxonomy`, `optionPage` (camelCase, NOT `options_page`), `user`, `comment`. `find` is the slug of the target. `operator` is `"="`. Guessing `belongs_to: "post"` returns `acpt_create_field_group_failed` with no useful detail. | (fields inline, nested under `boxes[]`) |

**Pods write-side decision**: when the target CPT already exists in WordPress (registered by ANY mechanism), prefer `pods-extend-builtin name=<existing-slug>` over `pods-create-cpt`. This avoids the dual-ownership conflict (safety rule #7) and is the safer default for ACF→Pods, ACPT→Pods, JE→Pods migrations where the source plugin owns the CPT registration and the user wants Pods to ADD field-group capability without replacing it.

### Phase 6 — Verify

Re-read the target schema with the same abilities used in Phase 2, translate it to canonical (same Phase 3 logic), and produce a verification summary:

- Entities created in the target vs entities in the source canonical.
- Fields present per field-group, count match.
- Any drift in field types between source canonical and target canonical (should be empty if Phase 3 was correct).
- Re-emitted `warnings[]` from the target read — these document what survived round-trip vs what was lost.

Surface the verification summary to the user before declaring success.

## Modes

The user can request a subset of the pipeline:

| Mode | Phases run | Output |
|---|---|---|
| `audit-only` | 0 → 3 | Canonical schema + warnings[]. No plan, no write. |
| `plan` | 0 → 4 | Canonical schema + migration plan + warnings[]. No write. |
| `execute` | 0 → 6 | Full pipeline; requires explicit confirmation between 4 and 5. |
| `verify` | 6 only (against existing target) | Verification summary comparing source canonical vs target canonical. Useful after a manual migration. |

If the user does not specify a mode, default to `plan` and ask whether to execute.

## Safety rules

These are non-negotiable. The agent must apply them even if the user asks to skip them.

1. **Never overwrite an existing target field group / CPT / taxonomy / options page** without the user explicitly typing the entity name to confirm. If the target already has an entity with the same name/key/slug as one we are about to create:
   - **Stop the execute phase.**
   - Propose: (a) skip this entity, (b) rename (offer a suffix like `_imported`), or (c) merge — only when the structures are compatible.
2. **Never modify source CPTs or taxonomies** during migration. The migration is additive on the target side; the source is read-only.
3. **Never migrate stored values** as part of a schema migration. If the user asks for "everything", restate the scope ("Migro lo schema. I valori richiedono un passo separato.") and require explicit re-confirmation if they insist on values too.
4. **Always pause on `severity: structural` warnings** before Phase 5. Read them back to the user verbatim and require yes/no.
5. **Refuse delete operations on the source.** The migration never deletes the source field group. Cleanup is a separate, explicit task.
6. **One pipeline per turn.** Don't chain multiple source-to-target migrations in one execute phase — each gets its own audit/plan/confirm cycle.
7. **CPT ownership conflict** (genuine footgun, plugin-vs-plugin). When BOTH the source plugin and the target plugin register CPTs (ACF Pro 6.1+, Pods, ASE Pro, ACPT, JetEngine CCT-as-CPT mode), migrating a CPT means two plugins will fight to register the same `post_type` slug on every page load. The migration is NOT complete just because both writes succeeded — the user must explicitly remove the source registration after verification.
   - **Detect** in Phase 4 plan, in either of these cases:
     - (a) the source canonical includes `post_types[]` AND the target plugin also registers CPTs through its own admin, OR
     - (b) the source canonical includes only `field_groups[]` BUT one or more `targets[].slug` refers to a `post_type` already registered in WordPress by a different plugin (call `post_type_exists()` to confirm). In this case the field group can be migrated but the agent must STILL decide whether to attach to the existing CPT (extend-builtin) or attempt to take ownership of the CPT registration (rarely correct).
   - **Resolutions**:
     - (a) **Convert and migrate**: the user approves removing the source CPT registration immediately after the target side is verified (Phase 6). Note in plan output.
     - (b) **Extend-builtin / attach-only**: instead of creating a new CPT on the target, attach a field group to the *already-registered* source CPT. On Pods this is `pods-extend-builtin`; on ACPT this is achieved by setting `belongs[].find` to the existing slug; on ACF this is just `location` rules. The CPT stays owned by the source plugin; only the field group moves. Often the cleanest path.
     - (c) **Skip the CPT**: migrate only the field groups, leave the source CPT alone. Default for "additive only" migrations.
   - If the user does not pick, halt — do not silently create the conflict.
8. **Pods reserved field names — check at write-side, not at translate-side.** Pods rejects field names that collide with WP query vars (`author`, `category`, `post`, `tag`, …) via the `pods_reserved_keywords` filter. The check belongs in Phase 5 (just before each `pods-create-field` call), not in Phase 3 (translate). The source plugin's read abilities have no view of the target's reserved set, so do not try to populate a `warnings[]` entry from the translate side. If `pods-create-field` rejects a name during execute, halt, propose a prefixed alternative (`book_author`), and resume from there.

## Mapping guidance — per plugin

Field-type translation goes through the `content-model-schema` ~18-type vocabulary. Plugin-specific quirks the agent must know:

### ACF

- `repeater`, `flexible_content`, `gallery`, `clone` are **ACF Pro only**. If the target site is ACF Free, these cannot be migrated as-is — represent them on the canonical side and warn the user.
- ACF field group `key` is opaque (`field_<hash>`) — auto-generate on create-side, do not preserve from source.
- `location` rules → canonical `targets[]`. ACF supports complex `OR` / `AND` location grids; flatten to the simplest equivalent and warn if you lose nuance.
- ACF Pro 6.1+ internal post types and taxonomies are first-class WP entities — read with `acf-get-post-type` / `acf-get-taxonomy`.
- **`acf-get-post-type` is keyed by `key` (e.g. `post_type_book`), not by `slug`** (e.g. `book`). The opaque key comes from `acf-list-post-types`; the slug is a property of the returned object. Do not pass the slug to `get-post-type` — it returns "not found". Asymmetric with Pods, which is keyed by slug (`name`).
- **`acf-get-fieldgroup-schema include_fields=true` returns ~80% runtime noise** per field: `_valid`, `aria-label`, `wrapper`, `value`, `prefix`, `class`, `id`, etc. For canonical translation, extract only: `name`, `type`, `label`, `required`, `instructions`, `default_value`, `conditional_logic`, `sub_fields` (for repeater/flexible/group), `min`/`max` (for number/repeater), `choices` (for select/radio/checkbox), `post_type`/`taxonomy`/`relationship` constraints, `mime_types`. Drop everything else.
- **`menu_order` ↔ Pods `weight` / ACPT `sort` / MB array index** — ACF stores per-field display order in `menu_order`. Canonical does not carry a dedicated field, but the order of `field_groups[i].fields[]` IS load-bearing on the target side. **Always emit canonical `fields[]` in source `menu_order` order**, and translate to the target's mechanism (`weight` for Pods, `sort` for ACPT, array index for ACF/MB). Without this, the display order in the admin UI is randomised.
- **ACF post type stores ~35 `labels` keys** (`add_new_item`, `view_items`, `archives`, `attributes`, `insert_into_item`, etc.); Pods stores 2 (`label_singular`, `label_plural`). The canonical `post_types[].label_singular` / `label_plural` covers only the two universal ones — the other 33 are dropped silently. Add an `informational` warning when migrating a CPT with a fully-fleshed `labels` block: "ACF defines 35 admin-label strings; the target plugin only honours singular + plural. WP core auto-derives the rest from those two."

### Pods

- A field declared as `repeatable: true` is **NOT a repeater type** — Pods does not have a Repeater field type. Canonical `text` / `number` / etc. with `repeatable: true` → emit as `{type: <native>, name: <slug>, repeatable: 1}`.
- **Canonical `repeater` (multi-field rows) → Pods has NO direct equivalent.** Pods' `repeatable: true` modifier works only for single-field repetition. For multi-field repeating rows the agent must pick one of:
  - (a) **Flatten to multiple `repeatable: true` scalar fields, one per row column.** E.g. canonical `{type: "repeater", name: "chapters", fields: [title text, pages number]}` becomes two Pods fields: `chapters_titles` (text, repeatable) + `chapters_pages` (number, repeatable). **Severity: structural** — the row-association (which title goes with which pages value) is lost; index alignment is by array position only and breaks on any user edit.
  - (b) **Create a Pods ACT (custom-table sub-pod)** keyed to the parent post via a relationship field. Real rows, real referential integrity. **Severity: informational** — schema-wise this is the correct translation but it's a structural change (canonical `repeater` becomes canonical `content_types[]` + `relationship`). Requires `act_component_active` on the Pods install and explicit user approval.
  - Default to (b) if `act_component_active` is true; otherwise (a) with the structural warning. Surface both options in the Phase 4 plan with `next_action_required: user_decision_on_structural_warnings`.
- **Read-side: `pods-list-fields` and `pods-get-field` return a COMPACT view** (`{id, name, label, type, group_id, weight}`) that DOES NOT include `repeatable`, `repeatable_limit`, conditional logic, or other field options. To read the full options for canonical translation, use **`pods-get-pod name=<slug> include_fields=true`** — its `pod.fields[]` array carries the full options blob (Pods 3.x Whatsit model stores type-specific keys flattened at the top level of the field object: `repeatable`, `pick_object`, `pick_val`, `text_max_length`, etc.). Skipping this step causes silent data loss on Pods→anything migrations.
- Groups are required for fields on Pods 3.x — when writing to Pods, create the group BEFORE the fields.
- Pods ACT with `storage: "table"` → canonical `content_types[]`. ACT with `storage: "meta"` → canonical `post_types[]` (it's effectively a CPT).
- `pick_object` / `pick_val` / `pick_format_type` → canonical `relationship` with `target_kind` + `target` + `cardinality`. Bidirectional `sister_id` is lost.
- Reserved field names (`author`, `category`, `post`, `tag`, …) — Pods will reject them. The check happens **at write-side** (Phase 5), not at translate-side — see Safety rule #8. If `pods-create-field` returns a reserved-name error, propose a prefixed alternative (`book_author`) and resume.
- **Display order**: Pods uses `weight` (integer, lower = earlier) on both groups and fields. Pass `weight` matching the position in `canonical.field_groups[i].fields[j]` (i.e. `j` starting from 0). Without `weight`, Pods auto-assigns a value and the admin UI order becomes unpredictable on subsequent edits.

### JetEngine

JetEngine exposes two **fundamentally different** field surfaces — translate them separately, do not conflate.

**A — JE meta-box fields** (rich UI types stored as postmeta):

- Meta boxes attach to objects (post / taxonomy / user) → canonical `targets[]`.
- Field types: `text`, `textarea`, `wysiwyg`, `number`, `date`, `time`, `datetime-local`, `select`, `radio`, `checkbox`, `switcher`, `colorpicker`, `iconpicker`, `media`, `gallery`, `posts`, `repeater`. Translation to canonical mirrors ACF: `wysiwyg → rich_text`, `switcher → boolean`, `colorpicker → color`, `iconpicker → text` with `format: icon`, `posts → relationship` with `target_kind: post`.
- `posts` field with `is_multiple` toggle → canonical `cardinality: single | multi`. JE compact read does NOT always surface `is_multiple`; default to `single` and warn `informational` when uncertain.
- `id`-with-underscore caveat: a meta-box id like `book_jet` persists at the DB layer but JE admin UI refuses to render the editor. Normalize id slugs to dash-separated (`book-jet`) when emitting target writes.

**B — JE CCT fields** (SQL DDL columns stored in a custom table):

- CCT (Custom Content Type) → canonical `content_types[]` with `storage: "custom_table"`. Migrating CCT to a plugin without custom-table support means converting to `post_types[]` — explicit user approval required (`severity: structural`).
- CCT fields are NOT meta-box fields — they are SQL columns. Read via `jetengine-get-cct slug=<slug>` which returns `db_field_data[]` with column type/length/null/default. Type vocabulary is SQL DDL, not JE UI types.
- SQL column type → canonical type translation:

| SQL DDL column | Canonical |
|---|---|
| `varchar(<=255)` | `text` |
| `varchar(>255)`, `text`, `mediumtext`, `longtext` | `paragraph` (or `rich_text` if the column is documented to hold HTML — agent must infer from context, default to `paragraph` + warn) |
| `int`, `mediumint`, `smallint`, `tinyint(>1)` | `number` with `subtype: integer` |
| `bigint(20)` (when used as FK to wp_posts / wp_users / wp_terms) | `relationship` with appropriate `target_kind` |
| `bigint(20)` (other) | `number` with `subtype: integer` |
| `decimal(M,D)`, `float`, `double` | `number` with `subtype: decimal` |
| `tinyint(1)` | `boolean` |
| `date` | `date` |
| `time` | `time` |
| `datetime`, `timestamp` | `datetime` |
| `enum(...)`, `set(...)` | `choice` (single for enum, multi for set) with `choices: [...]` extracted from the DDL |
| `json` | `paragraph` + warn `informational` (canonical has no first-class JSON type) |

- CCT does NOT have rich field-level metadata: no `label` (use column name as label), no `placeholder`, no `accepts`, no `default_value` beyond the SQL default. **Severity: informational** on these drops.
- CCT admin properties (`is_admin`, `admin_capability`, `cap_type`, `meta_data`) are CCT-type-level settings, not field-level. Drop as `informational` warnings — canonical `content_types[].admin_settings` does not exist.

**C — JE compact-read blind spot** (same root-cause class as ACPT NB-2 and Pods compact-read):

`jetengine-get-meta-box`, `jetengine-get-options-page`, `jetengine-get-cct` all return projected views that omit secondary properties (visibility conditions, admin column flags, conditional values, computed-by callbacks). For full-fidelity round-trip, the agent must use `execute-php` to read the raw `_meta_fields` / `_args` arrays from the underlying option (`jet_engine_meta_boxes` / `jet_engine_options_pages` / CCT DB schema). Until JE's read abilities expose the full shape, JE-as-source migrations are lossy on these advanced properties.

**D — Out of canonical scope**:

- Glossaries, Listings, Queries, Relations — they are rendering/binding entities, not schema. Flag any that exist as "not migrated, recreate in target if needed".

### ASE

- Field groups (CFGroup) attach to a single object — canonical `targets[]` has one entry per group.
- ASE supports a narrower field-type vocabulary than the others. Field types like ASE's `relationship` and `repeater` are supported; layout-only fields (no value storage) → canonical with a warning that they're not real data fields.
- **License gating is two-flag, not one**: BOTH `pro_active` AND `pro_licensed` in `ase-check-setup` output must be true. `pro_active` only means the Pro code path is loaded; `pro_licensed` confirms the license is valid at runtime. A licensed-but-not-active site **lets writes succeed silently but the entities never register** — same class of bug as ACF Free options-pages. Refuse the migration when either flag is false.

### ACPT

- 3-level hierarchy: MetaGroup → MetaBox → MetaField. Canonical `field_groups[i]` ≈ MetaGroup. Each MetaGroup can have multiple MetaBoxes — when migrating to a flat plugin (ACF/Pods/JE/MB/ASE), flatten by prefixing field names with the box name to avoid collisions, and warn.
- Field type vocabulary is title-case (`Text`, `Image`, `Repeater`) — translate to canonical lowercase semantic names.
- The composite key includes the field type — renaming a type on the source side after migration starts will silently break references. Snapshot the source at migration start, do not re-read mid-pipeline.
- ACPT visibility rules (`visibilityConditions`) are NOT in canonical — drop with `severity: informational` warning.
- **Read/write key asymmetry**: `acpt-get-field-group-schema include_fields=true` returns `meta_boxes[]` (the read shape), but the write abilities `acpt-create-field-group` / `acpt-edit-field-group` expect `boxes[]`. Translate the key name when flowing read output into a write payload — feeding read output verbatim to write WILL fail.
- **Advanced options blind spot**: `acpt-get-field-group-schema` returns a compact field view (`{id, name, type, label, sort, required, default_value}`) that DOES NOT include `accepts`, `placeholder`, validation rules, or other advanced options. Same root cause class as the Pods compact read view. For complete advanced-options round-trip, the agent today must either accept the loss (and warn `informational` for each affected field) or fetch the field's `advanced_options[]` directly via the underlying `ACPT_Plugin` PHP layer using `execute-php` as a last resort. Until ACPT's read ability is extended, treat ACPT-as-source field migrations as lossy on advanced options.
- **Canonical `flexible` → ACPT `FlexibleContent`** (the only direct 1:1 mapping in any target plugin). ACPT's `FlexibleContent` field type accepts `blocks: [{name, label, fields: [...]}]` matching the canonical `layouts[]` shape one-to-one. Plan:
  ```json
  { "type": "FlexibleContent", "name": "body", "label": "Body",
    "blocks": [
      { "name": "text_block", "label": "Text Block",
        "fields": [{ "type": "Editor", "name": "content", "label": "Content" }] },
      { "name": "image_block", "label": "Image Block",
        "fields": [{ "type": "Image", "name": "image", "label": "Image" }] }
    ] }
  ```
  No warning needed — semantics survive perfectly. For ANY other target (JE/MB/Pods/ASE), canonical `flexible` has NO direct equivalent: warn `structural` and either drop or convert to a repeater-of-groups (with a row-discriminator field). Document the conversion path in the migration plan.
- **Canonical `group` (single-instance nested) → ACPT**: ACPT has no single-instance `Group` field type — it has `Repeater` (always multi-instance). Two acceptable translations, pick based on user intent:
  - (a) Emit ACPT `Repeater` with `minimum_blocks: 1, maximum_blocks: 1` (top-level keys of the field; ACPT ignores `min_blocks` / `max_blocks`). Preserves nesting, costs one extra UI iteration to add the row. Warn `informational` ("ACPT has no single-instance group; emitted as locked-cardinality Repeater").
  - (b) Flatten with prefix into the parent box, like the 3-level → flat case. No nesting at runtime. Warn `structural` if any sub-field name collides with the parent box's existing fields.
- **Canonical `repeatable: true` modifier on a non-repeater field → ACPT**: same problem, same two options. (a) Wrap as ACPT `Repeater` containing one field of the original type; (b) emit as a flat array stored in a single field (loses the per-value UI). Default to (a) and warn `informational`.

### Meta Box

- Two storage modes: DB-stored (MB Builder) and PHP-registered. Only DB-stored field groups can be edited. PHP-registered groups can be READ (source) but the target-side must always be DB-stored.
- `clone` is a **boolean modifier on any field**, not a field type — canonical `repeatable: true` (on a single-field) covers the single-field-repeats-N-times case.
- `group` field type with sub-fields → canonical `group` with nested `fields[]`.
- **Canonical `repeater` (multi-field rows like `{title, pages}` per row) → MB `group + clone: true`**. The MB construct is: `type: "group"`, `clone: true`, `fields: [...]`. This is the only way MB represents a multi-field repeating row. Skill plan must emit:
  ```json
  { "type": "group", "id": "chapters", "name": "Chapters", "clone": true,
    "fields": [
      { "type": "text", "id": "title", "name": "Title" },
      { "type": "number", "id": "pages", "name": "Pages" }
    ] }
  ```
  Without `clone: true`, MB renders a single instance of the group (semantically equivalent to canonical `group`, not `repeater`).
- `image`, `file`, `image_advanced`, `single_image`, `file_upload`, `image_upload` all map to canonical `media` with different `cardinality` / `accepts` modifiers.
- MB Relationships (separate plugin) define cross-CPT links — out of canonical scope, flag as a follow-up.

## Output schema for each phase

For consistency the agent emits structured output. Suggested shape:

```json
{
  "mode": "plan",
  "source_plugin": "acf",
  "target_plugin": "pods",
  "canonical_schema": { /* the content-model-schema document */ },
  "migration_plan": [
    {
      "phase": "create_post_type",
      "ability": "novamira/pods-create-cpt",
      "payload": { "name": "book", "label": "Books", "..." }
    },
    {
      "phase": "create_group",
      "ability": "novamira/pods-create-group",
      "payload": { "pod": "book", "name": "details", "..." }
    },
    {
      "phase": "create_field",
      "ability": "novamira/pods-create-field",
      "payload": { "pod": "book", "group": "details", "name": "isbn", "type": "text", "..." }
    }
  ],
  "warnings": [
    { "path": "field_groups[0].fields[2]", "reason": "...", "severity": "structural" }
  ],
  "next_action_required": "user_confirmation_to_execute"
}
```

The `next_action_required` field signals the user what response unblocks the pipeline. Use one of this enum (extend only if a genuinely new case appears):

- `user_confirmation_to_execute` — plan is complete and waiting for go-ahead.
- `user_resolution_for_name_collision` — target already has an entity with the same name/key/slug; user must pick skip / rename / merge.
- `user_decision_on_structural_warnings` — one or more `severity: structural` entries in `warnings[]`; user must accept the loss.
- `user_resolution_for_cpt_ownership_conflict` — both source and target plugins register CPTs; user must pick convert / extend-builtin / skip (see safety rule #7).
- `user_decision_to_proceed_with_pro_only_types` — payload uses ACF Pro types (`repeater`, `flexible_content`, `gallery`, `clone`) but the target ACF is Free.
- `none` — pipeline complete; verification summary present.

**Mode-specific output key inclusion** (do NOT emit absent keys as `null` — leave them out of the JSON entirely):

| Mode | `canonical_schema` | `migration_plan` | `verification_summary` |
|---|---|---|---|
| `audit-only` | ✓ | omit | omit |
| `plan` | ✓ | ✓ | omit |
| `execute` | ✓ | ✓ | ✓ (emitted after Phase 6 runs) |
| `verify` | ✓ (the target's re-translated canonical) | omit | ✓ |

For `audit-only`, `next_action_required` should be `none` if there are no actionable warnings or unresolved decisions, or one of the `user_decision_*` enum values if structural warnings or Pro-only types require user input before any follow-up `plan` / `execute` would proceed.

## Things to NOT do

- Don't promise lossless migration. Even within the same family (e.g. ACF Pro → ACF Pro) there can be drift.
- Don't translate Builder bindings (Elementor dynamic tags, Bricks tokens) — they reference the source plugin's key format and will break. Flag for re-binding instead.
- Don't combine schema migration and value migration in one pass.
- Don't run the pipeline against a production site without `audit-only` first. If the user has only one environment, encourage them to dry-run on a clone.
- Don't market or describe this as "one-click universal import/export". Use "AI-assisted field schema migration" — it sets the right expectation.

## Things that are safe and encouraged

- Run `audit-only` first on every new migration request. Cheap, informative, surfaces all warnings before any commitment.
- Snapshot the canonical schema at the start of execute mode (save to a file the user can re-open) — it's the only authoritative record of what was migrated.
- Re-bind Builder dynamic-data references after migration using the `dynamic-data-binding` skill — the target plugin's key format is different.
- For partial migrations (one field group at a time on a large site), use `mode: plan` per group and let the user batch them.
- Recommend the source field groups stay live for a transition window — the user can validate the target side against the source before deleting anything.

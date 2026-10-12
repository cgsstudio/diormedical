---
name: ase-integration
description: Activate when working with the ASE plugin (Admin and Site Enhancements) Pro on a WordPress site — Custom Field Types (field groups + values), Custom Post Types, or Custom Taxonomies. Covers the storage model, edit semantics (merge top-level, replace-on-array), value-target abstraction (post / term / options-page), and the discover→list→get→modify→verify workflow. Treats ASE as the site's chosen content-modeling tool; coexistence with ACF/Pods/JetEngine is a footnote.
---

# ASE Integration

## When to use

Activate this skill when:
- The user mentions ASE, ASENHA, or "Admin and Site Enhancements".
- The user asks to create, edit, or delete a custom post type, taxonomy, or field group on a site that has ASE active.
- The user asks to read or write field values on a post, term, or options page on a site using ASE.
- `ase-check-setup` returns `active: true`.

## When NOT to use

- If ACF, Pods, or JetEngine is the primary content-modeling tool on the site, use the corresponding integration skill instead.
- Always call `ase-check-setup` first. If `pro_active: false`, the content-modeling abilities are not available — see Free vs Pro below.
- For a plugin-agnostic representation of the site's content model (snapshots, audits, cross-plugin migrations), activate the `content-model-schema` skill alongside this one.

## Free vs Pro reality check

On ASE Free, only `novamira/ase-check-setup` is registered. All other 17 ASE abilities require ASE Pro (Custom Field Types and Custom Content Types are Pro-only modules).

When `ase-check-setup` returns `pro_active: false`:
- Tell the user that the ASE content-modeling abilities require ASE Pro.
- If ACF Pro, Pods, or JetEngine is also installed, offer those as alternatives.
- Do NOT attempt to call any other ASE ability — they are not registered and will fail.

## License-state gotcha — pro_active vs pro_licensed

ASE Pro can be installed (`pro_active: true`) but unlicensed (`pro_licensed: false`). In that state:

- The agent's tool list still contains all 18 abilities.
- The abilities still **write correctly** to the DB — `asenha_cpt`, `asenha_ctax`, `asenha_cfgroup` rows are created with all the right postmeta.
- BUT ASE's CCT module **refuses to register the CPTs/taxonomies at runtime** because the Freemius gate `can_use_premium_code__premium_only` returns false.
- Result: the agent creates a "Books" CPT, the row is in the DB, but `Books` never appears in the wp-admin sidebar.

When `ase-check-setup` returns `pro_active: true, pro_licensed: false`:
- Warn the user that the rows will be saved but won't appear in wp-admin until the ASE Pro license is activated.
- For CFT field groups + values, this still works because the `asenha_cfgroup` post type is registered by ASE regardless of license state.

## Discover before you act

Always start with `novamira/ase-check-setup`. It returns:

| Field | Meaning |
|---|---|
| `active` | Plugin loaded (ASENHA_VERSION defined) |
| `pro_active` | Pro distribution loaded (bwasenha_fs() available — does not mean licensed) |
| `pro_licensed` | Valid Freemius license — required for CPTs/taxonomies to actually register at runtime |
| `cft_module_active` | Custom Field Types module toggle is on — needed for field group and values abilities |
| `cct_module_active` | Custom Content Types module toggle is on — needed for CPT and taxonomy abilities |
| `min_satisfied` | ASE version >= 8.7.0 |
| `field_groups_count` | Registered field groups |
| `custom_post_types_count` | Registered CPTs |
| `custom_taxonomies_count` | Registered taxonomies |

If `active: false` or `min_satisfied: false`, stop and report to the user. If `cft_module_active: false`, toggle it on before calling any CFT ability (see Module-first workflow below).

Then list before getting: call `ase-list-field-groups`, `ase-list-post-types`, or `ase-list-taxonomies` to enumerate existing entities before calling `ase-get-*` on a specific one.

## Module-first workflow

Every CFT ability (`ase-list-field-groups`, `ase-get-field-group`, `ase-create-field-group`, `ase-edit-field-group`, `ase-delete-field-group`, `ase-read-values`, `ase-write-values`) returns `ase_module_disabled` if the CFT toggle is off.

Every CPT/taxonomy ability returns `ase_module_disabled` if the CCT toggle is off.

To enable a module programmatically via `novamira/wp-run-php`:

```php
// Enable Custom Field Types
$opts = get_option('admin_site_enhancements', []);
$opts['enable_custom_field_types'] = true;
update_option('admin_site_enhancements', $opts);

// Enable Custom Content Types (CPTs + taxonomies)
$opts = get_option('admin_site_enhancements', []);
$opts['enable_custom_content_types'] = true;
update_option('admin_site_enhancements', $opts);
```

Alternatively, direct the user to ASE > Settings in wp-admin. After toggling, re-run `ase-check-setup` to confirm.

## Edit semantics: merge top-level, replace-on-array

`ase-edit-field-group`, `ase-edit-post-type`, and `ase-edit-taxonomy` follow the same merge pattern: provide only the keys you want to change. Top-level scalar keys are merged; array keys (fields, location sub-arrays, taxonomies, supports) replace atomically when provided, and are left untouched when omitted.

Field group example — change the title without touching fields:
```json
{ "key": "review_fields", "title": "Review Fields (Updated)" }
```

CPT example — add thumbnail support without resetting other supports:
```json
{ "slug": "review", "supports": ["title", "editor", "thumbnail"] }
```

Taxonomy example — attach to an additional post type:
```json
{ "slug": "genre", "post_types": ["review", "book"] }
```

## Progressive disclosure on reads

### list-* pagination

All list abilities accept `limit` (default 50, max 200), `offset` (default 0), and `search` (substring). Output includes `total`, `has_more`, and `next_offset`. For large sites, iterate with `offset = next_offset` until `has_more: false`.

### get-field-group

Default response: compact metadata + `fields_count`. Add `include_fields: true` to expand the full `fields[]` array. A group with 30 repeater sub-fields would otherwise consume 5-10K tokens on every introspection — only request fields when you need them.

### get-post-type / get-taxonomy

Default response: compact essentials (slug, labels singular+plural, supports, taxonomies, public, rest). Opt-in expansions via `include` array:
- `labels_full` — all auto-generated label keys (31 for CPTs, 26 for taxonomies)
- `capabilities` — capability_type, map_meta_cap, custom slugs (CPT only)
- `rewrite` — rewrite slug, with_front, feeds, pages, ep_mask, query_var

Request only the sections you need; omit the rest.

### read-values

Default: `only_with_values: true` — returns only fields that have a non-empty stored value. A target may have 50 declared fields but only 4 filled; flooding context with 46 nulls is wasteful. Set `only_with_values: false` to see every declared field including unset ones.

`format` controls the return shape: `api` (default, ASE-processed), `input` (form-ready), `raw` (database string). Most agent flows want `api`.

## CFT term values gotcha

Values set on a **term** target live in the custom table `wp_asenha_cfgroup_values_for_terms`, NOT in `wp_termmeta`. `get_term_meta()` will not see them. Always use `ase-read-values` and `ase-write-values` with `target.type: "term"` to round-trip term field values correctly.

## Layout fields

`tab`, `heading`, and `line_break` field types are layout-only — they store no values. If you call `ase-write-values` on a field of these types, the per-field result reports `status: error, code: ase_layout_field_write`, but the call does not fail. All other fields in the same call still write.

## Reserved slugs

`ase-create-post-type` refuses slugs that collide with WordPress core post types: `post`, `page`, `attachment`, `revision`, `nav_menu_item`, `custom_css`, `customize_changeset`, `oembed_cache`, `user_request`, `wp_block`, `wp_template`, `wp_template_part`, `wp_global_styles`, `wp_navigation`. Error code: `ase_post_type_slug_reserved`.

`ase-create-taxonomy` refuses: `category`, `post_tag`, `nav_menu`, `link_category`, `post_format`. Error code: `ase_taxonomy_slug_reserved`.

Pick unique slugs. Prefix with a project or client identifier if needed (e.g. `acme_review` instead of `review`).

## Safe delete by default

`ase-delete-post-type` removes the CPT registration row but keeps all existing posts in `wp_posts` (they become unregistered, not deleted). Pass `force_delete_content: true` to also permanently delete those posts.

`ase-delete-taxonomy` removes the taxonomy registration but keeps existing terms. Pass `force_delete_terms: true` to also delete the terms.

Never pass the force flags without explicit user confirmation.

## Typical end-to-end workflow: Reviews CPT

This example creates a "Reviews" CPT with a "Genre" taxonomy and a field group, then writes and reads back field values.

**1. Check setup.**
```
ase-check-setup
```
Verify `pro_active: true`, `cft_module_active: true`, `cct_module_active: true`.

**2. Create the taxonomy.**
```
ase-create-taxonomy
  slug: genre
  title: Genres
  labels: { singular: Genre }
  post_types: [review]
  hierarchical: true
```

**3. Create the CPT.**
```
ase-create-post-type
  slug: review
  title: Reviews
  labels: { singular: Review }
  supports: [title, editor, thumbnail]
  taxonomies: [genre]
  public: true
```

**4. Create the field group.**
```
ase-create-field-group
  key: review_fields
  title: Review Fields
  fields:
    - { name: score, label: Score, type: number }
    - { name: pros, label: Pros, type: textarea }
    - { name: cons, label: Cons, type: textarea }
  location: { placement: posts, post_types: [review] }
```

**5. Create a sample post of the new type.**
Use `novamira/create-post` (or `wp/run-php`) with `post_type: review`.

**6. Write field values.**
```
ase-write-values
  target: { type: post, id: <post_id> }
  values: { score: 9, pros: Great build quality, cons: Pricey }
```
All per-field results should show `status: ok`.

**7. Read back to verify.**
```
ase-read-values
  target: { type: post, id: <post_id> }
```
Confirms the written values are stored correctly.

## wp/run-php patterns for non-covered modules

ASE has 70+ modules. The abilities cover content modeling only. For everything else, use `novamira/wp-run-php` directly.

**Read the full master ASE config:**
```php
$config = get_option('admin_site_enhancements', []);
// Inspect any module toggle or setting.
```

**Toggle any module:**
```php
$opts = get_option('admin_site_enhancements', []);
$opts['enable_<module_key>'] = true; // or false
update_option('admin_site_enhancements', $opts);
```

**Read Limit Login Attempts failures (no dedicated ability):**
```php
global $wpdb;
$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}asenha_failed_logins LIMIT 50");
```

**Walk Redirect Manager entries:**
```php
$redirects = get_posts([
    'post_type'      => 'asenha_redirect',
    'post_status'    => 'publish',
    'posts_per_page' => 50,
]);
```

**Read Code Snippets (read only — never write):**
```php
$snippets = get_posts([
    'post_type'      => 'asenha_code_snippet',
    'post_status'    => ['publish', 'draft'],
    'posts_per_page' => 50,
]);
// NEVER update snippet content at runtime without explicit user consent.
// Writing PHP execution logic at runtime requires a dedicated consent flow.
```

## Coexistence footnote

If the site also has ACF, Pods, or JetEngine installed alongside ASE, each plugin maintains its own field-group, CPT, and taxonomy registry. They do not share data. Do not migrate content between integrations without explicit user direction. When in doubt, confirm which plugin is the chosen content-modeling tool and use only that integration's abilities.

## Error codes reference

| Code | Meaning |
|---|---|
| `ase_not_active` | ASE plugin is not loaded (guard check failed mid-session) |
| `ase_pro_required` | Feature needs ASE Pro; site is running the Free build |
| `ase_module_disabled` | The relevant module toggle (CFT or CCT) is off in ASE settings |
| `ase_field_group_not_found` | No field group with the given key |
| `ase_field_group_exists` | key already exists with different data (create conflict) |
| `ase_field_name_collision` | Two fields in fields[] share the same name |
| `ase_post_type_not_found` | No CPT registration with the given slug |
| `ase_post_type_slug_reserved` | Slug collides with a WordPress core post type |
| `ase_post_type_slug_conflict` | Slug exists with different data (create conflict) |
| `ase_taxonomy_not_found` | No taxonomy registration with the given slug |
| `ase_taxonomy_slug_reserved` | Slug collides with a WordPress core taxonomy |
| `ase_taxonomy_slug_conflict` | Slug exists with different data (create conflict) |
| `ase_layout_field_write` | Write attempted on a layout field (tab/heading/line_break); per-field only, call succeeds |
| `ase_unknown_field` | Field name not declared in any field group for the target; per-field only, call succeeds |

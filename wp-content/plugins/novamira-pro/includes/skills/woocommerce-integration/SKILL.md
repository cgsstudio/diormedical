---
name: woocommerce-integration
description: Activate when working with WooCommerce on a WordPress site: managing products, variations, categories, tags, attributes, or checking store settings. The read-only abilities plus full product and variation CRUD are all available. Variable products (type=variable) and their variations are fully supported.
---

# Working with WooCommerce

This skill is for using the `novamira/woocommerce-*` abilities together. Read it once at the start of any WC-related task and refer back when in doubt.

## When to use

Activate when the user asks to inspect, query, manage, or report on a WooCommerce store: listing products, creating products and variations, editing pricing or stock, deleting products or variations, searching by SKU, filtering by category, checking what extensions are installed, reading store currency / units, etc.

Do not activate when:
- The user wants order management, refunds, or customer service (out of scope, no orders abilities exist)
- The user wants to configure shipping / tax / payment (out of scope)
- The site does not have WooCommerce installed: call `woocommerce-check-setup` first to confirm

## Domain model

| Entity | Ability | Notes |
|---|---|---|
| Runtime | `check-setup` | Always run FIRST |
| Store | `get-store-settings` | Currency, units, country, address |
| Product (compact) | `list-products` | 15 fields per row + optional sections via `fields[]` |
| Product (full) | `get-product` | All ~50 fields, by id OR slug |
| Product (create) | `create-product` | type=simple or type=variable |
| Product (edit) | `edit-product` | PATCH semantics, by id or slug |
| Product (delete) | `delete-product` | Soft (trash) or force (permanent + variation cascade) |
| Variation (compact list) | `list-product-variations` | Compact rows; `fields:["full"]` for all fields |
| Variation (full) | `get-product-variation` | Full shape by id |
| Variation (create) | `create-product-variation` | Requires parent_id + attributes |
| Variation (edit) | `edit-product-variation` | PATCH semantics by id |
| Variation (delete) | `delete-product-variation` | Soft (trash) by default; permanent with `force: true`. Clears default if needed. |
| Category | `list-product-categories` | Tree-shaped (use `parent` to build hierarchy) |
| Category (create) | `create-product-category` | Name required, optional parent/thumbnail/display_type/menu_order |
| Category (edit) | `edit-product-category` | PATCH semantics by id |
| Category (delete) | `delete-product-category` | force:false refuses if products attached; force:true cascades |
| Tag | `list-product-tags` | Flat |
| Tag (create) | `create-product-tag` | Name required, optional slug+description. Tags are flat. |
| Tag (edit) | `edit-product-tag` | PATCH semantics by id |
| Tag (delete) | `delete-product-tag` | force:false refuses if products attached; force:true removes from products |
| Global attribute | `list-product-attributes` | With terms inline |
| Global attribute (terms list) | `list-product-attribute-terms` | Paginated list of terms for one attribute |
| Global attribute (create) | `create-product-attribute` | Creates the attribute definition |
| Global attribute (edit) | `edit-product-attribute` | PATCH semantics by id |
| Global attribute (delete) | `delete-product-attribute` | force:false refuses if terms or products exist |
| Attribute term (create) | `create-product-attribute-term` | Via attribute_id or taxonomy_slug |
| Attribute term (edit) | `edit-product-attribute-term` | PATCH semantics by term id |
| Attribute term (delete) | `delete-product-attribute-term` | force:false refuses if products/variations reference it |
| Shipping class (list) | `list-product-shipping-classes` | All shipping classes |
| Shipping class (create) | `create-product-shipping-class` | Name required |
| Shipping class (edit) | `edit-product-shipping-class` | PATCH semantics by id |
| Shipping class (delete) | `delete-product-shipping-class` | force:false refuses if products assigned |
| Product type | `list-product-types` | Static: simple/variable/grouped/external + virtual/downloadable flags |
| Product status | `list-product-statuses` | Static: publish/draft/pending/private/trash |

## First-call discipline

Always start with `novamira/woocommerce-check-setup`. It tells you:
- Whether WC is active at all (`active`)
- Whether the version is supported (`meets_minimum`)
- Currency + default country (for price formatting)
- Total published products (gauge the catalog size before paging)
- Which known WC extensions are installed (avoid recommending Subscriptions/Bookings features that aren't there)

If `meets_minimum` is false, stop and tell the user to update WooCommerce. No other ability will work usefully.

## The compact / full / fields pattern

`list-products` returns a **compact** row by default (15 fields, ~200 bytes per product). Use it when the user wants to see "what products exist". `get-product` returns the full object; use it when you need to inspect or report a single product in depth.

When the user wants intermediate detail (e.g. "show me products in the Brand X category"), pass `fields: ['categories']` to `list-products`. Available `fields` opt-in values:

| Field | Adds |
|---|---|
| `categories` | `[{id, slug, name}]` per product |
| `tags` | `[{id, slug, name}]` per product |
| `images` | `[{id, src, alt, position}]` per product |
| `attributes` | `[{id, name, slug, options[]}]` per product |
| `dimensions` | `{length, width, height, weight}` |
| `meta_data` | Caller-visible meta only (underscore-prefixed system keys filtered) |
| `variations` | Id list of variations (full variation objects available via list-product-variations) |
| `brands` | `[{id, slug, name}]` per product (only populated when WooCommerce Brands is active) |

Avoid N+1: do not loop over `list-products` and call `get-product` on each row when you can ask for the fields you need inline.

## Filtering

`list-products` accepts 18 inputs. The most useful patterns:

- **Find by SKU** → `{ sku: "ABC-123" }`
- **Search by title or SKU** → `{ search: "shirt" }`
- **Recent edits** → `{ orderby: "date", order: "desc", limit: 20 }`
- **Out-of-stock audit** → `{ stock_status: "outofstock", limit: 100 }`
- **In a category** → `{ category: "shirts" }` (slug OR id)
- **On sale right now** → `{ on_sale: true }`
- **Drafts only** → `{ status: "draft" }`
- **Recently modified** → `{ modified_after: "2026-05-01T00:00:00+00:00" }`
- **By id list** → `{ include: [1, 2, 3] }`
- **Exclude specific products** → `{ exclude: [99, 100] }` (`include` + `exclude` together resolves to "include AND NOT exclude")

`limit` defaults to 20, max 200 (clamped silently with `truncated: true`). For larger result sets, page via `offset`.

## Workflows

### "What products do I have?"

1. `check-setup` → confirm WC active + meets_minimum + products_count
2. `list-products` with `{ limit: 50 }` for the most recent 50
3. If user wants more, paginate with `offset: 50, 100, …`

### "Find product with SKU XYZ"

1. `list-products` with `{ sku: "XYZ" }`
2. If found, optionally call `get-product` for the full object

### "Show me what's in the Brand X category"

1. `list-product-categories` with `{ search: "Brand X" }` to confirm slug exists
2. `list-products` with `{ category: "brand-x", fields: ["categories"], limit: 50 }`

### "Audit out-of-stock items"

1. `list-products` with `{ stock_status: "outofstock", limit: 200 }`
2. Report by category / type if helpful

### "How many products are on sale right now?"

1. `list-products` with `{ on_sale: true, limit: 1 }` → use the `total` field to report the count without paging all of them
2. Or `{ on_sale: true, limit: 50 }` to actually list them

### "What extensions does this store have?"

1. `check-setup` → read `registered_extensions`. Active ones come back `active: true`. The allowlist covers the major commercial extensions; absence from the list does not mean absence on the site.

## Gotchas

- **Orders are out of scope.** This integration targets WooCommerce 9.0+. Order storage backend is not relevant to the product surface and is not assumed.
- **Variations.** `get-product` on a variable product returns variation ids only (compact). Use `list-product-variations` or `get-product-variation` for full variation objects.
- **`sale_price` is a string or null.** An empty `sale_price` in the WC database is normalized to `null` in both list-products and get-product responses. The effective current price is always `price`.
- **Stock decimals.** `stock_quantity` may be `null` for products with `manage_stock: false`. Read `stock_status` first.
- **Custom product types.** Third-party plugins may register types like `subscription` or `booking`. `list-product-types` returns only the WC-core six. Treat unknown `type` values from `get-product` as opaque.
- **Slug auto-generation.** When two products share a name, WC appends `-2`, `-3`, etc. Slugs are not necessarily stable across creates.
- **Third-party filters can mutate post columns after our validation.** Plugins that hook `wp_insert_post_data` at priority below 10 can rewrite the title, slug, status, or content of a product just before it lands in the database. Our ability validates the input shape but cannot guarantee the persisted column values when other plugins inject mutating filters. This is standard WordPress behavior; if a third-party plugin is interfering, the agent should report what was requested vs what get-product shows after the save.

## Output shape conventions

All list-style abilities return `{ <plural>: [...], total: <int> }` (some also include `returned` and `truncated`). All get-style abilities return the object directly. Errors come back as `WP_Error` with codes `wc_inactive`, `wc_below_minimum_version`, `wc_not_found`, `wc_invalid_input`, `wc_post_type_mismatch`.

## Slug naming patterns

Every ability is `novamira/woocommerce-<verb>-<object>`. Verbs are from the fixed vocabulary: `list`, `get`, `check`, `create`, `edit`, `delete`. No `update`, `patch`, `modify`, `remove`, `fetch`, `retrieve`.

## What is NOT in Sprint 1

So you can set expectations with the user:
- Order CRUD, refunds, fulfillment
- Customer accounts
- Coupons
- Stock mutations (read only via list/get; no `set-stock` yet)
- Pricing automation (sale schedules, bulk price changes)
- Tax / shipping / payment gateway configuration
- Reviews / ratings
- Multilingual handling (WPML / Polylang)
- Reports / analytics
- Variation full objects (only id list returned)
- Product CRUD itself

---

## Sprint 2: Write paths

Sprint 2 adds three write abilities for catalog management. All operate on **simple products only** (type=simple). Variable, grouped, and external product types arrive in Sprint 3. All three require an administrator user.

| Slug | What it does | Annotations |
|---|---|---|
| `novamira/woocommerce-create-product` | Create a new simple product | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-edit-product` | Partially update an existing product (PATCH semantics) | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-delete-product` | Trash or permanently delete a product | readonly: false, destructive: true, idempotent: false |

### Key behavior notes (D1-D10)

- **D1. Simple-only (initial write release):** Passing `type=variable` (or any non-simple type) returns `wc_invalid_input` unless you have the variable-product support available. Use `create-product` only for `type=simple` if variable product support is not yet on your site. Variable/grouped/external types are now supported via the variable-product release.
- **D2. Soft delete by default:** `delete-product { id: 123 }` moves the product to trash. Pass `force: true` for permanent deletion. The response on soft delete includes the product object with `status: "trash"`. The response on force delete includes `deleted_id` and no product object.
- **D3. PATCH semantics on edit:** Only fields you pass to `edit-product` change. Fields you omit stay as-is. To clear a nullable field, pass `null` (or empty string for string fields like `sale_price`).
- **D4. WP-native aliases:** Both short names (`name`, `slug`, `status`, `description`, `short_description`) and WP-native aliases (`post_title`, `post_name`, `post_status`, `post_content`, `post_excerpt`) are accepted on create and edit.
- **D5. SKU uniqueness:** SKU must be unique across the catalog. Duplicates return `wc_sku_exists` (HTTP 409). On edit, a product can re-save its own existing SKU without conflict.
- **D6. Images by id only:** `featured_image_id` (int) and `gallery_image_ids` (int[]) accept media-library attachment ids only. URL upload is not currently supported. A non-existent id returns `wc_attachment_not_found`.
- **D7. Stock coercion:** When `manage_stock: true` and `stock_quantity` is provided, `stock_status` is auto-derived from quantity unless you also pass `stock_status` explicitly. When `manage_stock: false`, `stock_quantity` is ignored.
- **D8. Categories and tags as slug or id:** `categories` and `tags` accept arrays of slugs, ids, or mixed. Unknown slugs return `wc_invalid_input`.
- **D9. Attributes:** Two shapes accepted per attribute entry: `{name, options[], visible?}` for local attributes, or `{taxonomy_slug, options[]}` for global attributes (pa_* taxonomies). Variation attributes require `variation: true` on the attribute entry and are supported for variable products.
- **D10. Full product in response:** All three abilities return the full product object (same shape as `get-product`) on success. Use the returned object directly; no follow-up `get-product` needed.

### Workflows

#### Create a product

1. Optionally run `check-setup` to confirm WC is active and `meets_minimum` is true.
2. Call `create-product` with at minimum `name` and, for a purchasable product, `regular_price`.
3. Optionally provide `sku`, `status`, `categories`, `stock_quantity`, `manage_stock`, `featured`, etc.
4. The response includes the full product object with the assigned `id`. Store the `id` for follow-up operations.

Example:
```
create-product {
  name: "Organic Cotton T-Shirt",
  sku: "ORG-TEE-S",
  regular_price: "29.99",
  sale_price: "24.99",
  status: "draft",
  manage_stock: true,
  stock_quantity: 100,
  categories: ["clothing", "t-shirts"]
}
```

#### Update a product

1. Identify the product by `id` or `slug`.
2. Call `edit-product` with only the fields that need to change.
3. Unchanged fields are not touched.

Example: update only the price of an existing product.
```
edit-product { id: 123, regular_price: "34.99", sale_price: null }
```

To rename a product:
```
edit-product { slug: "old-name", name: "New Name" }
```

Changing product type is not supported. If you need a different type, create a new product and delete the old one.

#### Delete a product

Soft delete (moves to trash, reversible from the WP admin):
```
delete-product { id: 123 }
```

Permanent delete (irreversible):
```
delete-product { id: 123, force: true }
```

Delete by slug:
```
delete-product { slug: "my-product", force: true }
```

### Error codes added in the write-paths release

| Code | HTTP | When |
|---|---|---|
| `wc_sku_exists` | 409 | Duplicate SKU on create or edit |
| `wc_attachment_not_found` | 400 | featured_image_id or gallery_image_ids refers to non-existent attachment |

### Gotchas (Sprint 2: Write paths)

- **Hook-throwing third-party plugins can leave orphan products.** If a plugin hooks `save_post_product` and throws after WooCommerce has already inserted the product row, the create-product ability returns `wc_internal_error` but the product remains in the database (no transaction rollback). This is a WooCommerce core limitation; the agent can list-products + delete-product to clean up if needed.
- **Trashed products are not restorable via `edit-product`.** Once `delete-product` has trashed a product (the default soft delete), it can't be brought back to publish through `edit-product`. Either use WP admin's "Restore" link, or `delete-product` with `force: true` and re-create from scratch.

### Behavior notes (write-path audit)

- **Force-delete after soft-delete:** If you soft-delete a product and then call `delete-product` with `force: true`, the product is permanently removed. The force path now accepts already-trashed products.
- **Idempotent soft-delete:** Calling `delete-product` a second time on an already-trashed product returns a clean success with the current product state. No error.
- **categories: [] WC fallback:** Passing `categories: []` does not produce a truly uncategorized product. WooCommerce assigns its default product category (typically `Uncategorized`). To avoid setting categories, omit the field entirely.
- **stock_status advisory when manage_stock is true:** When `manage_stock: true`, the `stock_status` you pass is advisory only. WooCommerce re-derives it from `stock_quantity` on save unless you also pass `stock_status` explicitly to override.
- **Clear featured image:** To clear the featured image on an existing product, pass `featured_image_id: 0` to `edit-product`.

### Limits in the simple-product write release

- Variable products and their variations (now supported; see the variable-product section below)
- Grouped and external product types (future)
- Image upload from URL (separate `upload-image` ability, future)
- Product duplication (future)
- Restore from trash (use `wp_untrash_post` directly or future ability)
- Bulk create/delete operations (future)
- **`brands` is read-only.** `get-product` returns brands and `list-products` exposes them via `fields: ['brands']`, but `create-product` and `edit-product` reject a `brands` input (additionalProperties:false). To assign a brand to a product, use the WP admin or call `wp_set_object_terms($id, $brand_term_ids, 'product_brand')` directly. Brand assignment via ability arrives in a future release.

---

## Sprint 3: Variable products and variation CRUD

Sprint 3 adds full support for `type=variable` products and five abilities for the variation lifecycle.

| Slug | What it does | Annotations |
|---|---|---|
| `novamira/woocommerce-create-product` (updated) | Now accepts `type=variable` with variation-enabled attributes | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-edit-product` (updated) | Rejects stock fields on variable parents | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-delete-product` (updated) | Force-delete now cascades to all child variations | readonly: false, destructive: true, idempotent: false |
| `novamira/woocommerce-list-product-variations` | Paginated compact list of variations for a parent | readonly: true, idempotent: true |
| `novamira/woocommerce-get-product-variation` | Full variation object by id | readonly: true, idempotent: true |
| `novamira/woocommerce-create-product-variation` | Create a new variation for a variable parent | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-edit-product-variation` | PATCH-edit a variation by id | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-delete-product-variation` | Soft (trash) by default; permanent with `force: true`. Clears default if needed. | readonly: false, destructive: true, idempotent: false |

### Key behavior notes (variable products and variations)

- **Variable parent requires at least one attribute with `variation: true`.** Omitting that flag, or passing `type=variable` with no attributes, returns `wc_invalid_input`.
- **Stock lives on variations, not the parent.** Passing `manage_stock`, `stock_quantity`, or `stock_status` on a variable parent (create or edit) returns `wc_invalid_input`.
- **Variation name is auto-derived.** Do not pass `name` when creating or editing a variation; the ability rejects it. WooCommerce computes the display name from the parent name and attribute combo.
- **Attribute input shape for variations:**
  - Global attribute (pa_* taxonomy): `{ taxonomy_slug: "pa_size", option: "m" }`
  - Custom attribute: `{ name: "Material", option: "Cotton" }`
  - Exactly one of `taxonomy_slug` or `name` per entry, never both and never neither.
  - The `option` value must be present in the parent product's attribute options list.
- **Storefront default variation.** Pass `is_default: true` on create or edit to mark a variation as the default that WooCommerce shows pre-selected on the product page. Only one variation can be default at a time; setting a new default replaces the prior one. On `edit-product-variation`, pass `is_default: false` to clear the default when this variation is currently default.
- **Force-delete parent cascades.** `delete-product` with `force: true` on a variable product permanently deletes all child variations before removing the parent. Soft-delete (no force) does not cascade; use `delete-product-variation` to clean up individual variations first if needed.
- **Variation post type is `product_variation`.** `get-product-variation` and `list-product-variations` only accept variation posts. Passing a product id to `get-product-variation` returns `wc_not_found`.

### Workflows

#### Create a variable product with variations

```
1. create-product {
     name: "Organic Tee",
     type: "variable",
     attributes: [
       { taxonomy_slug: "pa_size", options: ["s", "m", "l"], visible: true, variation: true }
     ]
   }
   → response: product.id = 42

2. create-product-variation {
     parent_id: 42,
     attributes: [{ taxonomy_slug: "pa_size", option: "s" }],
     regular_price: "19.99",
     sku: "TEE-S"
   }

3. create-product-variation {
     parent_id: 42,
     attributes: [{ taxonomy_slug: "pa_size", option: "m" }],
     regular_price: "19.99",
     sku: "TEE-M",
     is_default: true
   }
```

#### List and inspect variations

```
1. list-product-variations { parent_id: 42 }
   → compact rows: id, parent_id, sku, attributes_summary, regular_price, stock_status, is_default

2. get-product-variation { id: <variation_id> }
   → full shape including structured attributes, dimensions, image, description, etc.

3. list-product-variations { parent_id: 42, fields: ["full"] }
   → full shape for every variation in one call
```

#### Edit a variation's price

```
edit-product-variation { id: <variation_id>, regular_price: "24.99", sale_price: "19.99" }
```

#### Change the storefront default variation

```
edit-product-variation { id: <new_default_variation_id>, is_default: true }
```

#### Delete a variation

Default (soft delete, moves variation to trash):
```
delete-product-variation { id: <variation_id> }
→ { success: true, variation: { id: <variation_id>, status: "trash", ... } }
```

Permanent delete (irreversible):
```
delete-product-variation { id: <variation_id>, force: true }
→ { success: true, deleted_id: <variation_id> }
```

#### Delete a variable product (and all its variations)

```
delete-product { id: 42, force: true }
→ cascades to all child variations, then removes the parent
```

### Error codes added in the variable-product release

| Code | HTTP | When |
|---|---|---|
| `wc_not_variable_parent` | 400 | `parent_id` refers to a non-variable product in list/create-variation |
| `wc_attribute_not_variable` | 400 | The attribute exists on the parent but `variation` is not enabled |
| `wc_option_not_on_parent` | 400 | The option slug/name is not in the parent attribute's options list |

### Gotchas (Sprint 3: Variable products and variations)

- **Parent must be published (or at least not trashed) to add variations.** Creating a variation on a trashed parent returns `wc_not_found`. Variations on a draft parent are allowed.
- **Attribute option format for taxonomy attributes.** `option` must be the term slug (e.g. `"m"`), not the label (e.g. `"Medium"`). The ability validates against term slugs internally.
- **`list-product-variations` total count.** The `total` field counts all variation children regardless of `limit` and `offset`; it reflects the true total for the parent.
- **WP-native alias for variation description.** Both `description` and `post_excerpt` are accepted on create and edit for a variation's short description.
- **Attribute option slugs are always lowercase in WC.** When passing `option` in `create-product-variation` attributes, use `"s"` not `"S"`, `"red"` not `"Red"`. The `list-product-attributes` ability returns the canonical slugs.
- **Prices live on variations, not on the variable parent.** Passing `regular_price` or `sale_price` to `create-product` or `edit-product` with `type=variable` returns `wc_invalid_input`.
- **`attributes` on `edit-product-variation` replaces, does not merge.** When you pass `attributes` to `edit-product-variation`, the full attribute combination is replaced. List every attribute the variation should carry.
- **sale_price auto-clear.** If you lower `regular_price` below the current `sale_price` on a variation, WooCommerce silently clears `sale_price`. Set both together to avoid the side-effect.

### Workflow: build a variable T-shirt catalog (3 sizes × 3 colors = 9 variations)

This is the canonical workflow for seeding a variable product with a full combination matrix.

```
1. create-product {
     name: "Classic T-Shirt",
     type: "variable",
     attributes: [
       { taxonomy_slug: "pa_size",  options: ["s", "m", "l"],          visible: true, variation: true },
       { taxonomy_slug: "pa_color", options: ["red", "blue", "green"], visible: true, variation: true }
     ],
     status: "draft"
   }
   → response: product.id = <parent_id>

2. list-product-variations { parent_id: <parent_id> }
   → expect: total: 0  (no variations yet)

3a. create-product-variation {
      parent_id: <parent_id>,
      attributes: [{ taxonomy_slug: "pa_size", option: "s" }, { taxonomy_slug: "pa_color", option: "red" }],
      sku: "TEE-S-RED", regular_price: "19.99"
    }
3b. create-product-variation {
      parent_id: <parent_id>,
      attributes: [{ taxonomy_slug: "pa_size", option: "s" }, { taxonomy_slug: "pa_color", option: "blue" }],
      sku: "TEE-S-BLUE", regular_price: "19.99"
    }
3c. create-product-variation {
      parent_id: <parent_id>,
      attributes: [{ taxonomy_slug: "pa_size", option: "s" }, { taxonomy_slug: "pa_color", option: "green" }],
      sku: "TEE-S-GREEN", regular_price: "19.99"
    }
steps 3d-3i. … repeat for sizes "m" and "l" with each color (6 more calls)

4a. list-product-variations { parent_id: <parent_id> }
    → expect: total: 9, compact rows with attributes_summary ("S / Red", etc.)

4b. get-product-variation { id: <variation_id> }
    → full shape including structured attributes, price, stock status, is_default

5a. edit-product-variation { id: <m_red_id>, is_default: true }
    → marks "M / Red" as the storefront pre-selected default

5b. edit-product-variation { id: <l_blue_id>, regular_price: "24.99", sale_price: "19.99" }
    → discounted large blue variant

6a. delete-product-variation { id: <s_green_id> }
    → soft-delete (trash); response has status: "trash"

6b. delete-product-variation { id: <s_green_id>, force: true }
    → permanent delete; response has deleted_id

7. delete-product { id: <parent_id>, force: true }
   → cascades to all remaining child variations, then removes the parent
```

### What is NOT in Sprint 3

- Grouped and external product types (future)
- Variation image upload from URL (future)
- Bulk variation operations (future)
- Order CRUD, refunds, fulfillment (future track)

---

## Sprint 4: Categories + Tags CRUD

Sprint 4 adds six write abilities for taxonomy management. Categories support hierarchy and WC-specific fields. Tags are flat.

| Slug | What it does | Annotations |
|---|---|---|
| `novamira/woocommerce-create-product-category` | Create a new product category | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-edit-product-category` | PATCH-edit a category by id | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-delete-product-category` | Delete a category; force required when products attached | readonly: false, destructive: true, idempotent: false |
| `novamira/woocommerce-create-product-tag` | Create a new product tag | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-edit-product-tag` | PATCH-edit a tag by id | readonly: false, destructive: false, idempotent: false |
| `novamira/woocommerce-delete-product-tag` | Delete a tag; force required when products tagged | readonly: false, destructive: true, idempotent: false |

### Key behavior notes (categories and tags)

- **Category hierarchy.** Categories are hierarchical. Pass `parent_id` (int) or `parent_slug` (string) to nest under a parent. Pass neither (or `parent_id: 0`) for a root category. Mutual exclusion: passing both returns `wc_invalid_input`.
- **Default category protection.** The WC default "Uncategorized" category (stored in option `default_product_cat`) cannot be edited or deleted (returns `wc_invalid_input`). Identify it by id, not by the slug "uncategorized", since admins can rename it.
- **Delete with products attached.** Without `force`, deleting a category or tag that has published products attached returns `wc_term_has_products` (HTTP 409) with an `attached_count` field. Pass `force: true` to proceed. Categories: orphaned products move to Uncategorized. Tags: tag is removed from all products.
- **Hierarchy cascade on category delete.** When a category with children is deleted, WP promotes all direct children to the deleted category's parent (if it had one) or to root (if it was a root). Deep trees cascade one level at a time per delete.
- **Tags are flat.** `product_tag` is non-hierarchical. Tags have no `parent_id`, `thumbnail_id`, `display_type`, or `menu_order` fields.
- **Category-only fields.** `thumbnail_id` (attachment id, 0/null to clear), `display_type` (default|products|subcategories|both), `menu_order` (int >= 0) only apply to categories. The tag abilities reject these fields.
- **Slug auto-generation.** If `slug` is omitted, WP generates it from `name` via `sanitize_title`. On collision, WP appends `-2`, `-3`, etc. The response reflects the actual stored slug.
- **Name + parent uniqueness (categories).** Two categories can't share the same name under the same parent. Duplicate returns `wc_term_exists` (409).

### Workflow: organize the catalog by categories and tags

#### Create a 2-level category hierarchy and assign products

```
1. create-product-category { name: "Clothing" }
   → root category, response: category.id = <clothing_id>

2. create-product-category { name: "T-Shirts", parent_id: <clothing_id> }
   → child of Clothing, response: category.id = <tshirts_id>

3. create-product-category { name: "Polo", parent_id: <clothing_id> }
   → another child, response: category.id = <polo_id>

4. create-product { name: "Classic Tee", categories: [<tshirts_id>], ... }
   → product assigned to T-Shirts (and implicitly inherits Clothing for breadcrumbs on most themes)

5. list-product-categories {}
   → tree: Clothing (parent=0) > T-Shirts (parent=<clothing_id>) > Polo (parent=<clothing_id>)
```

#### Move a subcategory to a new parent

```
edit-product-category { id: <polo_id>, parent_id: <tshirts_id> }
→ Polo is now a child of T-Shirts instead of Clothing
```

#### Make a category a root

```
edit-product-category { id: <polo_id>, parent_id: 0 }
→ Polo is now a root category
```

#### Set a featured image and display mode on a category

```
edit-product-category {
  id: <tshirts_id>,
  thumbnail_id: <attachment_id>,
  display_type: "subcategories"
}
```

#### Remove a category that still has products (safe path)

```
1. delete-product-category { id: <tshirts_id> }
   → ERROR wc_term_has_products, attached_count: 5

2. Decide: move products manually or proceed with force
   list-products { category: "t-shirts" }
   → see which 5 products are affected

3a. Option A: move products first, then delete
    edit-product { id: <prod_id>, categories: [<other_cat_id>] }
    ...repeat for each product...
    delete-product-category { id: <tshirts_id> }
    → success (no products left)

3b. Option B: force delete (products go to Uncategorized)
    delete-product-category { id: <tshirts_id>, force: true }
    → deleted_id: <tshirts_id>; all products moved to Uncategorized
```

#### Create and assign tags

```
1. create-product-tag { name: "Summer 2026" }
   → tag.id = <summer_id>

2. create-product-tag { name: "New Arrival" }
   → tag.id = <new_id>

3. edit-product { id: <prod_id>, tags: [<summer_id>, <new_id>] }
   → product now carries both tags

4. list-product-tags { search: "Summer" }
   → shows Summer 2026 with count updated

5. delete-product-tag { id: <summer_id>, force: true }
   → tag deleted, removed from all products
```

### Error codes added in the category/tag release

| Code | HTTP | When |
|---|---|---|
| `wc_term_not_found` | 404 | Term id not found in the expected taxonomy |
| `wc_term_exists` | 409 | Duplicate name+parent (category) or duplicate slug (both) |
| `wc_term_has_products` | 409 | Delete refused without force; payload includes `attached_count` |
| `wc_invalid_parent` | 400 | Parent term not found, or circular reference |

### Gotchas (Sprint 4: Categories + Tags)

- **Default Uncategorized is read-only.** The term stored in WP option `default_product_cat` cannot be edited or deleted via these abilities. Identify it by the option, not by the slug "uncategorized".
- **Deleting a cat without force errors with attached_count.** Read the error data: `_data.attached_count` tells you how many published products are affected. You can then decide whether to force or to re-assign products first.
- **Hierarchy cascade promotes children one level.** Delete a middle category and its children move to the deleted category's parent (or to root). They do not skip multiple levels. Each delete in a deep tree promotes only one level.
- **Slug auto-collision append (-2, -3).** If you pass an explicit slug that is already taken, WP silently appends a suffix. The response always reflects the actual stored slug. Check `category.slug` in the response if you need the exact slug.
- **No parent on tags.** Tags are non-hierarchical. Passing `parent_id` or `parent_slug` to tag abilities returns a validation error.
- **`thumbnail_id: 0` clears the category image.** Passing `null` also clears it. Both are accepted.
- **count field reflects only published products.** The `count` field in the term shape counts published products only, matching the WP admin display.
- **Category extras are stored as term meta.** `thumbnail_id`, `display_type`, and `menu_order` use WC term meta conventions (`thumbnail_id`, `display_type`, `order_{term_id}`). These are standard WC fields; any theme or plugin that reads these meta keys will pick up the changes.

### What is NOT in Sprint 4

- Global attribute (pa_*) CRUD: Sprint 5 (done)
- Shipping class CRUD: Sprint 5 (done)
- Brand CRUD: Sprint 5 or later
- Move-product-to-category bulk operation: future
- Bulk-create-categories: future

---

## Sprint 5: Attributes + Attribute Terms + Shipping Classes

Sprint 5 completes the catalog surface. Agents can now create, organize, and clean up global product attributes and their options, plus shipping classes.

| Slug | What it does | Annotations |
|---|---|---|
| `novamira/woocommerce-create-product-attribute` | Create a global attribute (Size, Color, etc.) | readonly: false, destructive: false |
| `novamira/woocommerce-edit-product-attribute` | PATCH-edit an attribute by id | readonly: false, destructive: false |
| `novamira/woocommerce-delete-product-attribute` | Delete an attribute; force required when terms or products exist | readonly: false, destructive: true |
| `novamira/woocommerce-create-product-attribute-term` | Create a term (option) inside an attribute | readonly: false, destructive: false |
| `novamira/woocommerce-edit-product-attribute-term` | PATCH-edit a term by id | readonly: false, destructive: false |
| `novamira/woocommerce-delete-product-attribute-term` | Delete a term; force required when variations reference it | readonly: false, destructive: true |
| `novamira/woocommerce-list-product-attribute-terms` | List terms for an attribute | readonly: true |
| `novamira/woocommerce-create-product-shipping-class` | Create a shipping class | readonly: false, destructive: false |
| `novamira/woocommerce-edit-product-shipping-class` | PATCH-edit a shipping class by id | readonly: false, destructive: false |
| `novamira/woocommerce-delete-product-shipping-class` | Delete a shipping class; force required when products assigned | readonly: false, destructive: true |
| `novamira/woocommerce-list-product-shipping-classes` | List all shipping classes | readonly: true |

### Workflow: catalog setup from scratch

This is the canonical chain for setting up a fresh variable product catalog with global attributes.

```
1. create-product-attribute { name: "Size" }
   → response: attribute.id = <attr_id>, attribute.taxonomy_slug = "pa_size"

2. create-product-attribute-term { attribute_id: <attr_id>, name: "Small" }
   create-product-attribute-term { attribute_id: <attr_id>, name: "Medium" }
   create-product-attribute-term { attribute_id: <attr_id>, name: "Large" }
   → terms registered; slugs are lowercased automatically

3. create-product { name: "Classic Tee", type: "variable",
     attributes: [{ taxonomy_slug: "pa_size", options: ["small", "medium", "large"], visible: true, variation: true }] }
   → product.id = <parent_id>

4. create-product-variation { parent_id: <parent_id>,
     attributes: [{ taxonomy_slug: "pa_size", option: "small" }], regular_price: "19.99" }
   create-product-variation { parent_id: <parent_id>,
     attributes: [{ taxonomy_slug: "pa_size", option: "medium" }], regular_price: "19.99" }
   create-product-variation { parent_id: <parent_id>,
     attributes: [{ taxonomy_slug: "pa_size", option: "large" }], regular_price: "24.99" }

5. Done. Catalog setup complete.
```

### Key behavior notes (Sprint 5)

- **pa_ prefix handling.** `create-product-attribute` accepts `slug` as either `"size"` or `"pa_size"`. The pa_ prefix is stripped on input and stored as the short form. The response always includes both `slug` (e.g. `"size"`) and `taxonomy_slug` (e.g. `"pa_size"`). Always use `taxonomy_slug` when passing the attribute to create-product or create-product-variation.
- **Attribute identification.** All S5 abilities that target a specific attribute accept `attribute_id` (int) only. Use `list-product-attributes` to discover ids if you only know the label.
- **Attribute term identification.** Create/list accept either `attribute_id` OR `taxonomy_slug` (mutual exclusion). Edit/delete accept `id` (the term id). Never pass both `attribute_id` and `taxonomy_slug` - the ability returns `wc_invalid_input`.
- **Refuse-with-force on attribute delete.** `delete-product-attribute` refuses if the attribute has any terms OR is used as a variation axis on any product. The error data includes `{term_count, products_using}`. Clean up the products first, or pass `force: true` to cascade-delete everything. Force-delete is the highest-blast-radius S5 operation - it deletes all terms, removes the attribute row, and leaves orphan postmeta on any variation that referenced the attribute.
- **Refuse-with-force on attribute term delete.** `delete-product-attribute-term` refuses if any product or product_variation references this term via `wp_term_relationships`. Pass `force: true` to delete; variations that referenced the term will silently break at the storefront (the variation postmeta is not cleaned up). This matches WP's standard taxonomy delete behavior.
- **Refuse-with-force on shipping class delete.** `delete-product-shipping-class` refuses if any published product is assigned the class. Pass `force: true`; WP removes the class from all products automatically.
- **wc_attribute_term_not_found vs wc_term_not_found.** Attribute term operations return `wc_attribute_term_not_found` (not `wc_term_not_found`) when the term id is missing or does not belong to a `pa_*` taxonomy. Shipping class operations still use `wc_term_not_found`.
- **Attribute term slugs are lowercase.** WC lowercases term slugs. When creating variations, pass option slugs in lowercase (e.g. `"small"` not `"Small"`). `list-product-attribute-terms` returns the canonical slugs.

### Error codes added in Sprint 5

| Code | HTTP | When |
|---|---|---|
| `wc_attribute_not_found` | 404 | Attribute id or taxonomy slug does not exist |
| `wc_attribute_term_not_found` | 404 | Term id not found or does not belong to a pa_* taxonomy |
| `wc_attribute_in_use` | 409 | Attribute delete refused without force; payload: {term_count, products_using} |
| `wc_attribute_term_in_use` | 409 | Term delete refused without force; payload: {products_using} |
| `wc_shipping_class_in_use` | 409 | Shipping class delete refused without force; payload: {products_using} |
| `wc_invalid_attribute_type` | 400 | type not one of select/text |
| `wc_invalid_attribute_order_by` | 400 | order_by not in allowed enum |

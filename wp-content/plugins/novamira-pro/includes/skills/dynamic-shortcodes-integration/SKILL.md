---
name: dynamic-shortcodes-integration
description: Activate when working with Dynamic Shortcodes on a WordPress site — validating, composing, or evaluating shortcode strings, or managing Power Shortcodes. Covers the curly-brace syntax, real-world examples per type (post, acf, user, term, query, woo, …), the validate → evaluate → wrap-with-power workflow, and the site-context inputs (active_types, available_plugins). Treats Dynamic Shortcodes as the site's chosen dynamic-content tool, used inside page builders (Gutenberg, Elementor, Bricks, WPBakery, Oxygen, Breakdance) — each area must be enabled in DSH settings, then inserted via Elementor's "Dynamic Shortcodes" dynamic tag, or directly in the content for Bricks/Gutenberg/Oxygen/Breakdance/WPBakery; not integrated in Divi, Etch or Mosaic. Deep per-area reference and worked examples live in companion skills (dynamic-shortcodes-foundations, -post-data, -custom-fields, -loops, -logic, -data, -request, -wordpress, -custom), loaded on demand via novamira/skill-get.
---

# Dynamic Shortcodes Integration

## When to use

Activate this skill whenever you are about to write, edit, validate, or troubleshoot a Dynamic Shortcode on a WordPress site, or when you need to manage Power Shortcodes. Do not work with Dynamic Shortcodes without this skill — guessing the syntax causes obscure parser errors that cost retries.

## Workflow

1. **ANALYZE** — identify which Dynamic Shortcodes types are involved (`post`, `acf`, `query`, `if`, …).
2. **CHECK SETUP** (once per session) — call `novamira/dynamic-shortcodes-check-setup`. Confirm `min_satisfied=true`. Note `accepted_types` and `available_plugins`.
3. **LOAD TOPIC SKILL** — for any unfamiliar area, load the matching `dynamic-shortcodes-<family>` skill via `novamira/skill-get` (see Topic skills below). It carries the full syntax and worked examples for that area, inlined. Do not guess.
4. **CONTEXT** (optional) — call `novamira/dynamic-shortcodes-get-context` `type=<X>` to discover the real fields available on this site.
5. **DRAFT** — write the shortcode string. Hand-write it directly, or use `novamira/dynamic-shortcodes-compose-shortcode` when the structure (type, args, filters) is cleanly separable.
6. **LINT** — call `novamira/dynamic-shortcodes-validate-shortcode`. If errors are reported, fix and re-lint. Cap at 3 retries before asking the user.
7. **EVALUATE** — call `novamira/dynamic-shortcodes-evaluate-shortcode`. Optionally pass `post_id` to set the post context. If `requires_power=true`, go to step 8.
8. **POWER** — wrap the shortcode in a Power Shortcode via `novamira/dynamic-shortcodes-create-power-shortcode`, then reference it elsewhere as `{power:<name>}`.

## Decision matrix

| Goal | Ability |
|---|---|
| Confirm Dynamic Shortcodes is available + see active types | `dynamic-shortcodes-check-setup` |
| Discover real fields for a type | `dynamic-shortcodes-get-context` |
| Build a shortcode from structured parts | `dynamic-shortcodes-compose-shortcode` |
| Validate a shortcode string | `dynamic-shortcodes-validate-shortcode` |
| Run a shortcode and see the actual output | `dynamic-shortcodes-evaluate-shortcode` |
| Save a privileged shortcode for reuse | `dynamic-shortcodes-create-power-shortcode` |
| Modify an existing Power Shortcode | `dynamic-shortcodes-edit-power-shortcode` |
| Remove a Power Shortcode | `dynamic-shortcodes-delete-power-shortcode` |
| Inventory Power Shortcodes | `dynamic-shortcodes-list-power-shortcodes` |

## Syntax golden rules

1. **MANDATORY COLON.** Every Dynamic Shortcode MUST have a colon after the type.
   - CORRECT: `{post:title}`, `{get:my_var}`, `{acf:my_field}`
   - WRONG: `{title}`, `{my_var}`, `{acf_field}`
2. **TEMPLATING.** Use square brackets `[...]` for outputting HTML or text inside condition / loop structures. Example: `{if:{gt:{form:age} 18} [Access Granted]}`.
3. **STRINGS.** Use single quotes `'...'` for literal strings inside arguments.
4. **FILTERS.** Use `||` to access array indices or object properties. Example: `{get:posts || 0}` returns the first item.

## Extended syntax reference

A Dynamic Shortcode follows this structure:

```
{<type>:<args> @<keyargs> <filters> ?<fallback>}
```

- `<type>` — a sequence of letters / digits / underscores, starting with a letter (`post`, `acf`, `query`, `if`, …).
- `<args>` — positional arguments separated by spaces. Each arg can be:
  - an identifier (`title`, `permalink`, …),
  - a number (`42`),
  - a nested string in `[...]` (shortcodes inside are expanded),
  - a quoted string in `"..."`,
  - another Dynamic Shortcode `{nested:…}`.
- `@<keyargs>` — `@` followed by `key=value` pairs (or bare flags) separated by spaces. Example: `{post:terms @ taxonomy=post_tag}`.
- `<filters>` — applied with `|`, `||`, or `|-`:
  - `|name(arg)` — chain a transform.
  - `||name(arg)` — chain a transform that returns nothing if the previous value is empty.
  - `|-name(arg)` — chain a transform whose absence does not break the pipeline.
- `?<fallback>` — value used when the shortcode can't resolve. Can be an identifier, number, string, or shortcode.

Spaces separate arguments and options. Whitespace around the colon and inside templates is allowed.

## Pitfalls

- `loop` is ONLY for posts and attachments. For data arrays, use `for`.
- `query` only supports: `posts`, `users`, `terms`, `woo-products`, `woo-orders`.
- `acf` source: to fetch fields from specific objects use `@ID=value` (the `post` context is default and implied), or be explicit with `@post ID=value`. **`@post=value` is WRONG.** For other sources: `@term ID=value`, `@user ID=value`, `@option`.
- To get PHP constants use `{call:constant name}`.
- For calculations, use `{set:var …}` and then `{get:var}`. To increment a variable: `{set:my_sum {+:{get:my_sum} {get:item_value}}}`.
- Power Shortcodes are normal shortcodes that you SAVE on the site and then reference everywhere as `{power:<name>}`. `{power:<name>}` only runs them — it does not define them. Name them in **lowercase-with-hyphens** (kebab-case), e.g. `welcome-banner` (the name must start with a letter; letters/digits/`_`/`-` only). Call `list-power-shortcodes` before creating: `create-power-shortcode` never overwrites — on a name clash it returns `{exists:true}`, so use `edit-power-shortcode` to change an existing one.
- `evaluate-shortcode` returns `requires_power:true` (with `result:null`) when a shortcode needs the privileged Power context (e.g. it uses `{call:…}`) — that is the signal to wrap it in a Power Shortcode via `create-power-shortcode` and then run it as `{power:<name>}`, NOT a failure.
- NEVER use HTML inside templates unless the user explicitly requests it or it's obvious from the surrounding context (e.g. they pasted an HTML snippet).

## In page builders

Two things differ from just typing the shortcode somewhere, and both matter:

**1. The surface must be ENABLED in Dynamic Shortcodes' settings, and every area defaults to OFF.** Call `dynamic-shortcodes-check-setup` and read `enabled_areas` (`post_title`/`post_content`/`post_excerpt`, `gutenberg_blocks`, `text_widget`, `elementor_dynamic_tag`, `elementor_widgets`, `bricks`, `oxygen`, `breakdance`, `wpbakery`) to see which are on. If a shortcode shows up as literal text on the front end, that surface is almost always simply not enabled — turn it on in Dashboard → Dynamic Shortcodes.

**2. The insertion mechanism depends on the builder — two patterns (from the DSH source):**

*Pattern A — raw shortcode in the content* (type the shortcode string where you'd type text; DSH resolves it on render):

| Surface | Resolved via |
|---|---|
| Post title / content / excerpt | `the_title` / `the_content` / `the_excerpt` |
| Gutenberg / FSE — any text block | block content |
| Text widgets (incl. GeneratePress / Kadence content) | `widget_text` |
| **Bricks** — an element's content/text | `bricks/frontend/render_data` |
| WPBakery — a Text Block | the WPBakery surface |

*Pattern B — via the builder's dynamic-data / dynamic-tag picker* (NOT a raw shortcode in a text field — pick the Dynamic Shortcodes provider and enter the shortcode there):

| Builder | What to pick in the builder's dynamic-data / dynamic-tag picker, then enter the shortcode |
|---|---|
| **Elementor** | dynamic tag → group **"Dynamic.ooo - Dynamic Shortcodes"** → **Text** (or Image / Gallery) tag |
| **Oxygen** | dynamic data → **"Dynamic Shortcodes"** → **"Text with Dynamic Shortcodes"** |
| **Breakdance** | dynamic-data field → category **"Dynamic Shortcodes"** → **"Dynamic Shortcodes - String"** (control "Text with Dynamic Shortcodes") |

Caveats:

- **Not integrated by Dynamic Shortcodes: Divi, Etch, Mosaic, Beaver Builder** — there is no enabled-area toggle for them, so a raw shortcode stays literal. Use that builder's native dynamic-content instead.
- A **privileged** shortcode — anything `evaluate-shortcode` returns with `requires_power: true` (e.g. `{call:…}`) — cannot be inserted directly. Wrap it in a Power Shortcode via `create-power-shortcode` and insert `{power:<name>}`.
- It resolves on the **front end** (and the live preview), not in the raw editor input box. Use `evaluate-shortcode` to check the value before inserting.

## Examples

# Dynamic Shortcodes Examples

## How do I display the ACF Field of a Tag assigned to Post?

To retrieve the tag of a post, use the following shortcode:

```
{post:terms @ taxonomy=post_tag || 0}
```

This will fetch the first tag associated with the post (indicated by `|| 0`). If your post has only one tag, this approach will work perfectly. However, if your post might have multiple tags, keep in mind that this method only retrieves the first one.

Next, you can use the ACF Dynamic Shortcode to fetch a field associated with a specific term. To do this, you need to specify the term's ID, which can be done as follows:

```
{acf:field_id @ term id=...}
```

In this case, replace `id=...` with the term ID retrieved from the first shortcode. Combining the two, the complete shortcode looks like this:

```
{acf:field_id @ term id={post:terms @ taxonomy=post_tag || 0}}
```

This will dynamically fetch the ACF field value based on the first tag of the post.

---

## Why doesn't the following code work?

```
{if:{gt:{form:number} 4}
'<h4>Product 5</h4>
<div class="multi-column-100">
<div class="multi-column-1" width=""50%"">Model Name/No: <b>{form:model_5}</b></div>
<div class="multi-column-2" width=""50%"">CB: <b>{form:cb_5}</b></div>
</div>'
''
}
```

I get something like &123;form:model_5}

**Answer:** When you put text inside single quotes the text is taken literally and is not modified in any way. If you want Dynamic Shortcodes to be expanded inside some text you have to put them inside square brackets. So your code should look like this: `{if {gt:{form:field} 4} [... {form:field} ...]}`.

---

## How do I display a text in the PDF only if a form field is greater than 4?

To display content conditionally based on the value of a field, you can use the `if` shortcode with the `gt` (greater than) condition. Here's how to do it:

```
{if:{gt:{form:field_name ?? 0} 4}
[
	This content is shown only if the value of field_name is greater than 4.
]
}
```

Just replace `field_name` with the actual name of your field.

---

## I have a variable defined as `{set: posts {query:posts@category_name=cat1}}`. I want to display the title of the first post found.

You can achieve this by using the array accessor filter (`||`). Here's how to get the first post ID and pass it to the `post` shortcode to display the title:

```
{post:title @id={get:posts||0}}
```

---

## How do I get an ACF field inside a group? {acf:field-name} is not working

To fetch ACF fields inside field groups you need to prefix them with the group id followed by an underscore. In your case, supposing the group id is "group" the correct code would be `{acf:group_field-name}`

---

## I'm trying to access a metaterm and the shortcode returns {"evento":5}, but I need only 5 to be displayed. How can I filter that to show only 5 in this case?

It seems that the metaterm value is encoded in JSON. You can achieve what you want by deconding the JSON and using the accessor filter (`||`) to fetch the `evento` property within the resulting JSON value. The final code should look like this:`{term:field-id|json_decode||evento}`

---

## Show some content in the PDF only if "API" is selected in the checkbox field access_options

You can do it like this:

```
{if: {eq:{form:access_options} API} [
  <your content here>
]}
```

---

## I would like to know the best way to add "and" to the end of a list. For example, using the shortcode: {for:item {array:dog cat llama} {get:item} @ sep=', '} outputs: dog, cat, llama. How can I modify it to output: dog, cat and llama?

Supposing that the array is in a variable called `my-array` (adjust the code if that is not the case) you can do this by looping over the array until the penultimate item, then write ` and ` followed by the last item.

```
{set:my-array {array:dog cat llama}}
{set:last-index {-:{get:my-array|count} 1}}

{for:item {get:my-array|array_slice(0,{get:last-index})} {get:item} @ sep=', '} and {get:my-array||last}
```

---

## I have an ACF field containing shortcodes, I use {acf:field} but the shortcodes in the content of the field are not expanded

You can do this by explicitly requesting the Dynamic Shortcodes expansion on the result of ACF field Dynamic Shortcode. This is done with the `expand!` keyarg. Note though that it can only be used in a privileged context like inside Power Shortcodes. In your case the code is `{acf:field @expand!}`

---

## I have CPT. These posts have an ACF date field. I want to select only the posts where the date in this field is in 2023

You can do this with the Query Shortcode. The following does the query and then loops over it by printing the title of your CPT post:

```
{loop:
	{query:posts@post_type=your_post_type order=ASC meta_query={array:{array:@key=your_date_acf value={array:'2023-01-01' '2023-12-31'} compare=BETWEEN type=DATE}}}
	[{post:title}]
@sep=', '}
```

---

## How can I get the featured image of the previous or next post and not the one of this page?

You can do this by getting the ID of the next post with the Call Shortcode and the WordPress function `get_next_post`, this can only be done within a privileged context like in a Power Shortcode. Then you get the featured image id of this post and finally the image itself from it. Here is the code:

```
{media:image @ID={post:featured-image-id @ID={call:get_next_post |. ID}}}
```

---

## How do I show the image with ID 101 only if the ACF date field `date_for_dsh_image` is in the future?

You can do this with the `if` shortcode and the `lt` comparison shortcode:

```
{if: {lt: {date:now@fmt=U} {date:{acf:date_for_dsh_image}@fmt=U}} [{media:image @ID=101}]}
```

---

## Why doesn't {api:http://example.com/} work? I get {api:http://example.com/} back

The URL contains charcaters (`:`, `.`, `/`) that can only be inserted inside quoted string. So the correct shortcode is `{api:http://example.com/}`

---

## How do I get the URL of a media file in an ACF field?

```
{media:url @ id={acf:field}}
```

---

## I have an ACF Gallery field in an option page, I want to show the first image of this

You can get the ACF field of an option page with the `option` keyarg, then use the accessor filter (`||`) with a 0 (first) to get the first element of this. Supposing the field is named `option_gallery` the code is:

```
{acf:option_gallery @option ||0}
```

This will return the ID of the media file, if you want the image itself use the following:

```
{media:image @ID={acf:option_gallery @option ||0}}
```

---

## I want to show all the thumbnails of an ACF Gallery field of an option page

You can use the `option` keyarg to fetch an ACF field in an option page. Then loop over the field value and use the media shortcode with the `size` keyarg to select the thumbnail image. If for example your field name is `option_gallery` the code is this:

```
{for:value {acf:option_gallery @option} [{media:image @ID={get:value} size=thumbnail}]}
```

## Topic skills

This overview covers syntax and workflow. For the full reference and worked examples of a given area, load the matching skill via `novamira/skill-get` — each one inlines its examples, so there is nothing to read from disk:

- `dynamic-shortcodes-foundations` — syntax, setup, getting started, keyargs, platform compatibility
- `dynamic-shortcodes-post-data` — post, term, author and user data ({post}, {term}, {author}, {user})
- `dynamic-shortcodes-custom-fields` — ACF, JetEngine, Meta Box, Pods, Toolset fields ({acf}, {jet}, {metabox}, {pods}, {toolset})
- `dynamic-shortcodes-loops` — iterate posts, queries and arrays ({loop}, {query}, {for})
- `dynamic-shortcodes-logic` — conditionals, comparisons, filters, arithmetic ({if}, {switch}, |filters, {+})
- `dynamic-shortcodes-data` — arrays, variables, literals, dates ({array}, {set}/{get}, {lit}, {identity}, {date})
- `dynamic-shortcodes-request` — request/environment data ({query_var}, {param-get}, {cookie}, {server}, {option}, {api})
- `dynamic-shortcodes-wordpress` — WooCommerce, media, native WP shortcodes, cache ({woo}, {media}, {wp-shortcode}, {cache})
- `dynamic-shortcodes-custom` — extend with PHP and Power Shortcodes ({call}, registration, {power})

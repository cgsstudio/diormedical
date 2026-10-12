---
name: wpbakery-register-element
description: Register a new custom WPBakery element that appears as a clickable icon in the element picker under a custom category tab. Activate when the user asks to create a custom WPBakery element, add a new category to the WPBakery picker, or make a reusable component available in the WPBakery "Add Element" dialog.
---

# Registering a custom WPBakery element

This skill covers creating a new element that appears in the WPBakery "Add
Element" dialog under a custom category tab. The element is registered as a
WordPress shortcode + `vc_map()` entry and lives in the Novamira sandbox so
no plugin code needs to be modified.

This skill is workflow knowledge, not a PHP boilerplate generator — it teaches the agent which abilities to call and in what order. For the element catalogue and existing categories see `novamira/wpbakery-list-elements`; for page-building workflows see `novamira/wpbakery-build-page`.

This is different from saving a reusable template (see `novamira/wpbakery-build-page`
— template flow). Use this skill when the user wants a **named, configurable
element** with its own icon and parameter form in the picker, not just a saved
layout.

## Canonical sequence

1. **Collect requirements.** Determine the element name, the custom category
   label, the configurable parameters (title, text, colors, URL, etc.) and
   the desired HTML output.
2. **Choose a shortcode tag.** Use the `nm_` prefix to avoid collisions
   (e.g. `nm_promo_banner`, `nm_service_card`). Lowercase, underscores only.
3. **Write the sandbox file.** Use `novamira/write-file` to create a PHP file
   at the absolute path:
   `/path/to/wp-content/novamira-sandbox/<element-name>.php`
   The file must contain two parts (see PHP structure below).
4. **Verify registration.** Call `novamira/wpbakery-check-setup` and confirm
   the new category name appears in the `categories` list. If it does not,
   check that the file has no PHP errors via `novamira/execute-php`.
5. **Reload the editor.** The human editor must reload the WPBakery page for
   the new category tab to appear. No server restart needed.

## PHP file structure

Every sandbox element file has exactly two parts:

### Part 1 — Shortcode renderer

```php
add_shortcode( 'nm_example', function ( $atts ) {
    $atts = shortcode_atts(
        [
            'title'    => 'Default title',
            'subtitle' => '',
            'btn_text' => '',
            'btn_url'  => '',
            'bg_color' => '#1a3c6e',
            'el_class' => '',
        ],
        $atts,
        'nm_example'
    );

    ob_start();
    // render HTML using esc_html(), esc_url(), esc_attr() for every output
    return ob_get_clean();
} );
```

Always use `ob_start()` / `ob_get_clean()`. Always escape all output.

### Part 2 — WPBakery registration

```php
add_action( 'vc_before_init', function () {
    if ( ! function_exists( 'vc_map' ) ) {
        return;
    }

    vc_map( [
        'name'        => __( 'Element Label', 'novamira-pro' ),
        'base'        => 'nm_example',          // must match shortcode tag
        'description' => __( 'Short description shown in picker.', 'novamira-pro' ),
        'category'    => 'My Category',         // creates a new picker tab
        'icon'        => 'dashicons-admin-home', // any dashicons slug
        'params'      => [
            [
                'type'       => 'textfield',
                'heading'    => __( 'Title', 'novamira-pro' ),
                'param_name' => 'title',
                'value'      => 'Default title',
            ],
            [
                'type'       => 'colorpicker',
                'heading'    => __( 'Background colour', 'novamira-pro' ),
                'param_name' => 'bg_color',
                'value'      => '#1a3c6e',
            ],
            [
                'type'       => 'textfield',
                'heading'    => __( 'Extra CSS class', 'novamira-pro' ),
                'param_name' => 'el_class',
                'value'      => '',
            ],
        ],
    ] );
} );
```

**Critical:** `vc_map()` must be called inside `add_action( 'vc_before_init', ... )`.
Calling it directly at file scope will run before WPBakery initialises and the
element will silently not appear.

## vc_map param types

| `type` value | Use for |
| --- | --- |
| `textfield` | Single-line text, URLs, numbers |
| `textarea` | Multi-line text |
| `colorpicker` | Colour picker |
| `dropdown` | Fixed list of options (`value` is an array of strings) |
| `checkbox` | Boolean toggle (`value` is `['Yes' => 'yes']`) |
| `attach_image` | Single image (returns attachment ID) |

## Naming conventions

| Thing | Convention | Example |
| --- | --- | --- |
| Shortcode tag | `nm_<noun>` | `nm_promo_banner` |
| Sandbox filename | `<element-name>.php` | `piero-banner-element.php` |
| `vc_map` `base` | Same as shortcode tag | `nm_promo_banner` |
| Category label | Free text, shown as tab | `"Piero Custom"` |

## Custom CSS for the element

If the element needs custom CSS (background colours, spacing), create a
**separate** sandbox file (e.g. `<element-name>-styles.php`) that hooks into
`wp_head`:

```php
add_action( 'wp_head', function () { ?>
<style>
.nm-example { background: #1a3c6e; padding: 40px; }
</style>
<?php } );
```

Keep styles in a separate file so the element PHP stays focused on rendering.

## What belongs elsewhere

- **Adding an element to a page** — use `novamira/wpbakery-add-element` once
  the shortcode tag is registered.
- **Saving a layout section as a reusable template** — see
  `novamira/wpbakery-create-template` in the `wpbakery-build-page` skill.
- **Checking available categories and elements** — `novamira/wpbakery-check-setup`
  and `novamira/wpbakery-list-elements`.

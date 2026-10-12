---
name: dynamic-shortcodes-custom-fields
description: Read custom fields from third-party field plugins with Dynamic Shortcodes: {acf:...} (including acf-loop, acf-row and the expand! keyarg), {jet:...} (JetEngine), {metabox:...} (Meta Box), {pods:...} (Pods) and {toolset:...} (Toolset). Covers field sources, repeaters and loops, and the @ID / @post / @term / @user / @option source selectors. Use when outputting ACF/JetEngine/Meta Box/Pods/Toolset field values.
---

# Dynamic Shortcodes: Custom Fields (ACF, JetEngine, Meta Box, Pods, Toolset)

Read custom fields from third-party field plugins with Dynamic Shortcodes: {acf:...} (including acf-loop, acf-row and the expand! keyarg), {jet:...} (JetEngine), {metabox:...} (Meta Box), {pods:...} (Pods) and {toolset:...} (Toolset). Covers field sources, repeaters and loops, and the @ID / @post / @term / @user / @option source selectors. Use when outputting ACF/JetEngine/Meta Box/Pods/Toolset field values.

## Advanced Custom Fields Integration (acf, acf-loop, acf-row)

The ACF Dynamic Shortcode serves as a versatile tool for accessing values stored in Advanced Custom Fields within WordPress. It enables specific data retrieval from fields, including options, terms, and user data. This shortcode is particularly beneficial for developers and content managers who require dynamic content presentation in WordPress pages or posts.

### Syntax

Utilize the ACF Dynamic Shortcode with the following structure:

```
{acf:field_name}
```

* `field_name` refers to the desired ACF field for data extraction.

### Key Arguments

* `id` Defines the ID for the post, term or user from which the field data is retrieved.

  ```
  {acf:field @ID=3} default is post  
  {acf:field @ID=3 post}  
  {acf:field @ID=27 term}  
  {acf:field @ID=25 user}
  ```
* `option` Indicates that the field is an options field.

  ```
  {acf:field @option}
  ```
* `user` Specifies that the field is related to a user's data.

  ```
  {acf:field @user} current user  
  {acf:field @ID=53 user}
  ```
* `term` Used when the field is associated with a taxonomy term.

  ```
  {acf:field @term} current term  
  {acf:field @ID=72 term}
  ```
* `format` Determines whether to apply ACF formatting to the field value.

  ```
  {acf:field @format}  
  {acf:field @format ID=3}  
  {acf:field @format ID=23 user}
  ```

* `setting` Used for getting the setting of an ACF Field, for example its default value, label.

  ```
  {acf:field @setting=default_value}  
  {acf:field @setting=default_value ID=3}  
  {acf:field @setting=default_value ID=23 user}  
  {acf:field @setting=label}  
  {acf:field @setting=name}
  ```

### Examples

1. **Fetch a Field from a Specific Post**

   ```
   {acf:price@id=15}
   ```

   This shortcode retrieves the `price` field from the post with ID 15.
2. **Get User-specific Field Data**

   ```
   {acf:bio@user}
   ```

   This example pulls the `bio` field for the current logged-in user.
3. **Retrieve an Options Field Value**

   ```
   {acf:color@option}
   ```

   This will get the value of the `color` options field.
4. **Access Term-related Field Data**

   ```
   {acf:ingredients@term id=5}
   ```

   Here, the shortcode fetches the `ingredients` field from the term with ID 5.
5. **Obtain a Field with ACF Formatting**

   ```
   {acf:gallery@format}
   ```

   This example returns the `gallery` field with ACF's formatting applied.
6. **Get the default value of a field**

   ```
   {acf:name@setting=default_value}
   ```

   This will display the default value that was set for the field `name` in the ACF Fields settings.

### ACF Loop

Pass as the first argument the name of the field and as second the template that should be printed at each iteration. Say we have a pizza repeater with a subfield called name and one quantity. Than you can print them all in a list with the following shortcode   
​

```
{acf-loop:pizza-toppings  
     [<li>name: {acf:name} qty:{acf:quantity}]  
}
```

### Subfields in ACF Loop

If you wish to retrieve the value of a subfield within an ACF loop, you can call it as a normal ACF field `{acf:field_id}`. Note that in this case it is not compatible with the `ID` keyarg.

### ACF Field within a Group

If you wish to retrieve the value of a field inside a group you should use this syntax:

```
{acf:group_name_field_id}
```

The group name separated by an underscore must be entered as argument.

### ACF Flexible Content fields

You can dynamically loop through the rows of an ACF Flexible Content field and conditionally render templates for each layout. The general structure is:  
​

```
{acf-loop:<field_name>  
  {switch:{acf-row:layout}  
    "<layout_name>" [<template_for_layout>]  
    ...  
    <default_template>  
  }  
}
```

* `<field_name>`: The name of the ACF Flexible Content field.
* `<layout_name>`: The identifier of each layout in the Flexible Content field.
* `<template_for_layout>`: The template to display for a specific layout.
* `<default_template>` (optional): A fallback template for unrecognized layouts.

### Example

Suppose you have a Flexible Content field called `page_sections` with the following layouts:

* `hero_section`
* `text_block`

Here’s how you can render different HTML templates for each layout:  
​

```
{acf-loop:page_sections  
  {switch:{acf-row:layout}  
    "hero_section" [<section class="hero">{acf:hero_title}</section>]  
    "text_block" [<div class="text-block">{acf:text_content}</div>]  
  }  
}
```

* **`{acf:hero_title}`**: Fetches the ACF field `hero_title` for the current row.  
  ​

### Special Privileges

Certain operations, such as accessing private data or specific user fields, necessitate the use of Power Shortcodes, which possess comprehensive privileges.

## Expanding Embedded Shortcodes with expand! Keyarg (acf)

Starting from version 1.4.0 of Dynamic Shortcodes, it is possible to use the `expand!` key argument. This keyarg allows you to expand Dynamic Shortcodes embedded as values within another field.  
​

Let's assume you have an ACF Field, which you can retrieve with `{acf:description}`


If there are Dynamic Shortcodes within the content of this ACF field that you want to expand, you can use `{acf:description @expand!}`


This will display   
  
**Our Product Zebra Game has been active since 2023**  
​

assuming that `{post:title}` is "Zebra Game" and `{acf:year}` is "2023".

### Special Privileges

You must use the `expand!` keyarg inside a Power Shortcode.  
​

## JetEngine Field Retrieval (jet)

The JetEngine Dynamic Shortcode is an integral part of the Dynamic Shortcodes suite in WordPress, specifically tailored for retrieving values from JetEngine fields. It enhances content handling in WordPress by allowing users to dynamically access and display custom field data created with JetEngine.

### Syntax

```
{jet:field}
```

* `field`: Name of the JetEngine field to retrieve.

### Key Arguments

* `id`: Specifies the ID of the post from which to retrieve the JetEngine field.

  ```
  {jet:field @id=3}
  ```

### Examples

1. **Retrieve a Specific Field from the current post or page**

   ```
   {jet:custom_field_name}
   ```

   Fetches the value of *`custom_fieldname`* from the current page or post
2. **Retrieve a Specific Field**

   ```
   {jet:custom_field_name@id=10}
   ```
3. Fetches the value of *`custom_fieldname`* from the post with ID 10.

## Meta Box Field Access (metabox)

The Meta Box Dynamic Shortcode is specifically designed for WordPress sites utilizing the Meta Box Plugin. It efficiently retrieves custom fields and metadata associated with posts.

### Syntax

```
{metabox:field}
```

* `field`: Name of the custom field or metadata to retrieve.

### Key Arguments

* `id`: Specifies the post ID for metadata retrieval. Defaults to the current post if not provided.

  ```
  {metabox:field @ID=3}
  ```

### Examples

1. **Retrieve a Custom Field**:

   ```
   {metabox:custom_field}
   ```

   Fetches the value of `custom_field'`from the current post, created using the Meta Box Plugin.
2. **Specific Post's Custom Field**:

   ```
   {metabox:another_field@id=123}
   ```

   Retrieves `another_field` custom field value from the post with ID 123.  
   ​

## Pods Custom Fields Retrieval (pods)

The Pods Dynamic Shortcode in WordPress is an advanced tool tailored for retrieving and manipulating custom fields created with the Pods plugin.

### Syntax

```
{pods:field}
```

* `field`: a custom field name defined in Pods.

### Key Arguments

* `id` (optional): Designates the ID of the post to retrieve data from. Default is the current post.

  ```
  {pods:field @ID=3}
  ```

### Examples

1. **Retrieve a Custom Field**

   ```
   {pods:custom_field_name}
   ```

   Fetches the value of 'custom\_field\_name' from the current post, where 'custom\_field\_name' is a field created with the Pods plugin.
2. **Custom Field with Specific Post ID**

   ```
   {pods:custom_field_name@id=10}
   ```

   Obtains the 'custom\_field\_name' value from the post with ID 10.

## Toolset Integration (toolset)

The Toolset Dynamic Shortcode is a versatile shortcode in the Dynamic Shortcodes framework, designed to interact with the Toolset plugin in WordPress. It allows for the retrieval and display of custom fields and other metadata associated with posts or pages.

### Syntax

```
{toolset:field}
```

* `field`: The name of the metadata or custom field to be retrieved.

### Key Arguments

* `id`: Specifies the ID of the post from which to retrieve the metadata. If omitted, the current post ID is used.

  ```
  {toolset:field @ID=3}
  ```
* `format` (alias `fmt`): Defines the format of the output. It can be set to `normal` for standard formatting or `html` for HTML formatting.

  ```
  {toolset:field @format=html}
  ```

### Examples

1. **Custom Field Retrieval**

   ```
   {toolset:field_name}
   ```

   Retrieves the value of the custom field `custom_field_name` from the current post.
2. **Custom Field and Keyargs**

   ```
   {toolset:field_name@id=456}
   ```

   Retrieves the field called `field_name` of the post with ID 456.
3. **HTML Formatting**

   ```
   {toolset:field_name@format=html}
   ```

   Retrieves the field called `field_name` of the current post and formats it as HTML.

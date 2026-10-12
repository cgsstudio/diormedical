---
name: dynamic-shortcodes-loops
description: Loop and iterate with Dynamic Shortcodes: {loop:...} for posts and attachments, {query:...} for posts/users/terms/woo-products/woo-orders, and {for:...} for arrays and iterables. Explains when to use loop vs for vs query, building queries, and nested iteration. Use when repeating output over a set of posts, query results or array items.
---

# Dynamic Shortcodes: Loops & Queries

Loop and iterate with Dynamic Shortcodes: {loop:...} for posts and attachments, {query:...} for posts/users/terms/woo-products/woo-orders, and {for:...} for arrays and iterables. Explains when to use loop vs for vs query, building queries, and nested iteration. Use when repeating output over a set of posts, query results or array items.

## How to Loop Through Arrays and Iterables (for)

The `for` Dynamic Shortcode can be used to loop through an iterable value (for example an array) and print a template for each iteration.

It takes three arguments.

* A name to be given as a placeholder for the current value in the loop. This can later be retrieved with the `get` Shortcode.
* The iterable value to loop over.
* Another value to be printed at each iteration. Usually a template with Shortcodes, but can also be a single a Shortcode.

Example:

```
<ul>  
{for:item {array:dog cat llama} [<li>{get:item}]}  
</ul>
```

will print

* dog
* cat
* llama

#### Associative Arrays

Too loop through an associative array you have to pass two identifiers before the array. For example:

```
{for:key value <array> [do something with {get:key} and {get:value}]}
```

#### Separator

You can add a separator with the `sep` keyarg.

Example:

```
{for:item {array:dog cat llama} {get:item} @ sep=', '}
```

Will print  
**dog, cat, llama**

## Data Query and Retrieval (query, for)

The Query Dynamic Shortcode is a dynamic and advanced element in WordPress that efficiently retrieves various data types. It's designed for users who need to extract specific information from posts, users, terms, or WooCommerce orders without delving into the complexities of WordPress functions.

### Syntax

The basic syntax of the Query Dynamic Shortcode is:

```
{query:type}
```

* `type`: Specifies the type of data to retrieve (`posts`, `users`, `terms`, `woo-orders`).

### Key Arguments

Key arguments are specific to the type of query:

```
{query:type @key-arguments-here}
```

* For `posts`: Refer to the [WP\_Query documentation](https://developer.wordpress.org/reference/classes/wp_query/parse_query/).
* For `users`: Refer to the [WP\_User\_Query documentation](https://developer.wordpress.org/reference/classes/wp_user_query/prepare_query/).
* For `terms`: Refer to the [WP\_Term\_Query documentation](https://developer.wordpress.org/reference/classes/wp_term_query/__construct/).
* For `woo-orders`: Consult the [wc\_get\_orders and WC\_Order\_Query parameters](https://github.com/woocommerce/woocommerce/wiki/wc_get_orders-and-WC_Order_Query#parameters).

### Examples

1. **Fetching a Specific Number of Posts**

   ```
   {query:posts@posts_per_page=15}
   ```

   Retrieves 15 posts, overriding the default limit (10).
2. **Fetching Posts in a Category**

   ```
   {query:posts@category_name=technology}
   ```

   Retrieves posts that are in the 'technology' category.
3. **Posts by Specific Author**

   ```
   {query:posts@author=2}
   ```

   Fetches posts written by the author with ID 2.
4. **Exclude Certain Posts**

   ```
   {query:posts@post__not_in="10,20"}
   ```

   Excludes posts with IDs 10 and 20 from the results.
5. **Retrieve Posts with Custom Fields**

   ```
   {query:posts@meta_key=color meta_value=blue}
   ```

   Searches for posts with a custom field 'color' set to 'blue'.
6. **Display Sticky Posts Only**

   ```
   {query:posts@post__in=sticky}
   ```

   Shows only the posts that are marked as sticky.
7. **Retrieving Users by Role**

   ```
   {query:users@role=editor}
   ```

   Fetches all users with the 'editor' role
8. **Listing Categories**

   ```
   {query:terms@taxonomy=category}
   ```

   Retrieves all terms in the 'category' taxonomy.
9. **Extracting Completed Woo Orders**

   ```
   {query:woo-orders@status=completed}
   ```

   Gathers all completed Woo orders.
10. **Extracting Recent Woo Orders**

    ```
    {query:woo-orders@limit=5 status=processing}
    ```

    Gather the five most recent processing Woo orders.
11. **Custom Post Type Retrieval**

    ```
    {query:posts@post_type=students}
    ```

    Fetches all posts of the 'students' custom post type.
12. **Fetch Posts with Certain Tags**

    ```
    {query:posts@tag="health,fitness"}
    ```

    Retrieves posts tagged with either 'health' or 'fitness'.
13. **Posts in Multiple Categories**

    ```
    {query:posts@category_name="travel,food"}
    ```

    Fetches posts in both the 'travel' and 'food' categories.
14. **Retrieve Posts with a Specific Custom Field Value**

    ```
    {query:posts@meta_key=event_date meta_value='2023-12-01'}
    ```

    Searches for posts with a custom field 'event\_date' set to '2023-12-01'.
15. **Posts Sorted by Title**

    ```
    {query:posts@orderby=title order=ASC}
    ```

    Lists posts in ascending order by their title.
16. **Users with Multiple Roles**

    ```
    {query:users@role__in={array:subscriber contributor}}
    ```

    Fetches users who are either subscribers or contributors.
17. **Retrieve Users Ordered by Display Name**

    ```
    {query:users@orderby=display_name order=ASC}
    ```

    Lists users in ascending order by their display name.
18. **Exclude Users with Certain Roles**

    ```
    {query:users@role__not_in={array:administrator editor}}
    ```

    Excludes users with 'administrator' or 'editor' roles.
19. **Fetch Users with Specific Name**

    ```
    {query:users@search='John Doe'}
    ```

    Retrieves users whose names include 'John Doe'.
20. **Include Specific User IDs**

    ```
    {query:users@include={array:3 7 9}}
    ```

    Includes only users with IDs 3, 7, and 9.

### Handling Results with the `for` Dynamic Shortcode

The `for` Dynamic Shortcode in WordPress is a powerful tool used in conjunction with other Dynamic Shortcodes such as `query:posts` or `query:users` to iterate over and display the results they return. This section explains how to use the `for` shortcode to manage and present data retrieved by Query Dynamic Shortcodes.

#### Understanding the Output of Dynamic Shortcodes

* `{query:posts}`: Returns an array of post IDs.
* `{query:users}`: Provides an array of user IDs.
* `{query:terms}`: Outputs an array of term IDs.
* `{query:woo-orders}`: Returns an array of WooCommerce order IDs.

#### Using the `for` Dynamic Shortcode

The `for` Dynamic Shortcode iterates over arrays or iterable values and allows the integration of templates or placeholders in each iteration.

**Syntax:**

```
{for:placeholder iterable_value [template_or_value]}
```

* `placeholder`: A name that serves as a placeholder for the current value in the loop. This can be retrieved later with the `get` Shortcode.
* `iterable_value`: The array or iterable value to loop over.
* `template_or_value`: A template or a single shortcode will be displayed for each iteration.

#### Examples

1. **Basic Loop**

   ```
   <ul>   
   {for:post-id   
      {query:posts}   
      [<li>{post:title @ID={get:post-id}}]  
   }   
   </ul>
   ```

   This example will iterate over post IDs, displaying each Post Title in a list item.
2. **Loop with Separator**

   ```
   {for:user-id  
      {query:users}   
      [{user:nickname @ID={get:user-id}]   
      @ sep=', '  
   }
   ```

   This will iterate over user IDs and separate their nicknames with a comma.

These examples demonstrate how to utilize the effectively `for` Dynamic Shortcode to handle and display data returned by various Dynamic Shortcodes. This method provides a flexible way to customize the presentation of queried data in WordPress.

### Counting Posts with the Query Dynamic Shortcode

The ability to count posts, users, terms, or WooCommerce orders is a crucial feature of the Query Dynamic Shortcode. This functionality is especially useful for obtaining a quick summary of the number of items in a specific category or that meet particular criteria.

#### How It Works

Counting is executed by appending the `count` filter to the basic syntax of the Query Dynamic Shortcode, and then specify the key arguments. The general syntax for counting is:

```
{query:type | count @keyargs}
```

In this case `type` can be `posts`, `users`, `terms`, or `woo-orders`, and `keyargs` are the specific arguments relevant to the type.

Keep in mind that retrieving a large number of posts, terms, users, or WooCommerce orders at once **can significantly impact performance**, especially on sites with a lot of data. In this case, putting the result in a Dynamic Shortcodes cache is recommended.

#### Usage Examples

1. **Counting Users by Role**

   To count the number of users with the 'editor' role

   ```
   {query:users | count @role=editor}
   ```
2. **Counting Posts in a Category**

   To count the number of posts in the 'technology' category

   ```
   {query:posts | count @category_name=technology posts_per_page=-1}
   ```
3. **Counting WooCommerce Orders with a Certain Status**

   The number of WooCommerce orders with a 'completed' status:

   ```
   {query:woo-orders | count @status=completed limit='-1'}
   ```

If you want to count posts in WordPress or orders in WooCommerce, you need to set **`posts_per_page`** to **`-1`** for posts and **`limit`** to **`-1`** for WooCommerce orders, respectively. This is because the default value for **`posts_per_page`** in posts is **`5`**, while for WooCommerce orders, the default value of **`limit`** is **`10`**. It's also important to note that for terms and users, there is no need to set such parameters as they already retrieve all elements by default.

### Special Privileges

The Query Dynamic Shortcode should be used within a Power Shortcode.

## Looping Posts with Loop and Query (loop, query)

With the Loop Dynamic Shortcode you can comfortably loop over posts by setting the current post at each iteration. Freeing you from the need to reference the post ID when displaying its data.

If you want to loop over something other than WordPress posts use the `for` instead.

### Syntax

```
{loop:array-of-post-id-here template @ sep=','}
```

* `array-of-post-ids` An array of post IDs to loop over. These IDs can be provided by another Dynamic Shortcode like **`query`**, or directly specified using {array} with explicit IDs that represent valid posts within your WordPress database.
* `template` The template to be printed at each iteration. This can be a complex template or a single Dynamic Shortcode.

### Keyargs

* **`separator`** (alias **`sep`**): Defines a string to separate each iteration's output. The default is an empty string, but you can specify separators like commas, spaces, or HTML tags to suit your layout needs.

### Examples

Display a list of links to posts.

```
<ul>  
{loop:   
  {query:posts}  
  [<li><a href="{post:permalink}">{post:title}</a></li>]}  
</ul>
```

This example demonstrates how to use the Loop Dynamic Shortcode to fetch and display post titles from a generic query, with a separator between each title. This method is particularly useful when you want to display a list of titles in a single line or within a specific formatting context.

```
{loop:   
   {query:posts}  
   [{post:title}]  
   @sep=', '  
}
```

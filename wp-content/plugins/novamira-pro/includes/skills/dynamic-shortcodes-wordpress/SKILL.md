---
name: dynamic-shortcodes-wordpress
description: Dynamic Shortcodes for the wider WordPress ecosystem: WooCommerce product information ({woo:...}), media embedding and manipulation ({media:...}), running native WordPress shortcodes from inside Dynamic Shortcodes ({wp-shortcode:...}), and cache management ({cache}, {get-cache}, {delete-cache}). Use for WooCommerce product data, media, bridging to classic shortcodes, or caching expensive output.
---

# Dynamic Shortcodes: WordPress Ecosystem

Dynamic Shortcodes for the wider WordPress ecosystem: WooCommerce product information ({woo:...}), media embedding and manipulation ({media:...}), running native WordPress shortcodes from inside Dynamic Shortcodes ({wp-shortcode:...}), and cache management ({cache}, {get-cache}, {delete-cache}). Use for WooCommerce product data, media, bridging to classic shortcodes, or caching expensive output.

## WooCommerce Product Information (woo)

The WooCommerce Dynamic Shortcode is a versatile tool designed for WordPress, enabling users to dynamically display WooCommerce product information.

### Syntax

```
{woo:field}
```

* `field`: the product information you want to display

### Key Arguments

* `id`: Specifies the WooCommerce product ID.

  ```
  {woo:field @ID=23}
  ```

### Examples

1. **Basic Product Price Display**

   ```
   {woo:price}
   ```

   Displays the price of current product.
2. **Product Name**

   ```
   {woo:title}
   ```
3. **Product Attributes and Description**

   ```
   {woo:attributes@id=20}
   ```

   Retrieves and displays the attributes of the product with ID 20.
4. **Sale Price with Fallback**

   ```
   {woo:sale-price@id=25?'No sale price'}
   ```

   Shows the sale price of product ID 25; displays "No sale price" if none exists.

### Special Privileges

The Woo Dynamic Shortcode requires special privileges in certain scenarios:

* **Access to Non-Public Products**: To read meta data from non-public products, the shortcode must be used within a Power Shortcode.

## Media Embedding and Manipulation (media)

The Media Dynamic Shortcode is a shortcode used for embedding and manipulating media content within web pages. It allows for precise control over media elements, by accepting specific arguments and options.

### Syntax

The basic syntax for the media Dynamic Shortcode is:

```
{media:value}
```

* `value` Specifies the type of media information to retrieve (e.g., URL, title, caption).

### Key Arguments

* `id` The unique identifier for the media item (required).

  ```
  {media:value @ID=3}
  ```
* `size` Defines the size of the media item (e.g., thumbnail, medium, large). Default is 'large'.

  ```
  {media:url @size=thumbnail}
  ```

### Value

The value argument specifies what information you want to retrieve. The supported options are:

* `url` - The URL to the media file.
* `title` - The title of the media file.
* `caption` - The caption of the media file.
* `description` - The description of the media file.
* `alt-text` - The Alternative Text of the media file.
* `width` - only for images.
* `height` - only for images.
* `html` - to display Images
* `file-path` - Retrieves the file path

For example:

```
{media:url @ID=123}
```

Would output the URL to the media file with ID 123.

### Examples

1. **Retrieve Image URL**

   ```
   {media:url@id=123}
   ```

   This example fetches the URL of the media item with ID 123.
2. **Get Media Title**

   ```
   {media:title@id=456}
   ```

   Retrieves the title of the media item with ID 456.
3. **Print the Featured Image of the Current Post**

   ```
   {media:image @id={post:featured-image-id}}
   ```

   `{post:featured-image-id}` returns the Media ID of the Featured Image  
   ​  
   It is recommended to check if the Featured Image exists before printing it on the screen, using the conditions:

   ```
   {if:{post:featured-image-id}  
      {media:image @id={post:featured-image-id}}  
   }
   ```
4. **Fetch Image with Specific Size**

   ```
   {media:url@id=789 size=thumbnail}
   ```

   Obtains the URL of the media item with ID 789, specifically in thumbnail size. If you do not specify a size, it will default to the full size image URL.
5. **Display Media Caption**

   ```
   {media:caption@id=321}
   ```

   Shows the caption of the media item with ID 321.
6. **Access Media Description**

   ```
   {media:description@id=654}
   ```

   Retrieves the description text of the media item with ID 654.

## Interaction with WorpPress Shortcodes (wp-shortcode)

The WP Shortcode Dynamic Shortcode (wp-shortcode) allows WordPress Shortcodes to be executed alongside Dynamic Shortcodes. Its major strength is the ability to use Dynamic Shortcodes as arguments to WordPress Shortcodes, thus making the WordPress Shortcodes itself dynamic.

### Quick Example

Within a Dynamic Shortcode context, the following code  
​

```
{wp-shortcode:gallery @ ids="1,2,3" size="medium"}​
```

is equivalent to the WordPress Shortcode:

```
[gallery ids="1,2,3" size="medium"]
```

### Dynamic Arguments

Now, say that the gallery size should be dynamic and contained in an ACF field. With only WordPress Shortcodes, this is not possible, but it is with Dynamic Shortcodes using the WP Shortcode:  
​

```
​{wp-shortcode:gallery @ ids="1,2,3" size={acf:gallery-size}}​
```

Say now that the gallery itself is also specified in an ACF Gallery field. The code is similar; we only need to keep in mind that the result is now a list, and we need to convert it to a string, as required by the gallery shortcode:  
​

```
{wp-shortcode:gallery @ ids={acf:gallery|-implode(',')} size={acf:gallery-size}}
```

or equivalently:

```
{wp-shortcode:gallery @ ids={for:img {acf:gallery} {get:img} @sep=','} size={acf:gallery-size}}
```

### WP Shortcodes that accept content

Some wordpress shortcodes can accept content, for example  
​  
​`[example]this is some content[/example]`

To do this with wp-shortcode you pass the content as the second argument, so:  
​  
​`{wp-shortcode:example [this is some content]}`  
​

## Cache Management (cache, get-cache, delete-cache)

The Cache Dynamic Shortcode offers a method for temporary data storage, enabling the caching of content within WordPress. Its main benefit is to boost performance by reducing database load and cutting down on page loading times.

**This shortcode allows for the caching of other Dynamic Shortcodes**, and it simplifies the process of storing, fetching, and erasing cached entries with customizable expiration periods.

### Syntax

The syntax for implementing the Cache Dynamic Shortcode is as follows:

* ### For creating/updating cached data

  ```
  {cache:key value@keyargs}
  ```

  ###
* ### For retrieving cached data

  ```
  {get-cache:key}
  ```
* ### For deleting cached data

  ```
  {delete-cache:key}
  ```

### Key Arguments

* **`expiration` (alias `exp`)** Determines how long the data should be kept cached, specified in natural language like "30 minutes", "1 day" or "2 weeks".

### Caching a Complex Query

#### **Without Caching**

Typically, retrieving the latest posts from a specific category without caching would involve running a database query every time the page loads. This can be resource-intensive, especially on a high-traffic site.

**Example without Cache**

```
{loop:{query:posts@posts_per_page=50}  
   {post:title}  
   @separator=', '  
}
```

In this example, the **`{query:posts}`** shortcode directly queries the database each time the page is viewed, which could lead to slower page loading times as each query consumes resources.  
​

#### **With Caching**

Using the Cache Dynamic Shortcode, we can store the results of this query, reducing the number of times the database needs to be accessed.  
​

**Example with Cache**

```
{cache:latest_articles  
   {query:posts@posts_per_page=50}  
   @expiration="1 hour"  
}
```

After caching, retrieving and displaying the cached data would use the following shortcode:

```
{loop:{get-cache:latest_articles}  
   {post:title}  
   @separator=', '  
}
```

#### **Why Caching is Preferable**

* **Performance Improvement**: By caching the query results, we avoid repeatedly querying the database. This reduces the server's workload and significantly speeds up page loading times for users.
* **Consistency**: Cached data ensures that all users see the same content without slight variations that might occur if the database updates between individual user queries.
* **Resource Efficiency**: Reducing the frequency of queries helps conserve server resources, which is particularly beneficial during peak traffic periods.

Managing Cache Deletion

Deleting the cache is a crucial operation to ensure that data does not become stale, especially in dynamic contexts where content frequently updates.

#### Example of Cache Deletion

Suppose you want to reset the stored data for **`latest_articles`** to reflect significant content updates or after a specific event, such as the publication of new articles. You can use the following shortcode:

```
{delete-cache:latest_articles}
```

This command clears the data stored under the key **`latest_articles`**, ensuring that the next page view will generate fresh data and store it again in the cache.

##### **Important Considerations**

* **Single Execution**: It is vital to ensure that the **`delete-cache`** shortcode is only executed when necessary. Leaving the shortcode active on the page could lead to continuous cache clearing every time the page is loaded, negating the benefits of having a cache.
* **Removing the Shortcode**: After the **`delete-cache`** shortcode has been executed and the cache has been cleared, it is advisable to remove or deactivate the shortcode from the page to prevent unintended executions. This can be managed manually.

### 

​

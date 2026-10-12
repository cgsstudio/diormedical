---
name: dynamic-shortcodes-post-data
description: Retrieve post, term, author and user data with Dynamic Shortcodes: {post:...} (title, content, permalink, custom fields, terms), {term:...}, {author:...} and {user:...}. Covers field access, taxonomy/term lookups, and the implicit post-context model. Use when outputting data about the current or a specific post, term, author or user.
---

# Dynamic Shortcodes: Post, Term, Author & User Data

Retrieve post, term, author and user data with Dynamic Shortcodes: {post:...} (title, content, permalink, custom fields, terms), {term:...}, {author:...} and {user:...}. Covers field access, taxonomy/term lookups, and the implicit post-context model. Use when outputting data about the current or a specific post, term, author or user.

## Post Data Retrieval and Field Access (post)

The Post Dynamic Shortcode is a powerful tool designed to fetch and display specific data related to WordPress posts. It allows for fine-tuned data retrieval by specifying post fields, using key arguments for additional filtering, and even accessing post meta that isn't publicly exposed. Ideal for developers and content creators, it enhances the flexibility and control of content within WordPress pages or posts.

### Syntax

The syntax for the Post Dynamic Shortcode is as follows:

```
{post:field}
```

* `field` represents the specific post data you want to retrieve, such as the post title, author, or custom meta.

### Key Arguments

* `id` Specifies the ID of the post from which to retrieve data. If not provided, the current post is used.  
  ​

  ```
  {post:title@id=42}
  ```
* `all` A boolean key argument that, when set, returns all metadata associated with the specified post field.  
  ​

  ```
  {post:field@all}
  ```
* `taxonomy` When retrieving terms, this argument specifies which taxonomy's terms to retrieve (default is category). ***Note: only applicable with the 'terms' field.***  
  ​

  ```
  {post:terms@taxonomy=books}
  ```

### Examples

1. **Retrieve the Title of the Current Post**  
   ​

   ```
   {post:title}
   ```

   This shortcode will fetch and display the title of the current post.
2. **Retrieve the Title of a Specific Post**

   ```
   {post:title@id=42}
   ```

   This shortcode will fetch and display the title of the post with the ID 42.
3. **Access a Custom Field Value**  
   ​

   ```
   {post:custom_field_name}
   ```

   This retrieves the value of *custom\_field\_name* from the current post.
4. **Get the Excerpt of the Current Post**  
   ​

   ```
   {post:excerpt}
   ```

   This example will return the excerpt for the current post being viewed.  
   ​
5. **List Terms Associated with a Post**  
   ​

   ```
   {for:single-term   
      {post:terms@taxonomy=category id=10}   
         [<li>{term:name @ID={get:single-term}}]  
   }
   ```

   Here, the shortcode lists terms from the 'category' taxonomy associated with the post that has the ID 10. `{post:terms@taxonomy=category id=10}` returns an array so we cycled the terms with the shortcode {for}.  
   ​

### Special Privileges

Accessing certain post meta, particularly those starting with an underscore (which are typically private), requires the shortcode to be used within a Power Shortcode that has elevated privileges.   
​  
Additionally, if the post is not publicly visible (e.g., not published), elevated privileges are also required to retrieve its data.

## Term Data Retrieval (term)

The term Dynamic Shortcode in WordPress provides an advanced mechanism for retrieving fields from terms.

### Syntax

```
{term:field}
```

* `field`: Specifies the term field to retrieve.

### Key Arguments

* `id`: Specifies the term ID to fetch data for.

  ```
  {term:field @id=23}
  ```

### Examples

1. **Basic Term Name Retrieval**

   ```
   {term:name}
   ```

   This retrieves the name of the current term.
2. **Fetching Description with Term ID**

   ```
   {term:description@id=45}
   ```

   Fetches the description of the term with ID 45.
3. **Retrieving Term Permalink**

   ```
   {term:permalink@id=12}
   ```

   Gets the permalink of the term with ID 12.
4. **Displaying Term Count**

   ```
   {term:count@id=8}
   ```

   Shows the count of posts associated with the term ID 8.

## Author Data Retrieval (author)

The Author Dynamic Shortcode is a shortcode to dynamically retrieve and manipulate data related to post authors.

### Syntax

```
{author:field}
```

* `field` indicates the specific author information to retrieve.

### Key Arguments

Key arguments for the 'author' Dynamic Shortcode can include:

* `id` Designates the author's ID. Defaults to the current author if not specified.

  ```
  {author:field @ID=3}
  ```

### Examples

1. **Simple Author Name Retrieval**

   ```
   {author:name}
   ```

   Retrieves the name of the current author of the post.
2. **Author Name with Specific ID**

   ```
   {author:name@id=5}
   ```

   Fetches the name of the author with ID 5.
3. **Author Specific Information Retrieval**

   ```
   {author:custom_field@id=3}
   ```

   Here, the shortcode retrieves a specific custom field value (replace `custom_field` with the actual field name) related to the author with ID 3.

### Special Privileges

Accessing certain author fields might necessitate heightened privileges, especially for sensitive data. Such instances require the Author Dynamic Shortcode's use within Power Shortcodes, which are equipped to handle privileged data securely. For example, fetching an author's private email may need these elevated privileges.

## User Information Retrieval (user)

This particular shortcode specializes in fetching and displaying user-related information based on specified parameters.

### Syntax

```
{user:field}
```

* `field` represents the user metadata field.

### Key Arguments

* `id` Specifies the user ID to fetch data for. Requires Power Shortcodes privileges.

  ```
  {user:field @ID=3}
  ```
* `all` When set, returns all values of the specified metadata field.

  ```
  {user:field @all}
  ```

### Examples

1. **Email**  
   ​

   ```
   {user:email}
   ```

   Retrieves the email of the current user.
2. **User ID**  
   ​

   ```
   {user:ID}
   ```

   Retrieves the email of the current user  
   ​
3. **Display Name**  
   ​

   ```
   {user:display_name}
   ```

   Retrieves the name of the current user.
4. **Registration Date**  
   ​

   ```
   {date:{user:registered} @format='d-m-Y H:i'}
   ```

   Retrieves the registration date of the current user.   
   In this case `{user:registered}` is used inside the date shortcode to format it.
5. **Specifying User ID**

   ```
    {user:login@id=10}
   ```

   Fetches the login name of the user with ID 10.
6. **User Roles**

   ```
   {for:role  
      {user:roles}  
      {get:role}  
      @sep=', '  
   }
   ```

   Returns all roles assigned to the current user. `{user:roles}` returns an array so we cycled the terms with the shortcode {for}.

### Special Privileges

Some actions and information retrieval, such as accessing certain user metadata (e.g., `email`, `login`) or specifying a user ID, require the use of Power Shortcodes.   
These have elevated privileges to ensure sensitive data is securely handled.

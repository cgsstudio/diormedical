---
name: dynamic-shortcodes-request
description: Read request and environment data with Dynamic Shortcodes: query vars ({query_var:...}), URL and POST parameters ({param-get:...}, {param-post:...}), browser cookies ({cookie:...}), server info ({server:...}), WordPress site options ({option:...}), and outbound HTTP API requests ({api:...}). Use when reading the incoming request, environment, site settings, or calling an external API.
---

# Dynamic Shortcodes: Request & Environment

Read request and environment data with Dynamic Shortcodes: query vars ({query_var:...}), URL and POST parameters ({param-get:...}, {param-post:...}), browser cookies ({cookie:...}), server info ({server:...}), WordPress site options ({option:...}), and outbound HTTP API requests ({api:...}). Use when reading the incoming request, environment, site settings, or calling an external API.

## Query Variable Retrieval (query_var)

The query var Dynamic Shortcode allows you to retrieve different query variables within a page or post.

### Syntax

```
{query_var:name}
```

* `name`: The name of the query variable you want to retrieve.

### Examples

1. **Basic Usage**

   `{query_var:author}`

   Retrieves the author query variable. Useful for displaying the author of a post.
2. **Page ID Retrieval**

   ```
   {query_var:page_id}
   ```

   Fetches the page ID. Ideal for use in templates where the page ID is needed for further processing or conditional content.
3. **Search Term**:

   ```
   {query_var:s}
   ```

   Grabs the current search term. This is particularly handy for custom search results pages, allowing the display or use of the search term entered by the user.
4. **Category Name**:

   ```
   {query_var:category_name}
   ```

   Gets the name of the current category.

### Special Privileges

Access to certain query variables may require elevated privileges. In such cases, the shortcode must be used within a Power Shortcode to ensure proper access and functionality.

## Parameter Handling (param-get, param-post)

The Parameter Dynamic Shortcode allows accessing GET and POST parameters.

### Usage

#### Get a GET parameter

```
{param-get:myparam}
```

This will output the value of the `myparam` GET parameter.

For example, if the URL is

```
example.com?myparam=hello
```

then `{param-get:myparam}` will output `hello`.

#### Get a POST parameter

**Attention to the Distinction Between HTTP POST Parameters and WordPress Posts**

It is crucial to understand that HTTP POST parameters used in web requests are entirely different from WordPress posts.

* **HTTP POST Parameters:** These are used in HTTP requests to transmit data from a client to a server. They are an essential part of communication between the frontend and backend in web applications. Data sent via POST parameters are invisible to the user and do not appear in the URL, providing a level of security for sensitive data.
* **WordPress Posts:** These refer to the content published on a WordPress site, such as blog articles, pages, etc. They are visible to the site's users and are intended to provide information or entertainment.

To get a POST parameter, use:

```
{param-post:myparam}
```

This will output the value of the `myparam` POST parameter.

For example, if a form is submitted with a `myparam` field, `{param-post:myparam}` will output the value of that field.

### Sanitization

There is no need to sanitise the data as this is already done by Dynamic Shortcodes.

## Browser Cookie Access (cookie)

The Cookie Dynamic Shortcode is specifically tailored to access and return values stored in browser cookies, making it an essential tool for personalized user experiences on WordPress sites.

### Syntax

```
{cookie:name}
```

* `name`: The name of the cookie whose value is to be retrieved.

### Examples

1. **Basic Cookie Retrieval**

   ```
   {cookie:user_session}
   ```

   This example fetches the value of the 'user\_session' cookie. It's useful for retrieving session-specific data for a user.
2. **Fallback Value**

   ```
   {cookie:preferences?'Default settings'}
   ```

   Here, the shortcode attempts to retrieve the 'preferences' cookie. If the cookie is not found, it defaults to 'default\_settings'.
3. **Within a Conditional Statement**

   ```
   {if:{cookie:name}   
      'The Cookie exists'   
      "The Cookie doesn't exists"  
   }
   ```

   In this example, the shortcode checks if the 'name' cookie is not empty and displays a message accordingly.

## Server Information Retrieval (server)

The Server Dynamic Shortcode in WordPress allows users to retrieve specific server information efficiently.

### Syntax

```
{server:variable}
```

* `variable`: The name of the server variable you wish to access, corresponding to the keys in the `$_SERVER` PHP global array.

### Examples

1. **Accessing Server Name**

   ```
   {server:SERVER_NAME}
   ```

   This retrieves the server's domain name.
2. **Getting Server Software**

   ```
   {server:SERVER_SOFTWARE}
   ```

   Displays the server software being used (e.g., Apache, Nginx)
3. **Fetching Document Root**

   ```
   {server:DOCUMENT_ROOT}
   ```

   Returns the document root directory under which the current script is executing.
4. **Server Protocol**

   ```
   {server:SERVER_PROTOCOL}
   ```

   Shows the name and revision of the information protocol (e.g., HTTP/1.1).
5. **Remote Address**

   ```
   {server:REMOTE_ADDR}
   ```

   Retrieves the IP address from which the user is viewing the current page

### Special Privileges

The Server Dynamic Shortcode must be used within a Power Shortcode.

## WordPress Site Option Retrieval (option)

The Option Dynamic Shortcode in WordPress enables the retrieval and display of various website options.

### Syntax

```
{option:key}
```

* `key`: The name of the site option to retrieve.

**Note**

The Option shortcode returns the raw option value. **No formatting or escaping is applied.**

### Examples

1. **Basic Usage**

   ```
   {option:siteurl}
   ```

   Retrieves the 'siteurl' option from WordPress settings.
2. **With Fallback Value**:

   ```
   {option:nonexistent_option?https://fallbacksite.com}
   ```

   Attempts to fetch 'nonexistent\_option'. If not found, defaults to 'https://fallbacksite.com'.

### Special Privileges

For security reasons, only a whitelist of options can be accessed normally.

To access any option, you must use the Option Shortcode inside a Power Shortcode.  
​

The following options can be accessed normally:

* siteurl
* home
* blogname
* blogdescription
* start\_of\_week
* posts\_per\_page
* date\_format
* time\_format
* timezone\_string
* sticky\_posts
* www\_user\_roles
* thumbnail\_size\_h
* thumbnail\_size\_w
* users\_can\_register
* avatar\_default

## HTTP API Requests (api)

This Dynamic Shortcode is available from the version 1.5.0.

The API Dynamic Shortcode enables users to perform HTTP requests directly within WordPress content. By using this Dynamic Shortcode, users can fetch data from external APIs, send data to remote servers, or interact with other web services. The API Shortcode supports GET and POST methods, headers, and request bodies, allowing developers and content creators to access and display dynamic content.   
​  
The use of key arguments allows users to customize requests precisely, including setting timeouts and specifying request methods.   
​

### Syntax

The basic syntax for the API Shortcode is as follows:

```
{api:<url>@<keyarg_list>}
```

* **`<url>`**: A string representing the target URL for the HTTP request. It can be an identifier, number, nested string, or quoted string.
* **`@<keyarg_list>`**: A list of key arguments that define the request's behavior. These can include headers, body content, method type, and timeout settings.

### Key Arguments

The API Dynamic Shortcode supports several key arguments that allow you to customize HTTP requests:

* **post**: Specifies to use the POST HTTP method. The default is **get**.

  Example: `{api:'https://www.myurl.com'@`post}
* **`headers`**: An array of headers to include in the request.

  Example: `{api:'https://www.myurl.com'@headers={array:@Content-Type=" application/json" Authorization="Bearer token"}}`
* **`body`**: The request body is typically used with post methods.

  Example: `{api:'https://www.myurl.com'@body={array:@key=value}`}
* **`timeout`**: An integer specifying the maximum time, in seconds, to wait for a response. The default is `30`.

  Example: `{api:'https://www.myurl.com'@timeout=10}`
* **`no-decode`**: by default the result is treated as JSON and decoded, if you want to avoid it because the API does not return JSON, you can use this keyarg.

  Example: `{api:'https://www.myurl.com'@no-decode}`  
  ​

### Examples

Here are five examples demonstrating the API Shortcode in action:

1. ### Simple GET Request

   ```
   {api:"https://api.example.com/data"}
   ```

   **Explanation**: This example makes a simple GET request to the specified URL (`https://api.example.com/data`). The default method is GET; no additional headers or body content are provided.  
   ​
2. ### GET Request with Headers

   ```
   {api:"https://api.example.com/data"  
      @headers={array:@Authorization="Bearer token"}  
   }
   ```

   **Explanation**: This example demonstrates how to include a header in the request, specifically an authorization token. The request is made to `https://api.example.com/data` using the GET method.  
   ​
3. ### POST Request with JSON Body

   ```
   {api:"https://api.example.com/submit"  
      @post  
      body={array:@name='John Doe' email='john.doe@example.com'}  
      headers={array:"Content-Type: application/json"}  
   }
   ```

   **Explanation**: This example sends a POST request to `https://api.example.com/submit` with a JSON payload containing `name` and `email`. The Content-Type header is set to `application/json`.  
   ​
4. ### GET Request with Custom Timeout

   ```
   {api:"https://api.example.com/data"@timeout=5}
   ```

   **Explanation**: This example performs a GET request with a custom timeout of 5 seconds to `https://api.example.com/data`. The default timeout is overridden to be more responsive.

### Special Privileges

The API Shortcode must be executed within Power Shortcodes or in a privileged context.

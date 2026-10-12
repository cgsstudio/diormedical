---
name: dynamic-shortcodes-foundations
description: Dynamic Shortcodes core syntax and onboarding: the curly-brace structure {type:args @keyargs |filters ?fallback}, the mandatory colon after the type, templating with [...], single-quoted strings, the keyargs syntax, platform/plugin compatibility, and a guided tour of the core shortcodes (post, if, loop, query, acf, date). Load this first when learning, writing, or troubleshooting Dynamic Shortcodes syntax.
---

# Dynamic Shortcodes: Foundations

Dynamic Shortcodes core syntax and onboarding: the curly-brace structure {type:args @keyargs |filters ?fallback}, the mandatory colon after the type, templating with [...], single-quoted strings, the keyargs syntax, platform/plugin compatibility, and a guided tour of the core shortcodes (post, if, loop, query, acf, date). Load this first when learning, writing, or troubleshooting Dynamic Shortcodes syntax.

## Syntax and Structure Guide

## **Informal Description**

A Dynamic Shortcode is a special text element enclosed in curly braces `'{}'` . It has a specific structure that allows you to provide arguments, options, filters, and fallback values. Here's a simple guide to help you understand the syntax of a Dynamic Shortcode:

1. A shortcode starts with an identifier followed by a colon `':'`. An identifier is a sequence of letters, digits, or underscores, beginning with a letter.
2. Then there is a list of arguments separated by spaces, for some shortcodes types this list can also be empty. There are five types of arguments you can use:  
   - **Identifier**: Same as the shortcode's identifier, it's a sequence of letters, digits, or underscores, starting with a letter. For example, `{my_shortcode:arg1}`.

   - **Number**: A sequence of digits, e.g., `{my_shortcode:42}`.

   - **Nested string**: A sequence of characters enclosed in square brackets `[]`, e.g., `{my_shortcode:[nested string]}`. Shortcodes will be expanded inside the string.

   - **Quoted string**: A sequence of characters enclosed in double quotes `""`, e.g., `{my_shortcode:"quoted string"}`

   **- Dynamic Shortcodes**: . You can also insert directly a Dynamic Shortcode   
   `{my_shortcode:{nested_shortcode:}}`
3. You can also provide a list of options by adding an `` `@` `` symbol followed by one or more options separated by spaces. Each option can be:

   - An identifier, e.g., `{my_shortcode:@option1}`.

   - An identifier followed by an equal sign `` `=` `` and a value (identifier, number, nested string, or quoted string), e.g., `{my_shortcode:@option2=value}`.
4. You can apply filters to the shortcode using filter operators: `` `|` ``, `` `||` ``, or `` `|-` ``. A filter consists of a filter operator, an identifier, and an optional list of values enclosed in parentheses `` `()` `` and separated by commas `` `,` ``. For example, `{my_shortcode|filter1(identifier,value1,value2):}`.
5. Lastly, you can define a fallback value in case the shortcode can't be resolved. To do this, add a question mark `` `?` `` followed by a value (identifier, number, nested\_string, quoted\_string or shortcode). For example, `{my_shortcode:?fallback_value}`.

Remember, spaces are used to separate arguments and options.

​

## **Formal EBNF Description**

```
shortcode = '{', identifier, ':' , [ arguments_list ], [options_list], {filter}, [fallback], '}' ;  
  
arguments_list = value, { space, value } ;  
  
options_list = '@', option, {space, option} ;  
  
option = identifier | identifier, "=", value ;  
  
filter = filter_operator, identifier, [ '(', [ value ], { ',', value } , ')' ];  
  
fallback = '?', value;  
  
value = identifier | number | nested_string | quoted_string ;  
  
quoted_string = '"', { character } , '"' ;  
  
nested_string = '[', { character }, ']';  
  
identifier = letter, { letter | digit | '_' };  
  
number = digit, { digit } ;  
  
character = letter | digit | symbol ;  
  
filter_operator = "|" | "||" | "|-" ;   
  
(*  
  Basic components  
  ----------------  
  These are low level components, the small building blocks.  
*)  
  
letter = "A" | "B" | "C" | "D" | "E" | "F" | "G"  
       | "H" | "I" | "J" | "K" | "L" | "M" | "N"  
       | "O" | "P" | "Q" | "R" | "S" | "T" | "U"  
       | "V" | "W" | "X" | "Y" | "Z" | "a" | "b"  
       | "c" | "d" | "e" | "f" | "g" | "h" | "i"  
       | "j" | "k" | "l" | "m" | "n" | "o" | "p"  
       | "q" | "r" | "s" | "t" | "u" | "v" | "w"  
       | "x" | "y" | "z" ;  
  
digit = "0" | "1" | "2" | "3" | "4" | "5" | "6" | "7" | "8" | "9" ;  
  
symbol = "[" | "]" | "{" | "}" | "(" | ")" | "<" | ">"  
       | "'" | '"' | "=" | "|" | "." | "," | ";" ;  
  
space = " " ;
```

## Setup and Platform Compatibility Guide

### How to Set Up the Use of Shortcodes

To enable Dynamic Shortcodes, navigate to the dashboard's settings page. Select the areas to be used and click save.

Once you save the settings, any post you add or edit can now include Dynamic Shortcodes.

### Using Shortcodes in Page Builders or Editors

Dynamic Shortcodes can also be used in the following ways:

* Gutenberg & FSE (Beta)
* Elementor
* Bricks
* WPBakery
* Oxygen
* Breakdance
* Classic Editor (post title, post content, and post excerpt)

## Getting Started with Core Shortcodes (post, if, loop, query, acf, date)

Dynamic Shortcodes is a tool that allows you to create and use "shortcodes" within your WordPress content. You can think of Dynamic Shortcodes as a placeholder for a dynamically generated value.

### **Basic Example of Dynamic Shortcodes**

#### **Retrieving the Current Post's Title**

The **`{post:title}`** shortcode can be used to fetch and display the title of the post in which it is embedded. When you insert this shortcode into your content, the Dynamic Shortcodes plugin recognizes it and replaces it with the actual title of the post.

```
Hello, you're reading "{post:title}"!
```

If the current post’s title was "Snorkeling Guide", the displayed result would be:

```
Hello, you're reading "Snorkeling Guide"!
```

### **Expanding the Possibilities with Dynamic Shortcodes**

Dynamic Shortcodes are not limited to simple data retrieval; they can be used to implement complex logic and data manipulation. Here are some advanced ways to use them, explained in simple terms:

* ### **Conditional Displays**

  Use a Dynamic Shortcode to show different messages depending on who is viewing the page. For instance, you might display a personalized welcome message to administrators versus non-registered users.

  ​**Example**

  ```
  {if:  
     {and:  
        {eq:{acf:gender} 'female'}   
        {lt:{acf:age} 25}}   
           'Youth Female Category'   
           'Other Categories'  
  }
  ```

  In this example, if the `gender` field, created with Advanced Custom Fields, is 'female' and the `age` field is less than 25, 'Youth Female Category' is displayed. Otherwise, 'Other Categories' is shown.
* ### **Complex Data Queries**

  Display titles of the last three posts from a specific category. This demonstrates how Dynamic Shortcodes can be used to dynamically fetch and display results from complex queries.

  ​**Example**

  ```
  {loop:{query:posts}   
     [{post:title} by {post:author}]   
     @sep=', '  
  }
  ```

  Here, the **`{query}`** shortcode is nested within a **`{loop}`** shortcode to iterate over posts and display the title and author of each.
* ### **Advanced Formatting**

  Display the publication date and time of a post in a specified format, using a Dynamic Shortcode to fetch the post's publication date and format it according to a specified date and time format. This example demonstrates how to use nested shortcodes to perform simple but powerful operations

  **Example**

  ```
  Publication date: {date:{post:date}@format='d-m-Y H:i'}
  ```

  In this example, the **`{date}`** shortcode is used to retrieve and format the publication date of the current post. The **`@format`** key argument specifies the date and time format, showcasing how Dynamic Shortcodes can manage and display date information precisely.

## Keyargs Syntax and Usage Guide

## Understanding Keyargs in Dynamic Shortcodes

Dynamic Shortcodes provide an easy way to pass additional data and settings to the shortcode by using Keyargs.

### What is Keyargs Syntax?

Keyargs is an easy syntax that allows you to pass additional data and settings to Dynamic Shortcodes. One Keyarg has a key and a value associated to it. The key is a simple text identifier (for example, `id` or `format`) and the value can be virtually anything, depending on the requirements. The key and value are connected with an equal sign.

An example of a Keyarg is:

```
id=42
```

which associates the number 42 with the key id.

### Usage of to Keyargs in Dynamic Shortcodes

Keyargs must be specified in a keyarg section, a keyarg section is a list of keyarg declarations separated by spaces. A keyarg section is introduced by the `@` symbol.

All in all, if you have a shortcode like

```
{query:posts}
```

we can add Keyargs like this:

```
{query:posts@tag=food}
```

If you want to add more than one, you can do

```
{query:posts@tag=food post-status=publish}
```

### Omitting the Value

As a special case, a keyarg can omit the value. These are used to pass simple on/off flags to the Dynamic Shortcodes. A truth/on value is implied. For example, in

```
{acf:field@format}
```

you are telling the ACF Dynamic Shortcodes field to format the field according to the ACF Field settings. You could use

```
{acf:field@format=1}
```

but that is unnecessary.

## Demo Feature for Shortcode Discovery

If you want to check some of the Dynamic Shortcodes available on a particular page, you can use the Demos.


The demos show a long list of example Dynamic Shortcodes and their results when used on a particular page.

The easiest way to use the demos is as follows:

* go to any front page you want to use Dynamic Shortcodes;
* in the admin bar go to the Dynamic Shortcodes menu;

* click on the "Demo Shortcodes" link;
* this will take you to a page containing a list of many Dynamic Shortcodes that can be applied to the page you came from, and a preview of each.

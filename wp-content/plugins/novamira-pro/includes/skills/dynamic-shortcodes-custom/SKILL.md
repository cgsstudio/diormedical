---
name: dynamic-shortcodes-custom
description: Extend Dynamic Shortcodes with custom logic: execute arbitrary PHP functions ({call:...}, including PHP constants via {call:constant name}), register custom PHP-based shortcodes programmatically through the registration interface, and create reusable admin-defined Power Shortcodes referenced as {power:<name>}. Use when the built-in shortcodes are not enough and you need PHP or a saved, reusable shortcode.
---

# Dynamic Shortcodes: Custom PHP & Power Shortcodes

Extend Dynamic Shortcodes with custom logic: execute arbitrary PHP functions ({call:...}, including PHP constants via {call:constant name}), register custom PHP-based shortcodes programmatically through the registration interface, and create reusable admin-defined Power Shortcodes referenced as {power:<name>}. Use when the built-in shortcodes are not enough and you need PHP or a saved, reusable shortcode.

## PHP Function Execution (call)

Call is helpful for calling a function that takes no arguments. This is needed as filters always take at least an argument. Use the call shortcode with the function's name as the first argument and any number of arguments to pass to the function afterward.

### Syntax

The syntax for the 'call' Dynamic Shortcode is

```
{call:function_name args}
```

* `function_name` represents the PHP function you wish to execute
* `args` is optional and consists of one or more arguments passed to the function.

  For example

  ```
  {call:function_name arg1 arg2}
  ```

### Examples

1. **Simple Function Call**

   ```
   {call:my_custom_function}
   ```

   This example executes the `my_custom_function` PHP function.
2. **Function Call with Arguments**

   ```
   {call:calculate_sum 5 10}
   ```

   Here, `calculate_sum` function is called with arguments `5` and `10`.
3. **Nested Function Call**

   ```
   {call:my_function {acf:field}}
   ```

   Demonstrates nesting another shortcode within the `call` shortcode, where `my_function` is executed with the result of the shortcode.

### Special Privileges

The Call Dynamic Shortcode is available only inside Power Shortcodes.

### Notes

Instead of a function name, you can use a callable object returned from another shortcode.

## Creating Custom PHP-Based Shortcodes with Registration Interface

For adding simple custom Dynamic Shortcodes written in PHP we provide an easy to use interface similar to the one for adding WordPress Shortcodes.

​**Note**: This is a simplified interface that does not have all the features of the canonical interface. If you want to extend the plugin and add complex Dynamic Shortcodes you should probably use that.

### Example

The following will add the simple shortcode that is given a name as the first argument and the result is a greeting for that name, `{greet:Jane}` will expand to "Hello Jane!".

**Note**: This serves only as an example. This same custom Shortcode could be created much more easily with Power Shortcodes and without the need to write any PHP.

```
function greeter_dynamic_shortcode( $args, $keyargs, $type ) {  
	return "Hello $args[0]!";  
}  
  
add_action ( 'dynamic-shortcodes/types/register', function( $dsh_manager ) {  
	$dsh_manager->register_callback( 'greet', 'greeter_dynamic_shortcode' );  
});
```

To register a Dynamic Shortcode you use the `register_callback` method of the Shortcodes manager. You can get the manager with the `dynamic-shortcodes/types/register` action.

The `register_callback` method is given a name and a callback. Then every time a Dynamic Shortcode with the name is encountered the given callback is called. When called the callback will receive an array of args ($args), an array of keyargs ($keyargs), and the Shortcode name ( parameter $type, in our case the value will be 'greet').

### Advantages over custom WordPress Shortcodes

* If you want to pass arguments to the Shortcodes Dynamic Shortcodes are much easier to write. Compare `[greet name="Jane"]` with `{greet:Jane}`
* You can use filters: `{greet:Jane|strtolower}`
* You can use the fallback `{greet:Jane?fallback}`
* WordPress Shortcode always return strings. While Dynamic Shortcodes can return any kind of value. This is useful when used in combination with other Dynamic Shortcodes.

## Creating Custom Reusable Shortcodes for Administrators (power)

Power Shortcodes are very useful in two cases:

* They enable the use of power features that are usually unavailable due to security constraints.
* When you use a pattern repeated very often, with potentially only minor differences. You can use Power Shortcodes as a template.

**Powers Shortcodes allow you to define custom Dynamic Shortcodes easily by composing other Shortcodes.**

Note: When writing Power Shortcodes, keep in mind that they can be written only by administrators, but then also used by contributors.

### How do they work

A Power Shortcode has two components: a name and its content. You can insert this into the WordPress dashboard (see below for details). A Power Shortcode can then be used everywhere else with `{power:name}`, where `name` is the name you defined before.

Suppose we have a Power Shortcode with name "welcome-user" and code

```
{if: {call:is_user_logged_in}      
   "You are not logged in!" [Welcome {user:name}!]  
}
```

Then you can use it anywhere with the Power Shortcode passing it the name as an argument: `{power:welcome-user}`.

It will work as if you had written the Power Shortcode code directly.

### How to create and edit Power Shortcodes

Go to the plugin's Settings and the Power Shortcodes page. The interface contains a list of the Power Shortcodes defined, a button to add a new one, and a button to save the shortcodes to the database.

The name is an identifier that should contain no spaces and only characters, digits, underscores, and minus signs (for example `my-shortcode`). In the content you can insert any text mixed with shortcodes definitions, as you normally would elsewhere.

### Passing arguments to Power Shortcodes (Advanced)

You can pass arguments and key arguments to Power Shortcodes as you would with normal shortcodes. In the content, you can fetch them with the `args` and `keyargs` shortcodes.

`args` returns an array of the positional arguments passed while `keyargs` returns an array of the key arguments passed. You can use the accessor filters (`||`) to fetch the arguments.

For example

**Name of the Power Shortcode**: welcome

**Code of the welcome Power**: `{if: {eq: {keyargs:||lang} it} Benvenuto Welcome} {args:||0}!`

**Usage**: `{power:welcome Joe}`

**Result**: Welcome Joe!

**Usage**: `{power:welcome Giovanni @lang=it}`

**Result**: Benvenuto Giovanni!

### Power Shortcodes are not escaped

Since they can be written only by administrators, the content of the Power Shortcodes itself is not escaped.

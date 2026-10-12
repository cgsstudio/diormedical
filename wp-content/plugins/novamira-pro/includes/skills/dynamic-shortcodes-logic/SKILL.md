---
name: dynamic-shortcodes-logic
description: Conditional logic and output transformation with Dynamic Shortcodes: {if:...} and {switch:...} for branching, comparison operators (gt, lt, eq, …), output filters chained with | / || / |-, and arithmetic operations ({+:...}, {-:...}, {*:...}, {/:...}). Covers templating with [...] inside conditions. Use when branching on a value, transforming output, or doing calculations.
---

# Dynamic Shortcodes: Logic, Filters & Math

Conditional logic and output transformation with Dynamic Shortcodes: {if:...} and {switch:...} for branching, comparison operators (gt, lt, eq, …), output filters chained with | / || / |-, and arithmetic operations ({+:...}, {-:...}, {*:...}, {/:...}). Covers templating with [...] inside conditions. Use when branching on a value, transforming output, or doing calculations.

## Conditional Content Logic (if, switch)

### **Conditional Content with the 'if'**

The `if` Dynamic Shortcode is a valuable tool for conditionally displaying or returning content based on a comparison's result.

In its basic form, the `if` shortcode interprets a condition passed as first argument. If the conditions is true, it returns the second argument; if false, it returns the third argument.

#### Basic Syntax

The **`if`** shortcode evaluates a condition, and based on this evaluation, it returns content:

```
{if:condition-here return-when-true return-when-false}
```

* **condition-here**: The condition to be evaluated (must resolve to true or false).
* **return-when-true**: The content or value returned if the condition is true.
* **return-when-false**: The content or value returned if the condition is false (optional).

​If the third argument is omitted, nothing will be displayed if the condition is false:

```
{if:condition-here return-when-true}
```

#### Examples

* ```
  {if:{acf:field} 'Field exists' 'Field does not exist'}
  ```

  This example displays 'Field exists' if the field specified by `{acf:field}` has a value (i.e., it is not empty or null); otherwise, it displays 'Field does not exist'.

* ```
  {if:{post:featured-image-id}   
     'The Post has a Featured Image'  
     'The Post does not have a Featured Image'  
  }
  ```

  + This shortcode checks if the current post has a featured image. The **`{post:featured-image-id}`** dynamically retrieves the ID of the featured image associated with a post.
  + If the post has a featured image, the ID will be a positive number (true), triggering the true condition.
  + If there is no featured image set, the result will be **`false`** (or equivalent to no value), and the false condition is displayed.

* ```
  {if:{post:featured-image-id}   
     {media:image @ID={post:featured-image-id}}  
  }
  ```

  + **`{post:featured-image-id}`**: Retrieves the ID of the featured image. Acts as a condition check.
  + **`{media:image @ID={post:featured-image-id}}`**: This is nested inside the **`if`** shortcode. If the condition is true, this shortcode executes using the ID from **`{post:featured-image-id}`** to fetch and display the actual image in HTML format.

### **Comparison Shortcodes**

The `if` is often paired with comparison Dynamic Shortcodes, which return `true` or `false` based on argument comparisons. For example:

* `gt` (greater than): `{gt:A B}` equals `A > B`
* `lt` (less than): `{lt:A B}` equals `A < B`
* `ge` (greater than or equal): `{ge:A B}` equals `A >= B`
* `le` (less than or equal): `{le:A B}` equals `A <= B`
* `eq` (strict equality): `{eq:A B}` equals `A === B`
* `eqv` (equality): `{eqv:A B}` equals `A == B`

Combining `if` with comparison Dynamic Shortcodes allows for conditional displays.

For example,

```
{if:{lt:A B} A B}
```

shows A if A < B, else shows B.

#### Examples of Using **`if`** with Comparison Dynamic Shortcodes

* **Display Price Range**

  ```
  {if:{gt:{product:price} 100} 'High-end Product' 'Affordable Product'}
  ```

  This checks if the price of a product is greater than 100. If it is, it labels the product as a "High-end Product"; otherwise, it is labeled as "Affordable Product".
* **Age Verification for Content Access**

  ```
  {if:{ge:{user:age} 18} 'Access granted' 'Access denied'}
  ```

  This shortcode checks if the age of the user is 18 or older. If the condition is true, access to certain content is granted; if not, access is denied.

* **Adjust Content Based on User Experience Level**

  ```
  {if:{lt:{user:experience_years} 5}   
     'Beginner Tips'   
     'Advanced Strategies'  
  }
  ```

  Useful for educational content, this checks if the user's experience level is less than 5 years. New users see "Beginner Tips," while more experienced users see "Advanced Strategies."

* **Membership Renewal Reminder**

  ```
  {if:{le:{user:days_until_membership_expires} 30}   
     'Your membership expires soon, renew now!'   
     'Membership active'  
  }
  ```

  Checks if the user's membership expires in 30 days or less. If true, it prompts them to renew; otherwise, it confirms their membership is active.

* **Display Custom Message Based on Scores**

  ```
  {if:{eq:{user:quiz_score} 100}   
     'Perfect score! Congratulations!'   
     'Try again for a perfect score!'  
  }
  ```

  This checks if the user's quiz score is equal to 100. A perfect score displays a congratulatory message, while any other score encourages trying again.

### **Implementing Logic in Content with Logical Shortcodes**

Additional shortcodes provide logical operations:

* `and`: Logical AND. `{and:{gt:A B} {lt:A C}}` returns true if A > B and A < C.
* `or`: Logical OR. `{or:{eq:A B} {eq:A C}}` returns true if either A == B or A == C.
* `not`: Logical NOT. `{not:{eq:A B}}` returns true if A != B.

#### Logical Shortcodes Explained

1. #### **Logical AND (`and`)**

   **Syntax:** `{and:condition1 condition2}`

   This shortcode returns true if both conditions are true. It's useful for cases where you need to meet multiple criteria simultaneously.  
   ​

   **Example: Checking Price and Stock for a Discount Offer**

   ```
   {if:{and:{gt:{product:price} 100} {gt:{product:stock} 10}}   
      'Discount Available!'   
      'No Discount'  
   }
   ```

   This checks if a product's price is greater than 100 and if its stock is greater than 10. Only if both conditions are satisfied does it offer a discount.
2. #### **Logical OR (`or`)**

   **Syntax:** `{or:condition1 condition2}`

   Returns true if at least one of the conditions is true. It is useful when any of multiple criteria can qualify for a particular outcome.

   **Example: Membership or Age for Access**

   ```
   {if:{or:{eq:{user:member_status} 'premium'} {ge:{user:age} 21}}  
      'Access Granted'  
      'Access Denied'  
   }
   ```

   Access is granted if the user is a premium member or if they are 21 or older, meeting any of these conditions grants access.
3. #### **Logical NOT (`not`)**

   **Syntax:** `{not:condition}`

   Returns true if the given condition is false. This is useful for reversing a condition's logical value.

   **Example: Show a message if there is no billing address**

   ```
   {if:{not:{user:billing_address}}   
      'Please update your billing address.'  
   }
   ```

   This shortcode checks if there is a billing address associated with the user's account. The `{user:billing_address}` would typically return the billing address if it exists. If no billing address is set, this might return an empty string, depending on how your shortcode is configured.

### **Simplifying Multiple Conditions with the 'switch' Shortcode**

The **`switch`** Dynamic Shortcode allows you to compare a given value against a series of potential matches, executing corresponding outputs for each match, similar to a switch-case statement in programming. This shortcode simplifies decision-making in your templates by neatly handling multiple conditions without requiring nested or multiple **`if`** statements.

#### Syntax

```
{switch:test_value  
   match_1 result_1   
   match_2 result_2 ...   
   default_result  
}
```

* **`test_value`**: The value to test against the match cases.
* **`match_1, match_2, ...`**: Values to compare against **`test_value`**.
* **`result_1, result_2, ...`**: Outputs corresponding to each match case.
* **`default_result`**: Returned if **`test_value`** matches none of the provided cases.

#### Practical Example Using User Status

Imagine we want to display different messages based on the user's status, which can be 'active', 'inactive', or 'pending':

```
{switch:{user:status}   
   'active' 'Access granted'   
   'inactive' 'Please activate your account'   
   'pending' 'Account verification in progress'   
   'Access denied'  
}
```

* **`{user:status}`**: Retrieves the status of the current user, which is expected to be a simple, singular value like 'active', 'inactive', or 'pending'.
* The shortcode evaluates the user's status:

  + If 'active', it displays "Access granted."
  + If 'inactive', it displays "Please activate your account."
  + If 'pending', it displays "Account verification in progress."
  + For any other status or if the status is undefined, it defaults to "Access denied."

## How to Use Filters to Transform Output

Filters allows you to transform the Dynamic Shortcode output to get what you need.

The filters work as pipeline, they take a value they do something with it and then pass the result to the next filter or, if no more filters are present, as the final result of the shortcode. There can be arbitrarily many chained filters inside a shortcode.

### Types of Filters

* ### PHP Function - Pipe First (|)

  The pipe first (symbol |), will pass the value coming from the left to the given function as the first argument, the other arguments given inside parentheses will be passed to the function after that. For example `{identity:"hello world"|strtoupper}` will be expanded to "HELLO WORLD!".

  Another example is: `{acf:myfield|my_function(arg1)}` The value of the field will be passed to the custom function together with arg1, if $myfield\_value is the value of the ACF field, the function is called like this: `my_function($my_field_value, "arg1")`
* ### PHP Function - Pipe Last (|-)

  The pipe last (symbol |-, a pipe and minus) work like the pipe first filter, but the value coming from the left is always passed as the last argument.

  For example   
  ​`{identity:hello|-str_replace(e, a)}` will expand to `hallo`.
* ### Array Access (||)

  The access filter (symbol ||) is used for accessing value inside arrays. It can be used both with numeric and string keys. For example `{array:a b c||1}` will expand to b. And `{array:@a=1 b=2 c=3||b}` will expand to `2`.
* ### Property Access (|.)

  It's used to access object in PHP object, it's the equivalent of PHP `$object->property`. If you have a shortcode that returns a PHP object you can use it like so: `{post:my-object |. property_name}` This filter can only be used inside Power Shortcodes.

## Arithmetic Operations

The arithmetic Dynamic Shortcode allows for inline mathematical calculations within text, facilitating operations like addition, subtraction, multiplication, division, and modulo. Ideal for dynamic content, it can process multiple numeric inputs and supports nesting with other Dynamic Shortcodes for advanced calculations.

### Syntax

The arithmetic Dynamic Shortcode syntax can take the following forms depending on the operation:

* **Addition**: `{+:<number1> <number2>]`
* **Subtraction**: `{-:<number1> <number2>}`
* **Multiplication**: `{*:<number1> <number2>}`
* **Division**: `{/:<number1> <number2>}`
* **Modulo**: `{modulo:<number1> <number2>}`

Each operation type requires at least two numeric arguments. However, all operators except modulo can take more than two numbers, allowing a syntax like `{+:<number1> <number2> <number3> ...}` for summing multiple values.

### Examples

1. **Addition of Multiple Numbers**:

   ```
   {+:10 15 20}
   ```

   Sums up the numbers 10, 15, and 20, resulting in 45.
2. **Subtraction**:

   ```
   {-:50 20}
   ```

   Subtracts 20 from 50, yielding 30.
3. **Multiplication**:

   ```
   {*:7 8}
   ```

   Multiplies 7 by 8, resulting in 56.
4. **Division**:

   ```
   {/:36 6}
   ```

   Divides 36 by 6, resulting in 6.
5. **Modulo**:

   ```
   {modulo:29 5}
   ```

   Finds the remainder of 29 divided by 5, which is 4.
6. **Nested Shortcode with Arithmetic**:

   ```
   {+:{acf:price} {*:{acf:price} {acf:sales_tax}}}
   ```

   Retrieves the `price` field from ACF, multiplies it by the `sales_tax` field, and adds the product to the original `price` to calculate

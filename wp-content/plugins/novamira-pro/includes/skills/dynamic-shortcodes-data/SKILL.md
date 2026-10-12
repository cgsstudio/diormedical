---
name: dynamic-shortcodes-data
description: Work with values, variables and dates in Dynamic Shortcodes: static arrays ({array:...}, simple and associative), variable management ({set:...}, {get:...}, set-default), returning values unchanged ({identity:...}), PHP literal values ({lit:...}), and date formatting and calculations ({date:...}). Use when storing/reading intermediate values, building arrays, or formatting dates.
---

# Dynamic Shortcodes: Arrays, Variables, Literals & Dates

Work with values, variables and dates in Dynamic Shortcodes: static arrays ({array:...}, simple and associative), variable management ({set:...}, {get:...}, set-default), returning values unchanged ({identity:...}), PHP literal values ({lit:...}), and date formatting and calculations ({date:...}). Use when storing/reading intermediate values, building arrays, or formatting dates.

## Creating Static Arrays with Simple and Associative Data (array)

With the array shortcode you can build a static array to pass to other Shortcodes.

To use it, pass the elements of the array as arguments to the shortcode,

```
{array: 1 2 3}
```

will return the array

```
[1,2,3]
```

You can also use keyarg for associative arrays:

```
{array:@one=1 two=2 three=3}
```

will result in the array

```
['one' => 1, 'two' => 2, 'three' => 3]
```

There are many cases where this might be useful, but the most simple example of this is in combination with the Loop Shortcode, so that you can repeat a template many times for each element in a static list.

```
{loop:   
   {array: 27 109 356}   
   [{post:title}]  
   @sep=', '  
}
```

will print the title of the 3 posts with ID 27, 109 and 356, each separated by comma.

## Variable Management (set, get, set-default)

Variables can be defined with either the for or the let Shortcode, these variables are only valid while their respective Shortcodes are being evaluated.

If a variable is redefined in a nested scope, the previous variable is hidden until the end of the nested scope (shadowing).

The for Shortcode is documented in its respective page.

### Set Shortcode

Used to set the value of a variable. First argument is the variable name, second name is the new value.

Examples:

```
{set:x 3}
```

set the value of x to 3.

```
{set:x {+: $x 1}}
```

increment x by one.

If you use the set Shortcode on an undefined variable, a variable will be created that is not bound to any scope, it will be valid also after the end of the current scope.

There is also `set-default`, it will set a value only if the specified variable has not already been initialized. If the variable has been initialized it does nothing.

### Get Shortcode

Used to get the value of a variable. If the variable doesn't exist it throws an error. Example

```
{get:x} {get:foo}
```

If you already are inside a shortcode you can just write `$foo`, which is just the same as writing `{get:foo}`, for example `{+:$foo 1}` instead of `{+:{get:foo} 1}`

## Returning Values As-Is (identity)

The Identity Dynamic Shortcode is a part of the Dynamic Shortcodes system. Unlike traditional shortcodes, the Identity Dynamic Shortcode provides a straightforward yet powerful way to return the value of its single argument without any modifications.

**This feature is particularly useful in scenarios where you need to dynamically retrieve and display data exactly as it is input.**

Identity will assume the value of the only argument passed.

### Syntax

The Identity Dynamic Shortcode follows the basic syntax:

```
{identity:arg}
```

* `arg` represents the value that will be returned as is by the shortcode

The Identity Dynamic Shortcode accepts only one argument.

### Examples

1. **Basic Usage**

   ```
   {identity:sampleText}
   ```

   This example returns the string `sampleText`.
2. **Number as Argument**

   ```
   {identity:123}
   ```

   Here, the shortcode returns the number `123`.
3. **Using a Filter Function**

   ```
   {identity:argument|function}
   ```

   In this example, the Identity Dynamic Shortcode is used in conjunction with a filter function.

   The shortcode `{identity:argument}` initially returns the string `argument`.

   The `|function` part represents a filter applied to this output.

​

## PHP Literal Values Expression (lit)

In some situations you need to use the PHP values Null, True and False.

In a filter for example, you might have a function that requires them as arguments. You can do this with the `lit` Dynamic Shortcode.

You simply pass the value name as the first argument:  
​

* `{lit:true}` for True
* `{lit:false}` for False
* `{lit:null}` for Null

### Using Literal Values in Post Queries

In this example, we demonstrate how to use the **`lit`** Dynamic Shortcode to express PHP values Null, True, and False, which can be used as part of a query to dynamically filter posts.

#### Practical Example: Filtering Posts with Specific Values

Suppose you want to retrieve posts based on a custom field that can be true or false. For example, you might want to show only posts that are marked as "important" in a custom post field.

```
{query:posts @meta_key=important meta_value={lit:true}}
```

In this case, **`{lit:true}`** is used to pass a literal true value to the query, ensuring that the query looks for posts with the 'important' meta field set to true.

##### **Explanation:**

* **{lit:true}**: This literal shortcode returns the Boolean value **`true`**, which is used as the value for **`meta_value`** in the post query.
* **@meta\_key and @meta\_value**: These arguments specify that we want to filter posts that have a meta field called 'important' with a value of true.

#### Alternative Usage

You can also use **`{lit:false}`, `{lit:true}`** or **`{lit:null}`** to achieve different behaviors, depending on the specific filtering needs:

* **To exclude posts**: Suppose you want to exclude posts that are less important

  ```
  {query:posts @meta_key=important meta_value={lit:false}}
  ```
* **To include posts without a specified value**: If you want to include posts where the 'important' field has not been set:

  ```
  {query:posts @meta_key=important meta_value={lit:null}}
  ```

### Why it is important to use literal values

When using literal values such as **`true`**, **`false`**, or **`null`** directly in Dynamic Shortcodes without the **`lit`** prefix, they would be treated as strings rather than their intended Boolean or null values.

This is critical because the query system interprets strings differently from Boolean values and null, which could lead to unexpected results.

For example:

```
{query:posts @meta_key=important meta_value=true}
```

In the above line, **`true`** without **`{lit:true}`** is treated as the string **`'true'`**, which means it would look for posts where the 'important' meta field contains the string "true", not where it is logically true.

Similarly, using:

```
{query:posts @meta_key=important meta_value=false}
```

Here, **`false`** is treated as the string **`'false'`**, and the query would search for posts where the 'important' meta field contains the string "false", not where it is logically false.

And for **`null`**:

```
{query:posts @meta_key=important meta_value=null}
```

Without using **`{lit:null}`**, **`null`** is treated as the string **`'null'`**, not as an actual null value, which might not produce the intended effect of including posts with an unset or null 'important' field.

## Date Formatting and Calculations (date)

The date shortcode will return a formatted date. You can specify the date using an English string that is in the same format as PHP's `strtotime` function.

For example,

```
{date:now}
```

will display the current date.

The format of the outputted date can be specified by passing a `format` as a keyarg.

```
{date:now@format='Y-m-d'}
```

### Formats

All supported formats are those of the `date` function, which we summarise here and can also be found on the [official PHP website](https://www.php.net/manual/en/datetime.format.php).

|  |  |  |
| --- | --- | --- |
| **`format`** character | Description | Example returned values |
| *Day* |  |  |
| `d` | Day of the month, 2 digits with leading zeros | `01` to `31` |
| `D` | A textual representation of a day, three letters | `Mon` through `Sun` |
| `j` | Day of the month without leading zeros | `1` to `31` |
| `l` (lowercase 'L') | A full textual representation of the day of the week | `Sunday` through `Saturday` |
| `N` | ISO 8601 numeric representation of the day of the week | `1` (for Monday) through `7` (for Sunday) |
| `S` | English ordinal suffix for the day of the month, 2 characters | `st`, `nd`, `rd` or `th`. Works well with `j` |
| `w` | Numeric representation of the day of the week | `0` (for Sunday) through `6` (for Saturday) |
| `z` | The day of the year (starting from 0) | `0` through `365` |
| *Week* |  |  |
| `W` | ISO 8601 week number of year, weeks starting on Monday | Example: `42` (the 42nd week in the year) |
| *Month* |  |  |
| `F` | A full textual representation of a month, such as January or March | `January` through `December` |
| `m` | Numeric representation of a month, with leading zeros | `01` through `12` |
| `M` | A short textual representation of a month, three letters | `Jan` through `Dec` |
| `n` | Numeric representation of a month, without leading zeros | `1` through `12` |
| `t` | Number of days in the given month | `28` through `31` |
| *Year* |  |  |
| `L` | Whether it's a leap year | `1` if it is a leap year, `0` otherwise. |
| `o` | ISO 8601 week-numbering year. This has the same value as `Y`, except that if the ISO week number (`W`) belongs to the previous or next year, that year is used instead. | Examples: `1999` or `2003` |
| `X` | An expanded full numeric representation of a year, at least 4 digits, with `-` for years BCE, and `+` for years CE. | Examples: `-0055`, `+0787`, `+1999`, `+10191` |
| `x` | An expanded full numeric representation if required, or a standard full numeral representation if possible (like `Y`). At least four digits. Years BCE are prefixed with a `-`. Years beyond (and including) `10000` are prefixed by a `+`. | Examples: `-0055`, `0787`, `1999`, `+10191` |
| `Y` | A full numeric representation of a year, at least 4 digits, with `-` for years BCE. | Examples: `-0055`, `0787`, `1999`, `2003`, `10191` |
| `y` | A two digit representation of a year | Examples: `99` or `03` |
| *Time* |  |  |
| `a` | Lowercase Ante meridiem and Post meridiem | `am` or `pm` |
| `A` | Uppercase Ante meridiem and Post meridiem | `AM` or `PM` |
| `B` | Swatch Internet time | `000` through `999` |
| `g` | 12-hour format of an hour without leading zeros | `1` through `12` |
| `G` | 24-hour format of an hour without leading zeros | `0` through `23` |
| `h` | 12-hour format of an hour with leading zeros | `01` through `12` |
| `H` | 24-hour format of an hour with leading zeros | `00` through `23` |
| `i` | Minutes with leading zeros | `00` to `59` |
| `s` | Seconds with leading zeros | `00` through `59` |
| `u` | Microseconds. Note that [date()](https://www.php.net/manual/en/function.date.php) will always generate `000000` since it takes an [int](https://www.php.net/manual/en/language.types.integer.php) parameter, whereas **DateTime::format()** does support microseconds if [DateTime](https://www.php.net/manual/en/class.datetime.php) was created with microseconds. | Example: `654321` |
| `v` | Milliseconds. Same note applies as for `u`. | Example: `654` |
| *Timezone* |  |  |
| `e` | Timezone identifier | Examples: `UTC`, `GMT`, `Atlantic/Azores` |
| `I` (capital i) | Whether or not the date is in daylight saving time | `1` if Daylight Saving Time, `0` otherwise. |
| `O` | Difference to Greenwich time (GMT) without colon between hours and minutes | Example: `+0200` |
| `P` | Difference to Greenwich time (GMT) with colon between hours and minutes | Example: `+02:00` |
| `p` | The same as `P`, but returns `Z` instead of `+00:00` (available as of PHP 8.0.0) | Examples: `Z` or `+02:00` |
| `T` | Timezone abbreviation, if known; otherwise the GMT offset. | Examples: `EST`, `MDT`, `+05` |
| `Z` | Timezone offset in seconds. The offset for timezones west of UTC is always negative, and for those east of UTC is always positive. | `-43200` through `50400` |
| *Full Date/Time* |  |  |
| `c` | ISO 8601 date | 2004-02-12T15:19:21+00:00 |
| `r` | [» RFC 2822](http://www.faqs.org/rfcs/rfc2822)/[» RFC 5322](http://www.faqs.org/rfcs/rfc5322) formatted date | Example: `Thu, 21 Dec 2000 16:01:07 +0200` |
| `U` | Seconds since the Unix Epoch (January 1 1970, 00:00:00 GMT) | See also [time()](https://www.php.net/manual/en/function.time.php) |

### Adding and subtracting from a date

If you are getting the date dynamically, for example, from a meta field, you may need the date for the day before it or a few hours later. You can do this simply by specifying it in the second argument to the shortcode:

```
The last day for registrations is {date:{acf:start_date} '-1 day'}
```

You can also specify more than one interval by separating them with a command:  
​

```
​{date:{acf:start_date} '-1 day, -1 hour'}
```

**If you want more precision or use dynamic values**, you can do it with the `timedelta` shortcode, with the `sub` and `add` keyargs:  
​

```
{date:{acf:start_date} @ sub={timedelta:@days=1 hours=2}}
```

The `timedelta` accepted keyargs are: `weeks, days, hours, minutes` and `seconds`.

### Date Comparisons

You can compare dates using the comparison shortcodes, for all dates that need to be compared, you should use a format that can be ordered, for example `U`. Example.

```
{if: {gt: {date:{acf:start_date}@format=U} {date:now@format=U}}  
       [Registration opens on {acf:start_date}]  
}
```

On any day before ACF `start_date` the message in the second line will be displayed.

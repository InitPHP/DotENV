# InitPHP DotENV

Loads environment variables from `.env` or `.env.php` file.

[![Latest Stable Version](http://poser.pugx.org/initphp/dotenv/v)](https://packagist.org/packages/initphp/dotenv) [![Total Downloads](http://poser.pugx.org/initphp/dotenv/downloads)](https://packagist.org/packages/initphp/dotenv) [![Latest Unstable Version](http://poser.pugx.org/initphp/dotenv/v/unstable)](https://packagist.org/packages/initphp/dotenv) [![License](http://poser.pugx.org/initphp/dotenv/license)](https://packagist.org/packages/initphp/dotenv) [![PHP Version Require](http://poser.pugx.org/initphp/dotenv/require/php)](https://packagist.org/packages/initphp/dotenv)

## Requirements

- PHP 5.6 or higher

## Installation

```php 
composer require initphp/dotenv
```

## Usage

**Note :** Lines starting with any non-alphanumeric character are counted as comments and are not processed.

**Note :** Existing definitions in the `$_SERVER` or `$_ENV` globals are not processed.

### `.env` File

_**Note that .env files are externally accessible. To prevent access with `.htaccess` or better yet keep your `.env` file in a directory that cannot be accessed externally.**_

`/home/www/.env` : 

```
# Comment Line
SITE_URL = http://lvh.me

PAGE_URL = ${SITE_URL}/page

; Comment Line
TRUE_VALE = true

EMPTY_VALUE = empty

FALSE_VALUE = false

NULL_VALUE = null

NUMERIC_VALUE = 13
PI_NUMBER = 3.14
```

`any.php` : 

```php 
require_once "vendor/autoload.php";
use \InitPHP\DotENV\DotENV;

DotENV::create('/home/www/.env');

DotENV::get('TRUE_VALE'); // true
DotENV::get('FALSE_VALUE'); // false
DotENV::get('SITE_URL'); // "http://lvh.me"
DotENV::get('PAGE_URL'); // "http://lvh.me/page"
DotENV::get('EMPTY_VALUE'); // ""
DotENV::get('NULL_VALUE'); // NULL
DotENV::get('NUMERIC_VALUE'); // 13
DotENV::get('PI_NUMBER'); // 3.14

DotENV::get('NOT_FOUND', 'hi'); // "hi"
```

### `.env.php`

`/home/www/.env.php` :

```php 
<?php 
return [
    'SITE_URL'      => 'http://lvh.me',
    'PAGE_URL'      => '${SITE_URL}/page',
    'TRUE_VALE'     => true,
    'EMPTY_VALUE'   => '',
    'FALSE_VALUE'   => false,
    'NULL_VALUE'    => null,
    'NUMERIC_VALUE' => 13
];
```

`any.php` :

```php 
require_once "vendor/autoload.php";
use \InitPHP\DotENV\DotENV;

DotENV::create('/home/www/.env.php');


DotENV::get('TRUE_VALE'); // true
DotENV::get('FALSE_VALUE'); // false
DotENV::get('SITE_URL'); // "http://lvh.me"
DotENV::get('EMPTY_VALUE'); // ""
DotENV::get('NULL_VALUE'); // NULL
DotENV::get('NUMERIC_VALUE'); // 13

DotENV::get('NOT_FOUND', 'hi'); // "hi"
```

### `DotENV::create()`

Reads and defines an `.env` or `.env.php` file.

```php
public function create(string $path, bool $debug = true): void;
```

- `$path`  : The path to the file to be uploaded. If you define a directory path, Dotenv will try to search for the `.env` or `.env.php` file itself.
- `$debug` : Defines the exception throwing state. If `false` no exception is thrown.

**Note :** If the file is not found, the file is not a `.env`/`.env.php` file, or is unreadable, it throws a `\Exception` variant.

### `DotENV::get()`

Returns an ENV value.

```php
public function get(string $name, mixed $default = null): mixed;
```

**Note :** The priority order is as follows;

`$_ENV` -> `$_SERVER` -> `getenv()`

### `DotENV::env()`

It's an alias for the `Dotenv::get()` method.

```php
public function env(string $name, mixed $default = null): mixed;
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)

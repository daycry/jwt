[![Donate](https://img.shields.io/badge/Donate-PayPal-green.svg)](https://www.paypal.com/donate?business=SYC5XDT23UZ5G&no_recurring=0&item_name=Thank+you%21&currency_code=EUR)

# JWT

JWT for Codeigniter 4

[![Build Status](https://github.com/daycry/jwt/workflows/PHP%20Tests/badge.svg)](https://github.com/daycry/jwt/actions?query=workflow%3A%22PHP+Tests%22)
[![Coverage Status](https://coveralls.io/repos/github/daycry/jwt/badge.svg?branch=master)](https://coveralls.io/github/daycry/jwt?branch=master)
[![Downloads](https://poser.pugx.org/daycry/jwt/downloads)](https://packagist.org/packages/daycry/jwt)
[![GitHub release (latest by date)](https://img.shields.io/github/v/release/daycry/jwt)](https://packagist.org/packages/daycry/jwt)
[![GitHub stars](https://img.shields.io/github/stars/daycry/jwt)](https://packagist.org/packages/daycry/jwt)
[![GitHub license](https://img.shields.io/github/license/daycry/jwt)](https://github.com/daycry/jwt/blob/master/LICENSE)

## Installation via composer

Use the package with composer install

	> composer require daycry/jwt

## Manual installation

Download this repo and then enable it by editing **app/Config/Autoload.php** and adding the **Daycry\JWT**
namespace to the **$psr4** array. For example, if you copied it into **app/ThirdParty**:

```php
$psr4 = [
    'Config'      => APPPATH . 'Config',
    APP_NAMESPACE => APPPATH,
    'App'         => APPPATH,
    'Daycry\JWT' => APPPATH .'ThirdParty/JWT/src',
];
```

## Configuration

Run command:

	> php spark jwt:publish

This command will copy a config file to your app namespace.
Then you can adjust it to your needs. By default file will be present in `app/Config/JWT.php`.

## Usage Loading Library

```php
<?php 

$library = new \Daycry\JWT\JWT();
$encode = $this->library->encode('hello');

```
If you want change attributes before controller call:

```php
<?php
$config = config('JWT');
$library = new \Daycry\JWT\JWT($config);
$encode = $this->library->encode('hello');

$decode = $this->library->decode($encode);

$decode->get('data');

```
By default it is saved in the data parameter, but it can be modified as follows

```php
<?php
$config = config('JWT');
$library = (new \Daycry\JWT\JWT($config))->setParamData('another');
$encode = $this->library->encode('hello');

$decode = $this->library->decode($encode);

$decode->get('another');

```
If you want save an array data
```php
<?php
$config = config('JWT');
$library = (new \Daycry\JWT\JWT($config))->setParamData('another');
$encode = $this->library->encode(array( 'first' => '1', 'second' => '2' ));

$decode = $this->library->decode($encode);

\json_decode($decode->get('another'));

```
If you want to save the data array separately
```php
<?php
$config = config('JWT');
$library = (new \Daycry\JWT\JWT($config))->setSplitData();
$encode = $this->library->encode(array( 'first' => '1', 'second' => '2' ));

$decode = $this->library->decode($encode);

$decode->get('first');
$decode->get('second');

```

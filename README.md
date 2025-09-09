# IPQuery PHP

[![Build Status](https://scrutinizer-ci.com/g/ericsizemore/ipquery-php/badges/build.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/ipquery-php/build-status/main)
[![Code Coverage](https://scrutinizer-ci.com/g/ericsizemore/ipquery-php/badges/coverage.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/ipquery-php/?branch=main)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ericsizemore/ipquery-php/badges/quality-score.png?b=main)](https://scrutinizer-ci.com/g/ericsizemore/ipquery-php/?branch=main)
[![Continuous Integration](https://github.com/ericsizemore/ipquery-php/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/ericsizemore/ipquery-php/actions/workflows/continuous-integration.yml)
[![Type Coverage](https://shepherd.dev/github/ericsizemore/ipquery-php/coverage.svg)](https://shepherd.dev/github/ericsizemore/ipquery-php)
[![Psalm Level](https://shepherd.dev/github/ericsizemore/ipquery-php/level.svg)](https://shepherd.dev/github/ericsizemore/ipquery-php)
[![Latest Stable Version](https://img.shields.io/packagist/v/esi/ipquery-php.svg)](https://packagist.org/packages/esi/ipquery-php)
[![Downloads per Month](https://img.shields.io/packagist/dm/esi/ipquery-php.svg)](https://packagist.org/packages/esi/ipquery-php)
[![License](https://img.shields.io/packagist/l/esi/ipquery-php.svg)](https://packagist.org/packages/esi/ipquery-php)

`Esi\IPQuery` - A PHP library for `ipquery.io`, a free and performant ip address API.

> [!IMPORTANT]
> WIP: This library is not yet finished. Not recommended for production.

### Requirements

* PHP >= 8.3
* Composer
* And library(ies) that provide(s):
    * PSR-7 HTTP Message implementation
    * PSR-17 HTTP Factory implementation
    * PSR-18 HTTP Client implementation

## Installation

This library is decoupled from any HTTP messaging client by using [PSR-7](https://www.php-fig.org/psr/psr-7/), [PSR-17](https://www.php-fig.org/psr/psr-17/), and [PSR-18](https://www.php-fig.org/psr/psr-18/).

You can install the package via composer:

```bash
# First, install the base package
composer require esi/ipquery-php

# Then install your preferred PSR implementations.

# Example 1: Using Symfony components
composer require symfony/http-client:^7.0 symfony/psr-http-message-bridge:^7.0 nyholm/psr7:^1.0

# Example 2: Using Guzzle
composer require guzzlehttp/guzzle:^7.0

# There are other libraries and numerous combinations. The important thing is that a PSR-7, PSR-17, and PSR-18 implementation is provided.
```
---

This library makes use of [php-http/http-discovery](https://github.com/php-http/discovery#usage-as-a-library-user). This means that the `Builder` will default to 
any discoverable PSR-7, PSR-17, and PSR-18 libraries that are installed.

`php-http/http-discovery` also supports auto-installation if no libraries implementing the above PSR standards are found. This is accomplished by having the following
in `composer.json`:

```json
    "config": {
        "allow-plugins": {
            "php-http/discovery": true
        }
    }
```

## Usage

### Basic usage

```php
use Esi\IPQuery\Client;
use Esi\IPQuery\Api\IP;

$ipApi = new IP(new Client());

var_dump($ipApi->sendRequest(['1.1.1.1', '8.8.8.8']));

```

### HTTP Client Builder

You can customize the HTTP client by providing a `Esi\IPQuery\HttpClient\Builder` instance to the `Esi\IPQuery\Client` constructor.

For example, to set a custom user agent:

```php
use Esi\IPQuery\Client;
use Esi\IPQuery\HttpClient\Builder;
use Http\Client\Common\Plugin\HeaderSetPlugin;

$builder = (new Builder())
    ->addPlugin(new HeaderSetPlugin([
        'User-Agent' => 'My super cool user agent',
    ]));

$client = new Client($builder);
```

Or using a specific library for the PSR implementations, instead of what is found by `php-http/http-discovery`:

```php
use Esi\IPQuery\Client;
use Esi\IPQuery\HttpClient\Builder;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;

$factory = new HttpFactory();
$client = new Client(new Builder(new GuzzleClient(), $factory, $factory, $factory));

$ipApi = new IP(new Client());

var_dump($ipApi->sendRequest(['1.1.1.1', '8.8.8.8']));
```

## About

### Credits

- [Eric Sizemore](https://github.com/ericsizemore)
- [All Contributors](https://github.com/ericsizemore/ipquery-php/contributors)

### Contributing

See [CONTRIBUTING](./CONTRIBUTING.md).

Bugs and feature requests are tracked on [GitHub](https://github.com/ericsizemore/ipquery-php/issues).

### Contributor Covenant Code of Conduct

See [CODE_OF_CONDUCT.md](./CODE_OF_CONDUCT.md)

### Backward Compatibility Promise

See [backward-compatibility.md](./backward-compatibility.md) for more information on Backwards Compatibility.

### Changelog

See the [CHANGELOG](./CHANGELOG.md) for more information on what has changed recently.

### License

See the [LICENSE](./LICENSE) for more information on the license that applies to this project.

### Security

See [SECURITY](./SECURITY.md) for more information on the security disclosure process.

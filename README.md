# A filament plugin to create API endpoints for resources in the panel builder

[![Latest Version on Packagist](https://img.shields.io/packagist/v/allandereal/filament-api.svg?style=flat-square)](https://packagist.org/packages/allandereal/filament-api)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/allandereal/filament-api/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/allandereal/filament-api/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/allandereal/filament-api/fix-php-code-styling.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/allandereal/filament-api/actions?query=workflow%3A"Fix+PHP+code+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/allandereal/filament-api.svg?style=flat-square)](https://packagist.org/packages/allandereal/filament-api)



This is where your description should go. Limit it to a paragraph or two. Consider adding a small example.

## Installation

You can install the package via composer:

```bash
composer require allandereal/filament-api
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="filament-api-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="filament-api-config"
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="filament-api-views"
```

This is the contents of the published config file:

```php
return [
];
```

## Usage

```php
$filamentApi = new Allandereal\FilamentApi();
echo $filamentApi->echoPhrase('Hello, Allandereal!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Allan](https://github.com/allandereal)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

# Laravel Next Datatable

[![Latest Version on Packagist](https://img.shields.io/packagist/v/next-datatable/datatable.svg?style=flat-square)](https://packagist.org/packages/next-datatable/datatable)
[![Total Downloads](https://img.shields.io/packagist/dt/next-datatable/datatable.svg?style=flat-square)](https://packagist.org/packages/next-datatable/datatable)
![GitHub Actions](https://github.com/next-datatable/datatable/actions/workflows/main.yml/badge.svg)

A Server Side Proccessing Package Laravel for Vue Next Datatable.

You can see [Vue Next Datatable](https://github.com/devoverid/vue-next-datatable)

## Installation

You can install the package via composer:

```bash
composer require next-datatable/datatable
```

Publish config :
```
php artisan vendor:publish --provider="NextDatatable\Datatable\DatatableServiceProvider"
```

## Usage

```php
use NextDatatable\Datatable\Facades\Datatable;

$eloquent = App\Models\User::query();
$datatable = Datatable::of($eloquent)->make();
return $datatable;
```

### Testing

```bash
composer test
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email fiandwi0424@gmail.com instead of using the issue tracker.

## Credits

-   [viandwi24](https://github.com/next-datatable)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
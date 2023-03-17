
# Laravel Access Control Rules Ui
(ACR - Access Control Rules, Permissions Package with User Interface)

[![License](https://poser.pugx.org/wnikk/laravel-access-ui/license)](//packagist.org/packages/wnikk/laravel-access-ui)
[![PHP Version Require](http://poser.pugx.org/wnikk/laravel-access-ui/require/php)](https://packagist.org/packages/wnikk/laravel-access-ui)
[![Total Downloads](http://poser.pugx.org/wnikk/laravel-access-ui/downloads)](https://packagist.org/packages/wnikk/laravel-access-ui)
[![Latest Stable Version](https://poser.pugx.org/wnikk/laravel-access-ui/v)](//packagist.org/packages/wnikk/laravel-access-ui)
[![Latest Unstable Version](http://poser.pugx.org/wnikk/laravel-access-ui/v/unstable)](https://packagist.org/packages/wnikk/laravel-access-ui)

This is FrontEnd for package [Laravel Access Rules](https://github.com/wnikk/laravel-access-rules/).
 

## What does Access Rules support?

- Multiple user models.
- Multiple permissions can be attached to users.
- Multiple permissions can be attached to groups.
- Permissions verification.
- Permissions caching.
- Events when permissions are attached, detached or synced.
- Multiple permissions can be attached to user or group.
- Permissions can be inherited with unlimited investment from users and groups.
- Laravel gates and policies.
- Frontend themes, default "ukit".


## Documentation, Installation, and Usage Instructions Backend

Before using UI, it is necessary to install the ACR (Access Control Rules) itself.
See the [documentation](https://github.com/wnikk/laravel-access-rules/tree/master/docs) for detailed installation and usage instructions.


## You can install the package UI using composer:

```bash
composer require wnikk/laravel-access-ui
```

And publish the **config/accessUi.php** config file with:

```bash
php artisan vendor:publish --provider="Wnikk\LaravelAccessRules\AccessUiServiceProvider"
```


## Contributing

Please report any issue you find in the issues page. Pull requests are more than welcome.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

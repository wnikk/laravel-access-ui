
![Laravel Access Control Rules](https://raw.githubusercontent.com/wnikk/laravel-access-rules/main/docs/art/laravel-access-control-rules-logo.png)

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
- Frontend themes: "ukit", Bootstrap 4 and Bootstrap 5, default "bt".


## Documentation, Installation, and Usage Instructions Backend

Before using UI, it is necessary to install the ACR (Access Control Rules) itself.

See the [documentation](https://github.com/wnikk/laravel-access-rules/tree/master/docs) for detailed installation and usage instructions.


## You can install the package UI using composer:

```bash
composer require wnikk/laravel-access-ui
```

And publish the **config/accessUi.php** config file with:

```bash
php artisan vendor:publish --provider="Wnikk\LaravelAccessUi\AccessUiServiceProvider"
```

## Theme
### Bootstrap 4 and Bootstrap 5
![Laravel Access Control Rules Ui](https://raw.githubusercontent.com/wnikk/laravel-access-ui/main/docs/art/interface.bt.webp)

### ukit
![Laravel Access Control Rules Ui](https://raw.githubusercontent.com/wnikk/laravel-access-ui/main/docs/art/interface.ukit.webp)


## Optional GUI Routes
| HTTP method| Route| Main page| Controller| Action|  
|----|----|----|----|----|
| GET| accessui/| accessUi.main | ...AccessUiController| main|

| HTTP method| Route| Pages JSON data| Controller| Action|  
|----|----|----|----|----|
| GET| accessui/rules-data| accessUi.rules-data.index| ...RulesController| index| 
| POST| accessui/rules-data| accessUi.rules-data.store| ...RulesController| store| 
| PUT| accessui/rules-data/{id}| accessUi.rules-data.update| ...RulesController| update| 
| DELETE| accessui/rules-data/{id}| accessUi.rules-data.destroy| ...RulesController| destroy| 
| GET| accessui/owners-data| accessUi.owners-data.index| ...OwnersController| index| 
| POST| accessui/owners-data| accessUi.owners-data.store| ...OwnersController| store| 
| PUT| accessui/owners-data/{id}| accessUi.owners-data.update| ...OwnersController| update| 
| DELETE| accessui/owners-data/{id}| accessUi.owners-data.destroy| ...OwnersController| destroy| 
| GET| accessui/owner/{owner}/inherit-data| accessUi.owner.inherit-data.index| ...InheritController| index| 
| POST| accessui/owner/{owner}/inherit-data| accessUi.owner.inherit-data.store| ...InheritController| store| 
| DELETE| accessui/owner/{owner}/inherit-data/{id}| accessUi.owner.inherit-data.destroy| ...InheritController| destroy| 
| GET| accessui/owner/{owner}/permission-data| accessUi.owner.permission-data.index| ...PermissionController| index| 
| PUT| accessui/owner/{owner}/permission-data/{id}| accessUi.owner.permission-data.update| ...PermissionController| update| 


## Contributing

Please report any issue you find in the issues page. Pull requests are more than welcome.


## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

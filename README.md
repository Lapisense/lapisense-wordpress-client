# Lapisense WordPress Client [![Latest Stable Version](https://poser.pugx.org/lapisense/wordpress-client/v/stable.svg)](https://packagist.org/packages/lapisense/wordpress-client) [![License](https://poser.pugx.org/lapisense/wordpress-client/license.svg)](https://packagist.org/packages/lapisense/wordpress-client) [![Total Downloads](https://poser.pugx.org/lapisense/wordpress-client/downloads)](//packagist.org/packages/lapisense/wordpress-client)

WordPress integration for the Lapisense licensing API. Provides automatic update checking for plugins and themes, license activation, and product info display. Built on top of [lapisense/php-client](https://github.com/Lapisense/lapisense-php-client).

## Requirements

- PHP 7.4+
- WordPress 5.8+

## Installation

```bash
composer require lapisense/wordpress-client
```

## Usage

```php
use Lapisense\WordPressClient\Client;

// Licensed product
$lapisense = Client::init([
    'store_url'    => 'https://store.example.com',
    'product_uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d479',
    'product_type' => 'plugin', // or 'theme'
    'file'         => __FILE__,
]);

$lapisense->activate($licenseKey);
$lapisense->deactivate();
$lapisense->isActivated();
$lapisense->getActivationStatus();
```

## License

GPL-2.0-or-later

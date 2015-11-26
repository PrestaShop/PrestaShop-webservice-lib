# PrestaShop-webservice-lib
PHP library for PrestaShop Webservices

## Install
### from composer
```bash
composer require wic/prestashop-webservice-lib
```

## Usage
```php
use WIC\PrestaShop\WebService;
$url = 'http://prestashop.url';
$key = 'XXXXXXXXX';
$debug = true;
$webService = new WebService($url, $key, $debug);
```
See examples folder for more usage examples.

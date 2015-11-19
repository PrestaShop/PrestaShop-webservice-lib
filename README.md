# PrestaShop-webservice-lib
PHP library for PrestaShop Webservices
## Install 
### from composer
```bash
composer require prestashop/prestashop-webservice-lib
```

## Usage
```php
use PrestaShop\WebService;
$url = 'http://prestashop.url';
$key = 'XXXXXXXXX';
$debug = true;
$webService = new WebService($url, $key, $debug);
```

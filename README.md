# TechDivision/HeidelpayMockable

This module provides some plugins in order to manipulate API endpoints within the `magento2/heidelpay` (https://github.com/heidelpay/magento2) 
module. This is necessary for mocking purpose in a local development environment or for additional testing systems, which are
not supported by Heidelpay. 

> **ATTENTION:**
> Please do not use this module in production environment. It is designed for testing purpose only!

## Getting Started

### Installation

```
composer config repositories.techdivision.magento2-heidelpay-mockable vcs https://github.com/LukasKiederle/magento2-heidelpay-mockable.git
composer require techdivision/heidelpay-mockable
```

```
bin/magento setup:upgrade
```

### Configuration

After installing the module you need to configure the endpoints for your PSP mock service by adding following values
into your `<project-root>/app/etc/config.php`.

```php
'system' => 
  array (
    'default' => 
    array (
      'techdivision_heidelpay_mockable' => 
      array (
        'psp_mock_url' => 'https://psp-mock.test',
      ),
    ),
  ),
```

### Mock Service

This module was designed for https://github.com/techdivision/psp-mock, but it should also work for others.

## License

This project is licensed under the OSL 3.0 License.

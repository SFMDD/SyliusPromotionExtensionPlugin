<h1 align="center">FMDD Sylius promotion plugin</h1>

This plugin add : 
- Threshold promotion
- Free product promotion
## Installation

1. require the bundle with Composer:

```bash
$ composer require fmdd/sylius-promotion-plugin
```

2. enable the bundle :

```php
<?php

# config/bundles.php

return [
    // ...
    FMDD\SyliusPromotionPlugin\FMDDSyliusPromotionPlugin::class => ['all' => true],
    // ...
];
```

Now in you admin panel you have 2 new promotions actions : 
- Threshold promotion
- Free product promotion

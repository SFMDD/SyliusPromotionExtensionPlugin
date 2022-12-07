<h1 align="center">FMDD Sylius promotion plugin</h1>

This plugin add : 
- Threshold promotion
- Free product promotion
## Installation

1. require the bundle with Composer:

```bash
$ composer require fmdd/sylius-promotion-extension-plugin
```

2. enable the bundle :

```php
<?php

# config/bundles.php

return [
    // ...
    FMDD\SyliusPromotionExtensionPlugin\FMDDSyliusPromotionExtensionPlugin::class => ['all' => true],
    // ...
];
```

3.Add form theme block :

```yaml
# config/routes/sylius_admin.yaml
sylius_promotion_extension:
  resource: "@FMDDSyliusPromotionExtensionPlugin/Resources/config/admin_routing.yml"
```

4.Add form theme block :

```twig
...

{% block sylius_product_variant_autocomplete_choice_row %}
    {{ form_row(form, {'remote_url': path('fmdd_admin_ajax_product_variant_by_name_phrase'), 'load_edit_url': path('fmdd_admin_ajax_product_variant_by_code')}) }}
{% endblock %}
```

Now in you admin panel you have 2 new promotions actions : 
- Threshold promotion
- Free product promotion

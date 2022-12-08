<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Paweł Jędrzejewski
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FMDD\SyliusPromotionExtensionPlugin\Promotion\Filter;
 
use Sylius\Component\Core\Promotion\Filter\FilterInterface;

final class ProductVariantFilter implements FilterInterface
{
    public function filter(array $items, array $configuration): array
    {
        if (empty($configuration['filters']['variants_filter']['variants'])) {
            return $items;
        }

        $filteredItems = [];
        foreach ($items as $item) {
            if (in_array($item->getVariant()->getCode(), $configuration['filters']['variants_filter']['variants'], true)) {
                $filteredItems[] = $item;
            }
        }

        return $filteredItems;
    }
}

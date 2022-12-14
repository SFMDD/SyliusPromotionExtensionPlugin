<?php

namespace FMDD\SyliusPromotionExtensionPlugin\Promotion\Action;

use Doctrine\Common\Persistence\ObjectManager;
use FMDD\SyliusPromotionExtensionPlugin\Manager\PromotionManager;
use Sylius\Component\Core\Distributor\IntegerDistributorInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\Product;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Promotion\Action\UnitDiscountPromotionActionCommand;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Core\Repository\ProductVariantRepositoryInterface;

class FreeThresholdPromotionActionCommand extends UnitDiscountPromotionActionCommand
{
    public const TYPE = 'free_threshold';

    /**
     * @var FilterInterface
     */
    private $productFilter;
    
    /**
     * @var FilterInterface
     */
    private $productVariantFilter;

    /**
     * @var ProductVariantRepositoryInterface
     */
    private $productVariantRepository;

    /**
     * @var FactoryInterface
     */
    private $orderItemFactory;

    /**
     * @var OrderItemQuantityModifierInterface
     */
    private $itemQuantityModifier;

    /**
     * @var IntegerDistributorInterface
     */
    private $integerDistributor;

    /**
     * @var PromotionManager
     */
    private $promotionManager;

    /**
     * @var FilterInterface
     */
    private $taxonFilter;

    /**
     * @var FilterInterface
     */
    private $priceRangeFilter;

    /**
     * @param FactoryInterface                   $adjustmentFactory
     * @param FilterInterface                    $productFilter
     * @param FilterInterface                    $productVariantFilter
     * @param ProductVariantRepositoryInterface  $productVariantRepository
     * @param FactoryInterface                   $orderItemFactory
     * @param OrderItemQuantityModifierInterface $itemQuantityModifier
     * @param IntegerDistributorInterface        $integerDistributor
     * @param PromotionManager                   $promotionManager
     * @param FilterInterface                    $taxonFilter
     * @param FilterInterface                    $priceRangeFilter
     */
    public function __construct(
        FactoryInterface $adjustmentFactory,
        FilterInterface $productFilter,
        FilterInterface $productVariantFilter,
        ProductVariantRepositoryInterface $productVariantRepository,
        FactoryInterface $orderItemFactory,
        OrderItemQuantityModifierInterface $itemQuantityModifier,
        IntegerDistributorInterface $integerDistributor,
        PromotionManager $promotionManager,
        FilterInterface $taxonFilter,
        FilterInterface $priceRangeFilter
    ) {
        parent::__construct($adjustmentFactory);

        $this->productFilter = $productFilter;
        $this->productVariantFilter = $productVariantFilter;
        $this->productVariantRepository = $productVariantRepository;
        $this->orderItemFactory = $orderItemFactory;
        $this->itemQuantityModifier = $itemQuantityModifier;
        $this->integerDistributor = $integerDistributor;
        $this->promotionManager = $promotionManager;
        $this->taxonFilter = $taxonFilter;
        $this->priceRangeFilter = $priceRangeFilter;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(PromotionSubjectInterface $subject, array $configuration, PromotionInterface $promotion): bool
    {
        if (!$subject instanceof OrderInterface) {
            throw new UnexpectedTypeException($subject, OrderInterface::class);
        }

        $channelCode = $subject->getChannel()->getCode();
        if (!isset($configuration[$channelCode]) ||
            !isset($configuration[$channelCode]['threshold']) ||
            !isset($configuration[$channelCode]['quantity']) ||
            !isset($configuration[$channelCode]['variant_code'])
        ) {
            return false;
        }

        $filteredItems = $this->priceRangeFilter->filter(
            $subject->getItems()->toArray(),
            array_merge(['channel' => $subject->getChannel()], $configuration[$channelCode])
        );
        $filteredItems = $this->productFilter->filter($filteredItems, $configuration[$channelCode]);
        $filteredItems = $this->taxonFilter->filter($filteredItems, $configuration[$channelCode]);

        if (empty($filteredItems)) {
            return false;
        }

        // Check if products conditioning the rule are in the correct quantity in the cart
        $totalQty = 0;
        foreach ($filteredItems as $item) {
            $totalQty += $item->getQuantity();
        }

        if ($totalQty < $configuration[$channelCode]['threshold'] ) {
            return false;
        }

        $qtyToOffer = $configuration[$channelCode]['quantity'];
        $productToOffer = $configuration[$channelCode]['variant_code'];

        $variantFilter = [];
        $variantFilter['filters']['variants_filter']['variants'] = [$productToOffer];
        $filteredItems = $this->productVariantFilter->filter($subject->getItems()->toArray(), $variantFilter);

        $variantToOffer = $this->getVariant($productToOffer);
        if (null === $variantToOffer) {
            return false;
        }

        $variantItem = null;
        /** @var OrderItemInterface $filteredItem */
        foreach ($filteredItems as $filteredItem) {
            if ($variantToOffer->getId() === $filteredItem->getVariant()->getId()) {
                $variantItem = $filteredItem;
                break;
            }
        }

        // Product to offer is not in cart yet or variant is not in cart yet
        if (null === $variantItem) {
            $variantItem = $this->orderItemFactory->createNew();
            /** @var OrderItemInterface $variantItem */
            $variantItem->setVariant($variantToOffer);
            $this->updateItemQuantity($variantItem, $qtyToOffer);
            $subject->addItem($variantItem);
            $this->promotionManager->setShouldRelaunchPromotionProcessor(true);
        }

        if ($variantItem->getQuantity() < $qtyToOffer) {
            // Product to offer is in cart, but missing qties to offer
            // Get first matching item and add missing qty
            $missingQty = $qtyToOffer - $variantItem->getQuantity();
            $this->updateItemQuantity($variantItem, $variantItem->getQuantity() + $missingQty);
            $this->promotionManager->setShouldRelaunchPromotionProcessor(true);

            return false;
        }

        $promotionAmount = $variantItem->getUnitPrice() * $qtyToOffer;
        $splitPromotionAmounts = $this->integerDistributor->distribute($promotionAmount, $variantItem->getQuantity());
        $i = 0;
        foreach ($variantItem->getUnits() as $unit) {
            if (0 === $splitPromotionAmounts[$i]) {
                continue;
            }
            $this->addAdjustmentToUnit($unit, $splitPromotionAmounts[$i], $promotion);
            $i++;
        }

        return true;
    }

    /**
     * @param $variantCode
     *
     * @return ProductVariantInterface|null
     */
    private function getVariant($variantCode): ?ProductVariantInterface
    {
        /** @var ProductVariantInterface $variant */
        $variant = $this->productVariantRepository->findOneBy([
            'code' => $variantCode,
        ]);

        return $variant ?? null;
    }

    /**
     * @param \Sylius\Component\Order\Model\OrderItemInterface $item
     * @param int                                              $quantity
     */
    private function updateItemQuantity(\Sylius\Component\Order\Model\OrderItemInterface $item, $quantity): void
    {
        $this->itemQuantityModifier->modify($item, $quantity);
    }
}

<?php

namespace FMDD\SyliusPromotionExtensionPlugin\Promotion\Action;

use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Promotion\Action\UnitDiscountPromotionActionCommand;
use Sylius\Component\Core\Promotion\Checker\Rule\ContainsProductRuleChecker;
use Sylius\Component\Core\Promotion\Filter\FilterInterface;
use Sylius\Component\Promotion\Model\PromotionInterface;
use Sylius\Component\Promotion\Model\PromotionSubjectInterface;
use Sylius\Component\Resource\Exception\UnexpectedTypeException;
use Sylius\Component\Resource\Factory\FactoryInterface;

class UnitPercentageDiscountThresholdPromotionActionCommand extends UnitDiscountPromotionActionCommand
{
    public const TYPE = 'unit_percentage_discount_threshold';

    /**
     * @var FilterInterface
     */
    private $productFilter;

    /**
     * @var FilterInterface
     */
    private $taxonFilter;

    /**
     * @var FilterInterface
     */
    private $priceRangeFilter;

    /**
     * @param FactoryInterface $adjustmentFactory
     * @param FilterInterface  $productFilter
     */
    public function __construct(
        FactoryInterface $adjustmentFactory,
        FilterInterface $productFilter,
        FilterInterface $taxonFilter,
        FilterInterface $priceRangeFilter
    ) {
        parent::__construct($adjustmentFactory);

        $this->productFilter = $productFilter;
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
            !isset($configuration[$channelCode]['percentage']) ||
            !isset($configuration[$channelCode]['threshold'])
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

        $thresholdFilteredItems = [];
        foreach ($filteredItems as $item) {
            if ($item->getQuantity() < $configuration[$channelCode]['threshold']) {
                continue;
            }
            $thresholdFilteredItems[] = $item;
        }

        if (count($thresholdFilteredItems) === 0) {
            return false;
        }

        foreach ($thresholdFilteredItems as $item) {
            $promotionAmount = (int) round($item->getUnitPrice() * $configuration[$channelCode]['percentage']);
            $this->setUnitsAdjustments($item, $promotionAmount, $promotion);
        }

        return true;
    }

    /**
     * @param OrderItemInterface $item
     * @param int                $promotionAmount
     * @param PromotionInterface $promotion
     */
    private function setUnitsAdjustments(
        OrderItemInterface $item,
        int $promotionAmount,
        PromotionInterface $promotion
    ): void {
        foreach ($item->getUnits() as $unit) {
            $this->addAdjustmentToUnit($unit, $promotionAmount, $promotion);
        }
    }
}

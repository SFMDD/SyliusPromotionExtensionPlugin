<?php

namespace FMDD\SyliusPromotionExtensionPlugin\Form\Type;

use Sylius\Bundle\ProductBundle\Form\Type\ProductAutocompleteChoiceType;
use Sylius\Bundle\PromotionBundle\Form\Type\PromotionFilterCollectionType;
use Sylius\Bundle\ResourceBundle\Form\DataTransformer\ResourceToIdentifierTransformer;
use Sylius\Bundle\ResourceBundle\Form\Type\ResourceAutocompleteChoiceType;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;
use Symfony\Component\Validator\Constraints\Type;

class FreeThresholdConfigurationType extends AbstractType
{
    /**
     * @var RepositoryInterface
     */
    private $productVariantRepository;

    /**
     * @param RepositoryInterface $productVariantRepository
     */
    public function __construct(RepositoryInterface $productVariantRepository)
    {
        $this->productVariantRepository = $productVariantRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('threshold', IntegerType::class, [
                'label' => 'fmdd.free_threshold.threshold',
                'empty_data' => 1,
                'required' => false,
                'constraints' => [
                    new Type(['type' => 'numeric', 'groups' => ['sylius']]),
                    new Range([
                        'min' => 1,
                        'minMessage' => 'fmdd.free_threshold.threshold_min',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('filters', PromotionFilterCollectionType::class, [
                'label' => false,
                'required' => false,
                'currency' => $options['currency'],
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'fmdd.free_threshold.quantity',
                'empty_data' => 1,
                'required' => false,
                'constraints' => [
                    new Type(['type' => 'numeric', 'groups' => ['sylius']]),
                    new Range([
                        'min' => 1,
                        'minMessage' => 'fmdd.free_threshold.quantity_min',
                        'groups' => ['sylius'],
                    ]),
                ],
            ])
            ->add('variant_code', ProductVariantAutocompleteChoiceType::class, [
                'label' => 'sylius.form.promotion_action.add_product_configuration.product',
            ])
        ;

        $builder->get('variant_code')->addModelTransformer(
            new ReversedTransformer(new ResourceToIdentifierTransformer($this->productVariantRepository, 'code'))
        );
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('currency')
            ->setAllowedTypes('currency', 'string')
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'sylius_promotion_action_free_threshold_configuration';
    }
}

<?php

namespace App\Form;

use App\Entity\Ride;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Range;

class RideFormType extends AbstractType
{
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add('departureCity', TextType::class, [
                'label' => 'Ville de départ',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir une ville de départ.'
                    ),
                    new Length(max: 100),
                ],
            ])
            ->add('arrivalCity', TextType::class, [
                'label' => 'Ville d\'arrivée',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez saisir une ville d\'arrivée.'
                    ),
                    new Length(max: 100),
                ],
            ])
            ->add('departureAt', DateTimeType::class, [
                'label' => 'Date et heure de départ',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez choisir une date de départ.'
                    ),
                    new GreaterThan(
                        'now',
                        message: 'La date de départ doit être dans le futur.'
                    ),
                ],
            ])
            ->add('availableSeats', IntegerType::class, [
                'label' => 'Places disponibles',
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez indiquer le nombre de places.'
                    ),
                    new Range(
                        min: 1,
                        max: 8,
                        notInRangeMessage: 'Le nombre de places doit être compris entre {{ min }} et {{ max }}.'
                    ),
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Prix par passager (€)',
                'input' => 'string',
                'scale' => 2,
                'html5' => true,
                'attr' => [
                    'min' => '0.01',
                    'step' => '0.01',
                ],
                'constraints' => [
                    new NotBlank(
                        message: 'Veuillez indiquer un prix.'
                    ),
                    new Positive(
                        message: 'Le prix doit être supérieur à 0.'
                    ),
                ],
            ]);
    }

    public function configureOptions(
        OptionsResolver $resolver,
    ): void {
        $resolver->setDefaults([
            'data_class' => Ride::class,
        ]);
    }
}

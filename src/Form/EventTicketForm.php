<?php

namespace App\Form;

use App\Entity\EventTicket;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventTicketForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom du billet',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: VIP, Standard, Early Bird'
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => 'Prix (FCFA)',
                'currency' => 'XOF',
                'divisor' => 100,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0'
                ]
            ])
            ->add('quantity', IntegerType::class, [
                'label' => 'Quantité disponible',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Ex: 100'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'Décrivez les avantages de ce type de billet...'
                ]
            ])
            ->add('earlyBird', CheckboxType::class, [
                'label' => 'Tarif Early Bird',
                'required' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ]
            ])
            ->add('endDate', DateTimeType::class, [
                'label' => 'Date de fin de vente',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EventTicket::class,
        ]);
    }
}
<?php

namespace App\Form;

use App\Entity\Event;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre de l\'événement',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Concert de musique traditionnelle'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Décrivez votre événement en détail...'
                ]
            ])
            ->add('eventDate', DateTimeType::class, [
                'label' => 'Date de l\'événement',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('startTime', TimeType::class, [
                'label' => 'Heure de début',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('endTime', TimeType::class, [
                'label' => 'Heure de fin',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('venue', TextType::class, [
                'label' => 'Lieu',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Théâtre National Daniel Sorano'
                ]
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse complète',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Avenue Léopold Sédar Senghor, Dakar'
                ]
            ])
            ->add('category', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Musique' => 'Musique',
                    'Théâtre' => 'Théâtre',
                    'Danse' => 'Danse',
                    'Conférence' => 'Conférence',
                    'Sport' => 'Sport',
                    'Festival' => 'Festival',
                    'Exposition' => 'Exposition',
                    'Cinéma' => 'Cinéma',
                    'Technologie' => 'Technologie',
                    'Business' => 'Business',
                    'Autre' => 'Autre'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('ticketTypes', CollectionType::class, [
                'entry_type' => EventTicketForm::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Types de billets',
                'attr' => [
                    'class' => 'ticket-types-collection'
                ]
            ])
            ->add('imageUrl', UrlType::class, [
                'label' => 'Image de l\'événement (URL)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://example.com/image.jpg'
                ]
            ])
            ->add('capacity', IntegerType::class, [
                'label' => 'Capacité maximale',
                'attr' => [
                    'class' => 'form-control',
                    'min' => 1,
                    'placeholder' => 'Ex: 500'
                ]
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Brouillon' => 'draft',
                    'Publié' => 'published',
                    'Annulé' => 'cancelled'
                ],
                'attr' => [
                    'class' => 'form-select'
                ]
            ])
            ->add('ticketTypes', CollectionType::class, [
                'entry_type' => EventTicketForm::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'label' => 'Types de billets',
                'attr' => [
                    'class' => 'ticket-types-collection'
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
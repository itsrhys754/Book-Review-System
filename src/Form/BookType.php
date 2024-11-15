<?php

namespace App\Form;

use App\Entity\Book;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('title', TextType::class)
            ->add('author', TextType::class)
            ->add('pages', TextType::class)
            ->add('summary', TextType::class, [
                'required' => false,
            ])
            ->add('genre', ChoiceType::class, [
                'choices' => [
                    'Fiction' => 'Fiction',
                    'Non-Fiction' => 'Non-Fiction',
                    'Mystery' => 'Mystery',
                    'Fantasy' => 'Fantasy',
                    'Biography' => 'Biography',
                ],
                'placeholder' => 'Select a genre',
            ])
            ->add('imageFilename', FileType::class, [
                'label' => 'Book Image (JPEG, PNG)',
                'required' => false,
                'mapped' => false, // This field is not mapped to the entity directly
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}

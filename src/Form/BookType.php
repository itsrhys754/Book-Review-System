<?php

namespace App\Form;

use App\Entity\Book;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BookType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('isbn', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter ISBN-13',
                    'data-google-books-search' => true
                ]
            ])
            ->add('title', TextType::class, [
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('author', TextType::class, [
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('publisher', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('publishedDate', TextType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label' => 'Publication Date'
            ])
            ->add('pages', TextType::class, [
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('summary', TextareaType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5
                ]
            ])
            ->add('genre', ChoiceType::class, [
                'choices' => [
                    'Fiction' => 'Fiction',
                    'Non-Fiction' => 'Non-Fiction',
                    'Classic Literature' => 'Classic Literature',
                    'Mystery' => 'Mystery',
                    'Fantasy' => 'Fantasy',
                    'Biography' => 'Biography',
                    'Science Fiction' => 'Science Fiction',
                    'Romance' => 'Romance',
                    'Thriller' => 'Thriller',
                    'Young Adult' => 'Young Adult',
                    'Children\'s' => 'Children\'s',
                    'Other' => 'Other',
                ],
                'placeholder' => 'Select a genre',
                'required' => true,
                'attr' => ['class' => 'form-control']
            ])
            ->add('imageFilename', FileType::class, [
                'label' => 'Book Image (JPEG, PNG)',
                'required' => false,
                'mapped' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('googleBooksId', HiddenType::class, [
                'required' => false
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Book::class,
        ]);
    }
}

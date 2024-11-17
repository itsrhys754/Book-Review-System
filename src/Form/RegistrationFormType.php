<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class)
            ->add('password', PasswordType::class, [
                'constraints' => [
                    new Length([
                        'min' => 8,
                        'minMessage' => 'Your password must be at least {{ limit }} characters long',
                        
                    ]),
                    new Regex([
                        'pattern' => '/[0-9]/',
                        'message' => 'Your password must contain at least one number',
                    ])
                ],
                'attr' => [
                    'class' => 'password-input',
                ]
            ])
            ->add('confirm_password', PasswordType::class, [
                'mapped' => false,
                'label' => 'Confirm Password',
                'attr' => [
                    'class' => 'password-input',
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please confirm your password.',
                    ]),
                ],
            ])
            ->add('avatar', FileType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Avatar',
                'constraints' => [
                    new Image([
                        'mimeTypes' => ['image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Please upload a valid image format (JPEG or PNG)',
                    ])
                ]
            ])
            ->add('accept_privacy_policy', CheckboxType::class, [
                'mapped' => false,
                'label' => 'I have read and accept the Privacy Policy',
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\IsTrue([
                        'message' => 'You must accept the privacy policy to register.',
                    ]),
                ],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Register',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'validation_groups' => ['Default', 'registration'],
        ]);
    }

    public function validate($object, ExecutionContextInterface $context)
    {
        $form = $context->getRoot();
        $password = $form->get('password')->getData();
        $confirmPassword = $form->get('confirm_password')->getData();

        if ($password !== $confirmPassword) {
            $context->buildViolation('The passwords do not match. Please ensure both fields are identical.')
                ->atPath('confirm_password')
                ->addViolation();
        }
    }
}

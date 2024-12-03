<?php

namespace App\Controller;

use App\Entity\User; 
use App\Form\RegistrationFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface; 
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; 
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use App\Repository\UserRepository;

class RegistrationController extends AbstractController
{
    private UserRepository $userRepository;

    public function __construct(UserRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    // Route to register a new user
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newPassword = $form->get('password')->getData();
            $confirmNewPassword = $form->get('confirm_password')->getData();
            

            try {
                // Handle avatar upload
                $avatarFile = $form->get('avatar')->getData();
                if ($avatarFile) {
                    $newFilename = uniqid().'.'.$avatarFile->guessExtension();
                    try {
                        $avatarFile->move(
                            $this->getParameter('avatars_directory'),
                            $newFilename
                        );
                        $user->setAvatarFilename($newFilename); 
                    } catch (FileException $e) {
                        // Log the specific file upload error
                        error_log('Avatar upload error: ' . $e->getMessage());
                        $this->addFlash('danger', 'An error occurred while uploading your avatar.');
                    }
                }

                // Check if username already exists
                $existingUser = $this->userRepository->findOneBy(['username' => $user->getUsername()]);
                
                if ($existingUser) {
                    $this->addFlash('danger', 'This username is already taken. Please choose another one.');
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                if (!$form->get('accept_privacy_policy')->getData()) {
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                        'error' => 'You must accept the privacy policy to register.'
                    ]);
                }

                if ($newPassword !== $confirmNewPassword) {
                    $this->addFlash('danger', 'Passwords do not match.');
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Hash the password
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                );
                $user->setPassword($hashedPassword);

                // Save the user
                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Registration successful! You can now log in.');
                return $this->redirectToRoute('app_login');

            } catch (\Exception $e) {
                // Log the actual error
                error_log('Registration error: ' . $e->getMessage());
                $this->addFlash('danger', 'An error occurred during registration. Please try again.');
                return $this->render('registration/register.html.twig', [
                    'form' => $form->createView(),
                ]);
            }
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

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

class RegistrationController extends AbstractController
{
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
            // Check if the privacy policy is accepted
            if (!$form->get('accept_privacy_policy')->getData()) {
                return $this->render('registration/register.html.twig', [
                    'form' => $form->createView(),
                    'error' => 'You must accept the privacy policy to register.'
                ]);
            }

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
                        // Handle exception if something happens during file upload
                        $this->addFlash('danger', 'An error occurred while uploading your avatar.');
                    }
                }

                // Check if username already exists
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['username' => $user->getUsername()]);
                
                if ($existingUser) {
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                        $this->addFlash('danger', 'This username is already taken. Please choose another one.')
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
                return $this->render('registration/register.html.twig', [
                    'form' => $form->createView(),
                    $this->addFlash('danger', 'An error occurred during registration. Please try again.')
                ]);
            }
        }

        return $this->render('registration/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

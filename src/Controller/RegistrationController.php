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
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
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
                        error_log('Avatar upload error: ' . $e->getMessage());
                        $this->addFlash('danger', 'An error occurred while uploading your avatar.');
                    }
                }

                // Check if username already exists
                $existingUser = $entityManager->getRepository(User::class)
                    ->findOneBy(['username' => $user->getUsername()]);
                
                if ($existingUser) {
                    $this->addFlash('danger', 'This username is already taken. Please choose another one.');
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Check if email already exists
                $existingEmail = $entityManager->getRepository(User::class)
                    ->findOneBy(['email' => $user->getEmail()]);
                
                if ($existingEmail) {
                    $this->addFlash('danger', 'This email is already registered. Please choose another one.');
                    return $this->render('registration/register.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                // Generate a unique activation token
                $activationToken = bin2hex(random_bytes(16)); // Generates a random token
                $user->setActivationToken($activationToken);

                // Hash the password
                $hashedPassword = $passwordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                );
                $user->setPassword($hashedPassword);

                // Save the user
                $entityManager->persist($user);
                $entityManager->flush();

                // Send activation email
                $activationLink = $this->getParameter('APP_URL') . $this->generateUrl('app_activate', ['token' => $activationToken]);
                $email = (new Email())
                    ->from('noreply@yourdomain.com')
                    ->to($user->getEmail())
                    ->subject('Account Activation')
                    ->html('<p>Click the link below to activate your account:</p><p><a href="' . $activationLink . '">' . $activationLink . '</a></p>');

                $mailer->send($email);

                $this->addFlash('success', 'Registration successful! Please check your email to activate your account.');
                return $this->redirectToRoute('app_login');

            } catch (\Exception $e) {
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

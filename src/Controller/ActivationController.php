<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ActivationController extends AbstractController
{
    #[Route('/activate/{token}', name: 'app_activate')]
    public function activate(string $token, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['activationToken' => $token]);

        if (!$user) {
            return $this->render('activation/error.html.twig', [
                'message' => 'Invalid activation token.',
            ]);
        }

        $user->setIsActive(true);
        $user->setActivationToken(null);
        $entityManager->flush();

        return $this->render('activation/success.html.twig', [
            'message' => 'Your account has been activated successfully!',
        ]);
    }
} 
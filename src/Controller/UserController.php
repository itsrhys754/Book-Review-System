<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\User;
use Rhys\ReviewBundle\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Form\UserEditType;
use App\Repository\UserRepository;

class UserController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $entityManager, UserRepository $userRepository)
    {
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
    }

    #[Route("/profile", name:"app_profile")]
    public function profile(): Response
    {
        $user = $this->getUser();
        // Check if $user is an instance of User
        if (!$user instanceof User) {
            throw new \LogicException('User not found or not authenticated.');
        }
        $reviews = $user->getReviews(); // Fetch the user's reviews
        $books = $user->getBooks(); // Fetch the user's books
        

        return $this->render('profile/index.html.twig', [
            'user' => $user,
            'reviews' => $reviews,
            'books' => $books,
        ]);
    }

    // Route to view a public profile
    #[Route("/profile/{username}", name:"app_public_profile", priority: -1)]
    public function publicProfile(string $username): Response
    {
        // Don't process if this is the main profile route
        if ($username === 'review') {
            throw $this->createNotFoundException('Profile not found');
        }
        
        $user = $this->userRepository->findOneBy(['username' => $username]);
        
        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        // Get approved books and reviews
        $books = $this->entityManager->getRepository(Book::class)
            ->findBy(['user' => $user, 'approved' => true]);
            
        $reviews = array_filter(
            $user->getReviews()->toArray(),
            fn(Review $review) => $review->isApproved()
        );

        $isOwnProfile = $this->getUser() === $user;

        return $this->render('profile/public.html.twig', [
            'profileUser' => $user,
            'reviews' => $reviews,
            'books' => $books,
            'isOwnProfile' => $isOwnProfile,
        ]);
    }
    
    // Route to allow the user to delete their own review
    #[Route("/profile/review/delete/{id}", name:"delete_review")]
    public function deleteReview(
        Review $review, 
        EntityManagerInterface $entityManager
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        // Check if the user is the owner of the review
        if ($review->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot delete this review.');
        }

        $entityManager->remove($review);
        $entityManager->flush();

        $this->addFlash('success', 'Review deleted successfully.');

        return $this->redirectToRoute('app_profile');
    }

    #[Route("/profile/book/delete/{id}", name:"delete_book")]
    public function deleteBook(
        Book $book, 
        EntityManagerInterface $entityManager
    ): \Symfony\Component\HttpFoundation\RedirectResponse {
        // Check if the user is the owner of the book
        if ($book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You cannot delete this book.');
        }

        $entityManager->remove($book);
        $entityManager->flush();

        $this->addFlash('success', 'Book deleted successfully.');

        return $this->redirectToRoute('app_profile');
    }

    // Route to allow the user to edit their profile
    #[Route("/profile/edit", name:"app_profile_edit")]
    public function editProfile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not found.');
        }

        $form = $this->createForm(UserEditType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $currentPassword = $form->get('currentPassword')->getData();
            $newPassword = $form->get('newPassword')->getData();
            $confirmNewPassword = $form->get('confirmNewPassword')->getData();

            // Handle password change if requested
            if ($newPassword) {
                if (!$currentPassword) {
                    $this->addFlash('danger', 'Current password is required to change password.');
                    return $this->render('profile/edit.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $this->addFlash('danger', 'Current password is incorrect.');
                    return $this->render('profile/edit.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                if ($newPassword !== $confirmNewPassword) {
                    $this->addFlash('danger', 'Passwords do not match.');
                    return $this->render('profile/edit.html.twig', [
                        'form' => $form->createView(),
                    ]);
                }

                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Profile updated successfully.');
                return $this->redirectToRoute('app_profile');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while updating your profile.');
            }
        }

        $isOwnProfile = $this->getUser() === $user;

        return $this->render('profile/edit.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    // Route to allow the user to delete a notification
    #[Route("/profile/notification/delete/{index}", name:"delete_notification")]
    public function deleteNotification(Request $request, int $index): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('User not found.');
        }

        $notifications = $user->getNotifications();
        if (isset($notifications[$index])) {
            unset($notifications[$index]);
            $user->setNotifications(array_values($notifications)); 
            $this->entityManager->flush();
            $this->addFlash('success', 'Notification deleted successfully.');
        } else {
            $this->addFlash('error', 'Notification not found.');
        }

        // Get the referer URL or fallback to app_profile
        // This is to ensure that the user is stays on the same page after deleting a notification
        $returnUrl = $request->headers->get('referer', $this->generateUrl('app_profile'));
        
        return $this->redirect($returnUrl);
    }
}

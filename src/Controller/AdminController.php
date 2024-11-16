<?php

namespace App\Controller;

use App\Entity\Book;
use Rhys\ReviewBundle\Entity\Review;
use App\Repository\BookRepository;
use Rhys\ReviewBundle\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AdminController extends AbstractController
{
    private BookRepository $bookRepository;
    private ReviewRepository $reviewRepository;
    private UserRepository $userRepository;
    private TokenStorageInterface $tokenStorage;

    public function __construct(BookRepository $bookRepository, ReviewRepository $reviewRepository, UserRepository $userRepository, TokenStorageInterface $tokenStorage)
    {
        $this->bookRepository = $bookRepository;
        $this->reviewRepository = $reviewRepository;
        $this->userRepository = $userRepository;
        $this->tokenStorage = $tokenStorage;
    }

    #[Route('/admin/books', name: 'admin_books')]
    public function manageBooks(): Response
    {
        // Retrieve pending books that are not yet approved, excluding those owned by the current admin
        $pendingBooks = $this->bookRepository->findPendingBooks($this->getUser());

        return $this->render('admin/manage_books.html.twig', [
            'pendingBooks' => $pendingBooks,
        ]);
    }

    #[Route('/admin/reviews', name: 'admin_reviews')]
    public function manageReviews(): Response
    {
        // Retrieve pending reviews that are not yet approved, excluding those owned by the current admin
        $pendingReviews = $this->reviewRepository->findPendingReviews($this->getUser());

        return $this->render('admin/manage_reviews.html.twig', [
            'pendingReviews' => $pendingReviews,
        ]);
    }

    #[Route('/admin/approve/book/{id}', name: 'admin_approve_book')]
    public function approveBook(Book $book): Response
    {
        // Mark the book as approved
        $book->setApproved(true);
        // Persist the changes to the database
        $this->bookRepository->getEntityManager()->flush();

        // Add a success message to the session flash
        $this->addFlash('success', 'Book has been approved successfully.');

        // Add a notification for the user that their book has been approved
        $user = $book->getUser();
        $user->addNotification('Your book has been approved!');
        // Persist the user entity to save the notification
        $this->userRepository->getEntityManager()->flush();

        // Redirect to the manage books page
        return $this->redirectToRoute('admin_books');
    }

    #[Route('/admin/approve/review/{id}', name: 'admin_approve_review')]
    public function approveReview(Review $review): Response
    {
        // Check if the current admin is the owner of the review
        if ($review->getUser() === $this->getUser()) {
            $this->addFlash('error', 'You cannot approve your own review. Another administrator must review it.');
            return $this->redirectToRoute('admin_reviews');
        }

        // Mark the review as approved
        $review->setApproved(true);
        // Persist the changes to the database
        $this->reviewRepository->getEntityManager()->flush();

        // Add a success message to the session flash for the admin
        $this->addFlash('success', 'Review has been approved successfully.');

        // Notify the user that their review has been approved
        $user = $review->getUser();
        $user->addNotification('Your review has been approved!');
        
        // Persist the user entity to save the notification
        $this->userRepository->getEntityManager()->flush();

        // Redirect to the manage reviews page
        return $this->redirectToRoute('admin_reviews');
    }

    #[Route('/admin/delete/book/{id}', name: 'admin_delete_book')]
    public function deleteBook(Book $book): Response
    {
        // Remove the book from the database
        $this->bookRepository->getEntityManager()->remove($book);
        $this->bookRepository->getEntityManager()->flush();

        // Add a success message to the session flash
        $this->addFlash('success', 'Book deleted successfully.');

        // Notify the user that their book has been deleted
        $user = $book->getUser();
        $user->addNotification('Your book has not been approved.');
        // Persist the user entity to save the notification
        $this->userRepository->getEntityManager()->flush();

        // Redirect to the manage books page
        return $this->redirectToRoute('admin_books');
    }

    #[Route('/admin/delete/review/{id}', name: 'admin_delete_review')]
    public function deleteReview(Review $review): Response
    {
        // Remove the review from the database
        $this->reviewRepository->getEntityManager()->remove($review);
        $this->reviewRepository->getEntityManager()->flush();

        // Add a success message to the session flash
        $this->addFlash('success', 'Review deleted successfully.');

        // Notify the user that their review has been deleted
        $user = $review->getUser();
        $user->addNotification('Your review has not been approved.');
        // Persist the user entity to save the notification
        $this->userRepository->getEntityManager()->flush();

        // Redirect to the manage reviews page
        return $this->redirectToRoute('admin_reviews');
    }

    #[Route('/admin/users', name: 'admin_users')]
    public function manageUsers(Request $request): Response
    {
        // Get the search term from the request query parameters
        $searchTerm = $request->query->get('search', '');
        
        // Search for users by username using the UserRepository
        $users = $this->userRepository->searchByUsername($searchTerm);

        // Render the manage users template with the list of users and the search term
        return $this->render('admin/manage_users.html.twig', [
            'users' => $users,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/admin/delete/user/{id}', name: 'admin_delete_user')]
    public function deleteUser(User $user, Request $request): Response
    {
        // Check if the user to be deleted is an admin or moderator
        if ((in_array('ROLE_ADMIN', $user->getRoles()) || in_array('ROLE_MODERATOR', $user->getRoles())) && !$this->isGranted('ROLE_ADMIN')) {
            // Add an error message if the current admin is not allowed to delete this user
            $this->addFlash('error', 'You cannot delete an admin or moderator user. Only admins can do this.');
            return $this->redirectToRoute('admin_users');
        }
    
        // Store whether this is the current user before deletion
        $isCurrentUser = ($user === $this->getUser());
        
        // Get the entity manager
        $em = $this->bookRepository->getEntityManager();
        
        try {
            // Begin transaction
            $em->beginTransaction();
            
            // Remove the user from the database
            $em->remove($user);
            $em->flush();
            
            // If successful, commit transaction
            $em->commit();
            
            if ($isCurrentUser) {
                // Clear security context before any template rendering
                $this->tokenStorage->setToken(null);
                
                // Clear the user from the session
                $request->getSession()->invalidate(); // Invalidate the session
                
                // Perform a clean redirect response to the login page
                $response = new Response('', Response::HTTP_FOUND);
                $response->headers->set('Location', $this->generateUrl('app_login'));
                return $response;
            }
    
            // Add a success message to the session flash
            $this->addFlash('success', 'User deleted successfully.');
            return $this->redirectToRoute('admin_users');
            
        } catch (\Exception $e) {
            // If there's an error, rollback the transaction
            $em->rollback();
            // Add an error message to the session flash
            $this->addFlash('error', 'An error occurred while deleting the user.');
            return $this->redirectToRoute('admin_users');
        }
    }

    #[Route('/admin/make-mod/{id}', name: 'admin_make_user_mod')]
    public function makeUserMod(User $user): Response
    {
        // Check if the user is already a moderator
        if (in_array('ROLE_MODERATOR', $user->getRoles())) {
            // Add an error message if the user is already a moderator
            $this->addFlash('error', 'User is already a moderator.');
            return $this->redirectToRoute('admin_users');
        }

        // Add moderator role to the user
        $user->setRoles(array_unique(array_merge($user->getRoles(), ['ROLE_MODERATOR'])));
        // Persist the changes to the database
        $this->userRepository->getEntityManager()->flush();

        // Add a success message to the session flash
        $this->addFlash('success', 'User has been promoted to moderator successfully.');

        // Notify the user that they have been promoted to moderator
        $user->addNotification('You have been promoted to moderator!');
        // Persist the user entity to save the notification
        $this->userRepository->getEntityManager()->flush();

        // Redirect to the manage users page
        return $this->redirectToRoute('admin_users');
    }
}

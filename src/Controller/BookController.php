<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\BookType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\BookRepository;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookController extends AbstractController
{
    #[Route('/books', name: 'app_books')]
    public function index(BookRepository $bookRepository): Response
    {
        // Fetches all books to then be grouped by genre
        $booksByGenre = $bookRepository->findAll(); 

        // Group books by genre
        $groupedBooks = [];
        foreach ($booksByGenre as $book) {
            $groupedBooks[$book->getGenre()][] = $book;
        }

        return $this->render('book/index.html.twig', [
            'groupedBooks' => $groupedBooks,
        ]);
    }

    #[Route('/books/new', name: 'app_book_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Set the current user as the book's owner
                $book->setUser($this->getUser());
                
                // Handle file upload if there's an image
                $imageFile = $form->get('imageFilename')->getData();
                if ($imageFile) {
                    $newFilename = uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('book_images_directory'),
                            $newFilename
                        );
                        $book->setImageFilename($newFilename);
                    } catch (\Exception $e) {
                        // If image upload fails, continue without image
                        $book->setImageFilename(null);
                    }
                }

                $book->setApproved(false);
                $entityManager->persist($book);
                $entityManager->flush();

                $this->addFlash(
                    'success',
                    'Your book has been submitted successfully and is pending approval.'
                );

                return $this->redirectToRoute('app_books');
            } catch (\Exception $e) {
                // Log the actual error
                error_log($e->getMessage());
                
                $this->addFlash(
                    'error',
                    'Error saving book: ' . $e->getMessage()
                );
            }
        }

        return $this->render('book/form.html.twig', [
            'form' => $form->createView(),
            'editing' => false,
        ]);
    }

    #[Route('/books/{id}', name: 'app_book_show')]
    public function show(Book $book): Response
    {
        return $this->render('book/show.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/books/{id}/edit', name: 'app_book_edit')]
    public function edit(
        Request $request, 
        EntityManagerInterface $entityManager,
        int $id
    ): Response {
        // Manually fetch the book
        $book = $entityManager->getRepository(Book::class)->find($id);
        
        if (!$book) {
            throw new NotFoundHttpException('Book not found');
        }

        // Check if the current user is the owner of the book
        if ($book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only edit your own books.');
        }

        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Handle file upload if there's a new image
                $imageFile = $form->get('imageFilename')->getData();
                if ($imageFile) {
                    $newFilename = uniqid().'.'.$imageFile->guessExtension();
                    try {
                        $imageFile->move(
                            $this->getParameter('book_images_directory'),
                            $newFilename
                        );
                        // Delete old image if it exists
                        if ($book->getImageFilename()) {
                            $oldFilePath = $this->getParameter('book_images_directory').'/'.$book->getImageFilename();
                            if (file_exists($oldFilePath)) {
                                unlink($oldFilePath);
                            }
                        }
                        $book->setImageFilename($newFilename);
                    } catch (\Exception $e) {
                        // If image upload fails, keep existing image
                    }
                }

                // Reset approval status when edited
                $book->setApproved(false);
                $entityManager->flush();

                $this->addFlash(
                    'success',
                    'Your book has been updated and is pending approval.'
                );

                return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
            } catch (\Exception $e) {
                $this->addFlash(
                    'error',
                    'Error updating book: ' . $e->getMessage()
                );
            }
        }

        return $this->render('book/form.html.twig', [
            'form' => $form->createView(),
            'book' => $book,
            'editing' => true
        ]);
    }

    #[Route('/books/genre/{genre}', name: 'app_books_by_genre')]
    public function booksByGenre(string $genre, BookRepository $bookRepository): Response
    {
        $books = $bookRepository->findBy(['genre' => $genre, 'approved' => true]);

        return $this->render('book/genre.html.twig', [
            'genre' => $genre,
            'books' => $books,
        ]);
    }
}
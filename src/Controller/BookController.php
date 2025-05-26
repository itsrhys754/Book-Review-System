<?php

namespace App\Controller;

use App\Entity\Book;
use App\Form\BookType;
use App\Service\BookService;
use App\Service\GoogleBooksApiService;
use App\Service\NYTimesApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\BookRepository;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Psr\Log\LoggerInterface;

class BookController extends AbstractController
{
    private BookRepository $bookRepository;
    private GoogleBooksApiService $googleBooksApi;
    private NYTimesApiService $nyTimesApiService;
    private LoggerInterface $logger;
    private BookService $bookService;

    public function __construct(
        BookRepository $bookRepository,
        GoogleBooksApiService $googleBooksApi,
        NYTimesApiService $nyTimesApiService,
        LoggerInterface $logger,
        BookService $bookService
    ) {
        $this->bookRepository = $bookRepository;
        $this->googleBooksApi = $googleBooksApi;
        $this->nyTimesApiService = $nyTimesApiService;
        $this->logger = $logger;
        $this->bookService = $bookService;
    }

    // Route to view all books
    #[Route('/books', name: 'app_books')]
    public function index(): Response
    {
        // Fetches all books to then be grouped by genre
        $booksByGenre = $this->bookRepository->findAll(); 

        // Group books by genre
        $groupedBooks = [];

        foreach ($booksByGenre as $book) {
            $genre = $book->getGenre();
            $groupedBooks[$genre][] = $book;
        }

        return $this->render('book/index.html.twig', [
            'groupedBooks' => $groupedBooks,
        ]);
    }

    // Route to create a new book
    #[Route('/books/new', name: 'app_book_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $searchResults = [];
        $session = $request->getSession();
        
        // Handle Google Books search using the same logic as the search page
        $query = $request->query->get('q');
        $searchType = $request->query->get('type', '');
        $page = max(1, (int)$request->query->get('page', 1));
        $maxResults = max(1, min(40, (int)$request->query->get('per_page', 10)));

        // Handle direct Google Books ID
        $googleBooksId = $request->query->get('book_id');
        
        // Only search for books if no book_id is provided and there's a query
        if (!$googleBooksId && $query) {
            $googleBooks = $this->googleBooksApi->searchBooks($query, $searchType, $page, $maxResults);
            if (!isset($googleBooks['error']) && isset($googleBooks['items'])) {
                $searchResults = $googleBooks;
            }
        }
        
        if ($googleBooksId) {
            // Check if book already exists by Google Books ID
            $existingBook = $this->bookRepository->findOneBy(['googleBooksId' => $googleBooksId]);
            if ($existingBook) {
                $this->addFlash('warning', 'This book is already in the library.');
                return $this->redirectToRoute('app_book_show', ['id' => $existingBook->getId()]);
            }

            // Fetch book details from Google Books API
            $bookDetails = $this->googleBooksApi->getBookDetails($googleBooksId);
            if ($bookDetails && !isset($bookDetails['error'])) {
                $volumeInfo = $bookDetails['volumeInfo'];
                
                // Check if book exists by ISBN
                $isbn = null;
                if (isset($volumeInfo['industryIdentifiers'])) {
                    foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                        if ($identifier['type'] === 'ISBN_13') {
                            $isbn = $identifier['identifier'];
                            break;
                        }
                    }
                }
                
                if ($isbn) {
                    $existingBookByIsbn = $this->bookRepository->findOneBy(['isbn' => $isbn]);
                    if ($existingBookByIsbn) {
                        $this->addFlash('warning', 'A book with this ISBN already exists in the library.');
                        return $this->redirectToRoute('app_book_show', ['id' => $existingBookByIsbn->getId()]);
                    }
                }
                
                // Check if book exists by title
                if (isset($volumeInfo['title'])) {
                    $title = $volumeInfo['title'];
                    $existingBookByTitle = $this->bookRepository->findOneBy(['title' => $title]);
                    if ($existingBookByTitle) {
                        $this->addFlash('warning', 'A book with this title already exists in the library.');
                        return $this->redirectToRoute('app_book_show', ['id' => $existingBookByTitle->getId()]);
                    }
                }
                
                // Pre-fill the book data
                $book->setTitle($volumeInfo['title'] ?? '');
                $book->setAuthor($volumeInfo['authors'][0] ?? '');
                $book->setPages((int)($volumeInfo['pageCount'] ?? 0));
                // Clean the description before setting it
                $description = $volumeInfo['description'] ?? '';
                $cleanDescription = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $book->setSummary($cleanDescription);
                $book->setGenre($this->mapGenre($volumeInfo['categories'][0] ?? null));
                $book->setGoogleBooksId($googleBooksId);
                $book->setPublisher($volumeInfo['publisher'] ?? null);
                $book->setPublishedDate($volumeInfo['publishedDate'] ?? null);
                
                // Handle ISBN
                if ($isbn) {
                    $book->setIsbn($isbn);
                }
                
                // Handle image
                if (isset($volumeInfo['imageLinks']['thumbnail'])) {
                    $imageUrl = $volumeInfo['imageLinks']['thumbnail'];
                    $uploadDir = $this->getParameter('book_images_directory');
                    $imageFilename = $this->googleBooksApi->downloadImage($imageUrl, $uploadDir);
                    if ($imageFilename) {
                        $book->setImageFilename($imageFilename);
                        $session->set('temp_book_image', $imageFilename);
                    }
                }
                
                // Create a new form with pre-filled data
                $form = $this->createForm(BookType::class, $book);
            }
        }
        
        // Handle form submission
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                // Set the current user as the book's owner
                $book->setUser($this->getUser());
                
                // Handle file upload if there's a new image
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
                        error_log('Error uploading image: ' . $e->getMessage());
                    }
                } else {
                    // If no new image was uploaded, use the one from Google Books if available
                    $tempImage = $session->get('temp_book_image');
                    if ($tempImage) {
                        $book->setImageFilename($tempImage);
                    }
                }

                // Set approved status to false for admin review
                $book->setApproved(false);

                $entityManager->persist($book);
                $entityManager->flush();

                // Clear temporary data from session
                $session->remove('temp_book_image');

                $this->addFlash('success', 'Book added successfully! It will be visible after admin approval.');
                return $this->redirectToRoute('app_books');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while saving the book: ' . $e->getMessage());
            }
        } elseif ($form->isSubmitted()) {
            $this->addFlash('error', 'Please check the form for errors.');
        }

        return $this->render('book/form.html.twig', [
            'form' => $form->createView(),
            'editing' => false,
            'searchResults' => $searchResults
        ]);
    }

    // Route to view books by genre
    #[Route('/books/genre/{genre}', name: 'app_books_by_genre')]
    public function booksByGenre(string $genre): Response
    {
        $decodedGenre = urldecode($genre);
        $books = $this->bookRepository->findBy(['genre' => $decodedGenre, 'approved' => true]);

        // Fetch books from Google Books API for this genre
        $googleBooksResult = $this->googleBooksApi->searchBooks('subject:' . $decodedGenre);
        $googleBooks = [];
        
        if (!isset($googleBooksResult['error']) && isset($googleBooksResult['items'])) {
            $googleBooks = array_map(function($item) {
                return [
                    'id' => $item['id'],
                    'title' => $item['volumeInfo']['title'] ?? 'Unknown Title',
                    'authors' => $item['volumeInfo']['authors'] ?? ['Unknown Author'],
                    'thumbnail' => $item['volumeInfo']['imageLinks']['thumbnail'] ?? null,
                    'description' => $item['volumeInfo']['description'] ?? null,
                    'previewLink' => $item['volumeInfo']['previewLink'] ?? null
                ];
            }, array_slice($googleBooksResult['items'], 0, 12)); // Limit to 12 books
        }

        return $this->render('book/genre.html.twig', [
            'books' => $books,
            'googleBooks' => $googleBooks,
            'genre' => $decodedGenre
        ]);
    }

    // Route to show a book's details
    #[Route('/books/{id}', name: 'app_book_show', requirements: ['id' => '[^/]+'])]
    public function show(string $id): Response
    {
        try {
            $bookData = $this->bookService->getBookDetails(id: $id);
            $nytReviews = $this->bookService->getNYTimesReviews($bookData['book'] ?? $bookData);

            $this->logger->info('Final data being passed to template:', [
                'book' => $bookData,
                'nytReviews' => $nytReviews
            ]);

            return $this->render('book/show.html.twig', [
                'book' => $bookData['book'] ?? $bookData,
                'isGoogleBook' => $bookData['isGoogleBook'],
                'existingBook' => $bookData['existingBook'],
                'nytReviews' => $nytReviews
            ]);
        } catch (NotFoundHttpException $e) {
            throw $this->createNotFoundException($e->getMessage());
        }
    }

    // Route to edit a book
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

    // Route to delete a book
    #[Route('/books/{id}/delete', name: 'app_book_delete')]
    public function delete(
        Book $book,
        EntityManagerInterface $entityManager
    ): Response {
        // Check if the current user is the owner of the book
        if ($book->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only delete your own books.');
        }

        try {
            // Delete the book's image if it exists
            if ($book->getImageFilename()) {
                $imagePath = $this->getParameter('book_images_directory').'/'.$book->getImageFilename();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $entityManager->remove($book);
            $entityManager->flush();

            $this->addFlash('success', 'Book was successfully deleted.');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error deleting book: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_books');
    }

    /**
     * Map Google Books category to our genre
     */
    private function mapGenre(?string $googleCategory): string
    {
        if (!$googleCategory) {
            return 'Other';
        }

        $category = strtolower($googleCategory);
        
        $genreMap = [
            'fiction' => 'Fiction',
            'nonfiction' => 'Non-Fiction',
            'classic' => 'Classic Literature',
            'mystery' => 'Mystery',
            'fantasy' => 'Fantasy',
            'biography' => 'Biography',
            'science fiction' => 'Science Fiction',
            'romance' => 'Romance',
            'thriller' => 'Thriller',
            'young adult' => 'Young Adult',
            'children' => 'Children\'s'
        ];

        foreach ($genreMap as $key => $value) {
            if (str_contains($category, $key)) {
                return $value;
            }
        }

        return 'Other';
    }
}
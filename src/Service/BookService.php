<?php

namespace App\Service;

use App\Entity\Book;
use App\Repository\BookRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class BookService
{
    public function __construct(
        private readonly BookRepository $bookRepository,
        private readonly GoogleBooksApiService $googleBooksApi,
        private readonly NYTimesApiService $nyTimesApiService,
        private readonly LoggerInterface $logger
    ) {}

    public function getBookDetails(string $id): array
    {
        if ($this->isGoogleBooksId($id)) {
            return $this->getGoogleBookDetails($id);
        }
        return $this->getLocalBookDetails($id);
    }

    private function isGoogleBooksId(string $id): bool
    {
        return preg_match('/[a-zA-Z]/', $id);
    }

    private function getGoogleBookDetails(string $id): array
    {
        // Check if this Google Book is already in our database
        $existingBook = $this->bookRepository->findOneBy(['googleBooksId' => $id]);
        
        // Fetch book details from Google Books API
        $bookDetails = $this->googleBooksApi->getBookDetails($id);
        
        if (!$bookDetails || isset($bookDetails['error'])) {
            throw new NotFoundHttpException('Book not found on Google Books.');
        }

        // Format the book data for our application
        return [
            'title' => $bookDetails['title'],
            'authors' => $bookDetails['authors'],
            'description' => $bookDetails['description'],
            'categories' => $bookDetails['categories'],
            'pageCount' => $bookDetails['pageCount'],
            'publisher' => $bookDetails['publisher'],
            'publishedDate' => $bookDetails['publishedDate'],
            'thumbnail' => $bookDetails['imageLinks']['thumbnail'] ?? null,
            'googleBooksId' => $id,
            'isbn' => null,
            'averageRating' => $bookDetails['averageRating'] ?? null,
            'ratingsCount' => $bookDetails['ratingsCount'] ?? 0,
            'isGoogleBook' => true,
            'existingBook' => $existingBook
        ];
    }

    private function getLocalBookDetails(string $id): array
    {
        $book = $this->bookRepository->find($id);

        if (!$book) {
            throw new NotFoundHttpException('Book not found');
        }

        // If the book has a Google Books ID, fetch additional data
        $googleBooksId = $book->getGoogleBooksId();
        if ($googleBooksId) {
            $googleData = $this->googleBooksApi->getBookDetails($googleBooksId);
            if ($googleData && !isset($googleData['error'])) {
                $book->setGoogleBooksData($googleData['volumeInfo']);
            }
        }

        return [
            'book' => $book,
            'isGoogleBook' => false,
            'existingBook' => null
        ];
    }

    public function getNYTimesReviews($book): ?array
    {
        $isbn = null;
        $title = null;

        if ($book instanceof Book) {
            $isbn = $book->getIsbn();
            $title = $book->getTitle();
        } else {
            $isbn = $book['isbn'] ?? null;
            $title = $book['title'] ?? null;
        }
        $this->logger->info('Attempting to fetch NYTimes reviews', [
            'isbn' => $isbn,
            'title' => $title
        ]);

        $nytReviews = $this->nyTimesApiService->getBookReviews($isbn, $title);

        if ($nytReviews === null) {
            $this->logger->info('Error fetching NYTimes reviews');
        } else if (empty($nytReviews)) {
            $this->logger->info('No NYTimes reviews found', [
                'isbn' => $isbn,
                'title' => $title
            ]);
        } else {
            $this->logger->info('Found NYTimes reviews', [
                'isbn' => $isbn,
                'title' => $title,
                'review_count' => count($nytReviews)
            ]);
        }
        return $nytReviews;
    }
}

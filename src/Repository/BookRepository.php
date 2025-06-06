<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * Get books grouped by genre.
     * This method retrieves all books and groups them by their genre.
     * It returns an array of books for each genre.
     */
    public function findBooksByGenre(): array
    {
        return $this->createQueryBuilder('b')
            ->select('b.genre, b')
            ->groupBy('b.genre')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieve all distinct genres of approved books.
     * This method returns an array of unique genres for books that are approved
     * and not null, sorted in ascending order.
     */
    public function findAllGenres(): array
    {
        $result = $this->createQueryBuilder('b')
            ->select('DISTINCT b.genre')
            ->where('b.approved = true')
            ->andWhere('b.genre IS NOT NULL')
            ->orderBy('b.genre', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_filter($result, function($genre) {
            return !empty($genre);
        });
    }

    /**
     * Find pending books that are not yet approved.
     * Optionally, exclude books submitted by a specific user.
     * This method returns an array of books that are pending approval.
     */
    public function findPendingBooks(?User $excludeUser = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.approved = :approved')
            ->setParameter('approved', false);
        
        if ($excludeUser) {
            $qb->andWhere('b.user != :user')
               ->setParameter('user', $excludeUser);
        }
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Search for books based on various criteria.
     * This method allows searching by title, author, or summary,
     * filtering by genres, page count, and minimum rating.
     * It returns an array of books that match the search criteria.
     */
    public function searchBooks(
        string $query,
        array $genres = [],
        ?string $pages = null,
        ?string $rating = null
    ): array {
        $qb = $this->createQueryBuilder('book')
            ->where('book.approved = true')
            ->andWhere('book.title LIKE :query OR book.author LIKE :query OR book.summary LIKE :query')
            ->setParameter('query', '%' . $query . '%');
    
        if (!empty($genres)) {
            $qb->andWhere('book.genre IN (:genres)')
                ->setParameter('genres', $genres);
        }
    
        if ($pages) {
            switch ($pages) {
                case '0-200':
                    $qb->andWhere('book.pages <= 200');
                    break;
                case '200-400':
                    $qb->andWhere('book.pages > 200 AND book.pages <= 400');
                    break;
                case '400+':
                    $qb->andWhere('book.pages > 400');
                    break;
            }
        }
    
        if ($rating) {
            $minRating = (int)$rating[0];
            $qb->leftJoin('book.reviews', 'r')
                ->andWhere('r.approved = true')
                ->groupBy('book.id')
                ->having('AVG(r.rating) >= :minRating')
                ->setParameter('minRating', $minRating);
        }
    
        return $qb->getQuery()->getResult();
    }

    /**
     * Search for books based on various criteria.
     * This method allows searching by title, author, or summary,
     * filtering by genres, page count, and rating.
     * It returns an array of books that match the search criteria.
     */
    public function search(?string $query, array $selectedGenres = [], ?string $selectedPages = null, ?string $selectedRating = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.approved = true');

        // Apply search query if provided
        if ($query) {
            $qb->andWhere('b.title LIKE :query OR b.author LIKE :query OR b.summary LIKE :query')
               ->setParameter('query', '%' . $query . '%');
        }

        // Filter by selected genres
        if (!empty($selectedGenres)) {
            $qb->andWhere('b.genre IN (:genres)')
               ->setParameter('genres', $selectedGenres);
        }

        // Filter by page range
        if ($selectedPages) {
            switch ($selectedPages) {
                case '0-200':
                    $qb->andWhere('b.pages <= 200');
                    break;
                case '200-400':
                    $qb->andWhere('b.pages > 200 AND b.pages <= 400');
                    break;
                case '400+':
                    $qb->andWhere('b.pages > 400');
                    break;
            }
        }

        // Filter by rating
        if ($selectedRating) {
            if ($selectedRating === '10') {
                $qb->andWhere('b.rating = 10');
            } else {
                $rating = str_replace('+', '', $selectedRating);
                $qb->andWhere('b.rating >= :rating')
                   ->setParameter('rating', $rating);
            }
        }

        return $qb->orderBy('b.title', 'ASC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * Get all Google Books IDs from the database
     * This method returns an array of Google Books IDs that are already in our database
     */
    public function findGoogleBooksIds(): array
    {
        $result = $this->createQueryBuilder('b')
            ->select('b.googleBooksId')
            ->where('b.googleBooksId IS NOT NULL')
            ->getQuery()
            ->getResult();
            
        // Convert from array of arrays to flat array of IDs
        return array_map(function($item) {
            return $item['googleBooksId'];
        }, $result);
    }

    /**
     * Get all existing book identifiers (Google Books IDs and ISBNs) from the database
     * This method returns an array containing two arrays: googleBooksIds and isbns
     */
    public function findExistingBooksIdentifiers(): array
    {
        $result = $this->createQueryBuilder('b')
            ->select('b.googleBooksId, b.isbn')
            ->where('b.googleBooksId IS NOT NULL OR b.isbn IS NOT NULL')
            ->getQuery()
            ->getResult();
            
        $googleBooksIds = [];
        $isbns = [];
        
        foreach ($result as $item) {
            if ($item['googleBooksId']) {
                $googleBooksIds[] = $item['googleBooksId'];
            }
            if ($item['isbn']) {
                $isbns[] = $item['isbn'];
            }
        }
        
        return [
            'googleBooksIds' => array_filter(array_unique($googleBooksIds)),
            'isbns' => array_filter(array_unique($isbns))
        ];
    }
}

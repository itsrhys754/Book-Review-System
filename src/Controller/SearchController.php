<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Service\GoogleBooksApiService;
use App\Service\NYTimesApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private BookRepository $bookRepository,
        private GoogleBooksApiService $googleBooksApiService,
        private NYTimesApiService $nyTimesApiService
    ) {}

    // Route to search for books
    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        // Get the query parameters from the request
        $query = $request->query->get('query');
        $selectedGenres = $request->query->all('genres');
        $selectedPages = $request->query->get('pages');
        $selectedRating = $request->query->get('rating');
        $searchType = $request->query->get('searchType', ''); // Add search type parameter

        // Search for local books based on the query parameters
        $localBooks = $this->bookRepository->searchBooks(
            $query,
            $selectedGenres,
            $selectedPages,
            $selectedRating
        );

        // Search Google Books API if there's a query
        $googleBooks = [];
        if ($query) {
            // Prepare filters
            $filters = [];
            if ($selectedPages) {
                $filters['pages'] = $selectedPages;
            }

            $googleBooksResult = $this->googleBooksApiService->searchBooks(
                $query, 
                $searchType,
                1, // page
                10, // maxResults
                $filters
            );
            if (!isset($googleBooksResult['error']) && isset($googleBooksResult['items'])) {
                // Get all Google Books IDs and ISBNs that are already in the database
                $existingBooks = $this->bookRepository->findExistingBooksIdentifiers();
                
                // Filter out books that are already in the database (by Google Books ID or ISBN)
                $filteredItems = array_filter($googleBooksResult['items'], function($item) use ($existingBooks) {
                    $volumeInfo = $item['volumeInfo'];
                    
                    // Check Google Books ID
                    if (in_array($item['id'], $existingBooks['googleBooksIds'], true)) {
                        return false;
                    }
                    
                    // Check ISBN
                    if (isset($volumeInfo['industryIdentifiers'])) {
                        foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                            if (($identifier['type'] === 'ISBN_13' || $identifier['type'] === 'ISBN_10') 
                                && in_array($identifier['identifier'], $existingBooks['isbns'], true)) {
                                return false;
                            }
                        }
                    }

                    // Check title
                    if (isset($volumeInfo['title'])) {
                        $title = $volumeInfo['title'];
                        $existingBookByTitle = $this->bookRepository->findOneBy(['title' => $title]);
                        if ($existingBookByTitle) {
                            return false;
                        }
                    }
                    
                    return true;
                });

                $googleBooks = array_values(array_map(function($item) {
                    $volumeInfo = $item['volumeInfo'];
                    
                    // Extract genre from categories
                    $genre = null;
                    if (isset($volumeInfo['categories']) && !empty($volumeInfo['categories'])) {
                        $category = $volumeInfo['categories'][0];
                        // Take the first part if there are slashes or commas
                        if (str_contains($category, '/')) {
                            $genre = trim(explode('/', $category)[0]);
                        } elseif (str_contains($category, ',')) {
                            $genre = trim(explode(',', $category)[0]);
                        } else {
                            $genre = trim($category);
                        }
                    }

                    // Extract ISBN (prefer ISBN-13, fallback to ISBN-10)
                    $isbn = null;
                    if (isset($volumeInfo['industryIdentifiers'])) {
                        foreach ($volumeInfo['industryIdentifiers'] as $identifier) {
                            if ($identifier['type'] === 'ISBN_13') {
                                $isbn = $identifier['identifier'];
                                break;
                            } elseif ($identifier['type'] === 'ISBN_10' && !$isbn) {
                                $isbn = $identifier['identifier'];
                            }
                        }
                    }

                    return [
                        'id' => $item['id'],
                        'googleId' => $item['id'], // Add explicit googleId for the template
                        'title' => $volumeInfo['title'] ?? 'Unknown Title',
                        'author' => isset($volumeInfo['authors']) ? $volumeInfo['authors'][0] : 'Unknown Author',
                        'thumbnail' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
                        'summary' => $volumeInfo['description'] ?? null,
                        'previewLink' => $volumeInfo['previewLink'] ?? null,
                        'genre' => $genre,
                        'pages' => $volumeInfo['pageCount'] ?? null,
                        'isbn' => $isbn,
                        'publisher' => $volumeInfo['publisher'] ?? null,
                        'publishedDate' => $volumeInfo['publishedDate'] ?? null
                    ];
                }, $filteredItems));
            }
        }

        return $this->render('book/search.html.twig', [
            'localBooks' => $localBooks,
            'googleBooks' => $googleBooks,
            'query' => $query,
            'genres' => $this->bookRepository->findAllGenres(),
            'selectedGenres' => $selectedGenres,
            'selectedPages' => $selectedPages,
            'selectedRating' => $selectedRating,
            'searchType' => $searchType,
        ]);
    }
}

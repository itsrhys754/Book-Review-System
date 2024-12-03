<?php

namespace App\Controller;

use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function __construct(
        private BookRepository $bookRepository
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

        // Search for books based on the query parameters
        $books = $this->bookRepository->searchBooks(
            $query,
            $selectedGenres,
            $selectedPages,
            $selectedRating
        );

        return $this->render('book/search.html.twig', [
            'books' => $books,
            'query' => $query,
            'genres' => $this->bookRepository->findAllGenres(),
            'selectedGenres' => $selectedGenres,
            'selectedPages' => $selectedPages,
            'selectedRating' => $selectedRating,
        ]);
    }
}

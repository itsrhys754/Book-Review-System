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

    #[Route('/search', name: 'app_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('query');
        $selectedGenres = $request->query->all('genres');
        $selectedPages = $request->query->get('pages');
        $selectedRating = $request->query->get('rating');

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

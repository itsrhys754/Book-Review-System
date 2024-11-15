<?php

namespace App\Twig;

use App\Repository\BookRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private BookRepository $bookRepository
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_genres', [$this, 'getGenres']),
        ];
    }

    public function getGenres(): array
    {
        $genres = $this->bookRepository->findAllGenres();
        return array_filter($genres, function($genre) {
            return !empty($genre) && is_string($genre);
        });
    }
} 
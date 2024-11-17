<?php

namespace App\DataFixtures;

use App\Entity\Book;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BookFixtures extends Fixture implements DependentFixtureInterface
{
    public const BOOK_REFERENCE_PREFIX = 'book_';

    private const BOOKS = [
        [
            'title' => 'The Great Gatsby',
            'author' => 'F. Scott Fitzgerald',
            'pages' => 180,
            'summary' => 'A story of decadence and excess follows a cast of characters living in the fictional town of West Egg in the summer of 1922.',
            'genre' => 'Classic Literature',
            'approved' => true,
        ],
        [
            'title' => '1984',
            'author' => 'George Orwell',
            'pages' => 328,
            'summary' => 'A dystopian social science fiction novel following Winston Smith in a totalitarian future society.',
            'genre' => 'Science Fiction',
            'approved' => true,
        ],
        [
            'title' => 'Pride and Prejudice',
            'author' => 'Jane Austen',
            'pages' => 432,
            'summary' => 'The story follows the main character Elizabeth Bennet as she deals with issues of manners, upbringing, morality, education, and marriage.',
            'genre' => 'Romance',
            'approved' => true,
        ],
        [
            'title' => 'To Kill a Mockingbird',
            'author' => 'Harper Lee',
            'pages' => 281,
            'summary' => 'The story of racial injustice and the loss of innocence in the American South, told through the eyes of Scout Finch.',
            'genre' => 'Classic Literature',
            'approved' => false,
        ],
        [
            'title' => 'The Hobbit',
            'author' => 'J.R.R. Tolkien',
            'pages' => 310,
            'summary' => 'The tale of Bilbo Baggins, who embarks on a quest to help a group of dwarves reclaim their mountain home from a dragon.',
            'genre' => 'Fantasy',
            'approved' => true,
        ],
    ];

    public function load(ObjectManager $manager): void
    {
        foreach (self::BOOKS as $index => $bookData) {
            $book = new Book();
            $book->setTitle($bookData['title'])
                ->setAuthor($bookData['author'])
                ->setPages($bookData['pages'])
                ->setSummary($bookData['summary'])
                ->setGenre($bookData['genre'])
                ->setApproved($bookData['approved'])
                // Distribute books among users
                ->setUser($this->getReference(UserFixtures::USER_REFERENCE_PREFIX . ($index % 3))); // set to first 3 users

            $manager->persist($book);
            
            // Store reference for potential Review fixtures
            $this->addReference(self::BOOK_REFERENCE_PREFIX . $index, $book);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}
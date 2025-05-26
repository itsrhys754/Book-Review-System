<?php

namespace App\Controller;

use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use phpDocumentor\Reflection\DocBlock\Description;
use phpDocumentor\Reflection\Type;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Rhys\ReviewBundle\Repository\ReviewRepository;
use Rhys\ReviewBundle\Entity\Review;
use App\Entity\Book;
use App\Entity\User;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\BookRepository;
use App\Repository\UserRepository;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use App\Service\GoogleBooksApiService;
use App\Service\NYTimesApiService;
use Psr\Log\LoggerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Attribute\IsGranted;

class ApiController extends AbstractFOSRestController
{
    private $reviewRepository;
    private $bookRepository;
    private $userRepository;
    private $serializer;
    private $nyTimesApiService;
    private $logger;

    public function __construct(
        ReviewRepository $reviewRepository, 
        BookRepository $bookRepository, 
        UserRepository $userRepository, 
        SerializerInterface $serializer,
        GoogleBooksApiService $googleBooksApi,
        NYTimesApiService $nyTimesApiService,
        LoggerInterface $logger
    ) {
        $this->reviewRepository = $reviewRepository;
        $this->bookRepository = $bookRepository;
        $this->userRepository = $userRepository;
        $this->serializer = $serializer;
        $this->nyTimesApiService = $nyTimesApiService;
        $this->logger = $logger;
    }

    /**
     * Get all reviews
     *
     */
    #[Route('/api/v1/reviews', name: 'reviews', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Success",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'page', type: 'integer', example: 1),
                new OA\Property(property: 'limit', type: 'integer', example: 10),
                new OA\Property(property: 'total_reviews', type: 'integer', example: 100),
                new OA\Property(property: 'total_pages', type: 'integer', example: 10),
                new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid request",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid request')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Server error')
            ]
        )
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Page number for pagination',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of items per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    public function getReviews(Request $request): JsonResponse
    {

    $page = max(1, (int) $request->query->get('page', 1));
    $limit = max(1, (int) $request->query->get('limit', 10));

    $offset = ($page - 1) * $limit;
    $totalReviews = $this->reviewRepository->count([]);
    $reviews = $this->reviewRepository->findBy([], ['createdAt' => 'DESC'], $limit, $offset);

    $jsonContent = $this->serializer->serialize(
        $reviews,
        'json',
        SerializationContext::create()->setGroups(['review:list'])
    );

    return new JsonResponse([
        'page' => $page,
        'limit' => $limit,
        'total_reviews' => $totalReviews,
        'total_pages' => ceil($totalReviews / $limit),
        'reviews' => json_decode($jsonContent, true),
    ]);
}


    /**
     * Get a specific review by ID
     */
    #[Route('/api/v1/reviews/{id}', name: 'get_review', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Review found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'review', type: 'object')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid request",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid request')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Review not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Review not found')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Server error')
            ]
        )
    )]

    public function getReview(int $id): JsonResponse
    {
        // Retrieve the review by ID
        $review = $this->reviewRepository->find($id);
        if (!$review) {
            // Return an error if review is not found
            return new JsonResponse(['error' => 'Review not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Serialize the review using JMS Serializer with Groups
        $jsonContent = $this->serializer->serialize(
            $review, 
            'json', 
            SerializationContext::create()->setGroups(['review:item'])
        );

        // Decode the JSON to manipulate it
        $reviewData = json_decode($jsonContent, true); // Convert JSON to associative array

        // Wrap the review data in a new array with the "review" key
        $response = [
            'review' => $reviewData // Add the serialized review under the "review" key
        ];

        return new JsonResponse($response); // Automatically handles the array and converts it to JSON
    }

    /**
     * Get all reviews for a specific book
     */
    #[Route('/api/v1/books/{bookId}/reviews', name: 'book_reviews', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Success",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'page', type: 'integer', example: 1),
                new OA\Property(property: 'limit', type: 'integer', example: 10),
                new OA\Property(property: 'total_reviews', type: 'integer', example: 100),
                new OA\Property(property: 'total_pages', type: 'integer', example: 10),
                new OA\Property(property: 'book_id', type: 'integer', example: 1),
                new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid request",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid request')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Book not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Book not found')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Server error')
            ]
        )
    )]
    #[OA\Parameter(
        name: 'bookId',
        description: 'ID of the book to get reviews for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Page number for pagination',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of items per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    public function getBookReviews(int $bookId, Request $request): JsonResponse
    {
        // Find the book
        $book = $this->bookRepository->find($bookId);
        if (!$book) {
            return new JsonResponse(['error' => 'Book not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        // Get reviews for this specific book
        $totalReviews = $this->reviewRepository->count(['book' => $bookId]);
        $reviews = $this->reviewRepository->findBy(
            ['book' => $bookId], 
            ['createdAt' => 'DESC'], 
            $limit, 
            $offset
        );

        $jsonContent = $this->serializer->serialize(
            $reviews,
            'json',
            SerializationContext::create()->setGroups(['review:list'])
        );

        return new JsonResponse([
            'page' => $page,
            'limit' => $limit,
            'total_reviews' => $totalReviews,
            'total_pages' => ceil($totalReviews / $limit),
            'book_id' => $bookId,
            'reviews' => json_decode($jsonContent, true),
        ]);
    }

    /**
     * Create a new review for a specific book
     */
    #[Route('/api/v1/books/{bookId}/reviews', name: 'create_book_review', methods: ['POST'])]
    #[OA\RequestBody(
        description: 'Review content and rating',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'content', type: 'string', description: 'The content of the review'),
                new OA\Property(property: 'rating', type: 'integer', description: 'The rating given by the user (1 to 10)')
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: "Review created successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Review created successfully')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Missing required fields",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Missing required fields (content, rating)')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "User not authenticated",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not authenticated')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Book not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Book not found')
            ]
        )
    )]
    public function createBookReview(int $bookId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['content'], $data['rating'])) {
            return new JsonResponse(['error' => 'Missing required fields (content, rating)'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Validate rating range
        if (!is_numeric($data['rating']) || $data['rating'] < 1 || $data['rating'] > 10) {
            return new JsonResponse(['error' => 'Rating must be a number between 1 and 10'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Get the authenticated user from the token
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Find the book
        $book = $this->bookRepository->find($bookId);
        if (!$book) {
            return new JsonResponse(['error' => 'Book not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Create new review
        $review = new Review();
        $review->setContent($data['content']);
        $review->setRating($data['rating']);
        $review->setBook($book);
        $review->setUser($user);
        $review->setCreatedAt(new \DateTime());

        // Persist data
        $entityManager->persist($review);
        $entityManager->flush();

        // Return success response with `201 Created`
        return new JsonResponse(
            ['message' => 'Review created successfully'],
            JsonResponse::HTTP_CREATED,
            ['Location' => "/api/v1/reviews/{$review->getId()}"]
        );
    }

    /**
     * Get all reviews by a specific user
     */
    #[Route('/api/v1/users/{userId}/reviews', name: 'user_reviews', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Success",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'page', type: 'integer', example: 1),
                new OA\Property(property: 'limit', type: 'integer', example: 10),
                new OA\Property(property: 'total_reviews', type: 'integer', example: 100),
                new OA\Property(property: 'total_pages', type: 'integer', example: 10),
                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid request",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid request')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "User not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not found')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Server error')
            ]
        )
    )]
    #[OA\Parameter(
        name: 'userId',
        description: 'ID of the user to get reviews for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'page',
        description: 'Page number for pagination',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 1)
    )]
    #[OA\Parameter(
        name: 'limit',
        description: 'Number of items per page',
        in: 'query',
        schema: new OA\Schema(type: 'integer', default: 10)
    )]
    public function getUserReviews(int $userId, Request $request): JsonResponse
    {
        // Find the user
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, (int) $request->query->get('limit', 10));
        $offset = ($page - 1) * $limit;

        // Get reviews for this specific user
        $totalReviews = $this->reviewRepository->count(['user' => $userId]);
        $reviews = $this->reviewRepository->findBy(
            ['user' => $userId], 
            ['createdAt' => 'DESC'], 
            $limit, 
            $offset
        );

        $jsonContent = $this->serializer->serialize(
            $reviews,
            'json',
            SerializationContext::create()->setGroups(['review:list'])
        );

        return new JsonResponse([
            'page' => $page,
            'limit' => $limit,
            'total_reviews' => $totalReviews,
            'total_pages' => ceil($totalReviews / $limit),
            'user_id' => $userId,
            'reviews' => json_decode($jsonContent, true),
        ]);
    }

    /**
     * Get reviews for a book by a specific user
     */
    #[Route('/api/v1/books/{bookId}/users/{userId}/reviews', name: 'book_user_reviews', methods: ['GET'])]
    #[OA\Response(
        response: 200,
        description: "Success",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'book_id', type: 'integer', example: 1),
                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                new OA\Property(property: 'reviews', type: 'array', items: new OA\Items(type: 'object'))
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Invalid request",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Invalid request')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Book or User not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Book or User not found')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Server error')
            ]
        )
    )]
    #[OA\Parameter(
        name: 'bookId',
        description: 'ID of the book to get reviews for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'userId',
        description: 'ID of the user to get reviews for',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function getBookUserReviews(int $bookId, int $userId): JsonResponse
    {
        // Find the book
        $book = $this->bookRepository->find($bookId);
        if (!$book) {
            return new JsonResponse(['error' => 'Book not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Find the user
        $user = $this->userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Get reviews for this specific book and user
        $reviews = $this->reviewRepository->findBy([
            'book' => $bookId,
            'user' => $userId
        ]);

        $jsonContent = $this->serializer->serialize(
            $reviews,
            'json',
            SerializationContext::create()->setGroups(['review:list'])
        );

        return new JsonResponse([
            'book_id' => $bookId,
            'user_id' => $userId,
            'reviews' => json_decode($jsonContent, true),
        ]);
    }

    /**
     * Update your own review for a specific book
     */
    #[Route('/api/v1/books/{bookId}/reviews/{reviewId}', name: 'update_book_review', methods: ['PUT'])]
    #[OA\RequestBody(
        description: 'Review content and rating',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'content', type: 'string', description: 'The content of the review'),
                new OA\Property(property: 'rating', type: 'integer', description: 'The rating given by the user (1 to 5)')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Review updated successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Review updated successfully')
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: "Missing required fields",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Missing required fields')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "User not authenticated",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not authenticated')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: "Forbidden - You can only update your own reviews",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'You can only update your own reviews')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Book or Review not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Book or Review not found')
            ]
        )
    )]
    #[OA\Response(
        response: 500,
        description: "Server error",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Server error')
            ]
        )
    )]
    #[OA\Parameter(
        name: 'bookId',
        description: 'ID of the book the review belongs to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'reviewId',
        description: 'ID of the review to update',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function updateBookReview(int $bookId, int $reviewId, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['content'], $data['rating'])) {
            return new JsonResponse(['error' => 'Missing required fields'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Validate rating range
        if (!is_numeric($data['rating']) || $data['rating'] < 1 || $data['rating'] > 10) {
            return new JsonResponse(['error' => 'Rating must be a number between 1 and 10'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Get authenticated user
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Find the book
        $book = $this->bookRepository->find($bookId);
        if (!$book) {
            return new JsonResponse(['error' => 'Book not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Find the review by ID
        $review = $this->reviewRepository->find($reviewId);
        if (!$review) {
            return new JsonResponse(['error' => 'Review not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Check if the review belongs to the specified book
        if ($review->getBook()->getId() !== $bookId) {
            return new JsonResponse(['error' => 'Review does not belong to the specified book'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Check if the authenticated user owns this review
        if ($review->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'You can only update your own reviews'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Update review fields
        $review->setContent($data['content']);
        $review->setRating($data['rating']);
        // $review->setUpdatedAt(new \DateTime());

        // Persist data
        $entityManager->flush();

        // Return success response
        return new JsonResponse(['message' => 'Review updated successfully'], JsonResponse::HTTP_OK);
    }

    /**
     * Delete a review for a specific book
     */
    #[Route('/api/v1/books/{bookId}/reviews/{reviewId}', name: 'delete_book_review', methods: ['DELETE'])]
    #[OA\Response(
        response: 200,
        description: "Review deleted successfully",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'message', type: 'string', example: 'Review deleted successfully')
            ]
        )
    )]
    #[OA\Response(
        response: 401,
        description: "User not authenticated",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'User not authenticated')
            ]
        )
    )]
    #[OA\Response(
        response: 403,
        description: "Forbidden - You can only delete your own reviews",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'You can only delete your own reviews')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: "Book or Review not found",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Book or Review not found')
            ]
        )
    )]
    #[OA\Parameter(
        name: 'bookId',
        description: 'ID of the book the review belongs to',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    #[OA\Parameter(
        name: 'reviewId',
        description: 'ID of the review to delete',
        in: 'path',
        required: true,
        schema: new OA\Schema(type: 'integer')
    )]
    public function deleteBookReview(int $bookId, int $reviewId, EntityManagerInterface $entityManager): JsonResponse
    {
        // Get authenticated user
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'User not authenticated'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Find the book
        $book = $this->bookRepository->find($bookId);
        if (!$book) {
            return new JsonResponse(['error' => 'Book not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Find the review by ID
        $review = $this->reviewRepository->find($reviewId);
        if (!$review) {
            return new JsonResponse(['error' => 'Review not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Check if the review belongs to the specified book
        if ($review->getBook()->getId() !== $bookId) {
            return new JsonResponse(['error' => 'Review does not belong to the specified book'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Check if the authenticated user owns this review
        if ($review->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'You can only delete your own reviews'], JsonResponse::HTTP_FORBIDDEN);
        }

        // Remove the review
        $entityManager->remove($review);
        $entityManager->flush();

        // Return success response
        return new JsonResponse(['message' => 'Review deleted successfully'], JsonResponse::HTTP_OK);
    }
}

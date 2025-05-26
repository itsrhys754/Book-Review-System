<?php
// src/Controller/AuthController.php
namespace App\Controller;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use OpenApi\Attributes as OA;


class AuthController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private UserProviderInterface $userProvider;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(JWTTokenManagerInterface $jwtManager, UserProviderInterface $userProvider, UserPasswordHasherInterface $passwordHasher)
    {
        $this->jwtManager = $jwtManager;
        $this->userProvider = $userProvider;
        $this->passwordHasher = $passwordHasher;
    }

    // Login Route
    #[Route('/api/v1/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: "/api/v1/login",
        summary: "User login",
        description: "Authenticates a user and returns a JWT token",
        tags: ['Authentication']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username', 'password'],
            properties: [
                new OA\Property(property: 'username', type: 'string', example: 'user123'),
                new OA\Property(property: 'password', type: 'string', example: 'password123')
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Successful login",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'token', type: 'string', example: 'your.jwt.token')
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
        response: 401,
        description: "Invalid Credentials ",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'error', type: 'string', example: 'Incorrect Credentials')
            ]
        )
    )]
    public function login(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? null;
        $password = $data['password'] ?? null;

        if (!$username || !$password) {
            return new JsonResponse(['error' => 'Missing username or password'], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Load user from the database
        $user = $this->userProvider->loadUserByIdentifier($username);

        if (!$user instanceof PasswordAuthenticatedUserInterface || !$this->passwordHasher->isPasswordValid($user, $password)) {
            return new JsonResponse(['error' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        // Generate JWT token
        $token = $this->jwtManager->create($user);

        // Return token in response for AJAX
        return new JsonResponse(['token' => $token]);
    }

    // Page route to display the token on the frontend
    #[Route('/token', name: 'app_token', methods: ['GET'])]
    public function tokenPage(): Response
    {
        // Check if user is authenticated
        $user = $this->getUser();

        if ($user) {
            // If the user is logged in, create token
            $token = $this->jwtManager->create($user);

            // Return the token
            return $this->render('auth/token.html.twig', [
                'auto_token' => $token,
            ]);
        }

        // If user is not logged in, redirect to login form
        return $this->redirectToRoute('app_login');
    }

}

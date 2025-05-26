<?php

namespace App\Service;

use League\OAuth2\Client\Provider\Google;
use League\OAuth2\Client\Provider\GoogleUser;
use League\OAuth2\Client\Token\AccessToken;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * Service responsible for handling Google OAuth authentication and authorization.
 * 
 * This service manages the OAuth flow with Google, including authentication,
 * retrieving user data, and handling access/refresh tokens.
 */
class GoogleOAuthService
{
    private Google $provider;
    private SessionInterface $session;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    /**
     * Constructor initialises the Google OAuth provider with required credentials and scopes.
     *
     * @param string $clientId Google OAuth client ID
     * @param string $clientSecret Google OAuth client secret
     * @param string $redirectUri URI to redirect after successful authentication
     * @param RequestStack $requestStack Symfony request stack for session access
     * @param EntityManagerInterface $entityManager Doctrine entity manager for database operations
     * @param LoggerInterface $logger Logger for capturing OAuth-related operations
     */
    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        RequestStack $requestStack,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->provider = new Google([
            'clientId'     => $clientId,
            'clientSecret' => $clientSecret,
            'redirectUri'  => $redirectUri,
            'scopes'       => [
                'openid',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'https://www.googleapis.com/auth/books'
            ],
            'accessType' => 'offline', // Request refresh token
            'prompt' => 'consent'      // Force consent screen to ensure refresh token
        ]);
        $this->session = $requestStack->getSession();
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Generates the Google authorisation URL for initiating the OAuth flow.
     * 
     * Stores the OAuth state in the session for security verification.
     *
     * @return string The authorisation URL to redirect the user to
     */
    public function getAuthorizationUrl(): string
    {
        $authUrl = $this->provider->getAuthorizationUrl();
        $this->session->set('oauth2state', $this->provider->getState());
        return $authUrl;
    }

    /**
     * Processes the OAuth callback after user authorisation.
     * 
     * Exchanges the authorisation code for access/refresh tokens and retrieves user information.
     *
     * @param string $code The authorisation code returned by Google
     * @return array|null User data and tokens, or null if an error occurred
     */
    public function handleCallback(string $code): ?array
    {
        try {
            $this->logger->info('Starting Google OAuth callback processing', ['code_length' => strlen($code)]);
            
            // Get access token
            $token = $this->provider->getAccessToken('authorization_code', [
                'code' => $code
            ]);
            
            $this->logger->info('Successfully obtained access token', [
                'expires' => $token->getExpires(),
                'has_refresh_token' => $token->getRefreshToken() !== null
            ]);

            // Get user details
            /** @var GoogleUser $user */
            $user = $this->provider->getResourceOwner($token);
            
            $this->logger->info('Successfully retrieved Google user details', [
                'google_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return [
                'token' => $token->getToken(),
                'refresh_token' => $token->getRefreshToken(),
                'expires' => $token->getExpires(),
                'google_id' => $user->getId(),
                'email' => $user->getEmail(),
                'name' => $user->getName()
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error processing Google OAuth callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Validates the OAuth state parameter to prevent CSRF attacks.
     *
     * @param string $state The state parameter returned by Google
     * @return bool True if the state is valid, false otherwise
     */
    public function validateState(string $state): bool
    {
        $storedState = $this->session->get('oauth2state');
        return $state === $storedState;
    }

    /**
     * Updates a user's Google authentication data in the database.
     *
     * @param User $user The user entity to update
     * @param array $googleData Google authentication data including tokens and user info
     */
    public function updateUserGoogleData(User $user, array $googleData): void
    {
        $user->setGoogleId($googleData['google_id']);
        $user->setGoogleAccessToken($googleData['token']);
        $user->setGoogleRefreshToken($googleData['refresh_token']);
        
        // Handle expiration time
        if ($googleData['expires']) {
            $user->setGoogleTokenExpires(new \DateTime('@' . $googleData['expires']));
        } else {
            $user->setGoogleTokenExpires(null);
        }
        
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    /**
     * Refreshes an expired access token using the stored refresh token.
     * 
     * Updates the user entity with the new token information.
     *
     * @param User $user The user entity with the refresh token
     * @return string|null New access token or null if refresh failed
     */
    public function refreshAccessToken(User $user): ?string
    {
        if (!$user->getGoogleRefreshToken()) {
            return null;
        }

        try {
            $newToken = $this->provider->getAccessToken('refresh_token', [
                'refresh_token' => $user->getGoogleRefreshToken()
            ]);

            $user->setGoogleAccessToken($newToken->getToken());
            $user->setGoogleTokenExpires(new \DateTime('@' . $newToken->getExpires()));
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $newToken->getToken();
        } catch (\Exception $e) {
            return null;
        }
    }
}

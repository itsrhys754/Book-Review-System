<?php

namespace App\Controller;

use App\Service\GoogleOAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Psr\Log\LoggerInterface;

/**
 * Controller for managing Google OAuth authentication and integration.
 * Handles connecting user accounts to Google Books, the OAuth callback process,
 * and disconnecting accounts.
 */
class GoogleAuthController extends AbstractController
{
    public function __construct(
        private GoogleOAuthService $googleOAuthService,
        private LoggerInterface $logger
    ) {}

    /**
     * Initiates the Google OAuth connection process.
     * Redirects the user to Google's authentication page.
     */
    #[Route('/connect/google', name: 'connect_google')]
    public function connect(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $authUrl = $this->googleOAuthService->getAuthorizationUrl();
        return $this->redirect($authUrl);
    }

    /**
     * Handles the OAuth callback from Google after user authentication.
     * Processes the authorisation code, validates the state parameter,
     * and stores the user's Google credentials.
     */
    #[Route('/connect/google/callback', name: 'connect_google_callback')]
    public function connectCallback(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $this->logger->info('Google OAuth callback received', [
            'query_params' => $request->query->all(),
            'has_error' => $request->query->has('error'),
            'has_code' => $request->query->has('code'),
            'has_state' => $request->query->has('state')
        ]);
        
        // Check if Google returned an error
        $error = $request->query->get('error');
        if ($error) {
            $this->logger->error('Google OAuth error', ['error' => $error]);
            $this->addFlash('error', 'Failed to connect Google Books: ' . $error);
            return $this->redirectToRoute('app_profile');
        }

        // Verify required parameters are present
        $code = $request->query->get('code');
        $state = $request->query->get('state');

        if (!$code || !$state) {
            $this->logger->error('Missing code or state in Google OAuth callback');
            $this->addFlash('error', 'Missing required OAuth parameters');
            return $this->redirectToRoute('app_profile');
        }
        
        // Validate the state parameter to prevent CSRF attacks
        if (!$this->googleOAuthService->validateState($state)) {
            $this->logger->error('Invalid OAuth state', [
                'received_state' => $state,
                'expected_state' => $request->getSession()->get('oauth2state')
            ]);
            $this->addFlash('error', 'Invalid OAuth state - please try again');
            return $this->redirectToRoute('app_profile');
        }

        try {
            // Exchange authorisation code for access token
            $googleData = $this->googleOAuthService->handleCallback($code);
            if (!$googleData) {
                $this->logger->error('Failed to process Google OAuth callback - null response');
                $this->addFlash('error', 'Failed to connect Google Books - please check logs');
                return $this->redirectToRoute('app_profile');
            }
            
            $this->logger->info('Successfully processed Google OAuth callback', [
                'google_id' => $googleData['google_id'],
                'has_refresh_token' => !empty($googleData['refresh_token'])
            ]);

            // Save the Google credentials to the user's account
            $user = $this->getUser();
            $this->googleOAuthService->updateUserGoogleData($user, $googleData);

            $this->addFlash('success', 'Successfully connected Google Books');
            return $this->redirectToRoute('app_user_bookshelves');
        } catch (\Exception $e) {
            $this->logger->error('Exception during Google OAuth callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->addFlash('error', 'An error occurred while connecting to Google Books: ' . $e->getMessage());
            return $this->redirectToRoute('app_profile');
        }
    }

    /**
     * Removes the Google Books connection from the user's account.
     * Clears all Google-related credentials and data.
     */
    #[Route('/disconnect/google', name: 'disconnect_google')]
    public function disconnect(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        
        $user = $this->getUser();
        $this->googleOAuthService->updateUserGoogleData($user, [
            'google_id' => null,
            'token' => null,
            'refresh_token' => null,
            'expires' => null
        ]);

        $this->addFlash('success', 'Successfully disconnected Google Books');
        return $this->redirectToRoute('app_profile');
    }
}

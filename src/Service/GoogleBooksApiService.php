<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use App\Entity\User;

class GoogleBooksApiService
{
    private Client $client;
    private string $apiKey;
    private LoggerInterface $logger;
    private GoogleOAuthService $googleOAuthService;

    public function __construct(
        Client $client,
        LoggerInterface $logger,
        string $apiKey,
        GoogleOAuthService $googleOAuthService
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->apiKey = $apiKey;
        $this->googleOAuthService = $googleOAuthService;
    }

    /**
     * Format book data from Google Books API response
     */
    private function formatBookData(array $item): array
    {
        $volumeInfo = $item['volumeInfo'] ?? [];
        $description = $volumeInfo['description'] ?? '';
        // Strip HTML tags and decode HTML entities
        $cleanDescription = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return [
            'id' => $item['id'] ?? '',
            'title' => $volumeInfo['title'] ?? 'Unknown Title',
            'authors' => $volumeInfo['authors'] ?? [],
            'description' => $cleanDescription,
            'pageCount' => $volumeInfo['pageCount'] ?? 0,
            'categories' => $volumeInfo['categories'] ?? [],
            'imageLinks' => $volumeInfo['imageLinks'] ?? [],
            'publishedDate' => $volumeInfo['publishedDate'] ?? '',
            'publisher' => $volumeInfo['publisher'] ?? '',
            'volumeInfo' => $volumeInfo, // Include the full volumeInfo for additional fields
            'averageRating' => $volumeInfo['averageRating'] ?? null,
            'ratingsCount' => $volumeInfo['ratingsCount'] ?? 0
        ];
    }

    /**
     * Search for books by title, author, or ISBN
     * @param string $query The search query
     * @param string $searchType The type of search (isbn, author, title)
     * @param int $page The page number (1-based)
     * @param int $maxResults Maximum number of results per page (max 40)
     * @param array|null $filters Additional filters like page range
     * @return array
     */
    public function searchBooks(string $query, string $searchType = '', int $page = 1, int $maxResults = 10, ?array $filters = null): array
    {
        try {
            $searchQuery = trim($query);
            if (empty($searchQuery)) {
                return ['error' => 'Search query cannot be empty'];
            }

            // pagination parameters
            $maxResults = min(max(1, $maxResults), 40); // Ensure maxResults is between 1 and 40
            $page = max(1, $page); // Ensure page is at least 1
            $startIndex = ($page - 1) * $maxResults;

            // Parse page range if provided
            $minPages = null;
            $maxPages = null;
            if (isset($filters['pages']) && !empty($filters['pages'])) {
                if (preg_match('/^(\d+)-(\d+)$/', $filters['pages'], $matches)) {
                    $minPages = (int)$matches[1];
                    $maxPages = (int)$matches[2];
                }
            }

            // Handle special characters
            $searchQuery = urlencode($searchQuery);
            if ($searchType) {
                switch ($searchType) {
                    case 'isbn':
                        $searchQuery = 'isbn:' . $searchQuery;
                        break;
                    case 'author':
                        $searchQuery = 'inauthor:"' . $searchQuery . '"';
                        break;
                    case 'title':
                        $searchQuery = 'intitle:"' . $searchQuery . '"';
                        break;
                    default:
                        // If invalid search type, log it but continue with general search
                        $this->logger->warning('Invalid search type provided: ' . $searchType);
                }
            }

            // Request more results than needed to account for filtering
            $apiMaxResults = $maxResults * 2;

            $response = $this->client->get('volumes', [
                'query' => [
                    'q' => $searchQuery,
                    'startIndex' => $startIndex,
                    'maxResults' => $apiMaxResults,
                    'printType' => 'books',
                    'key' => $this->apiKey,
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            // Check if we got valid results
            if (!isset($result['items'])) {
                return [
                    'items' => [],
                    'totalItems' => 0,
                    'currentPage' => $page,
                    'maxResults' => $maxResults,
                    'hasNextPage' => false
                ];
            }

            // Filter results by page count if range is specified in filters
            if ($minPages !== null && $maxPages !== null) {
                $result['items'] = array_values(array_filter($result['items'], function($item) use ($minPages, $maxPages) {
                    $pageCount = $item['volumeInfo']['pageCount'] ?? null;
                    return $pageCount !== null && 
                           $pageCount >= $minPages && 
                           $pageCount <= $maxPages;
                }));

                // Update total items count
                $result['totalItems'] = count($result['items']);
            }

            // Trim results to requested size
            $result['items'] = array_slice($result['items'], 0, $maxResults);

            // Add pagination information to the response
            $result['currentPage'] = $page;
            $result['maxResults'] = $maxResults;
            $result['hasNextPage'] = isset($result['totalItems']) && 
                                   ($startIndex + $maxResults) < $result['totalItems'];

            return $result;
        } catch (GuzzleException $e) {
            $this->logger->error('Google Books API error: ' . $e->getMessage());
            return ['error' => 'Failed to fetch results from Google Books API'];
        }
    }

    /**
     * Get detailed information about a specific book by its Google Books ID
     */
    public function getBookDetails(string $bookId): ?array
    {
        try {
            $this->logger->info('Fetching book details from Google Books API', ['bookId' => $bookId]);
            
            $response = $this->client->get("volumes/{$bookId}", [
                'query' => [
                    'key' => $this->apiKey
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);
            
            if (!$result || isset($result['error'])) {
                $this->logger->error('Error fetching book details', [
                    'bookId' => $bookId,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
                return null;
            }

            // Format the book data using the existing formatter
            $formattedBook = $this->formatBookData($result);

            $this->logger->info('Successfully fetched book details', [
                'bookId' => $bookId,
                'volumeInfo' => $result['volumeInfo'] ?? null,
                'industryIdentifiers' => $result['volumeInfo']['industryIdentifiers'] ?? null
            ]);

            return $formattedBook;
        } catch (GuzzleException $e) {
            $this->logger->error('Google Books API error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get the authorisation headers for API requests based on the user's Google token.
     * This method ensures that the user's access token is valid and attempts to refresh it if expired.
     */
    private function getAuthHeaders(?User $user = null): array
    {
        // Return empty if user is not logged in or lacks a Google access token
        if (!$user || !$user->getGoogleAccessToken()) {
            $this->logger->warning('No user or access token available');
            return [];
        }

        // Check if token is expired and needs refresh
        if ($user->getGoogleTokenExpires() && $user->getGoogleTokenExpires() < new \DateTime()) {
            $this->logger->info('Token expired, attempting refresh');
            $newToken = $this->googleOAuthService->refreshAccessToken($user);
            if (!$newToken) {
                $this->logger->error('Failed to refresh token');
                return [];
            }
        }

        $token = $user->getGoogleAccessToken();
        $this->logger->info('Using access token: ' . substr($token, 0, 10) . '...');
        
        return [
            'Authorization' => 'Bearer ' . $token
        ];
    }

    /**
     * Get a user's bookshelves
     */
    public function getBookshelves(User $user): array
    {
        if (!$user->isGoogleConnected()) {
            return ['error' => 'Google Books not connected'];
        }

        try {
            $this->logger->info('Fetching bookshelves from Google Books API', [
                'userId' => $user->getGoogleId(),
                'isConnected' => $user->isGoogleConnected(),
                'hasToken' => $user->getGoogleAccessToken() !== null
            ]);

            // Get the user's authorisation headers
            $headers = $this->getAuthHeaders($user);
            if (empty($headers)) {
                return ['error' => 'No valid authentication token'];
            }

            // Make the API request to fetch bookshelves
            $response = $this->client->get("mylibrary/bookshelves", [
                'headers' => $headers,
                'query' => [
                    'key' => $this->apiKey
                ]
            ]);

            // Decode the response
            $result = json_decode($response->getBody()->getContents(), true);
            $this->logger->info('Bookshelves response', ['result' => $result]);
            return $result;
        } catch (GuzzleException $e) {
            $this->logger->error('Google Books API error: ' . $e->getMessage(), [
                'exception' => $e,
                'userId' => $user->getGoogleId()
            ]);
            return ['error' => 'Failed to fetch bookshelves: ' . $e->getMessage()];
        }
    }

    /**
     * Get books from a specific bookshelf for a user.
     * This method retrieves books contained within a specific bookshelf based on the provided shelf ID.
     */
    public function getBookshelfBooks(User $user, string $shelfId): array
    {
        if (!$user->isGoogleConnected()) {
            return ['error' => 'Google Books not connected'];
        }

        try {
            $this->logger->info('Fetching books from bookshelf', [
                'shelfId' => $shelfId,
                'userId' => $user->getGoogleId()
            ]);

            $headers = $this->getAuthHeaders($user);
            if (empty($headers)) {
                return ['error' => 'No valid authentication token'];
            }

            $response = $this->client->get("mylibrary/bookshelves/{$shelfId}/volumes", [
                'headers' => $headers,
                'query' => [
                    'key' => $this->apiKey
                ]
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            // Return formatted book data if items are found
            if (isset($result['items'])) {
                $formattedBooks = array_map([$this, 'formatBookData'], $result['items']);
                return ['items' => $formattedBooks];
            }
            // Return empty array if no books are found
            return ['items' => []];
        } catch (GuzzleException $e) {
            $this->logger->error('Google Books API error: ' . $e->getMessage(), [
                'exception' => $e,
                'shelfId' => $shelfId
            ]);
            return ['error' => 'Failed to fetch books: ' . $e->getMessage()];
        }
    }

    /**
     * Download and save image from URL
     * @param string $imageUrl The URL of the image to download
     * @param string $uploadDir The directory to save the image to
     * @return string|null The filename if successful, null otherwise
     */
    public function downloadImage(string $imageUrl, string $uploadDir): ?string
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false, // Skip SSL verification if needed
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);
            
            $response = $client->get($imageUrl);
            
            if ($response->getStatusCode() === 200) {
                $extension = 'jpg'; // Default to jpg
                $contentType = $response->getHeaderLine('Content-Type');
                
                // Determine file extension from content type
                if (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg')) {
                    $extension = 'jpg';
                } elseif (str_contains($contentType, 'png')) {
                    $extension = 'png';
                } elseif (str_contains($contentType, 'gif')) {
                    $extension = 'gif';
                }
                
                $filename = uniqid() . '.' . $extension;
                
                // Log the upload directory for debugging
                $this->logger->info('Upload directory: ' . $uploadDir);
                
                // Create directory if it doesn't exist
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                $filepath = $uploadDir . '/' . $filename;
                
                // Log the full file path
                $this->logger->info('Saving image to: ' . $filepath);
                
                // Save the image
                if (file_put_contents($filepath, $response->getBody()->getContents()) !== false) {
                    // Verify the file was created and is readable
                    if (file_exists($filepath) && is_readable($filepath)) {
                        $this->logger->info('Successfully saved image: ' . $filename);
                        return $filename;
                    } else {
                        $this->logger->error('File was not created or is not readable: ' . $filepath);
                    }
                } else {
                    $this->logger->error('Failed to write image to file: ' . $filepath);
                }
            } else {
                $this->logger->error('Failed to download image. Status code: ' . $response->getStatusCode());
            }
        } catch (\Exception $e) {
            $this->logger->error('Error downloading image: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());
        }
        
        return null;
    }
}

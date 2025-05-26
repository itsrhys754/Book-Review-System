<?php

namespace App\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class NYTimesApiService
{
    private Client $client;
    private string $apiKey;
    private LoggerInterface $logger;

    public function __construct(
        Client $client,
        LoggerInterface $logger,
        string $nytApiKey
    ) {
        $this->client = $client;
        $this->logger = $logger;
        $this->apiKey = $nytApiKey;
    }

    /**
     * Search for book reviews by ISBN or title
     */
    public function getBookReviews(?string $isbn = null, ?string $title = null): array
    {
        $this->logger->info('Fetching NYTimes reviews', [
            'isbn' => $isbn,
            'title' => $title
        ]);
        
        // Try ISBN first if available, as it's more precise
        if ($isbn !== null) {
            try {
                $reviews = $this->searchReviews(['isbn' => $isbn]);
                if (!empty($reviews)) {
                    return $reviews;
                }
            } catch (GuzzleException $e) {
                $this->logger->error('Error in ISBN search: ' . $e->getMessage());
                // Continue to title search if ISBN fails
            }
        }
        
        // Only try title search if ISBN not found or not provided
        if ($title !== null) {
            try {
                $this->logger->info('Trying title search', ['title' => $title]);
                return $this->searchReviews(['title' => $title]);
            } catch (GuzzleException $e) {
                $this->logger->error('Error in title search: ' . $e->getMessage());
                return [];
            }
        }
        
        return [];
    }

    private function searchReviews(array $params): array
    {
        $requestUrl = 'reviews.json';
        $queryParams = array_merge($params, ['api-key' => $this->apiKey]);
        
        try {
            $response = $this->client->get($requestUrl, [
                'query' => $queryParams,
                'headers' => [
                    'Accept' => 'application/json'
                ]
            ]);

            $statusCode = $response->getStatusCode();
            $this->logger->info('NYTimes API response', [
                'status_code' => $statusCode,
                'params' => array_diff_key($params, ['api-key' => '']), // Log params but exclude API key
                'headers' => $response->getHeaders()
            ]);

            // Handle rate limiting
            if ($statusCode === 429) {
                $this->logger->warning('NYTimes API rate limit reached', [
                    'retry_after' => $response->getHeader('Retry-After')[0] ?? 'unknown'
                ]);
                return [];
            }

            $data = json_decode($response->getBody()->getContents(), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error('JSON decode error', [
                    'error' => json_last_error_msg(),
                    'raw_response' => substr($response->getBody()->getContents(), 0, 1000) // Log first 1000 chars
                ]);
                return [];
            }
            
            if (!isset($data['status']) || $data['status'] !== 'OK') {
                $this->logger->warning('NYTimes API returned non-OK status', [
                    'status' => $data['status'] ?? 'unknown',
                    'message' => $data['errors'][0] ?? $data['fault']['faultstring'] ?? 'No error message'
                ]);
                return [];
            }

            if (!isset($data['results']) || empty($data['results'])) {
                $this->logger->info('No reviews found', [
                    'params' => array_diff_key($params, ['api-key' => ''])
                ]);
                return [];
            }
            
            return $this->formatReviewData($data['results']);

        } catch (GuzzleException $e) {
            $context = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'params' => array_diff_key($params, ['api-key' => '']),
            ];
            
            if ($e instanceof RequestException && $e->hasResponse()) {
                $response = $e->getResponse();
                $context['status_code'] = $response->getStatusCode();
                $context['response_body'] = substr($response->getBody()->getContents(), 0, 1000);
            }
            
            $this->logger->error('Error fetching NYTimes reviews', $context);
            return [];
        }
    }

    /**
     * Format the NYTimes review data
     */
    private function formatReviewData(array $reviews): array
    {
        // Filter out excerpts and only keep actual reviews
        $reviews = array_filter($reviews, function($review) {
            // Check if the summary or URL contains indicators of an excerpt
            $isExcerpt = false;
            $summary = strtolower($review['summary'] ?? '');
            $url = strtolower($review['url'] ?? '');
            
            $excerptIndicators = [
                'an excerpt from',
                'excerpt:',
                'excerpt of',
                '/excerpt',
                'read an excerpt',
                'chapter one',
                'first chapter'
            ];

            foreach ($excerptIndicators as $indicator) {
                if (str_contains($summary, $indicator) || str_contains($url, $indicator)) {
                    $isExcerpt = true;
                    break;
                }
            }

            $this->logger->info('Checking if review is excerpt:', [
                'summary' => $summary,
                'url' => $url,
                'isExcerpt' => $isExcerpt
            ]);

            return !$isExcerpt;
        });

        $formatted = array_map(function($review) {
            $formatted = [
                'byline' => $review['byline'] ?? 'Unknown Reviewer',
                'summary' => $review['summary'] ?? 'No summary available',
                'publication_date' => $review['publication_dt'] ?? null,
                'url' => $review['url'] ?? null,
            ];
            $this->logger->info('Formatted review:', ['review' => $formatted]);
            return $formatted;
        }, $reviews);

        $this->logger->info('Formatted all reviews:', ['reviews' => $formatted]);
        return array_values($formatted); // Re-index array after filtering
    }
}

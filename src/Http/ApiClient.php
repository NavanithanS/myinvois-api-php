<?php

namespace Nava\MyInvois\Http;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nava\MyInvois\Auth\AuthenticationClient;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\NetworkException;
use Nava\MyInvois\Exception\ValidationException;
use Psr\Http\Message\ResponseInterface;

/**
 * Client for making API requests to MyInvois.
 */
class ApiClient
{
    private $accessToken = null;
    private $tokenExpires = null;
    private $httpClient;
    private $cache;
    private $authClient;
    private $baseUrl;

    public function __construct(
        $clientId,
        $clientSecret,
        $baseUrl,
        GuzzleClient $httpClient,
        CacheRepository $cache,
        AuthenticationClient $authClient,
        $config = []
    ) {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
        $this->authClient = $authClient;
        $this->baseUrl = $baseUrl;
    }

    /**
     * Send a synchronous request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $options  Request options
     * @return array Response data
     *
     * @throws ApiException|ValidationException|AuthenticationException|NetworkException
     */
    public function request(string $method, string $endpoint, array $options = []): array
    {
        return $this->requestAsync($method, $endpoint, $options)->wait();
    }

    /**
     * Send an asynchronous request to the API.
     *
     * @param  string  $method  HTTP method
     * @param  string  $endpoint  API endpoint
     * @param  array  $options  Request options
     */
    public function requestAsync(string $method, string $endpoint, array $options = []): PromiseInterface
    {
        try {
            $authResponse = json_decode($options['authResponse'], true);

            $this->accessToken = $authResponse['access_token'] ?? null;
            // $this->tokenExpires = $authResponse['expires_in'] ?? null;

            $this->authenticateIfNeeded();

            $options['headers'] = array_merge(
                $options['headers'] ?? [],
                [
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ]
            );

            $this->logRequest($method, $endpoint, $options);

            return $this->httpClient->requestAsync($method, $this->baseUrl . $endpoint, $options)
                ->then(
                    function (ResponseInterface $response) {
                        return $this->handleResponse($response);
                    },
                    function (\Throwable $exception) use ($method, $endpoint, $options) {
                        if ($exception instanceof RequestException && $this->shouldRetry($exception)) {
                            return $this->retryRequest($method, $endpoint, $options);
                        }

                        // Log any exception for debugging
                        \Log::error("API request failed: " . $exception->getMessage(), [
                            'method' => $method,
                            'endpoint' => $endpoint,
                            'options' => $options
                        ]);

                        return $this->handleRequestException($exception);
                    }
                );
        } catch (\Throwable $e) {
            throw new ApiException('Unexpected error occurred: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Ensure a valid authentication token exists.
     *
     * @throws AuthenticationException
     */
    private function authenticateIfNeeded(): void
    {
        $tokenNeedsRefresh = !$this->accessToken;

        if ($tokenNeedsRefresh) {
            try {
                $authResponse = $this->authClient->authenticate('');
                $this->accessToken = $authResponse['access_token'];
                $this->tokenExpires = time() + ($authResponse['expires_in'] ?? 3600);
            } catch (\Throwable $e) {
                throw new AuthenticationException(
                    'Failed to authenticate with API: ' . $e->getMessage(),
                    0,
                    $e
                );
            }
        }
    }

    /**
     * Handle API response.
     *
     * @throws ValidationException|ApiException
     */
    private function handleResponse(ResponseInterface $response): array
    {

        $body = (string) $response->getBody();

        // If the body is empty, return a default response
        if (empty(trim($body))) {
            return ['status' => 200];
        }

        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException('Invalid JSON response from API');
        }

        if ($this->config['logging']['enabled'] ?? false) {
            $this->logResponse($response, $data);
        }

        if (isset($data['error'])) {
            $this->handleErrorResponse($data);
        }

        return $data;
    }

    /**
     * Handle error response data.
     *
     * @throws ValidationException|ApiException
     */
    private function handleErrorResponse(array $data): never
    {
        if ('validation_error' === ($data['error'] ?? null)) {
            throw new ValidationException(
                $data['message'] ?? 'Validation failed',
                $data['errors'] ?? []
            );
        }

        throw new ApiException(
            $data['message'] ?? 'API error occurred',
            $data['code'] ?? 0
        );
    }

    /**
     * Handle request exceptions.
     *
     * @throws ApiException|AuthenticationException|NetworkException|ValidationException
     */
    private function handleRequestException(\Throwable $e)
    {
        // If it's not a RequestException, log and rethrow it.
        if (!$e instanceof RequestException) {
            \Log::error('Unexpected exception in API request: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
            throw new ApiException('Unexpected error occurred', 500, $e);
        }

        $response = $e->getResponse();
        if (!$response) {
            throw new NetworkException(
                'Network error occurred: ' . $e->getMessage(),
                0,
                $e
            );
        }

        try {
            $body = json_decode((string) $response->getBody(), true);
        } catch (\Exception $e) {
            throw new ApiException(
                'Invalid response format from API',
                $response->getStatusCode(),
                $e
            );
        }

        $statusCode = $response->getStatusCode();
        $message = $body['error'] ?? $body['error_description'] ?? 'Unknown error occurred';

        switch ($statusCode) {
            case 400:
                if (is_string($body)) {
                    $body = json_decode($body, true);
                }

                $errorMessage = $body['error']['details'][0]['message'] ?? '';
                return [
                    'status' => 400,
                    'message' => $errorMessage
                ];

                // throw new ApiException(
                //     'Bad Request: ' . $errorMessage,
                //     400,
                //     $e
                // );

            case 404:
                return ['status' => 404, 'message' => 'No TIN found for the given search parameters.'];

                // throw new ApiException(
                //     'No TIN found for the given search parameters.',
                //     404,
                //     $e
                // );

            case 422:
                // throw new ValidationException(
                //     $message,
                //     $body['errors'] ?? [],
                //     422,
                //     $e
                // );

                return ['status' => 422, 'message' => $message];

            case 401:
                $this->accessToken = null;
                $this->tokenExpires = null;
                // throw new AuthenticationException($message, 401, $e);

                return ['status' => 401, 'message' => $message];

            case 429:
                // throw new ApiException('Rate limit exceeded', 429, $e);

                return ['status' => 429, 'message' => 'Rate limit exceeded'];

            default:
                $responseData = json_decode($response->getBody()->getContents(), true);
                $message = isset($responseData['error'])
                    ? (is_array($responseData['error']) ? json_encode($responseData['error']) : $responseData['error'])
                    : $response->getReasonPhrase();
                // throw new ApiException($message, $statusCode, $e);
                return ['status' => $statusCode, 'message' => $message];
        }
    }

    /**
     * Determine if the request should be retried.
     */
    private function shouldRetry(RequestException $e): bool
    {
        if (!($this->config['http']['retry']['enabled'] ?? true)) {
            return false;
        }

        $response = $e->getResponse();
        if (!$response) {
            return true; // Retry network errors
        }

        $statusCode = $response->getStatusCode();

        return 429 === $statusCode || $statusCode >= 500;
    }

    /**
     * Retry a failed request.
     */
    private function retryRequest(string $method, string $endpoint, array $options): PromiseInterface
    {
        $retries = $options['_retries'] ?? 0;
        $maxRetries = $this->config['http']['retry']['times'] ?? 3;

        if ($retries >= $maxRetries) {
            throw new ApiException('Maximum retry attempts reached');
        }

        $delay = $this->getRetryDelay($retries);
        $options['_retries'] = $retries + 1;

        return new Promise(function () use ($method, $endpoint, $options, $delay) {
            usleep($delay * 1000); // Convert to microseconds

            return $this->requestAsync($method, $endpoint, $options);
        });
    }

    /**
     * Get delay for retry attempt.
     */
    private function getRetryDelay(int $retry): int
    {
        if (isset($this->config['http']['retry']['sleep'])) {
            return is_callable($this->config['http']['retry']['sleep'])
                ? ($this->config['http']['retry']['sleep'])($retry)
                : $this->config['http']['retry']['sleep'];
        }

        // Exponential backoff with jitter
        $base = 1000; // 1 second base
        $max = 10000; // 10 seconds max
        $exponential = min($base * pow(2, $retry), $max);

        return $exponential + random_int(0, min(1000, $exponential));
    }

    /**
     * Log API request if logging is enabled.
     */
    private function logRequest(string $method, string $endpoint, array $options): void
    {
        if (!($this->config['logging']['enabled'] ?? false)) {
            return;
        }

        // Remove sensitive data from logs
        $logOptions = $options;
        unset($logOptions['headers']['Authorization']);

        logger()->channel($this->config['logging']['channel'] ?? 'stack')
            ->debug('MyInvois API Request', [
                'method' => $method,
                'endpoint' => $endpoint,
                'options' => $logOptions,
            ]);
    }

    /**
     * Log API response if logging is enabled.
     */
    private function logResponse(ResponseInterface $response, array $data): void
    {
        if (!($this->config['logging']['enabled'] ?? false)) {
            return;
        }

        logger()->channel($this->config['logging']['channel'] ?? 'stack')
            ->debug('MyInvois API Response', [
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
                'body' => $data,
            ]);
    }
}

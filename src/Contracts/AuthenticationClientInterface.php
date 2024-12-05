<?php

namespace Nava\MyInvois\Contracts;

use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\NetworkException;
use Nava\MyInvois\Exception\ValidationException;

/**
 * Interface for MyInvois authentication clients.
 */
interface AuthenticationClientInterface
{
    /**
     * Authenticate with the MyInvois API and get an access token.
     *
     * This method implements the OAuth 2.0 client credentials flow to obtain
     * an access token from the MyInvois identity service. If caching is enabled,
     * it will first check for a valid cached token before making an API request.
     *
     * @throws AuthenticationException If authentication fails due to invalid credentials
     * @throws ValidationException If the request or response validation fails
     * @throws NetworkException If a network error occurs
     *
     * @return array{
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string,
     *     created_at?: int
     * } The token data including expiration information
     */
    public function authenticate(): array;

    /**
     * Check if the current token is valid and not near expiration.
     *
     * This method verifies whether there is a valid token either in memory
     * or in cache that hasn't expired and isn't within the refresh buffer period.
     *
     * @return bool True if a valid token exists, false otherwise
     */
    public function hasValidToken(): bool;

    /**
     * Get the current access token if valid, or authenticate to get a new one.
     *
     * This method is the primary way to obtain a valid access token. It handles
     * checking for existing valid tokens and refreshing expired ones automatically.
     *
     * @throws AuthenticationException If authentication fails when getting a new token
     * @throws ValidationException If token validation fails
     * @throws NetworkException If a network error occurs during token refresh
     *
     * @return string A valid access token
     */
    public function getAccessToken(): string;
}

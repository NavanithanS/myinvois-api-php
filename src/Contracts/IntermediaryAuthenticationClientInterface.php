<?php

namespace Nava\MyInvois\Contracts;

use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\NetworkException;
use Nava\MyInvois\Exception\ValidationException;

/**
 * Interface for MyInvois intermediary authentication clients.
 *
 * This interface defines the contract for authentication clients that act as intermediaries,
 * authenticating on behalf of taxpayers. It extends the base AuthenticationClientInterface
 * with additional methods specific to intermediary functionality.
 *
 * Implementations must:
 * - Handle TIN (Tax Identification Number) validation
 * - Manage separate token caching per taxpayer
 * - Include the onbehalfof header in authentication requests
 * - Handle intermediary-specific authentication errors
 */
interface IntermediaryAuthenticationClientInterface extends AuthenticationClientInterface
{
    /**
     * Set the TIN of the taxpayer being represented.
     *
     * This method should:
     * - Validate the TIN format (must start with 'C' followed by 10 digits)
     * - Clear any existing authentication token when switching taxpayers
     * - Update the internal state to use this TIN for subsequent requests
     * - Ensure proper caching keys are used for this taxpayer
     *
     * @param  string  $tin  Tax Identification Number of the represented taxpayer
     *
     * @throws ValidationException If TIN format is invalid
     */
    public function onBehalfOf(string $tin): self;

    /**
     * Get the current taxpayer TIN being represented.
     *
     * This method should:
     * - Return the currently set TIN, if any
     * - Return null if no TIN has been set
     * - Not perform any validation checks
     *
     * @return string|null The current taxpayer TIN or null if not set
     */
    public function getCurrentTaxpayer(): ?string;

    /**
     * Authenticate with the MyInvois API as an intermediary.
     *
     * This method must override the parent authenticate() to:
     * - Ensure a TIN has been set before attempting authentication
     * - Include the onbehalfof header in the request
     * - Handle intermediary-specific error responses
     * - Cache tokens separately for each taxpayer
     *
     * @return array{
     *     access_token: string,
     *     token_type: string,
     *     expires_in: int,
     *     scope: string,
     *     created_at?: int
     * } The token data including expiration information
     *
     * @throws ValidationException If no TIN has been set or if the request is invalid
     * @throws AuthenticationException If authentication fails or intermediary is not authorized
     * @throws NetworkException If a network error occurs
     */
    public function authenticate(string $tin): array;

    /**
     * Get a valid access token for the current taxpayer.
     *
     * This method must:
     * - Ensure a TIN has been set
     * - Return a cached token if valid
     * - Obtain a new token if needed
     * - Handle taxpayer-specific token storage
     *
     * @return string A valid access token
     *
     * @throws ValidationException If no TIN has been set
     * @throws AuthenticationException If authentication fails
     * @throws NetworkException If a network error occurs
     */
    public function getAccessToken(): string;

    /**
     * Check if there is a valid token for the current taxpayer.
     *
     * This method should:
     * - Return false if no TIN has been set
     * - Check for a valid cached token specific to the current taxpayer
     * - Consider token expiration and refresh buffer
     *
     * @return bool True if a valid token exists for the current taxpayer
     */
    public function hasValidToken(): bool;
}

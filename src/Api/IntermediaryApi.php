<?php

namespace Nava\MyInvois\Api;

use Nava\MyInvois\Exception\ApiException;

/**
 * API trait for intermediary-related operations.
 *
 * Note: MyInvois v1.1.2 client supports intermediary authentication (on-behalf-of)
 * but does not expose intermediary management endpoints (add/remove). This trait
 * provides a status method based on auth capability so client code can monitor
 * intermediary linkage without hard failures.
 */
trait IntermediaryApi
{
    /**
     * Infer intermediary status for a taxpayer by attempting intermediary auth.
     *
     * @param string $representativeTin Taxpayer TIN to act on behalf of
     * @return array{success:bool, data?:array, error?:string}
     *
     * @throws ApiException When authentication fails unexpectedly
     */
    public function getIntermediaryStatus(string $representativeTin): array
    {
        $normalizedTin = strtoupper(preg_replace('/[\s-]+/', '', $representativeTin));

        // Only available when using IntermediaryAuthenticationClient
        if (! method_exists($this, 'onBehalfOf') || ! method_exists($this, 'authenticate')) {
            return [
                'success' => false,
                'error' => 'Intermediary authentication not supported by current client',
            ];
        }

        try {
            $this->onBehalfOf($normalizedTin);
            $auth = $this->authenticate();

            return [
                'success' => true,
                'data' => [
                    'representativeTin' => $normalizedTin,
                    'status' => 'linked',
                    'capability' => 'auth_on_behalf',
                    'token_ttl' => $auth['expires_in'] ?? null,
                ],
            ];
        } catch (\Throwable $e) {
            // Surface a clean failure response; callers may map this to UI
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}

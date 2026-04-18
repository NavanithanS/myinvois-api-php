---
tags: [api, authentication, oauth2]
updated: 2026-04-18
---

# Authentication

OAuth2 client credentials flow against the MyInvois Identity Server (OpenID Connect).

## Token Request

```
POST {identity_base}/connect/token
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials
client_id={clientId}
client_secret={clientSecret}
scope=InvoicingAPI
```

Response includes `access_token`, `token_type: bearer`, `expires_in` (seconds). Token is used as `Authorization: Bearer {token}` on all subsequent API requests.

## Key Files

| File | Purpose |
|------|---------|
| `src/Auth/AuthenticationClient.php` | Standard OAuth2 client |
| `src/Auth/IntermediaryAuthenticationClient.php` | Intermediary extension — adds `onbehalfof` header |
| `src/Http/ApiClient.php` | Injects token into every API request via `authenticateIfNeeded()` |
| `src/Contracts/AuthenticationClientInterface.php` | Interface contract |

## Token Caching

Tokens are cached in Laravel's cache store to avoid unnecessary token requests.

| Cache key | `myinvois_token_{clientId}` |
|-----------|----------------------------|
| Intermediary key | `myinvois_intermediary_token_{clientId}_{onBehalfOfTin}` |
| TTL | `expires_in - 300` seconds (5-minute buffer) |
| In-memory buffer | `ApiClient` also tracks expiry — re-authenticates 60s before expiry |

`AuthenticationClient::authenticate()` checks the cache first and skips the network call if a valid token exists. The cache check is bypassed when `$tin` is non-empty.

## Intermediary Mode

When using `IntermediaryAuthenticationClient`, every request (token request **and** API request) includes:

```
onbehalfof: {taxpayerTIN}
```

The header is injected by:
1. `executeAuthRequest()` — adds `onbehalfof` header to the token POST
2. `getAuthRequestHeaders()` — returns `['onbehalfof' => $tin]`, merged into API requests by `ApiClient`

Switching TINs via `onBehalfOf($tin)` clears the current token so the next call re-authenticates with the new TIN's context.

Accepted TIN formats for `onBehalfOf()`:
- `C` + 10–12 digits
- `IG` + 11–12 digits
- 12-digit NRIC

## SSL Verification

`'verify' => false` is set in the authentication HTTP request options (`executeAuthRequest()`). The Malaysian government identity server's certificate chain does not validate cleanly in many server environments. Do not re-enable without thorough environment testing.

## Scope Validation

After receiving the token, `validateAuthResponse()` confirms the granted `scope` includes `InvoicingAPI`. If scope is missing or different, it throws `AuthenticationException`.

## Rate Limiting

100 token requests per hour per client ID (`AuthenticationClient::enforceRateLimit()`). Tracked in cache under `auth_rate_limit_{clientId}`.

## Error Mapping

| HTTP status | Exception | Message pattern |
|------------|-----------|----------------|
| 400 | `ValidationException` | Invalid request format or parameters |
| 401 | `AuthenticationException` | Invalid credentials or expired token |
| 403 | `AuthenticationException` | Access denied — check permissions |
| 404 | `AuthenticationException` | Endpoint not found — check URL |
| 429 | `AuthenticationException` | Rate limit exceeded |
| 500 | `AuthenticationException` | Server error during authentication |
| Network error | `NetworkException` | Network error during authentication |

For intermediary-specific errors:
- 403: "Intermediary not authorized for this taxpayer"
- 400 with "taxpayer" in message: `ValidationException` with `tin` field

## Related

- [[overview#two-domain-architecture]] — identity vs API base URLs
- [[operations/config#auth]] — auth config keys
- [[operations/known-quirks#ssl-verification]] — why SSL verification is off

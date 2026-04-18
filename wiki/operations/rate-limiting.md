---
tags: [operations, rate-limiting, throttling]
updated: 2026-04-18
---

# Rate Limiting

## Key File

`src/Traits/RateLimitingTrait.php`

## Per-Operation Limits

| Operation | Method | Max requests | Window | Cache key prefix |
|-----------|--------|-------------|--------|-----------------|
| Document submission | `submitDocument` | 50 | 1 hour | `myinvois_method_ratelimit_submitDocument` |
| TIN search | `searchTin` | 60 | 1 minute | `myinvois_method_ratelimit_searchTin` |
| Token requests | (auth) | 100 | 1 hour | `auth_rate_limit_{clientId}` |
| Generic submission | `submission` | 100 | 1 hour | `myinvois_ratelimit_submission` |
| Submission status polling | (built-in) | 1 per 3s per submission | per-call | in-memory `$lastPollTimes` |

## How It Works

`checkRateLimit(string $key, array $config)` runs before each rate-limited API call:

1. Reads current count from cache: `{cache_prefix}{key}`
2. If count ≥ `max_requests` → throws `ApiException` (HTTP 429)
3. Increments count, stores with TTL = `window` seconds

```php
$this->checkRateLimit(
    'document_submission',
    $this->createRateLimitConfig('submitDocument', 50, 3600)
);
```

`createRateLimitConfig($method, $maxRequests, $windowSeconds)` returns the config array with cache prefix `myinvois_method_ratelimit_{method}`.

## Retry Behaviour on 429

When the **API server** returns 429 (not the client-side rate limiter), `ApiClient` and `DocumentSubmissionApi` both retry:

| Retry engine | Max retries | Backoff |
|-------------|------------|---------|
| `DocumentSubmissionApi::submitDocuments()` | 3 | `sleep(pow(2, $attempt))` — 1s, 2s, 4s |
| `ApiClient::retryRequest()` | `config.http.retry.times` (default 3) | Exponential with jitter — base 1s, max 10s |

Retryable HTTP codes: `429`, `5xx`. Non-retryable: `400`, `403`, `422`, others.

## Submission Status Polling

`SubmissionStatusApi` enforces a minimum 3-second gap between calls for the same `submissionId`, tracked in-memory via `$lastPollTimes` (microtime). Throws `ApiException(429)` if called too quickly. This is separate from cache-based rate limiting.

## Related

- [[api/authentication#rate-limit]] — token request limit
- [[api/taxpayer]] — TIN search limit (60/min)
- [[api/submission-status#polling-interval]] — submission polling constraint

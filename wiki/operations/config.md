---
tags: [operations, config, laravel, setup]
updated: 2026-04-18
---

# Configuration & Setup

## Laravel Integration

The library auto-registers via `composer.json` service provider discovery.

| Component | Class |
|-----------|-------|
| Service Provider | `Nava\MyInvois\Laravel\MyInvoisServiceProvider` |
| Facade | `Nava\MyInvois\Laravel\Facades\MyInvois` |
| Config file | `config/myinvois.php` |

Publish config:
```bash
php artisan vendor:publish --provider="Nava\MyInvois\Laravel\MyInvoisServiceProvider"
```

## All Config Keys

File: `config/myinvois.php`

### Credentials

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `client_id` | `MYINVOIS_CLIENT_ID` | — | OAuth2 client ID |
| `client_secret` | `MYINVOIS_CLIENT_SECRET` | — | OAuth2 client secret |
| `tin` | `MYINVOIS_SUPPLIER_TIN` | — | Default supplier TIN |
| `ic` | `MYINVOIS_SUPPLIER_IC` | — | Supplier IC number |

### Endpoints

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `base_url` | `MYINVOIS_BASE_URL` | Sandbox URL | Document API base |
| `auth.url` | `MYINVOIS_AUTH_URL` | Sandbox identity URL | Token endpoint base |

> **Default is sandbox** — you must explicitly set production URLs in production environments.

### Certificates (for `createDocument()` signing)

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `privatekey_path` | `MYINVOIS_PRIVATEKEY_PATH` | — | Path to PKCS#12 cert file |
| `sslcert_path` | `MYINVOIS_SSLCERT_PATH` | — | SSL cert path |
| `signedsignature_path` | `MYINVOIS_SIGNSIG_PATH` | — | Signed signature path |

### Intermediary

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `intermediary.enabled` | `MYINVOIS_INTERMEDIARY_ENABLED` | `false` | Enable intermediary mode |
| `intermediary.default_tin` | `MYINVOIS_DEFAULT_TAXPAYER_TIN` | — | Default taxpayer TIN |
| `intermediary.validate_tin` | `MYINVOIS_VALIDATE_TIN` | `true` | Validate TINs on set |

### Token

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `auth.token_ttl` | `MYINVOIS_AUTH_TOKEN_TTL` | `3600` | Token lifetime (s) |
| `auth.token_refresh_buffer` | `MYINVOIS_AUTH_TOKEN_REFRESH_BUFFER` | `300` | Refresh buffer (s) |

### Cache

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `cache.enabled` | `MYINVOIS_CACHE_ENABLED` | `true` | Enable token caching |
| `cache.store` | `MYINVOIS_CACHE_STORE` | `"file"` | Laravel cache store |
| `cache.ttl` | `MYINVOIS_CACHE_TTL` | `3600` | General cache TTL |

### HTTP

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `http.timeout` | `MYINVOIS_HTTP_TIMEOUT` | `30` | Request timeout (s) |
| `http.connect_timeout` | `MYINVOIS_HTTP_CONNECT_TIMEOUT` | `10` | Connection timeout (s) |
| `http.retry.times` | `MYINVOIS_HTTP_RETRY_TIMES` | `3` | Max retries |
| `http.retry.sleep` | `MYINVOIS_HTTP_RETRY_SLEEP` | `1000` | Retry sleep (ms) |

### Logging

| Key | Env var | Default | Purpose |
|-----|---------|---------|---------|
| `logging.enabled` | `MYINVOIS_LOGGING_ENABLED` | `true` | Enable API request logging |
| `logging.channel` | `MYINVOIS_LOG_CHANNEL` | `"stack"` | Laravel log channel |

## Factory Methods

```php
use Nava\MyInvois\MyInvoisClientFactory;

$factory = new MyInvoisClientFactory();

// Sandbox (default URLs)
$client = $factory->sandbox($clientId, $secret);

// Production
$client = $factory->production($clientId, $secret);

// Intermediary (production)
$client = $factory->intermediary($clientId, $secret, $taxpayerTin);

// Intermediary (sandbox)
$client = $factory->intermediarySandbox($clientId, $secret, $taxpayerTin);

// Custom options
$client = $factory->make($clientId, $secret, $baseUrl, $httpClient, $options);
```

## Minimal `.env` for Production

```env
MYINVOIS_CLIENT_ID=your-client-id
MYINVOIS_CLIENT_SECRET=your-client-secret
MYINVOIS_BASE_URL=https://myinvois.hasil.gov.my
MYINVOIS_AUTH_URL=https://api.myinvois.hasil.gov.my
MYINVOIS_SUPPLIER_TIN=C1234567890
MYINVOIS_PRIVATEKEY_PATH=/path/to/cert.p12
```

## Related

- [[overview#two-domain-architecture]] — why there are two URLs
- [[api/authentication]] — token and auth flow
- [[operations/known-quirks#ssl-verification]]

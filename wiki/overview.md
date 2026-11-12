---
tags: [overview, project]
updated: 2026-11-12
---

# MyInvois PHP Client — Overview

A PHP client library for Malaysia's **MyInvois** e-invoicing system, operated by LHDN (Hasil). Businesses are legally required to submit tax documents — invoices, credit notes, debit notes, refund notes — in UBL JSON or XML format to the MyInvois platform before they are legally valid.

## Core Purpose

- Submit UBL-format tax documents to LHDN's MyInvois platform
- Validate taxpayer TINs before using them in documents
- Retrieve documents, check submission status, generate shareable QR codes
- Support both direct taxpayer submissions and ERP intermediary submissions
- Provide a Laravel-native integration (service provider, facade, config publishing)

## Stack

| Layer | Technology |
|-------|-----------|
| Language | PHP |
| HTTP Client | `guzzlehttp/guzzle` |
| Cache | PSR `CacheRepository` (Laravel `Illuminate\Contracts\Cache\Repository`) |
| QR Code | `endroid/qr-code` (v3.5.x — uses `writeString()`) |
| DTOs | `spatie/data-transfer-object` |
| Assertions | `webmozart/assert` |
| Date handling | `carbon/carbon` |
| Test suite | PHPUnit 6.0 |
| Static analysis | PHPStan level 8 |
| Formatting | Laravel Pint |

## Hosts: API vs Portal

**Corrected 2026-11-12** (verified against preprod with real credentials). The e-Invoice API and the identity service share the `*-api` host; the portal host does **not** serve `/api/*` (404). Before 1.2.0 the library used the portal host as its API base URL, so every document/taxpayer call failed with 404.

| Purpose | Production | Sandbox |
|---------|-----------|---------|
| **Identity** (`/connect/token`) | `api.myinvois.hasil.gov.my` | `preprod-api.myinvois.hasil.gov.my` |
| **API** (`/api/v1.0/...`) | `api.myinvois.hasil.gov.my` | `preprod-api.myinvois.hasil.gov.my` |
| **Portal** (share/QR links `/{uuid}/share/{longId}`) | `myinvois.hasil.gov.my` | `preprod.myinvois.hasil.gov.my` |

- `MyInvoisClient::SANDBOX_URL` / `PRODUCTION_URL` (and `Config::`) are the API hosts; `PORTAL_SANDBOX_URL` / `PORTAL_PRODUCTION_URL` are the portal hosts.
- `Config::apiBaseUrl()` maps an old portal base URL to its API host in the client constructor, so existing `.env` files keep working.
- Share links use `getPortalBaseUrl()`: `myinvois.portal_url` if set, else `Config::portalBaseUrl(base_url)`.
- The identity URL is still derived from the base URL by detecting `preprod`.

## Three Client Modes

**Direct taxpayer** — the business submits its own documents.
```php
$client = $factory->production($clientId, $secret);
$client = $factory->sandbox($clientId, $secret);
```

**Intermediary** — an ERP/service provider acting on behalf of a taxpayer. Every request (token and API) includes `onbehalfof: {TIN}` header.
```php
$client = $factory->intermediary($clientId, $secret, $taxpayerTin);
// or switch at runtime:
$client->onBehalfOf('C1234567890');
```

**Direct construction** — flexible constructor used in tests and custom setups. Detects argument types in any order (string URL, GuzzleClient, CacheRepository, array config, JSON config string).
```php
$client = new MyInvoisClient($clientId, $secret, $baseUrl, $cache, $config, $httpClient);
```

## Key Files

| File | Purpose |
|------|---------|
| `src/MyInvoisClient.php` | Main orchestrator — uses all API traits |
| `src/MyInvoisClientFactory.php` | Factory for environment-specific clients |
| `src/Http/ApiClient.php` | HTTP transport — auth injection, retry, error mapping |
| `src/Auth/AuthenticationClient.php` | OAuth2 client credentials flow |
| `src/Auth/IntermediaryAuthenticationClient.php` | Intermediary extension — per-TIN token caching |
| `src/Config.php` | Version constants (incl. SVDP 1.2/1.3 window), URL constants, default values |
| `src/Validation/DocumentFieldRulesValidator.php` | LHDN field rules shared by `createDocument()` and `submitDocuments()` |
| `src/Validation/SvdpValidator.php` | SVDP signature/window/issue-date rules |
| `src/Support/UblJson.php` | Exponent-free JSON encode, lossless minify, number-preserving decode |
| `config/myinvois.php` | Laravel publishable config |
| `src/Laravel/MyInvoisServiceProvider.php` | Auto-registered service provider |
| `src/Laravel/Facades/MyInvois.php` | Laravel facade |

## Key Design Decisions

- **Trait-based API layer** — each API domain (`DocumentSubmissionApi`, `TaxpayerApi`, etc.) is a trait mixed into `MyInvoisClient`. Allows clean separation without sub-classing.
- **Cache-first token management** — tokens are cached in Laravel's cache store and refreshed 5 minutes before expiry. `ApiClient` also tracks expiry in-memory with a 60-second buffer.
- **SSL verification disabled** — auth requests use `'verify' => false`. Malaysian government cert infrastructure doesn't validate cleanly in many environments.
- **Per-TIN token cache** — intermediary tokens are cached separately per `{clientId}_{onBehalfOfTin}` combination.
- **Flexible constructor** — supports multiple calling conventions for test ergonomics without needing test-specific factories.
- **Validate locally before submitting (1.2.0)**: documents LHDN would reject (field rules, state 17, SVDP) throw `ValidationException` before any HTTP call. Code lists are copied from the SDK JSON files and never fetched at runtime.
- **Injectable clock**: `MyInvoisClient::setClock()`. Otherwise Carbon (so `Carbon::setTestNow()` works) or the system clock. Date-dependent tests must never use the real date.

## Related Pages

- [[api/authentication]] — token flow in detail
- [[domain/ubl-structure]] — document format
- [[operations/config]] — all config keys and env vars
- [[operations/known-quirks]] — non-obvious platform behaviors

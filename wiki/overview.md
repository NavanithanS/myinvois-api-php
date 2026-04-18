---
tags: [overview, project]
updated: 2026-04-18
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

## Two-Domain Architecture

MyInvois uses **two completely separate base domains**. This is the most common source of configuration confusion.

| Purpose | Production | Sandbox |
|---------|-----------|---------|
| **Identity** (token endpoint) | `api.myinvois.hasil.gov.my` | `preprod-api.myinvois.hasil.gov.my` |
| **API** (document operations) | `myinvois.hasil.gov.my` | `preprod.myinvois.hasil.gov.my` |

Token requests → identity domain at `/connect/token`.  
All document API calls → API domain at `/api/v1.0/...`.

The client auto-derives the identity URL from the base URL by detecting `preprod` in the hostname.

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
| `src/Config.php` | Version constants, URL constants, default values |
| `config/myinvois.php` | Laravel publishable config |
| `src/Laravel/MyInvoisServiceProvider.php` | Auto-registered service provider |
| `src/Laravel/Facades/MyInvois.php` | Laravel facade |

## Key Design Decisions

- **Trait-based API layer** — each API domain (`DocumentSubmissionApi`, `TaxpayerApi`, etc.) is a trait mixed into `MyInvoisClient`. Allows clean separation without sub-classing.
- **Cache-first token management** — tokens are cached in Laravel's cache store and refreshed 5 minutes before expiry. `ApiClient` also tracks expiry in-memory with a 60-second buffer.
- **SSL verification disabled** — auth requests use `'verify' => false`. Malaysian government cert infrastructure doesn't validate cleanly in many environments.
- **Per-TIN token cache** — intermediary tokens are cached separately per `{clientId}_{onBehalfOfTin}` combination.
- **Flexible constructor** — supports multiple calling conventions for test ergonomics without needing test-specific factories.

## Related Pages

- [[api/authentication]] — token flow in detail
- [[domain/ubl-structure]] — document format
- [[operations/config]] — all config keys and env vars
- [[operations/known-quirks]] — non-obvious platform behaviors

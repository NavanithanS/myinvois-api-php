---
tags: [api, taxpayer, tin, validation]
updated: 2026-04-18
---

# Taxpayer API

Two distinct operations: **TIN validation** (verify a TIN is valid for a given taxpayer) and **TIN search** (find a TIN given an ID).

## Key File

`src/Api/TaxpayerApi.php` — trait mixed into `MyInvoisClient`

## TIN Validation

### Endpoint

```
GET /api/v1.0/taxpayer/validate/{tin}?idType={type}&idValue={value}
```

### Usage

```php
$isValid = $client->validateTaxpayerTin('C1234567890', 'NRIC', '123456789012');
// true = valid, false = TIN not found, throws on other errors
```

### Input Validation (before hitting API)

All validated locally before any network call:

**TIN formats** (regex `/^(C\d{10}|IG\d{10,12}|\d{12})$/`):

| Format | Example | Who |
|--------|---------|-----|
| `C` + 10 digits | `C1234567890` | Company |
| `IG` + 10–12 digits | `IG12345678901` | Individual (non-NRIC) |
| 12 digits | `123456789012` | NRIC-based individual |

**Secondary ID types** (case-insensitive input, normalized to uppercase for request):

| Type | Pattern | Notes |
|------|---------|-------|
| `NRIC` | `/^\d{12}$/` | 12 digits |
| `PASSPORT` | `/^[A-Z]\d{8}$/` | Uppercase letter + 8 digits e.g. `A12345678` |
| `BRN` | `/^\d{12}$/` | Business Registration Number |
| `ARMY` | `/^\d{12}$/` | Army ID |

Passport additionally validates that the first character is uppercase (`ctype_upper`).

### Result Caching

| Cache key | `myinvois_tin_validation_` + SHA-256(`"{tin}:{idType}:{idValue}"`) |
|-----------|------------------------------------------------------------------|
| TTL | 86400 seconds (24 hours) |
| Cached? | Only `true` (valid) results — `false` (not found) is never cached |
| Bypass | Pass `$useCache = false` as fourth argument |

24-hour TTL reflects LHDN's guidance to call this API sparingly — cache results in your ERP.

### Error Handling

| API response | Library behaviour |
|-------------|------------------|
| 200 | Cache result, return `true` |
| 404 | Return `false` (not an exception) |
| Any other error | Re-throw `ApiException` |

## TIN Search

### Endpoint

```
GET /api/v1.0/taxpayer/search/tin?idType={type}&idValue={value}
```

### Usage

```php
$response = $client->getTaxpayerTin('NRIC', '123456789012');
```

Rate limited to **60 requests/minute** (`createRateLimitConfig('searchTin', 60, 60)`).

## Taxpayer QR Code Info

```php
$info = $client->getTaxpayerInfoFromQr(string $qrCodeText);
// GET /api/v1.0/taxpayers/qrcodeinfo/{qrCodeText}
```

Accepts a decoded Base64 QR code text string (not a URL). Returns taxpayer details. Throws `ValidationException` on empty input.

## Related

- [[domain/tax-codes#tin-formats]] — TIN format reference
- [[domain/tax-codes#secondary-id-types]] — ID type patterns
- [[operations/rate-limiting]] — rate limit details
- [[operations/known-quirks#tin-validation-caching]]

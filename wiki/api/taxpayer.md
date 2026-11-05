---
tags: [api, taxpayer, tin, validation]
updated: 2026-11-05
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
| `PASSPORT` | `/^[A-Za-z0-9]{1,12}$/` | 1–12 letters/digits (LHDN limit from 24 Oct 2026) |
| `BRN` | `/^\d{12}$/` | Business Registration Number |
| `ARMY` | `/^\d{12}$/` | Army ID |

Before 1.2.0 PASSPORT required `[A-Z]` + 8 digits and an uppercase first letter; that check was removed because LHDN now accepts any passport up to 12 characters.

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

### TIN + BRN cross-check (since 1 Aug 2026)

SDK release note 12 Jun 2026: from **1 Aug 2026** Validate TIN also checks that the TIN and the Business Registration Number belong together. Expect **more `false` (404) results** for `idType = BRN` when the buyer's BRN on record with HASiL is wrong or out of date, even if the TIN alone is valid. Nothing changed in the library. Get accurate BRNs from buyers and ask them to update HASiL. Because `false` is never cached, a corrected BRN takes effect on the next call.

## TIN Search

### Endpoint

```
GET /api/v1.0/taxpayer/search/tin?taxpayerName={name}&idType={type}&idValue={value}&fileType={1|2}
```

### Usage (since 1.2.0)

```php
$client->searchTaxpayerTin('ABC Sdn Bhd');                               // name only
$client->searchTaxpayerTin(null, 'BRN', '201901234567');                 // id only
$client->searchTaxpayerTin(null, 'NRIC', '770625015324', 1);             // id + fileType (1=individual/IG, 2=non-individual)
$client->searchTaxpayerTin('ABC Sdn Bhd', 'BRN', '201901234567', 2);     // all (AND search)
// => ['tin' => 'C1234567890']
```

The SDK allows only these combinations, and the library checks them before any HTTP call (`ValidationException`):

| Rule | Error key |
|------|-----------|
| `taxpayerName` required unless both `idType` + `idValue` given | `taxpayerName` |
| `idType` and `idValue` must come together | `idType` / `idValue` |
| `fileType` ∈ {1, 2} and **only with idType + idValue**. The SDK lists no name+fileType combination | `fileType` |
| ID type and value use the same patterns as TIN validation (see below) | `idType` / `idValue` |

API outcomes: **200** `{tin}`; **400** `BadArgument` (bad parameter set) or *"Search criteria in not conclusive and more than one TIN can be found"*, so add more criteria; **404** no match.

`getTaxpayerTin($idType, $idValue)` is **deprecated** (1.2.0). It keeps its old behaviour (no local ID-format checks; `InvalidArgumentException` on empty value) and uses the same request path.

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

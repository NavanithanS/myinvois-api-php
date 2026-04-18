---
tags: [operations, quirks, gotchas, decisions]
updated: 2026-04-18
---

# Known Quirks & Non-Obvious Behaviors

Non-obvious platform constraints, design decisions, and gotchas. When something doesn't work as expected, check here first.

---

## AllowanceCharge Two-Entry Requirement

MyInvois requires **exactly 2 AllowanceCharge entries** when no dynamic charges are provided — one with `ChargeIndicator: false` (discount) and one with `ChargeIndicator: true` (surcharge). Both at `0.00 MYR` with reason `"NA"`.

Omitting `AllowanceCharge` entirely, or including only one entry, results in API rejection. The `buildAllowanceCharges()` default handles this automatically when `allowanceCharges` is absent from the request.

When dynamic `allowanceCharges` are provided, any count is acceptable. Empty reason strings are coerced to `"NA"`.

---

## XAdES Signing Passphrase

The PKCS#12 private key in `createDocument()` is loaded with hardcoded passphrase `"BioEMyInvois"`:

```php
openssl_pkcs12_read($privateKey, $certs, "BioEMyInvois");
```

This is specific to the LHDN-provisioned signing certificate for this integration. If the cert changes or a different cert is used, this passphrase must be updated in `createDocument()`.

---

## `dd()` in Production Code

`createDocument()` contains:

```php
if (!openssl_pkcs12_read($privateKey, $certs, $passphrase)) {
    dd(openssl_error_string());
}
```

`dd()` (dump-and-die) kills the Laravel process and outputs raw debug data on cert failure. This is a Laravel dev utility — it will expose internal state in production if the cert file is malformed, inaccessible, or the passphrase is wrong.

---

## SSL Verification Disabled

`'verify' => false` in `AuthenticationClient::executeAuthRequest()`. The MyInvois identity server's TLS certificate chain doesn't validate cleanly in many server environments (missing intermediate CA, non-standard chain). Do not re-enable without testing against both sandbox and production — it will cause 100% auth failures in environments without the correct CA bundle.

---

## `createDocument()` vs `submitDocuments()`

Two entirely different submission paths:

| `createDocument(Request $request)` | `submitDocuments(array $documents)` |
|-----------------------------------|-------------------------------------|
| Takes a Laravel `Request` | Takes pre-built document arrays |
| Builds full UBL JSON from scratch | Validates pre-built documents |
| Embeds XAdES digital signature | No signing — caller is responsible |
| Calls `submitDocument()` at the end | Posts directly to submission endpoint |
| Specific to the invoice flow | General-purpose, supports JSON and XML |

Also note: `DocumentSubmissionApi::submitDocument(ApiClient $apiClient, array $document)` takes an `ApiClient` as first argument — distinct from `MyInvoisClient::submitDocument($updatedDocument, $authResponse)` which takes the document and auth response. Same method name, different signatures.

---

## Duplicate Submission Window

Resubmitting the same `codeNumber` within **10 minutes** returns HTTP 422 `DuplicateSubmission`. This is a server-side platform constraint — cannot be bypassed. You must either wait 10 minutes or use a different `codeNumber`.

---

## `getDocument()` Status Restriction

`GET /api/v1.0/documents/{uuid}/raw` returns HTTP 404 for documents in `Invalid` or `Submitted` (still processing) status. The error message from the API includes `"invalid status"` or `"submitted status"` to distinguish from a genuine not-found.

The library re-throws with a helpful message: "Document exists but has invalid status. Use `getDocumentDetails()` instead."

---

## longId Required for QR Code

`longId` is only populated in the document response after the document reaches `Valid` status. `generateQrCode()` calls `getDocumentLongId()` which will throw `ValidationException` if `longId` is absent — i.e. QR codes cannot be generated for pending, submitted, or invalid documents.

---

## Service Tax Rate: 6% in Code, 8% Since 2024

`getTaxCategoryId(float $taxRate)` maps rate `6` → category `"02"` (Service Tax). A comment says "adjusted from old 8%". Malaysia actually raised service tax from 6% to 8% in March 2024. Passing `8` falls through to `"01"` (Sales Tax default) — incorrect for service tax at 8%.

If submitting service-taxed documents at 8%, either:
- Pass rate as `6` and accept the semantic mismatch, or
- Update `getTaxCategoryId()` to explicitly map `8 → "02"`

Check current LHDN guidance before deciding.

---

## Default MSIC Code

`createDocument()` defaults to MSIC `68109` ("Real estate activities with own or leased property n.e.c.") when `supplierMsicCode` is not in the request. This is clearly wrong for most businesses. Always supply `supplierMsicCode` and `supplierMsicDescription` explicitly.

---

## TaxpayerApi Included Twice

`MyInvoisClient` includes `use TaxpayerApi` twice — once around line 65 in the trait list and again at line 115. PHP deduplicates trait methods silently, so no runtime error occurs. It's a harmless code smell but can cause confusion when searching for where methods are defined.

---

## Email Silently Dropped on Validation Failure

Both buyer and supplier email fields in `createDocument()` are validated with `FILTER_VALIDATE_EMAIL`. If invalid, the email is set to an empty string and omitted from the `Contact` block via `array_filter`. No exception or warning is thrown — invalid emails silently disappear from the document.

---

## Flexible Constructor Argument Order

`MyInvoisClient::__construct()` accepts the optional arguments (`$baseUrl`, `$cache`, `$config`, `$httpClient`) in any order via type detection. A string starting with `http(s)://` is treated as the base URL; a JSON-decodable string as config; `GuzzleClient` and `CacheRepository` instances are matched by type. This was built to support multiple calling conventions across tests without test-specific factories.

---

## Intermediary Token Cache Key Is Per-TIN

Intermediary tokens are cached separately per `{clientId}_{onBehalfOfTin}` — not shared across TINs for the same client. Switching TINs via `onBehalfOf()` clears only the previous TIN's cached token. This means warming up multiple TINs requires one token request per TIN.

---

## Related

- [[api/authentication#ssl-verification]]
- [[domain/signing]] — XAdES signature details
- [[domain/ubl-structure#allowancecharge]] — AllowanceCharge structure
- [[domain/tax-codes#tax-category-ids]] — service tax rate mapping

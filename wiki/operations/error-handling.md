---
tags: [operations, errors, exceptions]
updated: 2026-11-12
---

# Error Handling

## Exception Hierarchy

```
\Exception
  └── \RuntimeException
        ├── ValidationException     — invalid input before or after API call
        ├── AuthenticationException — auth failures (token, credentials, scope)
        ├── ApiException            — API-level errors (4xx/5xx from MyInvois)
        └── NetworkException        — connectivity failures (no response)
\RuntimeException
  └── SigningCertificateException  — createDocument() cannot load the PKCS#12 cert (since 1.2.0)
```

All exceptions are in `src/Exception/`.

## When Each Is Thrown

### `ValidationException`

Thrown for **input that is invalid before hitting the API** or for specific API errors that map to client-side mistakes:

- TIN format invalid
- ID type/value invalid
- Submission size exceeded
- Duplicate code numbers in batch
- `BadStructure` or `MaximumSizeExceeded` API errors (400)
- `DuplicateSubmission` API error (422)
- Unauthorized submitter (403 on submission)
- Since 1.2.0, raised locally before any HTTP call:
  - **LHDN field rules** (`DocumentFieldRulesValidator`). Every violation is in one exception with code 422; `getErrors()` maps each UBL path (prefixed `documents[i].` in `submitDocuments()`) to its messages. See [[domain/ubl-structure#lhdn-field-rules-enforced-locally-since-120]].
  - **State code** in `createDocument()`: missing/unknown, or `17` without a consolidated buyer. Keys `buyerState`/`supplierState`.
  - **SVDP**: `documentVersion`, `issueDate`/`issueTime`, and 1.2/1.3 signature or programme-window mismatches (`*.InvoiceTypeCode@listVersionID`).
  - **Search**: invalid `classificationCode`. **TIN search**: bad parameter combinations in `searchTaxpayerTin()`.
  - The field rules (not SVDP) can be switched off: `myinvois.validation.field_rules` or `setFieldRulesValidation(false)`. See [[operations/known-quirks#field-rules-can-be-switched-off-since-120]].

### `SigningCertificateException`

Since 1.2.0 (replaces `die()`/`dd()`). Thrown by `createDocument()` before any HTTP call when `myinvois.privatekey_path` is unset, the file is missing/unreadable/empty, or it cannot be opened as PKCS#12 with the expected passphrase. Extends `\RuntimeException`, not `ValidationException`.

Constructor: `ValidationException(string $message, array $errors = [], int $code = 0)`.

### `AuthenticationException`

Thrown for any token or credential failure:

- Invalid client ID/secret (401 on token request)
- Token scope mismatch
- Intermediary not authorized for taxpayer (403)
- TIN not set before intermediary auth call

### `ApiException`

Thrown for API-level failures that aren't validation or auth:

- HTTP 400, 404, 422, 429, 5xx from the API
- Invalid response format (missing required fields)
- Rate limit exceeded (client-side, code 429)

### `NetworkException`

Thrown when a Guzzle request fails with no HTTP response — DNS failures, connection timeouts, TCP errors.

## ApiClient Error Pipeline

`ApiClient::handleRequestException()` maps HTTP status codes to exceptions:

| HTTP code | Exception | Notes |
|----------|-----------|-------|
| 400 | `ApiException(400)` | Message from `body.message` or `body.error.details[0].message` |
| 404 | `ApiException(404)` | Message from `body.message` |
| 401 | `AuthenticationException(401)` | Also clears `accessToken` and `tokenExpires` |
| 422 | `ApiException(422)` | Surfaced to feature layers for normalization |
| 429 | `ApiException(429)` | "Rate limit exceeded" |
| 5xx | `ApiException(code)` | Message from `body.message` or `body.error` or reason phrase |
| No response | `NetworkException` | Network error with original Guzzle message |

Feature-layer traits (`DocumentSubmissionApi`, `DocumentRetrievalApi`) may further wrap or re-map these for domain-specific messages.

## TaxpayerApi Special Case

`validateTaxpayerTin()` catches `ApiException` with code 404 and returns `false` instead of throwing. This is intentional — "TIN not found" is a valid negative result, not an error.

## Empty Response Handling

`ApiClient::handleResponse()` treats an empty response body as `['status' => 200]`. This handles API endpoints that return 200 with no body (e.g. some PUT/DELETE operations).

## Related

- [[api/authentication#error-mapping]] — auth-specific error table
- [[api/document-submission#error-normalization]] — submission error mapping
- [[api/taxpayer]] — 404 as false, not exception

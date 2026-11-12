---
tags: [api, documents, retrieval]
updated: 2026-11-12
---

# Document Retrieval

Two distinct retrieval endpoints serve different purposes and work in different document states.

## Key Files

| File | Purpose |
|------|---------|
| `src/Api/DocumentRetrievalApi.php` | `getDocument()`, `getDocumentShareableUrl()` |
| `src/Api/DocumentDetailsApi.php` | `getDocumentDetails()`, `getDocumentValidationResults()`, `generateDocumentPublicUrl()` |

## Endpoint Comparison

| | `getDocument()` | `getDocumentDetails()` |
|--|-----------------|----------------------|
| Endpoint | `GET /api/v1.0/documents/{uuid}/raw` | `GET /api/v1.0/documents/{uuid}/details` |
| Works for statuses | Valid, Cancelled, Rejected | All statuses incl. Invalid, Submitted |
| Returns | Full UBL document content + metadata | Metadata only + `validationResults` |
| Use when | You need the document content | Checking status/validation of any document |
| Receivers | Receivers can access Valid/Cancelled | Receivers can only access Valid/Cancelled |

**Rule of thumb**: always try `getDocumentDetails()` first when checking status; use `getDocument()` only when you need the raw UBL content.

If `getDocument()` returns 404 with "invalid status" in the message, switch to `getDocumentDetails()`. The error message from the API distinguishes genuine not-found from status-restricted access.

## Response Fields

Both methods return a normalized array. All date fields are mapped to `DateTimeImmutable` objects.

| Field | Type | Notes |
|-------|------|-------|
| `uuid` | string | Document UUID |
| `submissionUid` | string | Parent submission UID |
| `longId` | ?string | 40+ char uppercase alphanumeric — only present after validation |
| `internalId` | string | Issuer's internal reference |
| `typeName` | string | e.g. "Invoice" |
| `typeVersionName` | string | e.g. "Version 1" |
| `typeVersionNumber` | ?string | e.g. "1.1" / "1.3". Added by LHDN on 25 Sep 2026 (Production 24 Oct 2026). Both methods return `null` when an older response doesn't have it. Use this, not `typeVersionName`, to detect SVDP versions 1.2/1.3 |
| `issuerTin` | string | Supplier TIN |
| `issuerName` | string | Supplier name |
| `receiverId` | ?string | Buyer TIN |
| `receiverName` | ?string | Buyer name |
| `dateTimeIssued` | DateTimeImmutable | |
| `dateTimeReceived` | DateTimeImmutable | When MyInvois received it |
| `dateTimeValidated` | ?DateTimeImmutable | |
| `totalExcludingTax` | float | |
| `totalDiscount` | float | |
| `totalNetAmount` | float | |
| `totalPayableAmount` | float | |
| `status` | string | See [[domain/tax-codes#document-statuses]] |
| `cancelDateTime` | ?DateTimeImmutable | |
| `rejectRequestDateTime` | ?DateTimeImmutable | |
| `documentStatusReason` | ?string | Reason for current status |
| `validationResults` | array | `getDocumentDetails()` only |

## longId and Shareable URLs

`longId` is a 40+ character alphanumeric string that **contains lower-case letters** (e.g. `848K10159P2R8V5WAF3QG67M10mbBOzD1794441448`, preprod 2026-11-12). It is only present once a document reaches `Valid` status (empty string when Invalid).

Document UUIDs and submission UIDs are **26** upper-case alphanumerics (e.g. `A284SAC7CQ8Y5EDXAF3QG67M10`). Before 1.2.0 `UuidValidationTrait` required exactly 15, so `getDocument()` / `getDocumentDetails()` rejected every real UUID; it now accepts 15-32.

Shareable URL format (portal host, not the API host):
```
{portal}/{uuid}/share/{longId}
```

Two methods generate this URL:
- `getDocumentShareableUrl(string $uuid, string $longId)` — on `DocumentRetrievalApi`
- `generateDocumentPublicUrl(string $uuid, string $longId)` — on `DocumentDetailsApi`

Both validate `longId` format with `/^[A-Za-z0-9\s]{40,}$/` and use `getPortalBaseUrl()`.

## QR Code Generation

```php
$qrData = $client->generateQrCode($uuid);
// returns: "data:image/png;base64,..."
```

Flow:
1. Calls `getDocumentLongId($uuid)` → `getDocument($uuid)` → extracts `longId`
2. Constructs `{portal}/{uuid}/share/{longId}` URL
3. Generates 100px QR with 10px margin using `endroid/qr-code` v3.5.x
4. Returns `data:image/png;base64,...` string

Throws `ValidationException` if `longId` is absent (document not yet valid).

**Note:** `endroid/qr-code` v3.5.x uses `writeString()` — not `write()` or `generate()`. Do not upgrade without testing.

## Taxpayer QR Code Info

```php
$info = $client->getTaxpayerInfoFromQr(string $qrCodeText);
// GET /api/v1.0/taxpayers/qrcodeinfo/{qrCodeText}
```

Takes decoded Base64 QR text (not the URL) and returns taxpayer details.

## Related

- [[api/submission-status]] — checking submission processing status
- [[domain/tax-codes#document-statuses]] — status values
- [[operations/known-quirks#longid-required-for-qr]]

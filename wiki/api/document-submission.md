---
tags: [api, submission, documents]
updated: 2026-04-18
---

# Document Submission

## Endpoint

```
POST /api/v1.0/documentsubmissions
Content-Type: application/json
```

## Key Files

| File | Purpose |
|------|---------|
| `src/Api/DocumentSubmissionApi.php` | Submission trait — validation, retry, submission logic |
| `src/MyInvoisClient.php` | `createDocument()`, `submitDocument()`, `submitInvoice()` |
| `src/Config.php` | Version constants for all document types |

## Two Submission Paths

### High-level: `createDocument(Request $request)`

Takes a Laravel `Request`, builds the full UBL JSON from scratch including XAdES digital signature, then submits. This is the batteries-included path for a standard invoice flow.

```
Request (HTTP)
  → createDocument()         reads all fields from Request
  → load PKCS#12 cert        config('myinvois.privatekey_path')
  → build $this->document    UBL JSON structure
  → compute SHA-256 digest   → certDoc
  → build SignedProperties   → certProps digest
  → RSA-SHA256 sign          → certValue
  → merge signature into document
  → submitDocument()
  → POST /api/v1.0/documentsubmissions
```

See [[domain/signing]] for the full XAdES signing flow.

### Low-level: `submitDocuments(array $documents, $format)`

Takes pre-built documents with required fields. Handles batch submission with validation and retry.

```php
$client->submitDocuments([
    [
        'document'     => $base64EncodedUblJson,
        'documentHash' => hash('sha256', $ublJson),   // hex, 64 chars
        'codeNumber'   => 'INV-2024-001',
    ]
]);
```

## Request Payload Format

```json
{
  "documents": [
    {
      "format": "JSON",
      "document": "<base64-encoded UBL>",
      "documentHash": "<sha256-hex>",
      "codeNumber": "INV-2024-001"
    }
  ]
}
```

- `format`: `"JSON"` or `"XML"`
- `document`: base64-encoded UBL document (minified first)
- `documentHash`: lowercase hex SHA-256 of the **raw** (pre-base64) document string
- `codeNumber`: `[A-Za-z0-9-]+` only — alphanumeric and hyphens

## Limits

| Constraint | Value |
|-----------|-------|
| Max documents per submission | 100 |
| Max size per document | 300 KB |
| Max total submission size | 5 MB |
| Duplicate submission window | 10 minutes (same `codeNumber` rejected) |
| Rate limit | 50 submissions per hour |

## Submission Response

```json
{
  "submissionUID": "XXXXXXXXXXXXXXXXXXXX",
  "acceptedDocuments": [
    { "uuid": "...", "invoiceCodeNumber": "INV-2024-001" }
  ],
  "rejectedDocuments": [
    { "invoiceCodeNumber": "INV-2024-002", "error": { "code": "...", "message": "..." } }
  ]
}
```

A submission can **partially succeed** — some documents accepted, others rejected in the same call. Always check both arrays. A non-empty `rejectedDocuments` does not throw an exception; it is logged as an error.

## Document Types and Version Codes

| Document type | `invoiceTypeCode` | Current version | Supported versions |
|-------------|------------------|----------------|--------------------|
| Invoice | `01` | `1.1` | `1.0`, `1.1` |
| Credit Note | `02` | `1.1` | `1.0`, `1.1` |
| Debit Note | `03` | `1.1` | `1.0`, `1.1` |
| Refund Note | `04` | `1.1` | `1.0`, `1.1` |

Use `Config::isVersionSupported($docType, $version)` to validate before submission.

## Retry Logic

`submitDocuments()` retries up to 3 times with `sleep(pow(2, $attempt))` exponential backoff (1s, 2s, 4s).

| HTTP status | Retried? |
|------------|---------|
| 429 | Yes |
| 500–599 | Yes |
| 400, 403, 422 | No — throws immediately |

## Error Normalization

`handleSubmissionError()` maps specific API error messages to domain exceptions before retrying:

| API error code | Thrown exception | Message |
|---------------|-----------------|---------|
| 400 `BadStructure` | `ValidationException` | "Invalid submission structure" |
| 400 `MaximumSizeExceeded` | `ValidationException` | "Maximum submission size exceeded" |
| 403 | `ValidationException` | "Not authorized to submit documents for this taxpayer" |
| 422 `DuplicateSubmission` | `ValidationException` | "Please wait 10 minutes before resubmitting" |

## Related

- [[domain/ubl-structure]] — how to build the UBL JSON document
- [[domain/signing]] — XAdES signature embedded in the document
- [[api/submission-status]] — polling after submission
- [[operations/known-quirks#duplicate-submission-window]]
- [[operations/known-quirks#createdocument-vs-submitdocuments]]

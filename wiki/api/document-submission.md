---
tags: [api, submission, documents]
updated: 2026-11-12
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

### Local field-rule validation (since 1.2.0)

Both paths run `DocumentFieldRulesValidator`. `createDocument()` runs it on the built UBL before authenticating or loading the certificate. `submitDocuments()` runs it on every JSON document, after the structural checks and before any HTTP call. All violations come back in one `ValidationException`. See [[domain/ubl-structure#lhdn-field-rules-enforced-locally-since-120]].

`submitDocuments()` minifies JSON losslessly (whitespace only, see [[domain/ubl-structure#amounts-and-json-encoding]]).

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

LHDN returns **`submissionUid`** (lower-case `id`; verified on preprod 2026-11-12), which is `null` when every document is rejected. Before 1.2.0 the library required `submissionUID` and threw `ApiException("Invalid response format from submission endpoint")` on every real 202, even though LHDN had accepted the documents. Since 1.2.0 `submitDocuments()` returns both `submissionUid` and `submissionUID` (same value) and defaults both document lists to `[]`.

```json
{
  "submissionUid": "EAKP79H5SHEKDKSGAF3QG67M10",
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
| Invoice | `01` | `1.1` | `1.0`, `1.1` (+ SVDP `1.2`, `1.3` until 31 Dec 2027) |
| Credit Note | `02` | `1.1` | `1.0`, `1.1` (+ SVDP) |
| Debit Note | `03` | `1.1` | `1.0`, `1.1` (+ SVDP) |
| Refund Note | `04` | `1.1` | `1.0`, `1.1` (+ SVDP) |

Use `Config::isVersionSupported($docType, $version, ?$now)` to validate before submission. The `*_SUPPORTED_VERSIONS` constants list only the regular versions. SVDP versions are added inside `isVersionSupported()` with the date check, so code that does `in_array(..., *_SUPPORTED_VERSIONS)` never accepts SVDP after the programme ends.

Self-billed types (`11`–`14`) also have SVDP 1.2/1.3 in the SDK, but `Config` doesn't model them yet.

## SVDP: Voluntary Disclosure Programme (since 1.2.0)

Source: SDK release note 8 Jul 2026 and https://sdk.myinvois.hasil.gov.my/types/.

| Version | Structure | Signature | Usable |
|---------|-----------|-----------|--------|
| `1.2` | same as 1.0 | **none** (no `UBLExtensions`/`Signature`) | until **31 Dec 2027** |
| `1.3` | same as 1.1 | **required** | until **31 Dec 2027** |

The SDK lists both versions for all 8 document types, self-billed included. During SVDP the issue date/time may be **backdated** to the taxpayer's phased mandatory implementation date (earliest **1 Aug 2024**).

Constants: `Config::SVDP_VERSIONS`, `SVDP_EARLIEST_ISSUE_DATE = '2024-08-01'`, `SVDP_END_DATE = '2027-12-31'`. The end date is inclusive and evaluated in `Asia/Kuala_Lumpur` (`Config::isSvdpActive($now)`), so the programme ends at 2027-12-31T16:00:00Z.

**`createDocument()`** always signs, so it accepts `documentVersion` `1.1` (default) or `1.3`. `1.0`/`1.2` throw and point you to `submitDocuments()`. `issueDate` (`YYYY-MM-DD`) and `issueTime` (`HH:MM:SS[Z]`, UTC, defaults to now) are accepted **only with 1.3**. The issue date must be ≥ 2024-08-01, not in the future, and the programme must still be active. `SigningTime` stays the real time.

```php
$client->createDocument(new Request([... , 'documentVersion' => '1.3', 'issueDate' => '2025-03-15', 'issueTime' => '09:30:00Z']));
```

**`submitDocuments()` / `submitDocument()`**: for JSON documents whose `InvoiceTypeCode@listVersionID` is 1.2/1.3, `SvdpValidator` rejects:
- 1.2 that carries `UBLExtensions` or `Signature`
- 1.3 that lacks either one
- either version once the programme has ended

Errors are keyed `documents[i].Invoice.InvoiceTypeCode@listVersionID` and are reported together with the field-rule violations. The library does **not** check the 2024-08-01 earliest date on this path. The official 1.2 sample has `IssueDate` 2024-07-23, and the real earliest date depends on the taxpayer's phase. LHDN enforces it.

**Clock**: `MyInvoisClient::setClock(callable)` overrides "now". Otherwise the client uses `Carbon::now('UTC')` when Carbon is installed (so `Carbon::setTestNow()` works), or the system clock.

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

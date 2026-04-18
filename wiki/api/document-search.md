---
tags: [api, documents, search]
updated: 2026-04-18
---

# Document Search

Search for documents across the taxpayer's account with date, status, type, and direction filters.

## Key File

`src/Api/DocumentSearchApi.php`

## Endpoint

```
GET /api/v1.0/documents/search
```

## Usage

```php
$results = $client->searchDocuments([
    'submissionDateFrom' => '2024-01-01T00:00:00Z',
    'submissionDateTo'   => '2024-01-31T23:59:59Z',
    'status'             => 'Valid',
    'documentType'       => DocumentTypeEnum::INVOICE,
    'invoiceDirection'   => 'Sent',
    'pageNo'             => 1,
    'pageSize'           => 50,
]);
```

## Filter Parameters

| Parameter | Type | Constraints | Notes |
|-----------|------|-------------|-------|
| `submissionDateFrom` | string/DateTimeInterface | Max 30-day range | ISO 8601, formatted to `Y-m-d\TH:i:s\Z` |
| `submissionDateTo` | string/DateTimeInterface | Max 30-day range | |
| `issueDateFrom` | string/DateTimeInterface | Max 30-day range | |
| `issueDateTo` | string/DateTimeInterface | Max 30-day range | |
| `status` | string/DocumentStatusEnum | Valid values only | `Valid`, `Invalid`, `Submitted`, `Cancelled`, `Pending`, `Rejected` |
| `documentType` | int/DocumentTypeEnum | Valid codes only | 4=Invoice, 11=CreditNote, 12=DebitNote |
| `invoiceDirection` | string | `Sent` or `Received` | |
| `uuid` | string | | Filter by specific UUID |
| `searchQuery` | string | `[A-Za-z0-9_\- ]+` | Text search — no special chars |
| `pageNo` | int | ≥ 1 | |
| `pageSize` | int | 1–100 | |

**Date range requirement**: at least one complete date range (both `submissionDateFrom`+`submissionDateTo` OR both `issueDateFrom`+`issueDateTo`) must be provided. Providing only one bound of a range throws `ValidationException`.

Both date range types cannot exceed **30 days**. Validated by `DateValidationTrait::validateDateRange()`.

## Response

```json
{
  "documents": [ /* array of DocumentSearchResult DTOs */ ],
  "metadata": {
    "totalCount": 42,
    "totalPages": 1,
    "hasNext": false
  }
}
```

Documents are normalized to `DocumentSearchResult` DTOs (`src/Data/DocumentSearchResult.php`). Missing fields are defaulted:

| Missing field | Default |
|--------------|---------|
| `createdByUserId` | `"unknown"` |
| `submissionChannel` | `"ERP"` |
| `supplierTIN` | `"C0000000000"` |
| `supplierName` | `"Unknown Supplier"` |
| `buyerName` | `"Unknown Buyer"` |
| `buyerTIN` | `"C0000000000"` |

## Related

- [[domain/tax-codes#document-statuses]] — valid status values
- [[domain/tax-codes#document-types]] — DocumentTypeEnum codes
- [[api/document-retrieval]] — retrieve full document by UUID

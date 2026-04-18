---
tags: [api, submission, status, polling]
updated: 2026-04-18
---

# Submission Status

After submitting documents, poll the submission status to determine when processing is complete and whether documents were accepted or rejected.

## Key File

`src/Api/SubmissionStatusApi.php`

## Endpoint

```
GET /api/v1.0/documentsubmissions/{submissionId}?pageNo={n}&pageSize={n}
```

`submissionId` must be uppercase alphanumeric with at least one letter — matches `/^(?=.*[A-Z])[A-Z0-9]+$/`.

## Response Structure

```json
{
  "submissionUid": "XXXXXXXXXXXX",
  "documentCount": 5,
  "dateTimeReceived": "2024-01-01T00:00:00Z",
  "overallStatus": "Valid",
  "documentSummary": [
    {
      "uuid": "...",
      "submissionUid": "...",
      "longId": "...",
      "internalId": "INV-001",
      "typeName": "Invoice",
      "typeVersionName": "1.1",
      "issuerTin": "C1234567890",
      "issuerName": "...",
      "receiverId": "...",
      "receiverName": "...",
      "dateTimeIssued": "...",
      "dateTimeReceived": "...",
      "dateTimeValidated": "...",
      "totalExcludingTax": 100.00,
      "totalDiscount": 0.00,
      "totalNetAmount": 100.00,
      "totalPayableAmount": 100.00,
      "status": "Valid",
      "cancelDateTime": null,
      "rejectRequestDateTime": null,
      "documentStatusReason": null,
      "createdByUserId": "..."
    }
  ]
}
```

## Overall Status Values

| `overallStatus` | Meaning | Is complete? |
|----------------|---------|-------------|
| `Submitted` | Processing in progress | No |
| `Valid` | All documents valid | Yes |
| `Partially Valid` | Some valid, some invalid | Yes |
| `Invalid` | All documents invalid | Yes |

Use `isSubmissionComplete(array $response): bool` to check — returns true for Valid, Partially Valid, Invalid.

## Polling Interval Enforcement

**Minimum 3 seconds** between status checks for the same `submissionId`. This is enforced client-side in `enforcePollingInterval()` using `microtime()` — no cache required. Exceeding it throws `ApiException` with HTTP 429.

Recommended polling strategy:
1. Submit documents → get `submissionUID`
2. Wait 5–10 seconds
3. Poll `getSubmissionStatus(submissionUID)`
4. If `isSubmissionComplete()` is false, wait and retry
5. Check `overallStatus` and `documentSummary[*].status`

## Pagination

`documentSummary` is paginated. Use `pageNo` and `pageSize` (max 100) to navigate.

`getAllSubmissionDocuments(string $submissionId): array` automatically paginates with `pageSize=1` until all documents are retrieved. This bypasses the 3-second polling interval internally (calls `buildQueryParams` directly without `enforcePollingInterval`).

## Failed Document Logging

If any document in `documentSummary` has `status === 'invalid'`, the response is logged as an error with:
- `submissionId`
- `failedCount`
- Per-document: `uuid`, `internalId`, `status`

## Related

- [[api/document-submission]] — the submission call that produces the submissionUID
- [[api/document-retrieval]] — retrieving individual documents after they're validated
- [[domain/tax-codes#document-statuses]] — per-document status values

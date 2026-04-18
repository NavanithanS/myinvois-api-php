---
tags: [domain, tax, codes, malaysia]
updated: 2026-04-18
---

# Malaysian Tax Codes & Reference Data

## Document Types

| `invoiceTypeCode` | Type | `DocumentTypeEnum` | Enum int value |
|-------------------|------|--------------------|---------------|
| `01` | Invoice | `INVOICE` | `4` |
| `02` | Credit Note | `CREDIT_NOTE` | `11` |
| `03` | Debit Note | `DEBIT_NOTE` | `12` |
| `04` | Refund Note | — | — |

**Note:** `DocumentTypeEnum` integer values (`4`, `11`, `12`) are MyInvois internal type IDs used in search/retrieval — **different** from the `invoiceTypeCode` string (`"01"`–`"04"`) embedded in UBL documents.

## Document Versions

Current version: `1.1`. Supported: `1.0`, `1.1` for all document types.

`Config::isVersionSupported($docType, $version)` validates before submission.

## Document Statuses

`DocumentStatusEnum` (string-backed):

| Value | Meaning |
|-------|---------|
| `Valid` | Accepted and validated by LHDN |
| `Invalid` | Rejected during validation |
| `Submitted` | Received, processing in progress |
| `Cancelled` | Cancelled by issuer |
| `Pending` | Awaiting processing |
| `Rejected` | Rejected by receiver |

## Tax Category IDs

Used in `TaxSubtotal.TaxCategory.ID`:

| ID | Tax type | Rate |
|----|---------|------|
| `01` | Sales Tax | 10% |
| `02` | Service Tax | 6% (was 8% pre-2024) |
| `03` | Tourism Tax | varies |
| `04` | High-Value Goods Tax | varies |
| `05` | Sales Tax on Low Value Goods | varies |
| `06` | Exempt / zero-rate | 0% |

Tax scheme for all: `{"ID": [{"_": "OTH", "schemeID": "UN/ECE 5153", "schemeAgencyID": "6"}]}`

`getTaxCategoryId(float $taxRate)` mapping in `MyInvoisClient`:

| Input rate | Category returned |
|-----------|-----------------|
| ≤ 0 | `"06"` (Exempt) |
| = 6 | `"02"` (Service Tax) |
| = 10 | `"01"` (Sales Tax) |
| any other | `"01"` (default Sales Tax) |

> **Warning:** Malaysia raised service tax from 6% to 8% in March 2024. The mapping still uses `6 → "02"`. Passing `8` falls through to `"01"` (Sales Tax). Check current LHDN guidance and update if needed.

## TIN Formats

Validated by `TaxpayerApi::validateTinFormat()` against `/^(C\d{10}|IG\d{10,12}|\d{12})$/`:

| Format | Example | Entity |
|--------|---------|--------|
| `C` + 10 digits | `C1234567890` | Company |
| `IG` + 10–12 digits | `IG12345678901` | Individual (non-NRIC) |
| 12 digits | `123456789012` | NRIC-based individual |

`Config::TIN_PATTERN` (`/^C\d{10}$/`) is more restrictive — the trait pattern is authoritative at runtime.

## Secondary ID Types

Used in TIN validation and party identification `schemeID`:

| Type | Regex pattern | Notes |
|------|--------------|-------|
| `NRIC` | `/^\d{12}$/` | Malaysian identity card |
| `PASSPORT` | `/^[A-Z]\d{8}$/` | Uppercase letter + 8 digits — e.g. `A12345678` |
| `BRN` | `/^\d{12}$/` | Business Registration Number |
| `ARMY` | `/^\d{12}$/` | Army ID |

## State Codes

Used in `PostalAddress.CountrySubentityCode`:

| Code | State |
|------|-------|
| `01` | Johor |
| `02` | Kedah |
| `03` | Kelantan |
| `04` | Melaka |
| `05` | Negeri Sembilan |
| `06` | Pahang |
| `07` | Pulau Pinang / Penang |
| `08` | Perak |
| `09` | Perlis |
| `10` | Selangor |
| `11` | Terengganu |
| `12` | Sabah |
| `13` | Sarawak |
| `14` | WP Kuala Lumpur / Kuala Lumpur |
| `15` | WP Labuan / Labuan |
| `16` | WP Putrajaya / Putrajaya |
| `17` | Not Applicable / NA |

State input handling in `createDocument()`: if input is already a 2-digit numeric string, it's used directly. Otherwise, a name-to-code lookup runs with fallback to `"17"`. Both `"Penang"` and `"Pulau Pinang"` map to `"07"`.

## MSIC Codes

Malaysia Standard Industrial Classification — 5-digit codes, sections A–U. Validated by `src/Validation/MsicValidator.php`.

Special code `00000` = Not Applicable.

Default when not supplied: `68109` ("Real estate activities with own or leased property n.e.c.") — **always supply MSIC explicitly**.

## SST & TTX Schemes

| Scheme | Meaning | When to use `"NA"` |
|--------|---------|-------------------|
| `SST` | Sales and Service Tax registration number | Company not SST-registered |
| `TTX` | Tourism Tax | Almost always — most businesses use `"NA"` |

Country code: always `MYS` (ISO 3166-1 alpha-3) with `listID: "ISO3166-1"`, `listAgencyID: "6"`.

## OAuth Scope

Single scope: `InvoicingAPI` — required for all operations.

## Related

- [[domain/ubl-structure]] — how these codes appear in documents
- [[api/taxpayer]] — TIN validation using ID types
- [[operations/known-quirks#service-tax-rate]]

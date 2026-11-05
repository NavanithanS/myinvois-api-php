---
tags: [domain, tax, codes, malaysia]
updated: 2026-11-05
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

Current version: `1.1`. Supported: `1.0`, `1.1` for all document types, plus SVDP `1.2` (unsigned) / `1.3` (signed) until 31 Dec 2027. See [[api/document-submission#svdp-voluntary-disclosure-programme-since-120]].

`Config::isVersionSupported($docType, $version, ?$now)` validates before submission.

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

`IntermediaryAuthenticationClient::onBehalfOf()` uses its own, wider pattern `/^(C\d{10,12}|IG\d{11,12}|\d{12})$/` — `C` + 10–12 digits was widened deliberately (commit `72ee617`) because real company TINs exceed 10 digits. Tests treat `C12345678901` as valid.

## Secondary ID Types

Used in TIN validation and party identification `schemeID`:

| Type | Regex pattern | Notes |
|------|--------------|-------|
| `NRIC` | `/^\d{12}$/` | Malaysian identity card |
| `PASSPORT` | `/^[A-Za-z0-9]{1,12}$/` | 1–12 letters/digits — LHDN max 12 chars from 24 Oct 2026 (release note 6 Aug 2026). Previously `/^[A-Z]\d{8}$/` |
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

### State code 17 rule (since 30 Apr 2026)

LHDN only accepts `17` (Not Applicable) for **consolidated e-Invoices** (buyer TIN `EI00000000010`) or **addresses outside Malaysia** (non-MYS parties may also send a free-text state name, ≤ 50 chars).

State input handling in `createDocument()` (`MyInvoisClient::resolveStateCode()`), since 1.2.0:

- 1–2 digit numeric input → zero-padded and checked against `01`–`17`.
- Otherwise a trimmed, case-insensitive name/alias lookup (`stateMapping` + `StateCodeValidator` aliases, e.g. `Penang`, `Pulau Pinang`, `Wilayah Persekutuan Labuan`).
- Missing/unknown state → `ValidationException` keyed `buyerState`/`supplierState`. **There is no silent fallback to `17` any more** (pre-1.2.0 it defaulted to `17`, which LHDN now rejects).
- `17` / `Not Applicable` / `NA` is accepted (for either party) only when buyer TIN is `EI00000000010`. Both parties are hard-coded `MYS` in `createDocument()`, so the non-Malaysia case does not arise there.

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

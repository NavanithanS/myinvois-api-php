---
tags: [plan, sdk, release-notes, validation, svdp]
updated: 2026-11-04
status: approved — not started
target_release: 1.2.0
---

# Plan: Adopt MyInvois SDK release notes (Jul 2025 – Sep 2026)

Source: https://sdk.myinvois.hasil.gov.my/sdk-1-0-release/ (reviewed 2026-11-04, latest entry 25 Sep 2026).
Today's state: master at `9752a52`, 217 tests green, CI on PHP 8.2–8.4, last tag `1.1.5`.
Unreleased on master already: `require.php` raised `>=7.1.0` → `>=8.1.0` (commit `be9f02a`).

## Decisions (agreed with the maintainer, 2026-11-04)

| Topic | Decision |
|-------|----------|
| State code 17 fallback | **Throw unless allowed.** Unknown/missing state → `ValidationException`. `17` is accepted only when buyer TIN is `EI00000000010` (consolidated) or the party's country is not `MYS`. |
| Field length / format rules | **Shared validator** used by both `createDocument()` and `submitDocuments()`; collect *all* violations into one `ValidationException` (field-path → messages). |
| SVDP | **Full support**: versions `1.2`/`1.3` accepted; `createDocument()` accepts a version and a backdated issue date with programme guards. |
| Delivery | **One branch** `feat/sdk-2026-updates`, one commit per phase, one PR, then tag **1.2.0** (together with the PHP 8.1 bump). |

## Release-note items → scope

| Item (effective in Production) | Phase |
|-------|-------|
| State code 17 only for consolidated / non-Malaysia (30 Apr 2026) | 1 |
| PASSPORT max 12 chars (24 Oct 2026) | 1 |
| `classificationCode` filter on Search Documents (24 Oct 2026) | 2 |
| `typeVersionNumber` in Get Document / Get Document Details (24 Oct 2026) | 2 |
| Search Taxpayer's TIN: `taxpayerName`, `fileType` 1=IG/individual, 2=non-individual (1 Aug 2025) | 2 |
| Field length/format rules (15 Aug 2026) + other character limits (3 Jul 2026) | 3 |
| Amounts ≤ 26 digits (24 Oct 2026), no scientific notation (30 Apr 2026) | 3 |
| SVDP versions 1.2 (unsigned) / 1.3 (signed), backdated IssueDate, until 31 Dec 2027 (8 Jul 2026) | 4 |
| TIN+BRN cross-check in Validate TIN (1 Aug 2026) — docs only | 5 |
| Not needed: Hectare unit (lib hard-codes `C62`), tax-exempt TaxAmount clarification, SSL renewal, exchange-rate rule (`createDocument()` is MYR-only), 10k search cap | — |

## Phase 0 — Setup

- `git checkout -b feat/sdk-2026-updates` from master. Do **not** commit the maintainer's unrelated uncommitted files (`.gitignore`, `wiki/.obsidian/workspace.json`, `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `.rtk/`, `php71-baseline.neon`, `.phpunit.cache/`).
- Baseline: `vendor/bin/phpunit --no-coverage` → 217 passing.
- Note: `/opt/homebrew/bin/php` is actually PHP 8.4 (CLAUDE.md is stale); `rtk` is not installed. `composer.json` has `config.platform.php = 8.2.0` — keep it.

## Phase 1 — Fixes already enforced in Production

**1a. State code 17** — `src/MyInvoisClient.php` ~L305–328 (`createDocument()`), mapping at ~L275.
- Replace `?? '17'` fallbacks with a helper `resolveStateCode(?string $input, string $party, bool $allow17): string`.
  - Accept 2-digit numeric codes `01`–`17` (validate via `StateCodeValidator`), or names via the existing mapping (trim + case-insensitive).
  - `17` / "Not Applicable" / "NA" allowed only when `$allow17` (buyer TIN `EI00000000010`; both parties are hard-coded `MYS` in `createDocument()`, so non-MYS doesn't arise there yet).
  - Otherwise throw `ValidationException('Invalid state', ['buyerState' => [...]])` naming the received value.
- Tests: known name, numeric code, trailing-space name, unknown name throws, `17` throws for normal buyer, `17` OK for `EI00000000010`.
- **Behaviour change** → CHANGELOG "Changed".

**1b. PASSPORT** — `src/Api/TaxpayerApi.php:42` and message at ~L194.
- Pattern → `/^[A-Za-z0-9]{1,12}$/`; message "Passport number must be 1–12 letters or digits".
- Remove/adjust any letter-prefix check at ~L207 that assumes the old format (read it first).
- Tests: `A12345678`, `K1234567`, `AB1234567890` (12) pass; 13 chars and `A-123` fail.
- Update `wiki/domain/tax-codes.md` Secondary ID table.

## Phase 2 — API additions (backward compatible)

**2a. Search Documents `classificationCode`** — `src/Api/DocumentSearchApi.php` (`$directFields` ~L180, validation ~L105–150).
- Accept string `"001,002"` or array `['001','002']` → send comma-joined string. Validate each code is 3 digits (and, if cheap, exists in `ClassificationValidator`).
- Tests: array and string forms appear in the query string; invalid code throws.

**2b. `typeVersionNumber`** — `src/Api/DocumentDetailsApi.php::mapDocumentDetails()` ~L146.
- Add `'typeVersionNumber' => $response['typeVersionNumber'] ?? null` (optional — older responses lack it). Update docblock shapes in `DocumentDetailsApi` and `DocumentRetrievalApi` (`getDocument()` already returns the raw response).
- Tests: present → mapped; absent → null.

**2c. Search Taxpayer TIN** — `src/MyInvoisClient.php::getTaxpayerTin($idType, $ic)` ~L683.
- Add a new method `searchTaxpayerTin(?string $taxpayerName = null, ?string $idType = null, ?string $idValue = null, ?int $fileType = null): array` with SDK rules: `taxpayerName` required unless idType+idValue given; idType and idValue must come together; `fileType` ∈ {1, 2}. Reuse `TaxpayerApi` ID-type validation.
- Keep `getTaxpayerTin()` as a thin wrapper calling the new method (no break); mark `@deprecated`.
- Tests: query params built correctly; each guard throws.

## Phase 3 — Shared field-rules validator

New `src/Validation/DocumentFieldRulesValidator.php` operating on the **UBL JSON array** (the shape `createDocument()` builds and `submitDocuments()` receives for JSON format; skip XML documents).

Rules (verify each UBL path against the SDK Invoice v1.1 spec, https://sdk.myinvois.hasil.gov.my/documents/invoice-v1-1/ — do not guess):

| Field | Likely UBL path | Rule |
|-------|-----------------|------|
| e-Invoice Code/Number | `Invoice.ID` | ≤ 50 |
| Supplier bank account | `PaymentMeans.PayeeFinancialAccount.ID` | ≤ 150 |
| Certified Exporter auth no. | supplier `AdditionalAccountID` (`schemeAgencyName=CertEX`) | ≤ 300 |
| Incoterms | `Delivery…DeliveryTerms.ID` (verify) | ≤ 3 |
| Frequency of Billing | `InvoicePeriod.Description` | ≤ 50 |
| Supplier business activity desc. | supplier `IndustryClassificationCode@name` | ≤ 300 |
| Payment terms | `PaymentTerms.Note` | ≤ 300 |
| PrePayment reference | `PrepaidPayment.ID` | ≤ 150 |
| All date fields | `IssueDate`, `InvoicePeriod.StartDate/EndDate`, `PrepaidPayment.PaidDate`, … | `YYYY-MM-DD` and a real date (no "N/A") |
| State code | party `CountrySubentityCode` | MYS → valid code list; non-MYS → ≤ 50 |
| Postcode | party/shipping `PostalZone` | MYS → ≤ 5; non-MYS → ≤ 50 |
| Unit of measure | `InvoicedQuantity@unitCode` | in the SDK unit-types list (add `UnitTypeValidator`; source https://sdk.myinvois.hasil.gov.my/codes/unit-types/, include "Hectare") |
| Country / currency / payment mode / tax type | respective codes | reuse existing validators |
| All monetary amounts (invoice + line level, see 6 Aug 2026 note) | every `cbc:*Amount`, `PriceAmount`, `Percent` used as tax rate | plain decimal string/number, ≤ 26 digits, no exponent |
| PASSPORT IDs inside parties | `PartyIdentification.ID@schemeID=PASSPORT` | ≤ 12 |

- Return all violations as `['Invoice.ID' => ['must not exceed 50 characters'], …]` in one `ValidationException`.
- Wire into `DocumentSubmissionApi::validateDocument()` (~L202, JSON only) and the end of `createDocument()` before signing.
- **Amount serialisation in `createDocument()`**: emit amounts via a single formatter (e.g. `number_format($v, 2, '.', '')`) or guarantee floats never hit `json_encode` in exponent form; add a test with `1e15`.
- Tests: one data-provider test per rule (pass + fail), plus a full valid sample (use SDK sample `https://sdk.myinvois.hasil.gov.my/files/sdksamples/1.1-Invoice-Sample.json` saved under `tests/fixtures/`, verify with `magika`).
- **Behaviour change** (stricter local validation) → CHANGELOG "Changed".

## Phase 4 — SVDP support

Facts (from SDK samples `1.2-Invoice-Sample.json` / `1.3-Invoice-Sample.json`): SVDP uses `InvoiceTypeCode@listVersionID` `"1.2"` (no `UBLExtensions`/`Signature`) and `"1.3"` (signed). Backdated `IssueDate` allowed from the phased mandatory date (earliest 1 Aug 2024). Programme ends **31 Dec 2027**.

- `src/Config.php`: add `SVDP_VERSIONS = ['1.2','1.3']`, `SVDP_EARLIEST_ISSUE_DATE = '2024-08-01'`, `SVDP_END_DATE = '2027-12-31'`; add `1.2`,`1.3` to supported versions. **Verify** whether SVDP applies to credit/debit/refund and self-billed types (SDK document-type pages or `GET /api/v1.0/documenttypes`) before extending those lists.
- `Config::isSvdpVersion()`, `Config::isSvdpActive(DateTimeInterface $now)`.
- `isVersionSupported()`: SVDP versions supported only while programme active.
- `createDocument()` (~L394–396): read optional `documentVersion` (default `1.1`) and `issueDate`/`issueTime`.
  - `createDocument()` always signs → allow `1.1` and `1.3` only; `1.0`/`1.2` → clear error pointing to `submitDocuments()`.
  - Backdated `issueDate` only for `1.3`; must be ≥ `SVDP_EARLIEST_ISSUE_DATE`, ≤ now, and programme active. Non-SVDP keeps "now (UTC)".
- `submitDocuments()` / `submitDocument()`: accept `1.2`/`1.3`; if `1.2` and the document contains `UBLExtensions`/`Signature`, or `1.3` without them, throw (mismatch).
- Tests: version gating, date guards (before 2024-08-01, future, after 2027-12-31 via injected clock), signed/unsigned mismatch. Use an injectable clock (or Carbon::setTestNow) — never real time.

## Phase 5 — Docs, wiki, release prep

- Wiki: update `api/document-search.md` (classificationCode), `api/document-retrieval.md` (typeVersionNumber), `api/taxpayer.md` (searchTaxpayerTin, fileType, PASSPORT, BRN cross-check from 1 Aug 2026 → more 404s), `domain/tax-codes.md` (state 17 rule, PASSPORT), `domain/ubl-structure.md` (field limits, amounts), `api/document-submission.md` + new section for SVDP, `operations/known-quirks.md` (remove the state-17 silent fallback if documented; add SVDP window). Bump `updated:`; add `wiki/log.md` entries; mark this plan `status: done`.
- README: short sections for SVDP and new methods.
- CHANGELOG: `## [1.2.0] - <date>` with Added / Changed (state 17 throws, stricter validation, PHP >=8.1) / Deprecated (`getTaxpayerTin`).
- Do **not** tag or publish — maintainer approves the release separately.

## Definition of done

- `vendor/bin/phpunit --no-coverage` green; new tests for every item above.
- `find src tests config -name '*.php' -print0 | xargs -0 -n1 php -l` clean.
- PHPStan error count in `src/` not increased vs baseline (397 total pre-existing; run with `--memory-limit=1G`).
- `composer audit --abandoned=report` clean.
- PR opened from `feat/sdk-2026-updates`; CI green on 8.2/8.3/8.4.

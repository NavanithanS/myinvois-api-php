# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Added
- `Validation\DocumentFieldRulesValidator`, `Validation\UnitTypeValidator`
  (SDK unit list, including Hectare `HAR`), and `Support\UblJson`
  (exponent-free encode, lossless minify).
- **SVDP (e-Invoice Voluntary Disclosure Programme)**: versions `1.2`
  (unsigned) and `1.3` (signed) are accepted until 31 Dec 2027 (Malaysia
  time) via `Config::isVersionSupported()`, `Config::isSvdpActive()` and
  `Config::isSvdpVersion()`. `createDocument()` accepts `documentVersion`
  (`1.1` default, or `1.3`) and, for 1.3 only, a backdated `issueDate` /
  `issueTime` (≥ 2024-08-01, not in the future). `submitDocuments()` and
  `submitDocument()` reject 1.2 documents that carry a signature, 1.3
  documents without one, and both once the programme has ended.
- `MyInvoisClient::setClock(callable)` to inject the current time.
- `searchDocuments()` accepts a `classificationCode` filter, as a
  comma-separated string or an array of 3-digit codes (LHDN, 25 Sep 2026).
- `getDocument()` / `getDocumentDetails()` return `typeVersionNumber`
  (e.g. `"1.1"`). It is `null` when the API omits it.
- `searchTaxpayerTin(?taxpayerName, ?idType, ?idValue, ?fileType)` supports
  name search and the individual/non-individual `fileType` filter
  (1 = IG/individual, 2 = non-individual). The SDK's allowed parameter
  combinations are checked locally.

### Deprecated
- `getTaxpayerTin($idType, $idValue)`. Use `searchTaxpayerTin(null, $idType, $idValue)`.
  Its behaviour is unchanged.

### Changed
- **Stricter local validation (behaviour change)**: `createDocument()` and
  `submitDocuments()` (JSON) now enforce the LHDN field rules (Production
  15 Aug / 24 Oct 2026) through the new `DocumentFieldRulesValidator`. These
  cover field lengths, `YYYY-MM-DD` dates, state/postcode rules per address
  country, and the unit, country, currency, payment mode and tax type code
  lists. Amounts must have no scientific notation and at most 26 digits.
  PASSPORT IDs are limited to 12 characters. All violations are reported in one
  `ValidationException` keyed by UBL path. Documents that LHDN would reject
  now fail before any HTTP call.
- `createDocument()` validates before authenticating or reading the signing
  certificate, and never writes amounts in exponent form.
- `submitDocuments()` minifies JSON losslessly (whitespace only). It used to
  re-encode, which turned large amounts into exponent form and changed escapes.
- `CurrencyValidator` / `CountryValidator` now use the SDK code lists
  (180 currencies, up from 40; adds `CRM` Crimea and `SCT` Scotland; `CPV` is
  renamed Cabo Verde).
- **State code 17 (behaviour change)**: `createDocument()` no longer falls back
  to state `17` (Not Applicable) for missing or unknown `buyerState` /
  `supplierState`. It now throws `ValidationException` naming the received
  value. `17` / `Not Applicable` / `NA` is accepted only for consolidated
  e-Invoices (buyer TIN `EI00000000010`), matching the LHDN rule in force since
  30 Apr 2026. State names are now trimmed and matched case-insensitively, and
  single-digit codes are zero-padded.
- **PASSPORT ID format**: TIN validation accepts any 1–12 letters/digits
  (LHDN limit from 24 Oct 2026) instead of `[A-Z]` + 8 digits.

---

## [1.1.5] - 2026-04-18

### Fixed
- **AllowanceCharge reason coercion**: Empty reason strings in both dynamic and
  default AllowanceCharge entries are now coerced to `"NA"` to satisfy MyInvois
  API validation (previously empty strings caused rejection).
- **Email validation**: Buyer and supplier email fields are now validated with
  `FILTER_VALIDATE_EMAIL` before inclusion in the UBL document. Invalid emails
  silently become empty and are omitted from the `Contact` block rather than
  being sent verbatim to the API.
- **TaxExemptionReason removed**: Empty `TaxExemptionReason` fields are no longer
  included in `TaxCategory` entries, eliminating unnecessary empty-string nodes.

### Changed
- **Dynamic TaxTotal aggregation**: `TaxTotal` is now computed dynamically from
  `lineItems` tax data, aggregating amounts per tax category. When no line items
  are provided, a single zero-amount subtotal using the default tax category is
  returned. Previously `TaxTotal` was always hardcoded to zero.
- **Numeric state code support**: `buyerState` and `supplierState` inputs now
  accept 2-digit numeric strings (e.g. `"14"`) in addition to state names,
  enabling callers that already hold LHDN state codes to pass them through
  directly.
- **Expanded state name aliases**: Additional common aliases are now accepted —
  `"Penang"` (→ `07`), `"Kuala Lumpur"` / `"WP Kuala Lumpur"` (→ `14`),
  `"Labuan"` / `"WP Labuan"` (→ `15`), `"Putrajaya"` / `"WP Putrajaya"`
  (→ `16`), `"NA"` (→ `17`).

### Added
- LLM knowledge wiki (`wiki/`) — 14 domain and operations pages covering UBL
  structure, tax codes, API quirks, authentication, and configuration.
- Agent guidance updated in `AGENTS.md`, `CLAUDE.md`, and `GEMINI.md`: PHP
  environment details, RTK CLI proxy usage, and Magika file-type detection.

---

## [1.1.4] - 2026-01-05

### Changed
- **TIN validation**: `TaxpayerApi` now accepts additional TIN formats including
  `IG\d{10,12}` (individual with passport prefix) and 12-digit NRIC-based TINs,
  in addition to the original `C\d{10}` company format.

---

## [1.1.3] - 2025-12-15

### Added
- **Dynamic invoice line items**: `MyInvoisClient::createDocument()` now builds
  `InvoiceLine` entries dynamically from a `lineItems` array in the request.
- **Dynamic AllowanceCharge**: Allowance and charge entries are built from a
  `allowanceCharges` array when present; falls back to the default 2-entry zero
  structure required by MyInvois when absent.
- **Dynamic supplier MSIC**: `supplierMsicCode` and `supplierMsicDescription`
  are read from the request; defaults to `68109` if not provided.

### Fixed
- Resolved `"Invalid TIN format"` error on certain valid TIN inputs.
- Multiple stability fixes to authentication, submission, and response handling.

### Added
- `IntermediaryApi` — direct access to intermediary-specific endpoint operations.

---

## [1.1.2] - 2025-11-15

### Changed
- Improved client stability across auth token refresh, request retries, and
  error mapping.
- Expanded test coverage for authentication and document submission flows.
- Documentation updates (README, inline PHPDoc).

---

## [1.1.1] - 2025-09-13

### Changed
- Minor updates to MSIC code handling and SST input fields.
- Internal refactors and reverts to stabilise client behaviour.

---

## [1.1.0] - 2025-05-05

### Added
- Submit document indexed by invoice number.
- Intermediary TIN retrieval and `onbehalfof` header support.
- QR code generation and document retrieval improvements.
- Digital signature (XAdES-BES) fixes: signature digest and verification.

---

## [1.0.0] - 2025-03-10

### Added
- Initial stable release.
- OAuth2 authentication with automatic token caching and refresh.
- Document submission (`submitDocuments`), retrieval, and search.
- Taxpayer TIN validation.
- Laravel service provider, facade, and configuration publishing.
- Rate limiting with exponential backoff.
- `DocumentSubmissionApi`, `DocumentRetrievalApi`, `DocumentSearchApi`,
  `TaxpayerApi`, `NotificationsApi`, `SubmissionStatusApi`.
- PSR-4 autoloading, typed exceptions, PHPStan level-8 compliance.

[1.1.5]: https://github.com/NavanithanS/myinvois-api-php/compare/1.1.4...1.1.5
[1.1.4]: https://github.com/NavanithanS/myinvois-api-php/compare/1.1.3...1.1.4
[1.1.3]: https://github.com/NavanithanS/myinvois-api-php/compare/1.1.2...1.1.3
[1.1.2]: https://github.com/NavanithanS/myinvois-api-php/compare/1.1.1...1.1.2
[1.1.1]: https://github.com/NavanithanS/myinvois-api-php/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/NavanithanS/myinvois-api-php/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/NavanithanS/myinvois-api-php/releases/tag/1.0.0

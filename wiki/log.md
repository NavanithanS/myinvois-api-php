# Wiki Log

Append-only record of wiki changes. Format: `## [YYYY-MM-DD] <type> | <title>`

---

## [2026-04-18] init | Initial wiki build (detailed)

Read codebase in full: `MyInvoisClient`, `AuthenticationClient`, `IntermediaryAuthenticationClient`, `ApiClient`, all API traits (`DocumentSubmissionApi`, `DocumentRetrievalApi`, `DocumentDetailsApi`, `DocumentSearchApi`, `SubmissionStatusApi`, `TaxpayerApi`, `NotificationsApi`, `IntermediaryApi`), `RateLimitingTrait`, `Config`, `MyInvoisClientFactory`, all Enums, `MsicValidator`, `config/myinvois.php`.

Modelled on theking-wiki format: YAML frontmatter, subdirectories, summary tables, code blocks with file paths, named wikilinks, Related section on every page.

Structure created:
- `wiki/overview.md`
- `wiki/api/authentication.md`
- `wiki/api/document-submission.md`
- `wiki/api/document-retrieval.md`
- `wiki/api/submission-status.md`
- `wiki/api/document-search.md`
- `wiki/api/taxpayer.md`
- `wiki/api/notifications.md`
- `wiki/domain/ubl-structure.md`
- `wiki/domain/tax-codes.md`
- `wiki/domain/signing.md`
- `wiki/operations/rate-limiting.md`
- `wiki/operations/error-handling.md`
- `wiki/operations/config.md`
- `wiki/operations/known-quirks.md`

Key findings: two-domain architecture, XAdES signing with hardcoded passphrase `"BioEMyInvois"`, AllowanceCharge 2-entry requirement, SSL verify:false, duplicate submission 10-min window, service tax rate 6%/8% mismatch, `dd()` in production cert-load path, duplicate TaxpayerApi use statement, default MSIC `68109`, per-TIN intermediary token cache.

## [2026-11-04] update | Dev stack can resolve to Laravel 12 / testbench 10 / PHPUnit 11 to clear Dependabot alerts; tests avoid withConsecutive (removed in PHPUnit 10)

## [2026-11-04] add | GitHub Actions CI (PHP 8.2–8.4: lint, PHPUnit, composer audit); lock pinned to platform PHP 8.2; stale TIN/config-publish tests fixed

## [2026-11-04] add | Plan: adopt SDK release notes Jul 2025–Sep 2026 (wiki/plans/sdk-release-notes-2026.md)

## [2026-11-05] update | State code 17 now throws unless consolidated (EI00000000010); PASSPORT accepts 1–12 alphanumerics (phase 1 of sdk-release-notes-2026)

## [2026-11-05] add | Search Documents classificationCode filter, typeVersionNumber on Get Document/Details, searchTaxpayerTin(taxpayerName, idType, idValue, fileType) (phase 2)

## [2026-11-05] add | DocumentFieldRulesValidator (field lengths, dates, code lists, amounts ≤26 digits/no exponent, PASSPORT ≤12) for createDocument() + submitDocuments(); UnitTypeValidator; SDK currency/country lists; lossless JSON minify (phase 3)

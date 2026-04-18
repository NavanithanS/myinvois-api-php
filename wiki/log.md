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

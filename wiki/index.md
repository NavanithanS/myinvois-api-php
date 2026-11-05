# MyInvois PHP Client — Wiki Index

Catalog of all pages. Updated on every ingest or significant change.

---

## Overview

| Page | Summary |
|------|---------|
| [[overview]] | Project orientation, client modes, two-domain architecture, key design decisions |

---

## API

| Page | Summary |
|------|---------|
| [[api/authentication\|authentication]] | OAuth2 flow, token caching, `onbehalfof` header, identity vs API base URLs |
| [[api/document-submission\|document-submission]] | Submission endpoint, payload structure, limits, retry logic |
| [[api/document-retrieval\|document-retrieval]] | `getDocument` vs `getDocumentDetails`, longId, QR code, shareable URLs |
| [[api/submission-status\|submission-status]] | Polling submission status, pagination, polling interval enforcement |
| [[api/document-search\|document-search]] | Search filters, date range requirements, result DTOs |
| [[api/taxpayer\|taxpayer]] | TIN validation, TIN search, caching strategy |
| [[api/notifications\|notifications]] | Notification types, status codes, filter parameters |

---

## Domain

| Page | Summary |
|------|---------|
| [[domain/ubl-structure\|ubl-structure]] | JSON UBL format, namespace encoding, Invoice fields, AllowanceCharge, InvoiceLines |
| [[domain/tax-codes\|tax-codes]] | Document type codes, tax categories, state codes, MSIC, TIN formats, ID types |
| [[domain/signing\|signing]] | XAdES-BES digital signature flow — cert loading, digest computation, embedding |

---

## Operations

| Page | Summary |
|------|---------|
| [[operations/rate-limiting\|rate-limiting]] | Per-operation limits, retry behaviour, exponential backoff |
| [[operations/error-handling\|error-handling]] | Exception hierarchy, HTTP→exception mapping, ApiClient error pipeline |
| [[operations/config\|config]] | All config keys, env vars, Laravel integration, factory methods |
| [[operations/known-quirks\|known-quirks]] | Non-obvious platform constraints and design decisions |

---

*Total pages: 14 | Last updated: 2026-11-05*

---

## Plans

| Page | Summary |
|------|---------|
| [[plans/sdk-release-notes-2026\|sdk-release-notes-2026]] | **Done** (branch `feat/sdk-2026-updates`). SDK release notes Jul 2025–Sep 2026 (state 17, PASSPORT, field rules, SVDP 1.2/1.3, new API params) for 1.2.0 |

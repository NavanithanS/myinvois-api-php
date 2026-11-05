---
tags: [domain, ubl, document-format]
updated: 2026-11-05
---

# UBL Document Structure

MyInvois uses a JSON encoding of UBL 2.1 (Universal Business Language). The format has specific conventions that differ from standard JSON.

## Namespace Prefixes

Every UBL document starts with three namespace declarations:

```json
{
  "_D": "urn:oasis:names:specification:ubl:schema:xsd:Invoice-2",
  "_A": "urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2",
  "_B": "urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2",
  "Invoice": [{ ... }]
}
```

## Scalar Value Encoding

Every scalar value is wrapped in a single-element array with a `"_"` key. Attributes are sibling keys in the same object:

```json
"ID": [{"_": "INV-001"}]
"InvoiceTypeCode": [{"_": "01", "listVersionID": "1.1"}]
"IdentificationCode": [{"_": "MYS", "listID": "ISO3166-1", "listAgencyID": "6"}]
```

## Invoice Top-Level Fields

```json
"Invoice": [{
  "ID":                   [{"_": "INV-2024-001"}],
  "IssueDate":            [{"_": "2024-01-15"}],           // UTC date YYYY-MM-DD
  "IssueTime":            [{"_": "08:30:00Z"}],            // UTC time HH:MM:SSZ
  "InvoiceTypeCode":      [{"_": "01", "listVersionID": "1.1"}],
  "DocumentCurrencyCode": [{"_": "MYR"}],
  "TaxCurrencyCode":      [{"_": "MYR"}],
  "AccountingSupplierParty": [...],
  "AccountingCustomerParty": [...],
  "AllowanceCharge": [...],
  "TaxTotal": [...],
  "LegalMonetaryTotal": [...],
  "InvoiceLine": [...]
}]
```

## Party Structure

Both `AccountingSupplierParty` and `AccountingCustomerParty` follow the same shape. Supplier additionally carries `IndustryClassificationCode` (MSIC).

```json
"AccountingSupplierParty": [{
  "Party": [{
    "IndustryClassificationCode": [{"_": "68109", "name": "Real estate activities..."}],
    "PartyIdentification": [
      {"ID": [{"_": "C1234567890",   "schemeID": "TIN"}]},
      {"ID": [{"_": "123456789012", "schemeID": "NRIC"}]},  // or BRN, PASSPORT, ARMY
      {"ID": [{"_": "W12-3456-789", "schemeID": "SST"}]},
      {"ID": [{"_": "NA",           "schemeID": "TTX"}]}
    ],
    "PostalAddress": [{
      "CityName":             [{"_": "Kuala Lumpur"}],
      "PostalZone":           [{"_": "50000"}],
      "CountrySubentityCode": [{"_": "14"}],               // 2-digit state code
      "AddressLine": [{"Line": [{"_": "123 Jalan Example"}]}],
      "Country": [{"IdentificationCode": [{"_": "MYS", "listID": "ISO3166-1", "listAgencyID": "6"}]}]
    }],
    "PartyLegalEntity": [{"RegistrationName": [{"_": "Company Name Sdn Bhd"}]}],
    "Contact": [{
      "Telephone": [{"_": "+60312345678"}],
      "ElectronicMail": [{"_": "billing@example.com"}]     // omitted if invalid email
    }]
  }]
}]
```

## AllowanceCharge

**Important constraint**: when no dynamic charges are provided, MyInvois requires **exactly 2 entries** — one discount (false) and one surcharge (true). Both at zero.

```json
"AllowanceCharge": [
  {
    "ChargeIndicator":        [{"_": false}],
    "AllowanceChargeReason":  [{"_": "NA"}],
    "Amount":                 [{"_": 0.00, "currencyID": "MYR"}]
  },
  {
    "ChargeIndicator":        [{"_": true}],
    "AllowanceChargeReason":  [{"_": "NA"}],
    "Amount":                 [{"_": 0.00, "currencyID": "MYR"}]
  }
]
```

When `allowanceCharges` is provided in the request, any number of entries is accepted. Empty reason strings are coerced to `"NA"`.

## TaxTotal

Aggregated at document level, summarized by tax category. One entry per category.

```json
"TaxTotal": [{
  "TaxAmount": [{"_": 6.00, "currencyID": "MYR"}],
  "TaxSubtotal": [{
    "TaxableAmount": [{"_": 100.00, "currencyID": "MYR"}],
    "TaxAmount":     [{"_": 6.00,  "currencyID": "MYR"}],
    "TaxCategory": [{
      "ID": [{"_": "02"}],
      "TaxScheme": [{"ID": [{"_": "OTH", "schemeID": "UN/ECE 5153", "schemeAgencyID": "6"}]}]
    }]
  }]
}]
```

If no `lineItems` in request, a zero-tax fallback entry is used: `TaxAmount: 0`, category `"06"` (Exempt).

## LegalMonetaryTotal

```json
"LegalMonetaryTotal": [{
  "TaxExclusiveAmount": [{"_": 100.00, "currencyID": "MYR"}],
  "TaxInclusiveAmount": [{"_": 100.00, "currencyID": "MYR"}],
  "PayableAmount":      [{"_": 100.00, "currencyID": "MYR"}]
}]
```

All three amounts are set to `totalPay` from the request, meaning tax is not separated at this level — tax breakdown is in `TaxTotal` and `InvoiceLine`.

## InvoiceLine

Line IDs are zero-padded 3-digit strings (`"001"`, `"002"`). Each line carries its own `TaxTotal` matching the category.

```json
"InvoiceLine": [{
  "ID":                   [{"_": "001"}],
  "InvoicedQuantity":     [{"_": 2.0, "unitCode": "C62"}],    // C62 = each
  "LineExtensionAmount":  [{"_": 200.00, "currencyID": "MYR"}],
  "TaxTotal": [{
    "TaxAmount": [{"_": 12.00, "currencyID": "MYR"}],
    "TaxSubtotal": [{
      "TaxableAmount": [{"_": 200.00, "currencyID": "MYR"}],
      "TaxAmount":     [{"_": 12.00,  "currencyID": "MYR"}],
      "Percent":       [{"_": 6}],
      "TaxCategory": [{
        "ID": [{"_": "02"}],
        "TaxScheme": [{"ID": [{"_": "OTH", "schemeID": "UN/ECE 5153", "schemeAgencyID": "6"}]}]
      }]
    }]
  }],
  "Item": [{
    "CommodityClassification": [{"ItemClassificationCode": [{"_": "022", "listID": "CLASS"}]}],
    "Description": [{"_": "Consulting Services"}]
  }],
  "Price": [{"PriceAmount": [{"_": 100.00, "currencyID": "MYR"}]}],
  "ItemPriceExtension": [{"Amount": [{"_": 200.00, "currencyID": "MYR"}]}]
}]
```

**Legacy fallback** (no `lineItems` in request): single line, description "Electricity Charge", classification `"022"`, quantity 1, all amounts = `totalPay`.

## LHDN Field Rules (enforced locally since 1.2.0)

`src/Validation/DocumentFieldRulesValidator.php` checks the UBL JSON that `createDocument()` builds (before authenticating or signing) and every JSON document passed to `submitDocuments()`. XML is not checked. **All** violations come back in one `ValidationException`, keyed by path, e.g. `Invoice.ID`, `documents[2].Invoice.InvoiceLine[1].Price.PriceAmount` (`[0]` is omitted from paths). Only fields that are present are checked, and `UBLExtensions`/`Signature` are skipped. Documents without an `Invoice` root are ignored.

Sources: SDK release notes 3 Jul 2026 (Production 15 Aug 2026), 30 Apr 2026, 6 Aug 2026 (Production 24 Oct 2026). Paths are from the Invoice v1.1 spec.

| Field | UBL path | Rule |
|-------|----------|------|
| e-Invoice Code/Number | `Invoice.ID` | ≤ 50 |
| Supplier bank account | `PaymentMeans.PayeeFinancialAccount.ID` | ≤ 150 |
| Certified Exporter no. | supplier `AdditionalAccountID[@schemeAgencyName=CertEX]` (case-insensitive) | ≤ 300 |
| Incoterms | `AdditionalDocumentReference.ID`. The spec says `[4]`, but the entry is identified as **the reference with no `DocumentType`** (as in the SDK sample: `{"ID":[{"_":"CIF"}]}`). It is **not** under `Delivery` | ≤ 3 |
| Frequency of Billing | `InvoicePeriod.Description` | ≤ 50 |
| Business activity desc. | supplier `IndustryClassificationCode@name` | ≤ 300 |
| Payment terms | `PaymentTerms.Note` | ≤ 300 |
| PrePayment reference | `PrepaidPayment.ID` | ≤ 150 |
| Dates | any key ending `Date` | `YYYY-MM-DD` and a real calendar date (`N/A` is rejected) |
| State | every `PostalAddress.CountrySubentityCode` | MYS address → `01`–`17`, and `17` only if buyer TIN is `EI00000000010`. Non-MYS → ≤ 50 |
| Postcode | every `PostalAddress.PostalZone` (party + delivery) | MYS → ≤ 5. Non-MYS → ≤ 50 |
| Unit | `InvoiceLine.InvoicedQuantity@unitCode` | in `UnitTypeValidator` (2162 SDK codes, incl. `HAR` Hectare), exact case |
| Country / currency / payment mode / tax type | `Country.IdentificationCode`, `DocumentCurrencyCode`/`TaxCurrencyCode`/every `@currencyID`, `PaymentMeansCode`, `TaxCategory.ID` | exact match on the SDK code lists |
| Amounts | every key ending `Amount`, plus `Percent` | plain decimal, **no exponent**, ≤ **26 digits** (sign and `.` not counted) |
| PASSPORT IDs | `PartyIdentification.ID[@schemeID=PASSPORT]` | ≤ 12 |

Code lists were regenerated on 2026-11-05 from the SDK JSON files (`/files/CurrencyCodes.json`: 180, `CountryCodes.json`: 253 incl. `CRM`/`SCT`, `UnitTypes.json`: 2162). Before that, `CurrencyValidator` had only 40 currencies.

### Amounts and JSON encoding

- PHP `json_encode()` writes floats ≥ 1e15 as `1.0e+15`, which LHDN rejects. `createDocument()` encodes through `Support\UblJson::encode()`, which rewrites any exponent literal as a plain decimal. The JSON is otherwise identical.
- `submitDocuments()` used to minify by `json_decode`+`json_encode`. That turned `12345678901234567890123.45` into `1.2345678901234568e+22` and could change escapes, so it no longer matched the caller's `documentHash`. Since 1.2.0 it uses `UblJson::minify()`, which only strips whitespace outside strings.
- The validator reads the **raw text** (`UblJson::decodePreservingNumbers()`), so exponent use and digit counts are judged exactly as written.

## Related

- [[domain/tax-codes]] — tax category IDs, MSIC, state codes
- [[domain/signing]] — how the signature block is appended to this structure
- [[api/document-submission]] — submission payload wrapping this document
- [[operations/known-quirks#allowancecharge-two-entry-requirement]]

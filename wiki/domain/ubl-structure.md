---
tags: [domain, ubl, document-format]
updated: 2026-04-18
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

## Related

- [[domain/tax-codes]] — tax category IDs, MSIC, state codes
- [[domain/signing]] — how the signature block is appended to this structure
- [[api/document-submission]] — submission payload wrapping this document
- [[operations/known-quirks#allowancecharge-two-entry-requirement]]

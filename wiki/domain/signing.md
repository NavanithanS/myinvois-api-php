---
tags: [domain, signing, xades, cryptography]
updated: 2026-04-18
---

# XAdES Digital Signing

MyInvois documents must be digitally signed using XAdES-BES (XML Advanced Electronic Signature, Basic Electronic Signature profile). This library implements it in JSON rather than XML.

## Key File

`src/MyInvoisClient.php` — `createDocument()` method (lines ~345–615)

## Certificate Setup

The signing certificate is loaded from a PKCS#12 (`.p12`/`.pfx`) file:

```php
$privateKey = file_get_contents(config('myinvois.privatekey_path'));
openssl_pkcs12_read($privateKey, $certs, "BioEMyInvois");
```

**Passphrase is hardcoded as `"BioEMyInvois"`** — specific to the LHDN-provisioned cert for this integration. See [[operations/known-quirks#xades-signing-passphrase]].

`$certs` after loading:
- `$certs['cert']` — the X.509 certificate
- `$certs['pkey']` — the private key resource

## Signing Flow

```
1. Load PKCS#12 cert
     openssl_pkcs12_read($pkcs12, $certs, "BioEMyInvois")

2. Extract certificate metadata
     openssl_x509_parse($certs['cert'])
     → certSN (serial number)
     → issuerName = "CN={cn}, O={o}, C={c}"

3. Compute cert digest
     Convert cert to DER (strip PEM headers, base64_decode)
     certDigest = base64(sha256(DER cert bytes))
     x509cert   = base64(DER cert bytes)

4. Build UBL document JSON
     $document = [ "_D"=>..., "_A"=>..., "_B"=>..., "Invoice"=>[...] ]

5. Compute document digest
     docJson   = json_encode($document, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
     certDoc   = base64(sha256(docJson, raw=true))

6. Build SignedProperties
     {
       "Target": "signature",
       "SignedProperties": [{
         "Id": "id-xades-signed-props",
         "SignedSignatureProperties": [{
           "SigningTime": ["YYYY-MM-DDTHH:MM:SSZ"],
           "SigningCertificate": [{
             "Cert": [{ "CertDigest": [...], "IssuerSerial": [...] }]
           }]
         }]
       }]
     }

7. Compute SignedProperties digest
     signedPropsJson = json_encode(signedProperties, ...)
     certProps       = base64(sha256(signedPropsJson, raw=true))

8. Sign the document
     openssl_sign($docJson, $signature, $certs['pkey'], OPENSSL_ALGO_SHA256)
     certValue = base64($signature)

9. Build UBLExtensions block
     UBLExtensions → UBLExtension → ExtensionContent → UBLDocumentSignatures
       → SignatureInformation → Signature
           KeyInfo:       X509Certificate, X509SubjectName, X509IssuerSerial
           SignatureValue: certValue
           SignedInfo:     references to certProps and certDoc

10. Merge into document
      $document['Invoice'][0] = array_merge($document['Invoice'][0], $signature)
      where $signature = { "UBLExtensions": [...], "Signature": [...] }
```

## Signature Block Position

The signature is merged **into `Invoice[0]`** after `InvoiceLine` is built. The final document structure is:

```
Invoice[0]:
  ID, IssueDate, IssueTime, InvoiceTypeCode, ...
  AccountingSupplierParty, AccountingCustomerParty
  AllowanceCharge
  TaxTotal
  LegalMonetaryTotal
  InvoiceLine
  UBLExtensions     ← appended by merge
  Signature         ← appended by merge
```

## Digest Algorithms

| What | Algorithm | Encoding |
|------|-----------|---------|
| Document content | SHA-256 of JSON string | base64 (raw bytes) |
| SignedProperties | SHA-256 of JSON string | base64 (raw bytes) |
| Certificate | SHA-256 of DER bytes | base64 (raw bytes) |
| Document signature | RSA-SHA256 (`OPENSSL_ALGO_SHA256`) | base64 |
| Submission hash | SHA-256 of JSON string | hex (for `documentHash` field) |

Note: the `documentHash` sent in the submission payload is SHA-256 **hex**, but `certDoc` (the digest embedded in the signature) is SHA-256 **base64** of raw bytes. These serve different purposes.

## Related

- [[operations/known-quirks#xades-signing-passphrase]] — hardcoded passphrase
- [[operations/known-quirks#dd-in-production-code]] — `dd()` on cert load failure
- [[api/document-submission]] — how the signed document is submitted

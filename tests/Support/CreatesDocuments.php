<?php

namespace Nava\MyInvois\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;

/**
 * Helpers for driving MyInvoisClient::createDocument() end-to-end in tests.
 *
 * A throwaway self-signed PKCS#12 certificate is generated per test run (never
 * committed) using the passphrase createDocument() hard-codes.
 */
trait CreatesDocuments
{
    /** @var string|null */
    private static $signingCertPath = null;

    protected function useGeneratedSigningCertificate(): void
    {
        if (self::$signingCertPath === null || !is_file(self::$signingCertPath)) {
            $dn = ['countryName' => 'MY', 'organizationName' => 'Test CA Sdn Bhd', 'commonName' => 'Test Signing CA'];
            $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
            if (!$csr instanceof \OpenSSLCertificateSigningRequest) {
                $this->fail('Could not create CSR: ' . openssl_error_string());
            }
            $cert = openssl_csr_sign($csr, null, $key, 30, ['digest_alg' => 'sha256'], 1001);
            if ($cert === false) {
                $this->fail('Could not sign certificate: ' . openssl_error_string());
            }
            $p12 = '';
            openssl_pkcs12_export($cert, $p12, $key, 'BioEMyInvois');

            $path = tempnam(sys_get_temp_dir(), 'myinvois-test-cert-');
            file_put_contents($path, $p12);
            self::$signingCertPath = $path;
        }

        config()->set('myinvois.privatekey_path', self::$signingCertPath);
    }

    /**
     * Minimal valid createDocument() request payload.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function documentRequest(array $overrides = []): Request
    {
        return new Request(array_replace([
            'total_amount' => 106.00,
            'invoice_no' => 'INV-TEST-001',
            'buyerIdType' => 'NRIC',
            'buyerIC' => '770625015324',
            'buyerTIN' => 'IG12345678901',
            'buyerName' => 'Test Buyer',
            'buyerPhone' => '+60123456789',
            'buyerEmail' => 'buyer@example.com',
            'buyerAddress1' => '1 Jalan Satu',
            'buyerAddress2' => 'Taman Dua',
            'buyerPostcode' => '50000',
            'buyerCity' => 'Kuala Lumpur',
            'buyerState' => 'Kuala Lumpur',
            'supplierIdType' => 'BRN',
            'supplierTIN' => 'C1234567890',
            'supplierIC' => '202001234567',
            'supplierSST' => 'NA',
            'supplierName' => 'Test Supplier Sdn Bhd',
            'supplierPhone' => '+60312345678',
            'supplierEmail' => 'billing@example.com',
            'supplierAddress1' => '2 Jalan Tiga',
            'supplierAddress2' => 'Seksyen 4',
            'supplierPostcode' => '40000',
            'supplierCity' => 'Shah Alam',
            'supplierState' => '10',
            'supplierMsicCode' => '62010',
            'supplierMsicDescription' => 'Computer programming activities',
            'lineItems' => [
                ['description' => 'Consulting', 'quantity' => 1, 'unitPrice' => 100.00, 'taxRate' => 6, 'taxAmount' => 6.00, 'subtotal' => 100.00, 'total' => 106.00, 'classificationCode' => '022'],
            ],
        ], $overrides));
    }

    /**
     * Queue the HTTP responses createDocument() consumes (token + submission).
     */
    protected function mockDocumentSubmission(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(202, [], (string) json_encode([
            'submissionUID' => 'SUB123',
            'acceptedDocuments' => [['uuid' => 'UUID1', 'invoiceCodeNumber' => 'INV-TEST-001']],
            'rejectedDocuments' => [],
        ])));
    }

    /**
     * Decode the UBL document from the last submission request.
     *
     * @return array<string, mixed>
     */
    protected function lastSubmittedDocument(): array
    {
        foreach (array_reverse($this->container) as $transaction) {
            $request = $transaction['request'];
            if (str_contains($request->getUri()->getPath(), '/documentsubmissions')) {
                $payload = json_decode((string) $request->getBody(), true);

                return json_decode(base64_decode($payload['documents'][0]['document']), true);
            }
        }

        $this->fail('No submission request was sent');
    }

    /**
     * Raw (pre-base64) JSON of the last submitted UBL document.
     */
    protected function lastSubmittedDocumentJson(): string
    {
        foreach (array_reverse($this->container) as $transaction) {
            $request = $transaction['request'];
            if (str_contains($request->getUri()->getPath(), '/documentsubmissions')) {
                $payload = json_decode((string) $request->getBody(), true);

                return base64_decode($payload['documents'][0]['document']);
            }
        }

        $this->fail('No submission request was sent');
    }
}

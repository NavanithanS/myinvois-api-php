<?php

namespace Nava\MyInvois\Tests\Feature;

use Nava\MyInvois\Exception\SigningCertificateException;
use Nava\MyInvois\Tests\Support\CreatesDocuments;
use Nava\MyInvois\Tests\TestCase;

/**
 * createDocument() must raise a catchable exception (never die()/dd()) when the
 * signing certificate cannot be loaded, and must do so before any HTTP call.
 */
class SigningCertificateTest extends TestCase
{
    use CreatesDocuments;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function tempFile(string $contents): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'myinvois-bad-cert-');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function p12WithPassphrase(string $passphrase): string
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Wrong Passphrase'], $key, ['digest_alg' => 'sha256']);
        $this->assertInstanceOf(\OpenSSLCertificateSigningRequest::class, $csr);
        $cert = openssl_csr_sign($csr, null, $key, 1, ['digest_alg' => 'sha256']);
        $this->assertNotFalse($cert);
        $p12 = '';
        openssl_pkcs12_export($cert, $p12, $key, $passphrase);

        return $p12;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function badCertificateProvider(): array
    {
        return [
            'path not configured' => ['unset'],
            'file missing' => ['missing'],
            'empty file' => ['empty'],
            'not PKCS#12' => ['garbage'],
            'wrong passphrase' => ['wrong-passphrase'],
        ];
    }

    /**
     * @dataProvider badCertificateProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('badCertificateProvider')]
    public function test_bad_certificate_throws_before_any_http_call(string $case): void
    {
        $path = match ($case) {
            'unset' => null,
            'missing' => sys_get_temp_dir() . '/myinvois-no-such-cert-' . bin2hex(random_bytes(4)) . '.p12',
            'empty' => $this->tempFile(''),
            'garbage' => $this->tempFile('-----BEGIN NOT A CERT-----'),
            default => $this->tempFile($this->p12WithPassphrase('something-else')),
        };
        config()->set('myinvois.privatekey_path', $path);
        $this->mockDocumentSubmission();

        try {
            $this->client->createDocument($this->documentRequest());
            $this->fail('Expected SigningCertificateException');
        } catch (SigningCertificateException $e) {
            $this->assertStringContainsString('Signing certificate', $e->getMessage());
        }

        $this->assertCount(0, $this->container, 'No HTTP call (including authentication) should be made');
    }

    public function test_valid_certificate_still_signs_and_submits(): void
    {
        $this->useGeneratedSigningCertificate();
        $this->mockDocumentSubmission();

        $this->assertSame('SUB123', $this->client->createDocument($this->documentRequest())['submissionUID']);
        $this->assertArrayHasKey('Signature', $this->lastSubmittedDocument()['Invoice'][0]);
    }
}

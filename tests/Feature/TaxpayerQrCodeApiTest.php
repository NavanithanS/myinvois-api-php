<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Tests\TestCase;

class TaxpayerQrCodeApiTest extends TestCase
{
    /** @test */
    public function it_can_get_taxpayer_info_from_qr(): void
    {
        $this->mockSuccessfulAuthentication();

        $qr = '4e1bc907-25b7-45b1-9620-2d671a6f9cae';
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'tin' => 'C25480996000',
                'name' => 'Example Sdn Bhd',
            ]))
        );

        $result = $this->client->getTaxpayerInfoFromQr($qr);

        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString("/api/v1.0/taxpayers/qrcodeinfo/{$qr}", $request->getUri()->getPath());

        $this->assertEquals('C25480996000', $result['tin']);
        $this->assertEquals('Example Sdn Bhd', $result['name']);
    }

    /** @test */
    public function it_handles_qr_code_not_found(): void
    {
        $this->mockSuccessfulAuthentication();

        $qr = 'missing-qr-code';
        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'NotFound',
                'message' => 'QR Code Not Found',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->client->getTaxpayerInfoFromQr($qr);
    }
}


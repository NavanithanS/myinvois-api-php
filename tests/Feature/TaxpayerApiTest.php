<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class TaxpayerApiTest extends TestCase
{
    /** @test */
    public function it_validates_taxpayer_tin_successfully(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock successful validation response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'valid' => true,
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('TIN validation successful'));

        $this->client->setLogger($logger);

        $result = $this->client->validateTaxpayerTin(
            'C1234567890',
            'NRIC',
            '770625015324'
        );

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/taxpayer/validate/C1234567890', $request->getUri()->getPath());

        // Verify query parameters
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);
        $this->assertEquals('NRIC', $query['idType']);
        $this->assertEquals('770625015324', $query['idValue']);

        // Verify result
        $this->assertTrue($result);
    }

    /** @test */
    public function it_returns_false_for_invalid_tin(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock not found response
        $this->mockHandler->append(
            new Response(404, [], json_encode([
                'error' => 'not_found',
                'message' => 'Taxpayer not found',
            ]))
        );

        $result = $this->client->validateTaxpayerTin(
            'C1234567890',
            'NRIC',
            '770625015324'
        );

        $this->assertFalse($result);
    }

    /** @test */
    public function it_validates_tin_format(): void
    {
        $invalidTins = [
            'invalid', // Invalid format
            'D1234567890', // Wrong prefix
            'C123456789', // Too short
            'C12345678901', // Too long
            'C123456789X', // Non-numeric
        ];

        foreach ($invalidTins as $tin) {
            try {
                $this->client->validateTaxpayerTin($tin, 'NRIC', '770625015324');
                $this->fail('Expected ValidationException for invalid TIN: ' . $tin);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid TIN format', $e->getMessage());
                $this->assertArrayHasKey('tin', $e->getErrors());
            }
        }
    }

    /** @test */
    public function it_validates_id_types(): void
    {
        try {
            $this->client->validateTaxpayerTin('C1234567890', 'INVALID', '770625015324');
            $this->fail('Expected ValidationException for invalid ID type');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Invalid ID type', $e->getMessage());
            $this->assertArrayHasKey('idType', $e->getErrors());
        }
    }

    /** @test */
    public function it_validates_nric_format(): void
    {
        $invalidNrics = [
            '12345', // Too short
            '1234567890123', // Too long
            'ABC123456789', // Contains letters
            '77062501532A', // Contains letter at end
        ];

        foreach ($invalidNrics as $nric) {
            try {
                $this->client->validateTaxpayerTin('C1234567890', 'NRIC', $nric);
                $this->fail('Expected ValidationException for invalid NRIC: ' . $nric);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid NRIC format', $e->getMessage());
                $this->assertArrayHasKey('idValue', $e->getErrors());
            }
        }
    }

    /** @test */
    public function it_validates_passport_format(): void
    {
        $invalidPassports = [
            '12345678', // No letter prefix
            'AA12345678', // Multiple letters
            'A1234567', // Too short
            'A123456789', // Too long
            '12A345678', // Letter in wrong position
        ];

        foreach ($invalidPassports as $passport) {
            try {
                $this->client->validateTaxpayerTin('C1234567890', 'PASSPORT', $passport);
                $this->fail('Expected ValidationException for invalid passport: ' . $passport);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid passport number format', $e->getMessage());
                $this->assertArrayHasKey('idValue', $e->getErrors());
            }
        }
    }

    /** @test */
    public function it_validates_brn_format(): void
    {
        $invalidBrns = [
            '12345', // Too short
            '1234567890123', // Too long
            'ABC123456789', // Contains letters
            '20190123456A', // Contains letter
        ];

        foreach ($invalidBrns as $brn) {
            try {
                $this->client->validateTaxpayerTin('C1234567890', 'BRN', $brn);
                $this->fail('Expected ValidationException for invalid BRN: ' . $brn);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid BRN format', $e->getMessage());
                $this->assertArrayHasKey('idValue', $e->getErrors());
            }
        }
    }

    /** @test */
    public function it_validates_army_number_format(): void
    {
        $invalidArmyNumbers = [
            '12345', // Too short
            '1234567890123', // Too long
            'ABC123456789', // Contains letters
            '55158770654A', // Contains letter
        ];

        foreach ($invalidArmyNumbers as $armyNumber) {
            try {
                $this->client->validateTaxpayerTin('C1234567890', 'ARMY', $armyNumber);
                $this->fail('Expected ValidationException for invalid army number: ' . $armyNumber);
            } catch (ValidationException $e) {
                $this->assertStringContainsString('Invalid army number format', $e->getMessage());
                $this->assertArrayHasKey('idValue', $e->getErrors());
            }
        }
    }

    /** @test */
    public function it_handles_api_errors(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock error response
        $this->mockHandler->append(
            new Response(500, [], json_encode([
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred',
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('TIN validation failed'));

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('An unexpected error occurred');

        $this->client->validateTaxpayerTin('C1234567890', 'NRIC', '770625015324');
    }

    /** @test */
    public function it_accepts_case_insensitive_id_types(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock successful response
        $this->mockHandler->append(
            new Response(200, [], json_encode(['valid' => true]))
        );

        // Test with lowercase
        $result = $this->client->validateTaxpayerTin('C1234567890', 'nric', '770625015324');
        $this->assertTrue($result);

        // Mock another successful response
        $this->mockHandler->append(
            new Response(200, [], json_encode(['valid' => true]))
        );

        // Test with mixed case
        $result = $this->client->validateTaxpayerTin('C1234567890', 'PasSpoRt', 'A12345678');
        $this->assertTrue($result);

        // Verify that ID types were normalized to uppercase in requests
        $requests = array_map(function ($transaction) {
            parse_str($transaction['request']->getUri()->getQuery(), $query);
            return $query['idType'];
        }, $this->container);

        $this->assertEquals('NRIC', $requests[1]);
        $this->assertEquals('PASSPORT', $requests[2]);
    }

    /** @test */
    public function it_validates_id_value_for_each_type(): void
    {
        $validTestCases = [
            ['NRIC', '770625015324'],
            ['PASSPORT', 'A12345678'],
            ['BRN', '201901234567'],
            ['ARMY', '551587706543'],
        ];

        foreach ($validTestCases as [$idType, $idValue]) {
            $this->mockSuccessfulAuthentication();
            $this->mockHandler->append(
                new Response(200, [], json_encode(['valid' => true]))
            );

            try {
                $result = $this->client->validateTaxpayerTin('C1234567890', $idType, $idValue);
                $this->assertTrue($result, "Validation should pass for $idType: $idValue");
            } catch (ValidationException $e) {
                $this->fail("Unexpected ValidationException for valid $idType: $idValue");
            }
        }
    }
}

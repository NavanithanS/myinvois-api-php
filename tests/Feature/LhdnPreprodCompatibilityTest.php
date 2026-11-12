<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Config;
use Nava\MyInvois\Data\DocumentSearchResult;
use Nava\MyInvois\MyInvoisClient;
use Nava\MyInvois\Tests\TestCase;

/**
 * Response shapes and hosts as observed on LHDN preprod on 2026-11-12.
 * The JSON bodies below are trimmed copies of real preprod responses
 * (sandbox test data, no credentials).
 */
class LhdnPreprodCompatibilityTest extends TestCase
{
    private function submission(): array
    {
        $json = '{"Invoice":[{"ID":[{"_":"INV-1"}]}]}';

        return ['document' => $json, 'documentHash' => hash('sha256', $json), 'codeNumber' => 'INV-1'];
    }

    public function test_submission_response_with_lowercase_submission_uid_is_returned(): void
    {
        $this->client->setFieldRulesValidation(false);
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(202, [], (string) json_encode([
            'submissionUid' => 'EAKP79H5SHEKDKSGAF3QG67M10',
            'acceptedDocuments' => [['uuid' => 'A284SAC7CQ8Y5EDXAF3QG67M10', 'invoiceCodeNumber' => 'INV-1']],
            'rejectedDocuments' => [],
        ])));

        $response = $this->client->submitDocuments([$this->submission()]);

        $this->assertSame('EAKP79H5SHEKDKSGAF3QG67M10', $response['submissionUid']);
        $this->assertSame('EAKP79H5SHEKDKSGAF3QG67M10', $response['submissionUID']);
        $this->assertSame('A284SAC7CQ8Y5EDXAF3QG67M10', $response['acceptedDocuments'][0]['uuid']);
    }

    public function test_all_rejected_submission_returns_null_uid_instead_of_throwing(): void
    {
        $this->client->setFieldRulesValidation(false);
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(202, [], (string) json_encode([
            'submissionUid' => null,
            'acceptedDocuments' => [],
            'rejectedDocuments' => [[
                'invoiceCodeNumber' => 'INV-1',
                'error' => ['code' => '2', 'message' => 'Validation Error', 'details' => [['code' => 'CF417', 'message' => 'Incoterms maximum length is 3']]],
            ]],
        ])));

        $response = $this->client->submitDocuments([$this->submission()]);

        $this->assertNull($response['submissionUid']);
        $this->assertNull($response['submissionUID']);
        $this->assertSame('CF417', $response['rejectedDocuments'][0]['error']['details'][0]['code']);
        $this->assertCount(2, $this->container, 'One token call and one submission, no retry');
    }

    public function test_search_documents_maps_real_result_rows(): void
    {
        $this->mockSuccessfulAuthentication();
        $this->mockHandler->append(new Response(200, [], (string) json_encode([
            'result' => [[
                'totalExcludingTax' => 1436.5, 'totalDiscount' => 10, 'totalNetAmount' => 1426.5, 'totalPayableAmount' => 1512.09,
                'uuid' => '03MN70QDP1Q79KEAD4QSG67M10', 'submissionUid' => 'ZT4264R9RZ8AD776D4QSG67M10',
                'longId' => 'HPA7E64TQXCP78G6D4QSG67M10sOaBz31794441534', 'internalId' => 'INV-1',
                'typeName' => 'Invoice', 'typeVersionName' => '1.2',
                'supplierTIN' => 'IG12345678901', 'supplierName' => "Supplier's Name",
                'buyerName' => "Buyer's Name", 'buyerTIN' => 'EI00000000010',
                'receiverID' => 'NA', 'receiverTIN' => 'EI00000000010', 'receiverName' => "Buyer's Name",
                'dateTimeIssued' => '2024-08-01T00:30:00+00:00', 'dateTimeReceived' => '2026-11-11T23:58:54.6048098Z',
                'dateTimeValidated' => null, 'status' => 'Submitted', 'cancelDateTime' => null,
                'rejectRequestDateTime' => null, 'documentStatusReason' => null, 'submissionChannel' => 'ERP',
                'intermediaryName' => null, 'intermediaryTIN' => null,
                'createdByUserId' => 'IG12345678901:00000000-0000-0000-0000-000000000000',
                'issuerTIN' => 'IG12345678901', 'issuerID' => '000000000000', 'issuerIDType' => 'NRIC',
                'documentCurrency' => 'MYR', 'classificationCodeValues' => [],
            ]],
            'metadata' => ['totalPages' => 1, 'totalCount' => 1],
        ])));

        $response = $this->client->searchDocuments([
            'submissionDateFrom' => '2026-11-11T00:00:00Z',
            'submissionDateTo' => '2026-11-12T00:00:00Z',
        ]);

        $this->assertSame('IG12345678901', $response['result'][0]['issuerTIN'], 'Raw rows are kept');
        $document = $response['documents'][0];
        $this->assertInstanceOf(DocumentSearchResult::class, $document);
        $this->assertSame('ZT4264R9RZ8AD776D4QSG67M10', $document->submissionUID);
        $this->assertSame('IG12345678901', $document->issuerTin);
        $this->assertSame("Supplier's Name", $document->issuerName);
        $this->assertSame(1436.5, $document->totalSales);
        $this->assertSame(1426.5, $document->netAmount);
        $this->assertSame(1512.09, $document->total);
        $this->assertNull($document->dateTimeValidated);
        $this->assertNull($document->jsonSerialize()['dateTimeValidated']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function hostProvider(): array
    {
        return [
            'sandbox API host' => [Config::SANDBOX_URL, 'https://preprod-api.myinvois.hasil.gov.my', 'https://preprod.myinvois.hasil.gov.my'],
            'production API host' => [Config::PRODUCTION_URL, 'https://api.myinvois.hasil.gov.my', 'https://myinvois.hasil.gov.my'],
            'old sandbox portal host' => ['https://preprod.myinvois.hasil.gov.my/', 'https://preprod-api.myinvois.hasil.gov.my', 'https://preprod.myinvois.hasil.gov.my'],
            'old production portal host' => ['https://myinvois.hasil.gov.my', 'https://api.myinvois.hasil.gov.my', 'https://myinvois.hasil.gov.my'],
            'custom host unchanged' => ['https://proxy.example.com', 'https://proxy.example.com', 'https://proxy.example.com'],
        ];
    }

    /**
     * @dataProvider hostProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('hostProvider')]
    public function test_api_requests_go_to_the_api_host_and_share_links_to_the_portal(string $configured, string $apiHost, string $portalHost): void
    {
        $this->assertSame($apiHost, Config::apiBaseUrl($configured));

        $mock = new MockHandler([
            new Response(200, [], (string) json_encode(['access_token' => 't', 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'InvoicingAPI'])),
            new Response(200, [], (string) json_encode(['result' => []])),
        ]);
        $history = [];
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new MyInvoisClient('id', 'secret', $configured, app('cache')->store(), new GuzzleClient(['handler' => $stack]));

        $client->getDocumentTypes();

        $this->assertSame($apiHost, 'https://' . end($history)['request']->getUri()->getHost());
        $uuid = 'A284SAC7CQ8Y5EDXAF3QG67M10';
        $longId = '848K10159P2R8V5WAF3QG67M10mbBOzD1794441448';
        $this->assertSame("{$portalHost}/{$uuid}/share/{$longId}", $client->getDocumentShareableUrl($uuid, $longId));
    }

    public function test_portal_url_can_be_set_explicitly(): void
    {
        config()->set('myinvois.portal_url', 'https://portal.example.com/');

        $this->assertStringStartsWith(
            'https://portal.example.com/A284SAC7CQ8Y5EDXAF3QG67M10/share/',
            $this->client->getDocumentShareableUrl('A284SAC7CQ8Y5EDXAF3QG67M10', '848K10159P2R8V5WAF3QG67M10mbBOzD1794441448')
        );
    }
}

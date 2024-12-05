<?php

namespace Nava\MyInvois\Tests\Feature;

use GuzzleHttp\Psr7\Response;
use Nava\MyInvois\Data\Notification;
use Nava\MyInvois\Enums\NotificationStatusEnum;
use Nava\MyInvois\Enums\NotificationTypeEnum;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Tests\TestCase;
use Psr\Log\LoggerInterface;

class NotificationsApiTest extends TestCase
{
    /** @test */
    public function it_can_get_notifications(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock notifications response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    [
                        'notificationId' => 'NOTIF123',
                        'receiverName' => 'Test Company Sdn Bhd',
                        'notificationDeliveryId' => 'DEL456',
                        'creationDateTime' => '2024-01-01T10:00:00Z',
                        'receivedDateTime' => '2024-01-01T10:00:05Z',
                        'notificationSubject' => 'Document Received',
                        'deliveredDateTime' => '2024-01-01T10:00:10Z',
                        'typeId' => NotificationTypeEnum::DOCUMENT_RECEIVED->value,
                        'typeName' => 'Document received',
                        'finalMessage' => 'Your document has been received successfully',
                        'address' => 'test@company.com',
                        'language' => 'en',
                        'status' => 'DELIVERED',
                        'deliveryAttempts' => [
                            [
                                'attemptDateTime' => '2024-01-01T10:00:10Z',
                                'status' => 'delivered',
                                'statusDetails' => null,
                            ],
                        ],
                    ],
                ],
                'metadata' => [
                    'hasNext' => false,
                ],
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Retrieved notifications successfully'));

        $this->client->setLogger($logger);
        $response = $this->client->getNotifications();

        // Verify request
        $request = $this->getLastRequest();
        $this->assertEquals('GET', $request->getMethod());
        $this->assertStringContainsString('/api/v1.0/notifications/taxpayer', $request->getUri()->getPath());

        // Verify response structure
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('metadata', $response);
        $this->assertCount(1, $response['result']);
        $this->assertFalse($response['metadata']['hasNext']);

        // Create DTO from response data
        $notification = Notification::fromArray($response['result'][0]);
        $this->assertEquals('NOTIF123', $notification->notificationId);
        $this->assertEquals(NotificationTypeEnum::DOCUMENT_RECEIVED->value, $notification->typeId);
        $this->assertTrue($notification->isDelivered());
    }

    /** @test */
    public function it_can_filter_notifications(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock filtered response
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [],
                'metadata' => ['hasNext' => false],
            ]))
        );

        $filters = [
            'dateFrom' => '2024-01-01T00:00:00Z',
            'dateTo' => '2024-01-31T23:59:59Z',
            'type' => NotificationTypeEnum::DOCUMENT_RECEIVED->value,
            'language' => 'en',
            'status' => NotificationStatusEnum::DELIVERED->value,
            'pageNo' => 1,
            'pageSize' => 50,
        ];

        $response = $this->client->getNotifications($filters);

        // Verify request query parameters
        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals('2024-01-01T00:00:00Z', $query['dateFrom']);
        $this->assertEquals('2024-01-31T23:59:59Z', $query['dateTo']);
        $this->assertEquals(NotificationTypeEnum::DOCUMENT_RECEIVED->value, $query['type']);
        $this->assertEquals('en', $query['language']);
        $this->assertEquals(NotificationStatusEnum::DELIVERED->value, $query['status']);
        $this->assertEquals(1, $query['pageNo']);
        $this->assertEquals(50, $query['pageSize']);
    }

    /** @test */
    public function it_validates_page_size(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Page size must be between 1 and 100');

        $this->client->getNotifications(['pageSize' => 101]);
    }

    /** @test */
    public function it_validates_language_code(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Language must be either "ms" or "en"');

        $this->client->getNotifications(['language' => 'invalid']);
    }

    /** @test */
    public function it_validates_notification_type(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid notification type');

        $this->client->getNotifications(['type' => 999]);
    }

    /** @test */
    public function it_validates_notification_status(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid notification status');

        $this->client->getNotifications(['status' => 999]);
    }

    /** @test */
    public function it_validates_date_range(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Start date must be before end date');

        $this->client->getNotifications([
            'dateFrom' => '2024-02-01T00:00:00Z',
            'dateTo' => '2024-01-01T00:00:00Z',
        ]);
    }

    /** @test */
    public function it_handles_invalid_response_format(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'invalid' => 'format',
            ]))
        );

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid response format from notifications endpoint');

        $this->client->getNotifications();
    }

    /** @test */
    public function it_handles_api_errors(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(500, [], json_encode([
                'error' => 'internal_error',
                'message' => 'An unexpected error occurred',
            ]))
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Failed to retrieve notifications'));

        $this->client->setLogger($logger);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('An unexpected error occurred');

        $this->client->getNotifications();
    }

    /** @test */
    public function it_accepts_datetime_objects_for_date_range(): void
    {
        $this->mockSuccessfulAuthentication();

        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [],
                'metadata' => ['hasNext' => false],
            ]))
        );

        $dateFrom = new \DateTimeImmutable('2024-01-01T00:00:00Z');
        $dateTo = new \DateTimeImmutable('2024-01-31T23:59:59Z');

        $response = $this->client->getNotifications([
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);

        $request = $this->getLastRequest();
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->assertEquals($dateFrom->format('Y-m-d\TH:i:s\Z'), $query['dateFrom']);
        $this->assertEquals($dateTo->format('Y-m-d\TH:i:s\Z'), $query['dateTo']);
    }

    /** @test */
    public function it_retrieves_paginated_results(): void
    {
        $this->mockSuccessfulAuthentication();

        // Mock first page
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    [
                        'notificationId' => 'NOTIF1',
                        'receiverName' => 'Test Company Sdn Bhd',
                        'notificationDeliveryId' => 'DEL1',
                        'creationDateTime' => '2024-01-01T10:00:00Z',
                        'receivedDateTime' => '2024-01-01T10:00:05Z',
                        'notificationSubject' => 'Document 1',
                        'typeId' => NotificationTypeEnum::DOCUMENT_RECEIVED->value,
                        'typeName' => 'Document received',
                        'address' => 'test@company.com',
                        'language' => 'en',
                        'status' => 'DELIVERED',
                        'deliveryAttempts' => [],
                    ],
                ],
                'metadata' => [
                    'hasNext' => true,
                ],
            ]))
        );

        // First page
        $response1 = $this->client->getNotifications(['pageNo' => 1, 'pageSize' => 1]);
        $this->assertCount(1, $response1['result']);
        $this->assertTrue($response1['metadata']['hasNext']);

        // Mock second page
        $this->mockHandler->append(
            new Response(200, [], json_encode([
                'result' => [
                    [
                        'notificationId' => 'NOTIF2',
                        'receiverName' => 'Test Company Sdn Bhd',
                        'notificationDeliveryId' => 'DEL2',
                        'creationDateTime' => '2024-01-01T10:00:00Z',
                        'receivedDateTime' => '2024-01-01T10:00:05Z',
                        'notificationSubject' => 'Document 2',
                        'typeId' => NotificationTypeEnum::DOCUMENT_RECEIVED->value,
                        'typeName' => 'Document received',
                        'address' => 'test@company.com',
                        'language' => 'en',
                        'status' => 'DELIVERED',
                        'deliveryAttempts' => [],
                    ],
                ],
                'metadata' => [
                    'hasNext' => false,
                ],
            ]))
        );

        // Second page
        $response2 = $this->client->getNotifications(['pageNo' => 2, 'pageSize' => 1]);
        $this->assertCount(1, $response2['result']);
        $this->assertFalse($response2['metadata']['hasNext']);

        // Verify different notification IDs
        $notification1 = Notification::fromArray($response1['result'][0]);
        $notification2 = Notification::fromArray($response2['result'][0]);
        $this->assertNotEquals($notification1->notificationId, $notification2->notificationId);
    }
}

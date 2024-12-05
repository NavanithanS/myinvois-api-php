<?php

namespace Nava\MyInvois\Tests\Unit\Data;

use DateTimeImmutable;
use Nava\MyInvois\Data\DeliveryAttempt;
use Nava\MyInvois\Data\Notification;
use Nava\MyInvois\Enums\NotificationStatusEnum;
use Nava\MyInvois\Enums\NotificationTypeEnum;
use Nava\MyInvois\Tests\TestCase;

class NotificationTest extends TestCase
{
    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validData = [
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
        ];
    }

    /** @test */
    public function it_creates_from_valid_data(): void
    {
        $notification = Notification::fromArray($this->validData);

        $this->assertEquals('NOTIF123', $notification->notificationId);
        $this->assertEquals('Test Company Sdn Bhd', $notification->receiverName);
        $this->assertEquals('DEL456', $notification->notificationDeliveryId);
        $this->assertInstanceOf(DateTimeImmutable::class, $notification->creationDateTime);
        $this->assertInstanceOf(DateTimeImmutable::class, $notification->receivedDateTime);
        $this->assertEquals('Document Received', $notification->notificationSubject);
        $this->assertInstanceOf(DateTimeImmutable::class, $notification->deliveredDateTime);
        $this->assertEquals(NotificationTypeEnum::DOCUMENT_RECEIVED->value, $notification->typeId);
        $this->assertEquals('Document received', $notification->typeName);
        $this->assertEquals('Your document has been received successfully', $notification->finalMessage);
        $this->assertEquals('test@company.com', $notification->address);
        $this->assertEquals('en', $notification->language);
        $this->assertEquals('DELIVERED', $notification->status);
        $this->assertCount(1, $notification->deliveryAttempts);
        $this->assertInstanceOf(DeliveryAttempt::class, $notification->deliveryAttempts[0]);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $requiredFields = [
            'notificationId',
            'receiverName',
            'notificationDeliveryId',
            'creationDateTime',
            'receivedDateTime',
            'notificationSubject',
            'typeId',
            'typeName',
            'address',
            'language',
            'status',
            'deliveryAttempts',
        ];

        foreach ($requiredFields as $field) {
            $invalidData = $this->validData;
            unset($invalidData[$field]);

            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage(sprintf('%s is required', ucfirst(preg_replace('/([A-Z])/', ' $1', $field))));

            Notification::fromArray($invalidData);
        }
    }

    /** @test */
    public function it_validates_language_code(): void
    {
        $this->validData['language'] = 'invalid';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Language must be either "ms" or "en"');

        Notification::fromArray($this->validData);
    }

    /** @test */
    public function it_validates_notification_type(): void
    {
        $this->validData['typeId'] = 999;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid notification type ID');

        Notification::fromArray($this->validData);
    }

    /** @test */
    public function it_handles_optional_fields(): void
    {
        unset($this->validData['deliveredDateTime']);
        unset($this->validData['finalMessage']);

        $notification = Notification::fromArray($this->validData);

        $this->assertNull($notification->deliveredDateTime);
        $this->assertNull($notification->finalMessage);
    }

    /** @test */
    public function it_converts_to_enum_types(): void
    {
        $notification = Notification::fromArray($this->validData);

        $type = $notification->getType();
        $status = $notification->getStatus();

        $this->assertInstanceOf(NotificationTypeEnum::class, $type);
        $this->assertEquals(NotificationTypeEnum::DOCUMENT_RECEIVED, $type);

        $this->assertInstanceOf(NotificationStatusEnum::class, $status);
        $this->assertEquals(NotificationStatusEnum::DELIVERED, $status);
    }

    /** @test */
    public function it_determines_delivery_status(): void
    {
        // Test delivered notification
        $notification = Notification::fromArray($this->validData);
        $this->assertTrue($notification->isDelivered());

        // Test pending notification
        $this->validData['status'] = 'PENDING';
        $notification = Notification::fromArray($this->validData);
        $this->assertFalse($notification->isDelivered());
    }

    /** @test */
    public function it_handles_delivery_attempts(): void
    {
        // Multiple delivery attempts
        $this->validData['deliveryAttempts'] = [
            [
                'attemptDateTime' => '2024-01-01T10:00:00Z',
                'status' => 'error',
                'statusDetails' => 'Connection failed',
            ],
            [
                'attemptDateTime' => '2024-01-01T10:00:10Z',
                'status' => 'delivered',
                'statusDetails' => null,
            ],
        ];

        $notification = Notification::fromArray($this->validData);

        $this->assertCount(2, $notification->deliveryAttempts);
        $lastAttempt = $notification->getLastDeliveryAttempt();
        $this->assertInstanceOf(DeliveryAttempt::class, $lastAttempt);
        $this->assertEquals('delivered', $lastAttempt->status);
    }

    /** @test */
    public function it_detects_delivery_errors(): void
    {
        // Test successful delivery
        $notification = Notification::fromArray($this->validData);
        $this->assertFalse($notification->hasDeliveryErrors());

        // Test error in delivery attempts
        $this->validData['deliveryAttempts'] = [
            [
                'attemptDateTime' => '2024-01-01T10:00:00Z',
                'status' => 'error',
                'statusDetails' => 'Connection failed',
            ],
        ];
        $notification = Notification::fromArray($this->validData);
        $this->assertTrue($notification->hasDeliveryErrors());

        // Test error status
        $this->validData['status'] = 'ERROR';
        $notification = Notification::fromArray($this->validData);
        $this->assertTrue($notification->hasDeliveryErrors());
    }

    /** @test */
    public function it_serializes_to_json(): void
    {
        $notification = Notification::fromArray($this->validData);
        $json = json_encode($notification);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertEquals($notification->notificationId, $decoded['notificationId']);
        $this->assertEquals($notification->receiverName, $decoded['receiverName']);
        $this->assertEquals(
            $notification->creationDateTime->format('c'),
            $decoded['creationDateTime']
        );
        $this->assertEquals(
            $notification->deliveryAttempts[0]->attemptDateTime->format('c'),
            $decoded['deliveryAttempts'][0]['attemptDateTime']
        );
    }
}

class DeliveryAttemptTest extends TestCase
{
    /** @test */
    public function it_creates_from_valid_data(): void
    {
        $data = [
            'attemptDateTime' => '2024-01-01T10:00:00Z',
            'status' => 'delivered',
            'statusDetails' => null,
        ];

        $attempt = DeliveryAttempt::fromArray($data);

        $this->assertInstanceOf(DateTimeImmutable::class, $attempt->attemptDateTime);
        $this->assertEquals('delivered', $attempt->status);
        $this->assertNull($attempt->statusDetails);
    }

    /** @test */
    public function it_validates_required_fields(): void
    {
        $data = [
            'status' => 'delivered',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Attempt date time is required');

        DeliveryAttempt::fromArray($data);
    }

    /** @test */
    public function it_validates_status_values(): void
    {
        $data = [
            'attemptDateTime' => '2024-01-01T10:00:00Z',
            'status' => 'invalid',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status must be either "delivered" or "error"');

        DeliveryAttempt::fromArray($data);
    }

    /** @test */
    public function it_handles_optional_status_details(): void
    {
        $data = [
            'attemptDateTime' => '2024-01-01T10:00:00Z',
            'status' => 'error',
            'statusDetails' => 'Connection failed',
        ];

        $attempt = DeliveryAttempt::fromArray($data);
        $this->assertEquals('Connection failed', $attempt->statusDetails);

        unset($data['statusDetails']);
        $attempt = DeliveryAttempt::fromArray($data);
        $this->assertNull($attempt->statusDetails);
    }

    /** @test */
    public function it_serializes_to_json(): void
    {
        $data = [
            'attemptDateTime' => '2024-01-01T10:00:00Z',
            'status' => 'delivered',
            'statusDetails' => 'Test details',
        ];

        $attempt = DeliveryAttempt::fromArray($data);
        $json = json_encode($attempt);
        $decoded = json_decode($json, true);

        $this->assertIsString($json);
        $this->assertEquals(
            $attempt->attemptDateTime->format('c'),
            $decoded['attemptDateTime']
        );
        $this->assertEquals($attempt->status, $decoded['status']);
        $this->assertEquals($attempt->statusDetails, $decoded['statusDetails']);
    }
}

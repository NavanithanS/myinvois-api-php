<?php

namespace Nava\MyInvois\Data;

use DateTimeImmutable;
use JsonSerializable;
use Nava\MyInvois\Enums\NotificationStatusEnum;
use Nava\MyInvois\Enums\NotificationTypeEnum;
use Spatie\DataTransferObject\DataTransferObject;
use Webmozart\Assert\Assert;

/**
 * Represents a notification in the MyInvois system.
 */
class Notification extends DataTransferObject implements JsonSerializable
{
    public string $notificationId;
    public string $receiverName;
    public string $notificationDeliveryId;
    public DateTimeImmutable $creationDateTime;
    public DateTimeImmutable $receivedDateTime;
    public string $notificationSubject;
    public ?DateTimeImmutable $deliveredDateTime;
    public int $typeId;
    public string $typeName;
    public ?string $finalMessage;
    public string $address;
    public string $language;
    public string $status;
    /** @var DeliveryAttempt[] */
    public array $deliveryAttempts;

    /**
     * Create a new Notification instance from an array.
     *
     * @param array $data Raw data from API
     * @throws \InvalidArgumentException If data validation fails
     */
    public static function fromArray(array $data): self
    {
        Assert::keyExists($data, 'notificationId', 'Notification ID is required');
        Assert::keyExists($data, 'receiverName', 'Receiver name is required');
        Assert::keyExists($data, 'notificationDeliveryId', 'Notification delivery ID is required');
        Assert::keyExists($data, 'creationDateTime', 'Creation date time is required');
        Assert::keyExists($data, 'receivedDateTime', 'Received date time is required');
        Assert::keyExists($data, 'notificationSubject', 'Subject is required');
        Assert::keyExists($data, 'typeId', 'Type ID is required');
        Assert::keyExists($data, 'typeName', 'Type name is required');
        Assert::keyExists($data, 'address', 'Address is required');
        Assert::keyExists($data, 'language', 'Language is required');
        Assert::keyExists($data, 'status', 'Status is required');
        Assert::keyExists($data, 'deliveryAttempts', 'Delivery attempts array is required');

        // Validate language
        Assert::inArray($data['language'], ['ms', 'en'], 'Language must be either "ms" or "en"');

        // Validate notification type
        Assert::true(NotificationTypeEnum::isValidCode((int) $data['typeId']), 'Invalid notification type ID');

        return new self([
            'notificationId' => $data['notificationId'],
            'receiverName' => $data['receiverName'],
            'notificationDeliveryId' => $data['notificationDeliveryId'],
            'creationDateTime' => new DateTimeImmutable($data['creationDateTime']),
            'receivedDateTime' => new DateTimeImmutable($data['receivedDateTime']),
            'notificationSubject' => $data['notificationSubject'],
            'deliveredDateTime' => isset($data['deliveredDateTime'])
            ? new DateTimeImmutable($data['deliveredDateTime'])
            : null,
            'typeId' => (int) $data['typeId'],
            'typeName' => $data['typeName'],
            'finalMessage' => $data['finalMessage'] ?? null,
            'address' => $data['address'],
            'language' => $data['language'],
            'status' => $data['status'],
            'deliveryAttempts' => array_map(
                fn(array $attempt) => DeliveryAttempt::fromArray($attempt),
                $data['deliveryAttempts']
            ),
        ]);
    }

    public function toArray(): array
    {
        return [
            'notificationId' => $this->notificationId,
            'receiverName' => $this->receiverName,
            'notificationDeliveryId' => $this->notificationDeliveryId,
            'creationDateTime' => $this->creationDateTime->format('Y-m-d H:i:s'),
            'receivedDateTime' => $this->receivedDateTime->format('Y-m-d H:i:s'),
            'notificationSubject' => $this->notificationSubject,
            'deliveredDateTime' => $this->deliveredDateTime ? $this->deliveredDateTime->format('Y-m-d H:i:s') : null,
            'typeId' => $this->typeId,
            'typeName' => $this->typeName,
            'finalMessage' => $this->finalMessage,
            'address' => $this->address,
            'language' => $this->language,
            'status' => $this->status,
            'deliveryAttempts' => array_map(function (DeliveryAttempt $attempt) {
                return $attempt->toArray();
            }, $this->deliveryAttempts),
        ];
    }

    /**
     * Get the notification type enum instance.
     */
    public function getType(): NotificationTypeEnum
    {
        return NotificationTypeEnum::from($this->typeId);
    }

    /**
     * Get the notification status enum instance.
     */
    public function getStatus(): NotificationStatusEnum
    {
        $statusMap = [
            'NEW' => NotificationStatusEnum::NEW ,
            'PENDING' => NotificationStatusEnum::PENDING,
            'BATCHED' => NotificationStatusEnum::BATCHED,
            'DELIVERED' => NotificationStatusEnum::DELIVERED,
            'ERROR' => NotificationStatusEnum::ERROR,
        ];

        return $statusMap[strtoupper($this->status)] ?? NotificationStatusEnum::ERROR;
    }

    /**
     * Check if the notification has been delivered.
     */
    public function isDelivered(): bool
    {
        return $this->getStatus() === NotificationStatusEnum::DELIVERED;
    }

    /**
     * Get the last delivery attempt if any.
     */
    public function getLastDeliveryAttempt(): ?DeliveryAttempt
    {
        if (empty($this->deliveryAttempts)) {
            return null;
        }

        return end($this->deliveryAttempts);
    }

    /**
     * Check if there were any delivery errors.
     */
    public function hasDeliveryErrors(): bool
    {
        return $this->getStatus() === NotificationStatusEnum::ERROR ||
        array_reduce(
            $this->deliveryAttempts,
            fn(bool $carry, DeliveryAttempt $attempt) => $carry || 'error' === $attempt->status,
            false
        );
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'notificationId' => $this->notificationId,
            'receiverName' => $this->receiverName,
            'notificationDeliveryId' => $this->notificationDeliveryId,
            'creationDateTime' => $this->creationDateTime->format('c'),
            'receivedDateTime' => $this->receivedDateTime->format('c'),
            'notificationSubject' => $this->notificationSubject,
            'deliveredDateTime' => $this->deliveredDateTime?->format('c'),
            'typeId' => $this->typeId,
            'typeName' => $this->typeName,
            'finalMessage' => $this->finalMessage,
            'address' => $this->address,
            'language' => $this->language,
            'status' => $this->status,
            'deliveryAttempts' => array_map(
                fn(DeliveryAttempt $attempt) => $attempt->jsonSerialize(),
                $this->deliveryAttempts
            ),
        ];
    }
}

/**
 * Represents a delivery attempt for a notification.
 */
class DeliveryAttempt extends DataTransferObject implements JsonSerializable
{
    public DateTimeImmutable $attemptDateTime;
    public string $status;
    public ?string $statusDetails;

    /**
     * Create a new DeliveryAttempt instance from an array.
     *
     * @param array $data Raw data from API
     * @throws \InvalidArgumentException If data validation fails
     */
    public static function fromArray(array $data): self
    {
        Assert::keyExists($data, 'attemptDateTime', 'Attempt date time is required');
        Assert::keyExists($data, 'status', 'Status is required');
        Assert::inArray($data['status'], ['delivered', 'error'], 'Status must be either "delivered" or "error"');

        return new self([
            'attemptDateTime' => new DateTimeImmutable($data['attemptDateTime']),
            'status' => $data['status'],
            'statusDetails' => $data['statusDetails'] ?? null,
        ]);
    }

    public function toArray(): array
    {
        return [
            'attemptDateTime' => $this->attemptDateTime->format('Y-m-d H:i:s'),
            'status' => $this->status,
            'statusDetails' => $this->statusDetails,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function jsonSerialize(): array
    {
        return [
            'attemptDateTime' => $this->attemptDateTime->format('c'),
            'status' => $this->status,
            'statusDetails' => $this->statusDetails,
        ];
    }
}

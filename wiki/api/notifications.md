---
tags: [api, notifications]
updated: 2026-04-18
---

# Notifications

Retrieve taxpayer notifications from MyInvois with optional filtering.

## Key File

`src/Api/NotificationsApi.php`

## Endpoint

```
GET /api/v1.0/notifications/taxpayer
```

## Usage

```php
$result = $client->getNotifications([
    'dateFrom'  => '2024-01-01T00:00:00Z',
    'dateTo'    => '2024-01-31T23:59:59Z',
    'type'      => 3,
    'language'  => 'en',
    'status'    => 1,
    'pageNo'    => 1,
    'pageSize'  => 50,
]);

// $result['result']            — array of notification objects
// $result['metadata']['hasNext'] — boolean for pagination
```

## Filter Parameters

| Parameter | Type | Constraints |
|-----------|------|-------------|
| `dateFrom` | string/DateTimeInterface | Formatted to `Y-m-d\TH:i:s\Z` |
| `dateTo` | string/DateTimeInterface | Formatted to `Y-m-d\TH:i:s\Z` |
| `type` | int | See notification types below |
| `language` | string | `"ms"` or `"en"` only |
| `status` | int | 1–5 |
| `pageNo` | int | ≥ 1 |
| `pageSize` | int | 1–100 |

Date range validation: `dateFrom` must not be after `dateTo`.

## Notification Types

Valid type codes: `3, 6, 7, 8, 10, 11, 15, 26, 33, 34, 35`

See `src/Enums/NotificationTypeEnum.php` for the mapping of codes to names.

## Notification Statuses

Valid status codes: `1, 2, 3, 4, 5`

See `src/Enums/NotificationStatusEnum.php` for code-to-name mapping.

## Response

```json
{
  "result": [
    { /* notification object */ }
  ],
  "metadata": {
    "hasNext": false
  }
}
```

Both `result` and `metadata` fields are required — `ApiException` is thrown if either is absent.

## DTO

Notification objects are modelled in `src/Data/Notification.php`.

## Related

- [[overview]] — client modes (notifications are per-taxpayer in intermediary mode)

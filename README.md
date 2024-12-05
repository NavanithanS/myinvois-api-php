# MyInvois PHP Client Library

A robust, feature-rich PHP client library for interacting with Malaysia's MyInvois API for tax document submissions.

## Features

-   Easy to use, intuitive API
-   Supports authentication and token management
-   Handles document type, document type version, and notification operations
-   Supports submitting single and multiple documents
-   Built-in response validation and error handling
-   Customizable caching and logging
-   Laravel integration via service provider and facade
-   Comprehensive test suite
-   Adheres to PSR standards
-   Extensible architecture

## Installation

Install the library using Composer:

```bash
composer require nava/myinvois
```

## Usage

### Initialize the Client

You can create a client instance using the factory:

```php
$factory = new \Nava\MyInvois\MyInvoisClientFactory();

// Create a production client
$client = $factory->production('client_id', 'client_secret');

// Create a sandbox client
$sandboxClient = $factory->sandbox('sandbox_id', 'sandbox_secret');

// Create an intermediary client
$intermediaryClient = $factory->intermediary('client_id', 'client_secret', 'C1234567890');
```

### Configuration

The library supports various configuration options for customizing its behavior:

```php
$factory->configure([
    'cache' => [
        'enabled' => true,
        'store' => 'redis',
        'ttl' => 1800,
    ],
    'logging' => [
        'enabled' => true,
        'channel' => 'myinvois',
    ],
    'http' => [
        'timeout' => 15,
        'retry' => [
            'times' => 3,
            'sleep' => 1000,
        ],
    ],
]);
```

## Document Submission

### Submit a single document:

```php
    $response = $client->submitDocument(
        DocumentTypeEnum::INVOICE,
        DocumentFormat::JSON,
        [
            'invoiceNumber' => 'INV-001',
            'issueDate' => '2024-11-12',
            'dueDate' => '2024-12-12',
            'items' => [
                [
                    'description' => 'Product A',
                    'quantity' => 2,
                    'unitPrice' => 100.00,
                    'amount' => 200.00
                ]
            ],
            'totalAmount' => 200.00,
            'tax' => 12.00
            // ... other invoice data
        ],
        'C9876543210',  // Seller TIN
        'C1234567890'   // Buyer TIN
    );
```

### Submit multiple documents:

```php

    $response = $client->submitDocuments([
    [
        'documentType' => DocumentTypeEnum::INVOICE,
        'format' => DocumentFormat::JSON,
        'content' => [
            'invoiceNumber' => 'INV-001',
            // ... other invoice data
        ],
        'sellerTIN' => 'C25845632020',
        'buyerTIN' => 'C98765432100'
    ],
     [
        'documentType' => DocumentType::CREDIT_NOTE,
        'format' => DocumentFormat::XML,
        'content' => '<?xml version="1.0"?><creditNote>...</creditNote>',
        'sellerTIN' => 'C25845632020',
        'buyerTIN' => 'C98765432100'
    ]
    ]);
```

### Document Types API

Retrieve and work with document types:

```php
// Get all document types
$documentTypes = $client->getDocumentTypes();

// Get active document types
$activeTypes = $client->getActiveDocumentTypes();

// Find a document type by code
$invoiceType = $client->findDocumentTypeByCode(DocumentTypeEnum::INVOICE->value);

// Check if a document type is active
$isActive = $client->isDocumentTypeActive(DocumentTypeEnum::INVOICE->value);

// Get the latest version of a document type
$latestVersion = $invoiceType->getLatestVersion();
```

### Document Type Versions API

Retrieve and work with document type versions:

```php
// Get a specific document type version
$version = $client->getDocumentTypeVersion(45, 454);

// Find a version by number
$version = $client->findDocumentTypeVersion(45, 2.0);

// Get active versions for a document type
$activeVersions = $client->getActiveDocumentTypeVersions(45);

// Get the latest version for a document type
$latestVersion = $client->getLatestDocumentTypeVersion(45);
```

### Notifications API

Retrieve notifications with optional filtering:

```php
$filters = [
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-12-31',
    'type' => NotificationTypeEnum::DOCUMENT_RECEIVED->value,
    'language' => 'en',
    'status' => NotificationStatusEnum::NEW->value,
];

$notifications = $client->getNotifications($filters);
```

### Error Handling

The library throws custom exceptions for different error scenarios:

-   `Nava\MyInvois\Exception\ValidationException`: For validation errors
-   `Nava\MyInvois\Exception\AuthenticationException`: For authentication failures
-   `Nava\MyInvois\Exception\ApiException`: For general API errors
-   `Nava\MyInvois\Exception\NetworkException`: For network-related errors

Be sure to catch and handle these exceptions appropriately.

## Laravel Integration

If you're using Laravel, the library provides a service provider and facade for seamless integration.
Register the service provider and facade in your `config/app.php`:

```php
'providers' => [
    // ...
    \Nava\MyInvois\Laravel\MyInvoisServiceProvider::class,
],

'aliases' => [
    // ...
    'MyInvois' => \Nava\MyInvois\Laravel\Facades\MyInvois::class,
],
```

Then publish the config file:

```bash
php artisan vendor:publish --provider="Nava\MyInvois\Laravel\MyInvoisServiceProvider"
```

Configure your .env:

```php
MYINVOIS_CLIENT_ID=your_client_id
MYINVOIS_CLIENT_SECRET=your_secret
MYINVOIS_BASE_URL=https://api.myinvois.com
```

Now you can use the `MyInvois` facade to interact with the API:

```php
use MyInvois;

$documentTypes = MyInvois::getDocumentTypes();
```

# Document Types API

### Get All Document Types

Retrieve all document types from MyInvois:

```php
$documentTypes = $client->getDocumentTypes();

foreach ($documentTypes as $type) {
    echo "Type {$type->id}: {$type->description}\n";

    // Get latest version
    $latestVersion = $type->getLatestVersion();
    if ($latestVersion) {
        echo "Latest version: {$latestVersion->versionNumber}\n";
    }
}
```

### Get Active Document Types

Get only currently active document types:

```php
$activeTypes = $client->getActiveDocumentTypes();
```

### Find Document Type by Code

Find a specific document type using its code:

```php
use Nava\MyInvois\Enums\DocumentType;

$invoiceType = $client->findDocumentTypeByCode(DocumentType::INVOICE->value);

if ($invoiceType && $invoiceType->isActive()) {
    // Use the document type...
}
```

### Validate Document Type

Check if a document type is currently active:

```php
if ($client->isDocumentTypeActive(DocumentType::INVOICE->value)) {
    // Document type is active...
}
```

### Document Type Versions

Each document type can have multiple versions:

```php
$documentType = $client->findDocumentTypeByCode(DocumentType::INVOICE->value);

// Get all active versions
$activeVersions = $documentType->getActiveVersions();

// Get the latest version
$latestVersion = $documentType->getLatestVersion();
if ($latestVersion) {
    echo "Latest version: {$latestVersion->versionNumber}\n";
    echo "Status: {$latestVersion->status}\n";
}
```

## Changelog

Please see CHANGELOG for more information on what has changed recently.

## Testing

The library includes a comprehensive test suite. To run the tests:

```bash
composer test
```

## Contributing

Please see CONTRIBUTING for details.

## Security Vulnerabilities

Please review our security policy on how to report security vulnerabilities.

## Credits

-   All Contributors

## License

The MIT License (MIT). Please see License File for more information.

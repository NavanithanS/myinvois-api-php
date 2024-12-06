# MyInvois PHP Client Library 🇲🇾

> **⚠️ BETA STATUS**: This library is currently in beta. While it's functional and being actively developed, APIs may change before final release. Please test thoroughly before using in production.

A robust PHP client library for interacting with Malaysia's MyInvois API for tax document submissions. This library provides a clean, type-safe interface for all MyInvois API operations with comprehensive validation and error handling.

## Features

-   🔒 Secure OAuth2 authentication and token management
-   📄 Full support for all document operations:
    -   Document submission (single & batch)
    -   Document retrieval and search
    -   Document type management
    -   Status tracking
-   🏢 Complete taxpayer operations support
-   ✅ Built-in validation for all entity types
-   🚦 Rate limiting and retry mechanisms
-   📦 Laravel integration via service provider
-   🧪 Comprehensive test coverage

## Requirements

-   PHP 8.1 or higher
-   OpenSSL PHP Extension
-   JSON PHP Extension
-   Composer 2.0+

## Installation

Install via Composer:

```bash
composer require nava/myinvois
```

## Quick Start

```php
use Nava\MyInvois\MyInvoisClientFactory;

// Create client instance
$factory = new MyInvoisClientFactory();
$client = $factory->production(
    'your_client_id',
    'your_client_secret'
);

// Submit a document
$response = $client->submitDocument([
    'invoiceNumber' => 'INV-001',
    'issueDate' => '2024-01-01',
    'totalAmount' => 1000.00,
    'items' => [
        [
            'description' => 'Product A',
            'quantity' => 2,
            'unitPrice' => 500.00,
        ]
    ]
]);

// Check document status
$status = $client->getDocumentStatus($response['documentId']);
```

## Laravel Integration

1. Register the service provider in `config/app.php`:

```php
'providers' => [
    Nava\MyInvois\Laravel\MyInvoisServiceProvider::class,
],

'aliases' => [
    'MyInvois' => Nava\MyInvois\Laravel\Facades\MyInvois::class,
]
```

2. Publish the config file:

```bash
php artisan vendor:publish --provider="Nava\MyInvois\Laravel\MyInvoisServiceProvider"
```

3. Configure in `.env`:

```env
MYINVOIS_CLIENT_ID=your_client_id
MYINVOIS_CLIENT_SECRET=your_client_secret
MYINVOIS_BASE_URL=https://api.myinvois.com
```

4. Use the facade:

```php
use MyInvois;

$documents = MyInvois::getDocuments([
    'startDate' => '2024-01-01',
    'endDate' => '2024-01-31'
]);
```

## Advanced Usage

### Document Submission

Submit single documents or batches:

```php
// Single document
$response = $client->submitDocument([
    'documentType' => DocumentTypeEnum::INVOICE,
    'format' => DocumentFormat::JSON,
    'content' => [
        'invoiceNumber' => 'INV-001',
        // ... other fields
    ]
]);

// Batch submission
$response = $client->submitDocuments([
    [
        'documentType' => DocumentTypeEnum::INVOICE,
        'content' => [...],
    ],
    [
        'documentType' => DocumentTypeEnum::CREDIT_NOTE,
        'content' => [...],
    ]
]);
```

### Document Search

Search with filters:

```php
$documents = $client->searchDocuments([
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-01-31',
    'status' => 'Valid',
    'type' => DocumentTypeEnum::INVOICE
]);
```

### Error Handling

The library throws typed exceptions for different error scenarios:

```php
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\ApiException;

try {
    $result = $client->submitDocument($doc);
} catch (ValidationException $e) {
    // Handle validation errors
    $errors = $e->getErrors();
} catch (AuthenticationException $e) {
    // Handle auth errors
} catch (ApiException $e) {
    // Handle other API errors
}
```

### Configuration Options

```php
$factory->configure([
    'cache' => [
        'enabled' => true,
        'store' => 'redis',
        'ttl' => 3600
    ],
    'logging' => [
        'enabled' => true,
        'channel' => 'myinvois'
    ],
    'http' => [
        'timeout' => 30,
        'retry' => [
            'times' => 3,
            'sleep' => 1000
        ]
    ]
]);
```

## Beta Status Notice

While in beta:

-   APIs may change without major version bump
-   Additional features will be added
-   Documentation will be expanded
-   More comprehensive test coverage will be added
-   Performance optimizations will be made

Please report any issues or suggestions via GitHub issues.

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/awesome-feature`)
3. Commit your changes (`git commit -m 'Add awesome feature'`)
4. Push to the branch (`git push origin feature/awesome-feature`)
5. Create a Pull Request

## Testing

Run the test suite:

```bash
composer test
```

Run static analysis:

```bash
composer analyse
```

Format code:

```bash
composer format
```

## Security

If you discover any security vulnerabilities, please email gua@navins.biz instead of using the issue tracker.

## License

This library is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Credits

[All Contributors](../../contributors)

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

-   PHP 7.1 or higher
-   JSON PHP Extension
-   OpenSSL PHP Extension
-   Composer

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
$response = $client->createDocument([
    'invoice_no' => 'INV-001',
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
    'total_amount' => 1000.00,
    'supplierTIN' => 'C1234567890',
    'supplierIC' => 'IC12345678',
    'supplierIdType' => 'NRIC',
    'supplierName' => 'Supplier Company',
    'supplierPhone' => '0123456789',
    'supplierEmail' => 'supplier@example.com',
    'supplierAddress1' => '123 Supplier Street',
    'supplierAddress2' => 'Supplier Area',
    'supplierPostcode' => '50000',
    'supplierCity' => 'Kuala Lumpur',
    'supplierState' => 'Wilayah Persekutuan Kuala Lumpur',
    'buyerTIN' => 'C0987654321',
    'buyerIC' => 'IC87654321',
    'buyerIdType' => 'NRIC',
    'buyerName' => 'Buyer Company',
    'buyerPhone' => '9876543210',
    'buyerEmail' => 'buyer@example.com',
    'buyerAddress1' => '456 Buyer Street',
    'buyerAddress2' => 'Buyer Area',
    'buyerPostcode' => '40000',
    'buyerCity' => 'Shah Alam',
    'buyerState' => 'Selangor'
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
MYINVOIS_BASE_URL=https://preprod.myinvois.hasil.gov.my
MYINVOIS_SSLCERT_PATH=/path/to/ssl/cert.pem
MYINVOIS_SIGNSIG_PATH=/path/to/signed/signature.pem
MYINVOIS_PRIVATEKEY_PATH=/path/to/private/key.pem
MYINVOIS_SUPPLIER_TIN=C1234567890
MYINVOIS_SUPPLIER_IC=IC12345678
```

4. Use the facade:

```php
use MyInvois;

$response = MyInvois::createDocument([
    'invoice_no' => 'INV-001',
    'date_from' => '2024-01-01',
    'date_to' => '2024-01-31',
    'total_amount' => 1000.00,
    // Additional required fields as shown in Quick Start
]);
```

## Advanced Usage

### Document Submission

Submit documents with all required fields:

```php
// Create a Request object with document data
$request = new \Illuminate\Http\Request();
$request->merge([
    'invoice_no' => 'INV-002',
    'date_from' => '2024-02-01',
    'date_to' => '2024-02-29',
    'total_amount' => 2500.00,
    'supplierTIN' => 'C1234567890',
    'supplierIC' => 'IC12345678',
    'supplierIdType' => 'NRIC',
    'supplierName' => 'Supplier Company',
    // Complete all required fields
]);

$response = $client->createDocument($request);
```

### Document Retrieval

```php
// Get document by UUID
$document = $client->getDocument('ABC12345DEFGHI');

// Generate QR code for a document
$qrCode = $client->generateQrCode('ABC12345DEFGHI');
```

### Taxpayer Validation

```php
// Validate taxpayer TIN with secondary identification
$isValid = $client->validateTaxpayerTin('NRIC', 'C1234567890', 'IC12345678');

// Search for a taxpayer's TIN
$tinInfo = $client->getTaxpayerTin('NRIC', 'IC12345678');
```

### Error Handling

The library throws typed exceptions for different error scenarios:

```php
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Exception\AuthenticationException;
use Nava\MyInvois\Exception\ApiException;

try {
    $result = $client->createDocument($request);
} catch (ValidationException $e) {
    // Handle validation errors
    $errors = $e->getErrors();
} catch (AuthenticationException $e) {
    // Handle auth errors
} catch (ApiException $e) {
    // Handle other API errors
}
```

## Configuration Options

When creating a client, you can pass additional options:

```php
$factory = new MyInvoisClientFactory();
$client = $factory->make(
    'your_client_id',
    'your_client_secret',
    'https://preprod.myinvois.hasil.gov.my',
    null,
    [
        'cache' => [
            'enabled' => true,
            'ttl' => 3600 // 1 hour
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
    ]
);
```

## Environments

```php
// Production
$client = $factory->production('your_client_id', 'your_client_secret');

// Sandbox
$client = $factory->sandbox('your_client_id', 'your_client_secret');

// Intermediary (for service providers)
$client = $factory->intermediary(
    'your_client_id',
    'your_client_secret',
    'taxpayer_TIN'
);
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

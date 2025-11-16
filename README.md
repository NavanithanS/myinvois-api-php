# MyInvois PHP Client Library 🇲🇾

<div align="center">

[![Latest Stable Version](https://img.shields.io/packagist/v/nava/myinvois.svg)](https://packagist.org/packages/nava/myinvois)
[![License](https://img.shields.io/packagist/l/nava/myinvois.svg)](https://github.com/NavanithanS/myinvois-api-php/blob/master/LICENSE)
[![PHP Version Require](https://img.shields.io/packagist/php-v/nava/myinvois.svg)](https://packagist.org/packages/nava/myinvois)

</div>

> **⚠️ BETA STATUS**: This library is currently in beta. While functional and actively developed, APIs may change before final release. Please test thoroughly before using in production.

A robust PHP client library for Malaysia's MyInvois API, providing a clean, type-safe interface for tax document submissions with comprehensive validation and error handling.

## ✨ Features

-   🔒 **Secure Authentication** - OAuth2 with automatic token management
-   📄 **Complete Document Operations** - Submit, retrieve, search, and manage documents
-   🏢 **Taxpayer Services** - Validation and TIN operations
-   ✅ **Built-in Validation** - Comprehensive input validation for all entity types
-   🚦 **Rate Limiting** - Built-in retry mechanisms with exponential backoff
-   📦 **Laravel Ready** - Auto-discovery service provider and facades
-   🧪 **Thoroughly Tested** - Comprehensive test coverage with PHPUnit
-   🔧 **Developer Friendly** - PSR-4 autoloading, typed exceptions, and clear documentation

## 📋 Requirements

-   **PHP**: PHP 8.1+
-   **Extensions**: JSON, OpenSSL
-   **Composer**: For dependency management

## 🚀 Installation

Install via Composer:

```bash
composer require nava/myinvois
```

## ⚡ Quick Start

### Basic Usage

```php
<?php
use Nava\MyInvois\MyInvoisClient;

$client = new MyInvoisClient(
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    baseUrl: MyInvoisClient::SANDBOX_URL,
    cache: new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore),
    config: [
        'http' => [
            // In production set verify => true
            'verify' => false,
        ],
        'logging' => ['enabled' => true, 'channel' => 'stack'],
    ]
);

// Submit an invoice (convenience method)
$invoice = [
    'issueDate' => '2024-01-01',
    'totalAmount' => 1000.00,
    'items' => [[
        'description' => 'Service', 'quantity' => 1, 'unitPrice' => 1000.00, 'taxAmount' => 0
    ]],
];

$resp = $client->submitInvoice($invoice);
$status = $client->getSubmissionStatus($resp['submissionUID']);
```

### Environment selection

```php
// Use SANDBOX_URL for testing, PRODUCTION_URL for production
$client = new MyInvoisClient($id, $secret, MyInvoisClient::PRODUCTION_URL, $cache);

// Intermediary (service providers)
$client->onBehalfOf('C1234567890'); // set taxpayer TIN
```

## 🔧 Configuration

### Standalone

```php
use Nava\MyInvois\MyInvoisClient;

$client = new MyInvoisClient(
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    baseUrl: 'https://preprod.myinvois.hasil.gov.my',
    cache: new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore),
    config: [
        'cache' => ['enabled' => true, 'ttl' => 3600],
        'logging' => ['enabled' => true, 'channel' => 'myinvois'],
        'http' => [
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry' => ['times' => 3, 'sleep' => 1000],
            'verify' => false, // set true in production
        ],
    ]
);
```

### Laravel Integration

#### 1. Service Provider Registration

The package uses Laravel auto-discovery. If you need manual registration:

```php
// config/app.php
'providers' => [
    Nava\MyInvois\Laravel\MyInvoisServiceProvider::class,
],

'aliases' => [
    'MyInvois' => Nava\MyInvois\Laravel\Facades\MyInvois::class,
]
```

#### 2. Publish Configuration

```bash
php artisan vendor:publish --provider="Nava\MyInvois\Laravel\MyInvoisServiceProvider"
```

#### 3. Environment Variables

Add to your `.env` file:

```env
# Required
MYINVOIS_CLIENT_ID=your_client_id
MYINVOIS_CLIENT_SECRET=your_client_secret

# Environment URLs
MYINVOIS_BASE_URL=https://preprod.myinvois.hasil.gov.my
MYINVOIS_AUTH_URL=https://preprod-api.myinvois.hasil.gov.my
# Optional: pin a CA/cert for TLS

# Certificate paths (if required)
MYINVOIS_SSLCERT_PATH=/path/to/ssl/cert.pem
MYINVOIS_SIGNSIG_PATH=/path/to/signed/signature.pem
MYINVOIS_PRIVATEKEY_PATH=/path/to/private/key.pem

# Default taxpayer information
MYINVOIS_SUPPLIER_TIN=C1234567890
MYINVOIS_SUPPLIER_IC=IC12345678

# Optional: Intermediary settings
MYINVOIS_INTERMEDIARY_ENABLED=false
MYINVOIS_DEFAULT_TAXPAYER_TIN=

# Optional: Performance settings
MYINVOIS_CACHE_ENABLED=true
MYINVOIS_CACHE_TTL=3600
MYINVOIS_HTTP_TIMEOUT=30
MYINVOIS_LOGGING_ENABLED=true
```

#### 4. Laravel Usage

```php
use MyInvois;

// Using the facade
$response = MyInvois::submitDocuments($documents);

// Or inject the client
use Nava\MyInvois\MyInvoisClient;

class InvoiceController extends Controller
{
    public function __construct(private MyInvoisClient $myinvois) {}

    public function submit(Request $request)
    {
        $response = $this->myinvois->submitDocuments($documents);
        return response()->json($response);
    }
}
```

## 📚 Advanced Usage Examples

### Document Operations

```php
// Submit multiple documents
$documents = [
    [
        'document' => json_encode($document1),
        'documentHash' => hash('sha256', json_encode($document1)),
        'codeNumber' => 'INV-001',
    ],
    [
        'document' => json_encode($document2),
        'documentHash' => hash('sha256', json_encode($document2)),
        'codeNumber' => 'INV-002',
    ]
];
$response = $client->submitDocuments($documents);

// Get document by UUID
$document = $client->getDocument('ABC12345DEFGHI');

// Search documents (use submissionDate* or issueDate*)
$searchResults = $client->searchDocuments([
  'submissionDateFrom' => '2024-01-01T00:00:00Z',
  'submissionDateTo' => '2024-01-31T23:59:59Z',
  'status' => 'Valid',
  'pageSize' => 50,
  'pageNo' => 1,
]);

// Or by issue date
// $searchResults = $client->searchDocuments([
//   'issueDateFrom' => '2024-01-01T00:00:00Z',
//   'issueDateTo' => '2024-01-31T23:59:59Z',
// ]);

// Generate QR code for a document (returns data URI string)
$qrCode = $client->generateQrCode('ABC12345DEFGHI');
// e.g., <img src="$qrCode" alt="QR" />
```

### Taxpayer Operations

```php
// Validate TIN (idType case‑insensitive)
$isValid = $client->validateTaxpayerTin('C1234567890', 'nric', '770625015324');

// Get taxpayer TIN information
$tinInfo = $client->getTaxpayerTin('NRIC', '201901234567');
```

### Error Handling

```php
use Nava\MyInvois\Exception\{ValidationException, AuthenticationException, ApiException};

try {
    $response = $client->submitDocuments($documents);
} catch (ValidationException $e) {
    // Handle validation errors
    $errors = $e->getErrors();
    foreach ($errors as $field => $messages) {
        echo "Field $field: " . implode(', ', $messages) . "\n";
    }
} catch (AuthenticationException $e) {
    // Handle authentication errors
    echo "Authentication failed: " . $e->getMessage();
} catch (ApiException $e) {
    // Handle API errors
    echo "API Error: " . $e->getMessage();
    echo "Status Code: " . $e->getCode();
}
```

### Working with Document Types

```php
// Get available document types
$documentTypes = $client->getDocumentTypes();

// Get document type versions
$version = $client->findDocumentTypeVersion(45, 2.0);

// Get specific version details
$versionDetails = $client->getDocumentTypeVersionDetails('Invoice', '1.0');
```

## 🧪 Testing & Quality

```bash
# Tests
composer test

# Coverage
vendor/bin/phpunit --coverage-html build/coverage

# Static analysis & formatting
composer analyse
composer format
```

## 🏗️ Architecture

### Core Components

-   **MyInvoisClient** - Main client orchestrating all operations
-   **MyInvoisClientFactory** - Factory for environment-specific clients
-   **API Classes** - Document operations, taxpayer, notifications, etc.
-   **Authentication** - OAuth2 with token cache/refresh
-   **Validation** - Comprehensive input validation system
-   **Exception Handling** - `ValidationException`, `AuthenticationException`, `ApiException`

### Supported Operations

| Operation           | Description                           |
| ------------------- | ------------------------------------- |
| Document Submission | Submit single or batch documents      |
| Document Retrieval  | Get documents by UUID                 |
| Document Search     | Search with various filters           |
| Document Types      | Manage document type information      |
| Taxpayer Operations | Validate and retrieve TIN information |
| Notifications       | Manage system notifications           |
| Status Tracking     | Monitor submission status             |

## 📖 Documentation

The essentials are embedded here for convenience.

### Quickstart

Create a client

```php
use Nava\MyInvois\MyInvoisClient;

$client = new MyInvoisClient(
    clientId: getenv('MYINVOIS_CLIENT_ID') ?: 'your_client_id',
    clientSecret: getenv('MYINVOIS_CLIENT_SECRET') ?: 'your_client_secret',
    baseUrl: MyInvoisClient::SANDBOX_URL,
    cache: new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore),
    config: [
        'logging' => ['enabled' => true, 'channel' => 'stack'],
    ]
);
```

Check API status

```php
$status = $client->getApiStatus();
var_dump($status);
```

Submit an invoice (JSON)

```php
$invoice = [
    'issueDate' => date('Y-m-d'),
    'totalAmount' => 100.50,
    'items' => [
        ['description' => 'Service', 'quantity' => 1, 'unitPrice' => 100.50, 'taxAmount' => 0],
    ],
];

$resp = $client->submitInvoice($invoice);
echo $resp['documentId'] ?? '';
```

Handle errors

```php
try {
    $client->listDocuments(['page' => 1]);
} catch (\Nava\MyInvois\Exception\ValidationException $e) {
    // input errors
} catch (\Nava\MyInvois\Exception\ApiException $e) {
    // remote errors
}
```

### Authentication

Tokens are obtained from the identity service and cached. Refresh happens automatically.

```php
use Nava\MyInvois\MyInvoisClient;

$client = new MyInvoisClient(
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    baseUrl: MyInvoisClient::SANDBOX_URL,
    cache: new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore)
);
```

Intermediary (on behalf of)

```php
use Nava\MyInvois\Auth\IntermediaryAuthenticationClient;

// If you need intermediary semantics, inject an IntermediaryAuthenticationClient
// in config['auth']['client'] and call:
$client->onBehalfOf('C1234567890');
$client->authenticate();
```

Notes

-   Retries on 429/5xx with exponential backoff
-   Default headers include `Authorization: Bearer <token>` and `Accept: application/json`

### Document Submission

Single invoice (JSON)

```php
$invoice = [
  'issueDate' => '2024-11-12',
  'totalAmount' => 1000.50,
  'items' => [ ['description' => 'Item', 'quantity' => 1, 'unitPrice' => 1000.50, 'taxAmount' => 60.03] ],
];
$resp = $client->submitInvoice($invoice);
```

Cancel a document

```php
$client->cancelDocument('DOC123', 'Wrong information');
```

Handling validation

```php
try {
  $client->submitInvoice([]);
} catch (\Nava\MyInvois\Exception\ValidationException $e) {
  // $e->getMessage() and $e->getErrors()
}
```

Versioned notes

```php
$client->submitRefundNote(['invoiceNumber' => 'RN-001', 'issueDate' => '2024-01-01'], '1.1');
```

### Search

Basic filters

```php
$filters = [
  'submissionDateFrom' => '2024-01-01T00:00:00Z',
  'submissionDateTo' => '2024-01-31T23:59:59Z',
  'status' => 'Valid',
  'pageSize' => 50,
  'pageNo' => 1,
];
$result = $client->searchDocuments($filters);
```

Result mapping

```php
/** @var \Nava\MyInvois\Data\DocumentSearchResult $doc */
$doc = $result['documents'][0];
echo $doc->uuid; // string
echo $doc->getStatus()->value; // Valid
```

Errors

```php
try { $client->searchDocuments(['pageSize' => 101]); }
catch (\Nava\MyInvois\Exception\ValidationException $e) { /* invalid page size */ }
```

### Recent Documents

Get recent documents

```php
$result = $client->getRecentDocuments([
  'submissionDateFrom' => '2024-01-01T00:00:00Z',
  'submissionDateTo' => '2024-01-31T23:59:59Z',
  'pageNo' => 1,
  'pageSize' => 10,
]);
```

Validation

-   Page size between 1 and 100
-   Invoice direction: Sent or Received
-   Status must be a valid value (e.g., Valid, Invalid)
-   ID types: NRIC, PASSPORT, BRN, ARMY

### Notifications

List notifications

```php
$resp = $client->getNotifications([
  'dateFrom' => '2024-01-01T00:00:00Z',
  'dateTo' => '2024-01-31T23:59:59Z',
  'type' => 6, // DOCUMENT_RECEIVED
  'language' => 'en',
  'status' => 4, // DELIVERED
  'pageNo' => 1,
  'pageSize' => 50,
]);
```

Parse a notification DTO

```php
$n = \Nava\MyInvois\Data\Notification::fromArray($resp['result'][0]);
if ($n->isDelivered()) {
  // handle delivered
}
```

### Taxpayer Validation

Validate a TIN with secondary ID

```php
try {
  $ok = $client->validateTaxpayerTin('C1234567890', 'NRIC', '770625015324');
  if ($ok) { /* valid */ }
} catch (\Nava\MyInvois\Exception\ValidationException $e) {
  // input errors, e.g., invalid TIN or ID format
}
```

Case-insensitive ID type

```php
$client->validateTaxpayerTin('C1234567890', 'passport', 'A12345678');
```

Rate limiting

-   Results are cached by default; set a custom cache repository in the constructor if needed

## 🤝 Contributing

We welcome contributions! Please follow these steps:

1. **Fork the repository**
2. **Create a feature branch**: `git checkout -b feature/awesome-feature`
3. **Write tests** for your changes
4. **Ensure code quality**:
    ```bash
    composer test
    composer analyse
    composer format
    ```
5. **Commit your changes**: `git commit -m 'Add awesome feature'`
6. **Push to the branch**: `git push origin feature/awesome-feature`
7. **Create a Pull Request**

### Development Guidelines

-   Follow PSR-4 autoloading standards
-   Write comprehensive tests for new features
-   Maintain backward compatibility where possible
-   Update documentation for new features
-   Follow existing code style and patterns

## 🔒 Security

If you discover security vulnerabilities, please email **gua@navins.biz** instead of using the issue tracker. All security vulnerabilities will be promptly addressed.

## 📄 License

This library is licensed under the [MIT License](LICENSE). See the LICENSE file for details.

## 🙏 Credits

-   **Author**: [Nava](https://github.com/NavanithanS)
-   **Contributors**: [All Contributors](../../contributors)
-   Built with ❤️ for the Malaysian developer community

## 🆘 Support

-   **Documentation**: Check this README and inline code documentation
-   **Issues**: [GitHub Issues](https://github.com/NavanithanS/myinvois-api-php/issues)
-   **Discussions**: [GitHub Discussions](https://github.com/NavanithanS/myinvois-api-php/discussions)

---

<div align="center">
Made with ❤️ in Malaysia 🇲🇾
</div>

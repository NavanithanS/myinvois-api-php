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

-   **PHP**: 7.1 or higher
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

use Nava\MyInvois\MyInvoisClientFactory;

// Create client instance
$factory = new MyInvoisClientFactory();
$client = $factory->production(
    'your_client_id',
    'your_client_secret'
);

// Submit a document
$documents = [
    [
        'document' => json_encode([
            'invoiceNumber' => 'INV-001',
            'issueDate' => '2024-01-01',
            'totalAmount' => 1000.00,
            // ... additional document fields
        ]),
        'documentHash' => hash('sha256', $documentContent),
        'codeNumber' => 'INV-001',
    ]
];

$response = $client->submitDocuments($documents);

// Check submission status
$status = $client->getSubmissionStatus($response['submissionUID']);
```

### Environment Selection

```php
// Production environment
$client = $factory->production($clientId, $clientSecret);

// Sandbox environment (for testing)
$client = $factory->sandbox($clientId, $clientSecret);

// Intermediary mode (for service providers)
$client = $factory->intermediary($clientId, $clientSecret, $taxpayerTin);
```

## 🔧 Configuration

### Standalone Configuration

```php
$factory = new MyInvoisClientFactory();
$client = $factory->make(
    clientId: 'your_client_id',
    clientSecret: 'your_client_secret',
    baseUrl: 'https://preprod.myinvois.hasil.gov.my',
    httpClient: null, // Optional custom Guzzle client
    options: [
        'cache' => [
            'enabled' => true,
            'ttl' => 3600
        ],
        'logging' => [
            'enabled' => true,
            'channel' => 'myinvois'
        ],
        'http' => [
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry' => [
                'times' => 3,
                'sleep' => 1000
            ]
        ]
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
MYINVOIS_AUTH_URL=https://preprod.myinvois.hasil.gov.my

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

// Search documents
$searchResults = $client->searchDocuments([
    'dateFrom' => '2024-01-01',
    'dateTo' => '2024-01-31',
    'status' => 'Valid'
]);

// Generate QR code for document
$qrCode = $client->generateQrCode('ABC12345DEFGHI');
```

### Taxpayer Operations

```php
// Validate taxpayer TIN
$isValid = $client->validateTaxpayerTin('NRIC', 'C1234567890', 'IC12345678');

// Get taxpayer TIN information
$tinInfo = $client->getTaxpayerTin('NRIC', 'IC12345678');
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
$versions = $client->getDocumentTypeVersions('Invoice');

// Get specific version details
$versionDetails = $client->getDocumentTypeVersionDetails('Invoice', '1.0');
```

## 🧪 Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Feature/DocumentSubmissionApiTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html build/coverage
```

### Code Quality

```bash
# Static analysis
composer analyse

# Code formatting
composer format
```

### Test Structure

-   `tests/Feature/` - Integration tests for API operations
-   `tests/Unit/` - Unit tests for individual components
-   `tests/Laravel/` - Laravel-specific integration tests

## 🏗️ Architecture

### Core Components

-   **MyInvoisClient** - Main client orchestrating all operations
-   **MyInvoisClientFactory** - Factory for environment-specific clients
-   **API Classes** - Specialized classes for different API endpoints
-   **Authentication** - OAuth2 implementation with token management
-   **Validation** - Comprehensive input validation system
-   **Exception Handling** - Typed exceptions for different error scenarios

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

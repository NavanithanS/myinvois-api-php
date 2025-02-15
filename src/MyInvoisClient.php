<?php

namespace Nava\MyInvois;

use Carbon\Carbon;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Nava\MyInvois\Api\DocumentDetailsApi;
use Nava\MyInvois\Api\DocumentRejectionApi;
use Nava\MyInvois\Api\DocumentRetrievalApi;
use Nava\MyInvois\Api\DocumentSearchApi;
use Nava\MyInvois\Api\DocumentSubmissionApi;
use Nava\MyInvois\Api\DocumentTypesApi;
use Nava\MyInvois\Api\DocumentTypeVersionsApi;
use Nava\MyInvois\Api\NotificationsApi;
use Nava\MyInvois\Api\RecentDocumentsApi;
use Nava\MyInvois\Api\SubmissionStatusApi;
use Nava\MyInvois\Api\TaxpayerApi;
use Nava\MyInvois\Auth\AuthenticationClient;
use Nava\MyInvois\Exception\ApiException;
use Nava\MyInvois\Exception\ValidationException;
use Nava\MyInvois\Http\ApiClient;
use Nava\MyInvois\Traits\DateValidationTrait;
use Nava\MyInvois\Traits\LoggerTrait;
use Nava\MyInvois\Traits\RateLimitingTrait;
use Nava\MyInvois\Traits\UuidValidationTrait;
use Webmozart\Assert\Assert;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;

/**
 * MyInvois API Client
 *
 * This client library provides a robust interface to the MyInvois API for
 * submitting and managing tax documents in Malaysia.
 *
 * Key features:
 * - Authentication and token management
 * - Document submission and retrieval
 * - Validation and error handling
 * - Rate limiting and retry logic
 *
 * @author Nava
 * @license MIT
 */
class MyInvoisClient
{
    use RateLimitingTrait;

    private $apiClient;
    private $utcTime;
    private $document;
    private $signature = '';
    private $invoiceNo;
    private $dateFrom;
    private $dateTo;
    private $supplierTIN;
    private $supplierIC;
    private $buyerTIN;
    private $buyerIC;
    private $buyerName;
    private $buyerPhone;
    private $buyerEmail;
    private $buyerCity;
    private $buyerPostcode;
    private $buyerStateCode;
    private $totalPay;
    private $certDoc;
    private $certSN;
    private $certDigest;
    private $certValue;
    private $certProps;
    private $buyerAddress1;
    private $buyerAddress2;
    private $stateMapping;
    private $client;

    // private $clientId;

    // private $config;

    // protected $cache;

    // use DateValidationTrait;
    // use DocumentDetailsApi;
    // use DocumentRejectionApi;
    // use DocumentRetrievalApi;
    // use DocumentSearchApi;
    // use DocumentSubmissionApi;
    // use DocumentTypesApi;
    // use DocumentTypeVersionsApi;
    // use LoggerTrait;
    // use NotificationsApi;
    // use RecentDocumentsApi;
    // use SubmissionStatusApi;
    // use TaxpayerApi;
    // use UuidValidationTrait;

    public const PRODUCTION_URL = 'https://myinvois.hasil.gov.my';

    public const SANDBOX_URL = 'https://preprod.myinvois.hasil.gov.my';

    public const IDENTITY_PRODUCTION_URL = 'https://api.myinvois.hasil.gov.my/connect/token';

    public const IDENTITY_SANDBOX_URL = 'https://preprod-api.myinvois.hasil.gov.my/connect/token';

    public function __construct()
    {
        Assert::notEmpty(config('myinvois.client_id'), 'Myinvois client ID cannot be empty');
        Assert::notEmpty(config('myinvois.client_secret'), 'Myinvois client secret cannot be empty');
        Assert::notEmpty(config('myinvois.base_url'), 'Myinvois base url cannot be empty');
        Assert::notEmpty(config('myinvois.sslcert_path'), 'Myinvois sslcert path cannot be empty');

        $this->cache = Cache::store();

        // Create GuzzleHttp client
        $httpClient = new GuzzleClient([
            'verify' => config('myinvois.sslcert_path'),
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        // Create authentication client
        $authClient = new AuthenticationClient(
            config('myinvois.client_id'),
            config('myinvois.client_secret'),
            config('myinvois.base_url'),
            $httpClient,
            Cache::store(),
            [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'myinvois',
                ],
                'http' => [
                    'timeout' => 30,
                    'connect_timeout' => 10,
                    'retry' => [
                        'times' => 3,
                        'sleep' => 1000,
                    ],
                ],
            ]
        );

        $this->apiClient = new ApiClient(
            config('myinvois.client_id'),
            config('myinvois.client_secret'),
            config('myinvois.base_url'),
            $httpClient,
            Cache::store(),
            $authClient,
            [
                'logging' => [
                    'enabled' => true,
                    'channel' => 'myinvois',
                ],
            ]
        );

        $authClient->authenticate();

        $this->supplierTIN = "IG5574752030";
        $this->supplierIC = "770225075371";
        // $this->buyerTIN = "IG50207950090";
        // $this->buyerIC = "000716070507";
        $this->utcTime = Carbon::now('UTC')->toTimeString() . "Z";

        $this->document = [
            "Invoice" => [
                [
                    "ID" => [["_" => $this->invoiceNo]],
                    "IssueDate" => [["_" => Carbon::now()->toDateString()]],
                    "IssueTime" => [["_" => $this->utcTime]],
                    "InvoiceTypeCode" => [["_" => "01", "listVersionID" => "1.1"]],
                    "DocumentCurrencyCode" => [["_" => "MYR"]],
                    "TaxCurrencyCode" => [["_" => "MYR"]],
                    "InvoicePeriod" => [
                        [
                            "StartDate" => [["_" => $this->dateFrom]],
                            "EndDate" => [["_" => $this->dateTo]],
                            "Description" => [["_" => "Monthly"]]
                        ]
                    ],
                    "AccountingSupplierParty" => [
                        [
                            "Party" => [
                                [
                                    "IndustryClassificationCode" => [["_" => "46510", "name" => "Wholesale of computer hardware, software and peripherals"]],
                                    "PartyIdentification" => [
                                        ["ID" => [["_" => $this->supplierTIN, "schemeID" => "TIN"]]],
                                        ["ID" => [["_" => $this->supplierIC, "schemeID" => "NRIC"]]],
                                        ["ID" => [["_" => "NA", "schemeID" => "SST"]]],
                                        ["ID" => [["_" => "NA", "schemeID" => "TTX"]]]
                                    ],
                                    "PostalAddress" => [
                                        [
                                            "CityName" => [["_" => "Kuala Lumpur"]],
                                            "PostalZone" => [["_" => "50480"]],
                                            "CountrySubentityCode" => [["_" => "10"]],
                                            "AddressLine" => [["Line" => [["_" => "NA"]]]],
                                            "Country" => [["IdentificationCode" => [["_" => "MYS", "listID" => "ISO3166-1", "listAgencyID" => "6"]]]]
                                        ]
                                    ],
                                    "PartyLegalEntity" => [
                                        ["RegistrationName" => [["_" => "Suppliers Name"]]]
                                    ],
                                    "Contact" => [
                                        [
                                            "Telephone" => [["_" => "+60-123456789"]],
                                            "ElectronicMail" => [["_" => "supplier@email.com"]]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "AccountingCustomerParty" => [
                        [
                            "Party" => [
                                [
                                    "PostalAddress" => [
                                        [
                                            "CityName" => [["_" => $this->buyerCity]],
                                            "PostalZone" => [["_" => $this->buyerPostcode]],
                                            "CountrySubentityCode" => [["_" => $this->buyerStateCode]],
                                            "AddressLine" => [["Line" => [["_" => $this->buyerAddress1 . $this->buyerAddress2]]]],
                                            "Country" => [["IdentificationCode" => [["_" => "MYS", "listID" => "ISO3166-1", "listAgencyID" => "6"]]]]
                                        ]
                                    ],
                                    "PartyLegalEntity" => [
                                        ["RegistrationName" => [["_" => $this->buyerName]]]
                                    ],
                                    "PartyIdentification" => [
                                        ["ID" => [["_" => $this->buyerTIN, "schemeID" => "TIN"]]],
                                        ["ID" => [["_" => $this->buyerIC, "schemeID" => "NRIC"]]],
                                        ["ID" => [["_" => "NA", "schemeID" => "SST"]]],
                                        ["ID" => [["_" => "NA", "schemeID" => "TTX"]]]
                                    ],
                                    "Contact" => [
                                        [
                                            "Telephone" => [["_" => $this->buyerPhone]],
                                            "ElectronicMail" => [["_" => $this->buyerEmail]]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "TaxTotal" => [
                        [
                            "TaxAmount" => [["_" => 0, "currencyID" => "MYR"]],
                            "TaxSubtotal" => [
                                [
                                    "TaxableAmount" => [["_" => 0, "currencyID" => "MYR"]],
                                    "TaxAmount" => [["_" => 0, "currencyID" => "MYR"]],
                                    "TaxCategory" => [
                                        ["ID" => [["_" => "01"]], "TaxScheme" => [["ID" => [["_" => "OTH", "schemeID" => "UN/ECE 5153", "schemeAgencyID" => "6"]]]]]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    "LegalMonetaryTotal" => [
                        [
                            "TaxExclusiveAmount" => [["_" => $this->totalPay, "currencyID" => "MYR"]],
                            "TaxInclusiveAmount" => [["_" => $this->totalPay, "currencyID" => "MYR"]],
                            "PayableAmount" => [["_" => $this->totalPay, "currencyID" => "MYR"]]
                        ]
                    ],
                    "InvoiceLine" => [
                        [
                            "ID" => [["_" => $this->invoiceNo]],
                            "LineExtensionAmount" => [["_" => $this->totalPay, "currencyID" => "MYR"]],
                            "TaxTotal" => [
                                [
                                    "TaxAmount" => [["_" => 0, "currencyID" => "MYR"]],
                                    "TaxSubtotal" => [
                                        [
                                            "TaxableAmount" => [["_" => 0, "currencyID" => "MYR"]],
                                            "TaxAmount" => [["_" => 0, "currencyID" => "MYR"]],
                                            "Percent" => [["_" => 0]],
                                            "TaxCategory" => [
                                                ["ID" => [["_" => "06"]], "TaxExemptionReason" => [["_" => ""]], "TaxScheme" => [["ID" => [["_" => "OTH", "schemeID" => "UN/ECE 5153", "schemeAgencyID" => "6"]]]]]
                                            ]
                                        ]
                                    ]
                                ]
                            ],
                            "Item" => [
                                [
                                    "CommodityClassification" => [["ItemClassificationCode" => [["_" => "003", "listID" => "CLASS"]]]],
                                    "Description" => [["_" => "Laptop Peripherals"]]
                                ]
                            ],
                            "Price" => [
                                ["PriceAmount" => [["_" => $this->totalPay, "currencyID" => "MYR"]]]
                            ],
                            "ItemPriceExtension" => [
                                ["Amount" => [["_" => $this->totalPay, "currencyID" => "MYR"]]]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->signature = [
            "UBLExtensions" => [
                [
                    "UBLExtension" => [
                        [
                            "ExtensionURI" => [["_" => "urn:oasis:names:specification:ubl:dsig:enveloped:xades"]],
                            "ExtensionContent" => [
                                [
                                    "UBLDocumentSignatures" => [
                                        [
                                            "SignatureInformation" => [
                                                [
                                                    "ID" => [["_" => "urn:oasis:names:specification:ubl:signature:1"]],
                                                    "ReferencedSignatureID" => [["_" => "urn:oasis:names:specification:ubl:signature:Invoice"]],
                                                    "Signature" => [
                                                        [
                                                            "Id" => "signature",
                                                            "Object" => [
                                                                [
                                                                    "QualifyingProperties" => [
                                                                        [
                                                                            "Target" => "signature",
                                                                            "SignedProperties" => [
                                                                                [
                                                                                    "Id" => "id-xades-signed-props",
                                                                                    "SignedSignatureProperties" => [
                                                                                        [
                                                                                            "SigningTime" => [["_" => $this->utcTime]],
                                                                                            "SigningCertificate" => [
                                                                                                [
                                                                                                    "Cert" => [
                                                                                                        [
                                                                                                            "CertDigest" => [
                                                                                                                [
                                                                                                                    "DigestMethod" => [["_" => "", "Algorithm" => "http://www.w3.org/2001/04/xmlenc#sha256"]],
                                                                                                                    "DigestValue" => [["_" => $this->certDigest]]
                                                                                                                ]
                                                                                                            ],
                                                                                                            "IssuerSerial" => [
                                                                                                                [
                                                                                                                    "X509IssuerName" => [["_" => "CN=Trial LHDNM Sub CA V1, OU=Terms of use at http://www.posdigicert.com.my, O=LHDNM, C=MY"]],
                                                                                                                    "X509SerialNumber" => [["_" => $this->certSN]]
                                                                                                                ]
                                                                                                            ]
                                                                                                        ]
                                                                                                    ]
                                                                                                ]
                                                                                            ]
                                                                                        ]
                                                                                    ]
                                                                                ]
                                                                            ]
                                                                        ]
                                                                    ]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            "KeyInfo" => [
                                                [
                                                    "X509Data" => [
                                                        [
                                                            "X509Certificate" => [["_" => ""]],
                                                            "X509SubjectName" => [["_" => "CN=Trial LHDNM Sub CA V1, OU=Terms of use at http://www.posdigicert.com.my, O=LHDNM, C=MY"]],
                                                            "X509IssuerSerial" => [
                                                                [
                                                                    "X509IssuerName" => [["_" => "CN=Trial LHDNM Sub CA V1, OU=Terms of use at http://www.posdigicert.com.my, O=LHDNM, C=MY"]],
                                                                    "X509SerialNumber" => [["_" => $this->certSN]]
                                                                ]
                                                            ]
                                                        ]
                                                    ]
                                                ]
                                            ],
                                            "SignatureValue" => [["_" => $this->certValue]],
                                            "SignedInfo" => [
                                                [
                                                    "SignatureMethod" => [["_" => "", "Algorithm" => "http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"]],
                                                    "Reference" => [
                                                        [
                                                            "Type" => "http://uri.etsi.org/01903/v1.3.2#SignedProperties",
                                                            "URI" => "#id-xades-signed-props",
                                                            "DigestMethod" => [["_" => "", "Algorithm" => "http://www.w3.org/2001/04/xmlenc#sha256"]],
                                                            "DigestValue" => [["_" => $this->certProps]]
                                                        ],
                                                        [
                                                            "Type" => "",
                                                            "URI" => "",
                                                            "DigestMethod" => [["_" => "", "Algorithm" => "http://www.w3.org/2001/04/xmlenc#sha256"]],
                                                            "DigestValue" => [["_" => $this->certDoc]]
                                                        ]
                                                    ]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            "Signature" => [
                [
                    "ID" => [["_" => "urn:oasis:names:specification:ubl:signature:Invoice"]],
                    "SignatureMethod" => [["_" => "urn:oasis:names:specification:ubl:dsig:enveloped:xades"]]
                ]
            ]
        ];

        $this->stateMapping = [
            'Johor' => '01',
            'Kedah' => '02',
            'Kelantan' => '03',
            'Melaka' => '04',
            'Negeri Sembilan' => '05',
            'Pahang' => '06',
            'Pulau Pinang' => '07',
            'Perak' => '08',
            'Perlis' => '09',
            'Selangor' => '10',
            'Terengganu' => '11',
            'Sabah' => '12',
            'Sarawak' => '13',
            'Wilayah Persekutuan Kuala Lumpur' => '14',
            'Wilayah Persekutuan Labuan' => '15',
            'Wilayah Persekutuan Putrajaya' => '16',
            'Not Applicable' => '17',
        ];
    }

    public function createDocument(Request $request)
    {
        $this->totalPay = $request->input('total_amount');
        $this->invoiceNo = $request->input('invoice_no');
        $this->dateFrom = $request->input('date_from');
        $this->dateTo = $request->input('date_to');
        $this->buyerIC = $request->input('identification_no');
        $this->buyerTIN = $request->input('identification_tin');
        $this->buyerName = $request->input('name');
        $this->buyerPhone = $request->input('phone_number');
        $this->buyerEmail = $request->input('email');
        $this->buyerAddress1 = $request->input('address_1');
        $this->buyerAddress2 = $request->input('address_2');
        $this->buyerPostcode = $request->input('postcode');
        $this->buyerCity = $request->input('city');
        $this->buyerStateCode = $this->stateMapping[$request->input('state')] ?? null;

        // Check if decoding was successful
        if (json_last_error() !== JSON_ERROR_NONE) {
            die("JSON Decode Error: " . json_last_error_msg());
        }

        // Check if 'InvoiceLine' exists and merge the signature array
        if (isset($this->document['Invoice'][0]['InvoiceLine'])) {
            $this->document['Invoice'][0] = array_merge($this->document['Invoice'][0], $this->signature);
        }

        // Convert back to JSON format
        $updatedDocument = json_encode($this->document, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $hash = hash('sha256', $updatedDocument, true); // true for raw binary output
        $docdigest = base64_encode($hash);

        echo "DocDigest: " . $docdigest . PHP_EOL;
        $this->certDoc = $docdigest;

        $privateKeyPem = 'C:/Users/Lee Guang You/Desktop/BioE/Smart-Metering/smart-metering/app/Http/Controllers/Paynet/private_key.pem'; // Load private key

        $privateKey = file_get_contents($privateKeyPem);
        if (!$privateKey) {
            die("Failed to read private key file.");
        }

        $passphrase = "";
        $privateKeyResource = openssl_pkey_get_private($privateKey, $passphrase);
        if (!$privateKeyResource) {
            die("Failed to load private key: " . openssl_error_string());
        }

        // Sign the document digest
        openssl_sign($hash, $this->signature, $privateKey, OPENSSL_ALGO_SHA256);
        $signatureBase64 = base64_encode($this->signature);

        echo "Sig: " . $signatureBase64 . PHP_EOL;
        $this->certValue = $signatureBase64;

        $certificatePem = file_get_contents('C:\Program Files\OpenSSL-Win64\bin\public_key.cer'); // Load certificate

        $certificate = openssl_x509_read($certificatePem);
        if (!$certificate) {
            die("Failed to load certificate");
        }

        $certInfo = openssl_x509_parse($certificate);
        echo "X509 Serial Number: " . $certInfo['serialNumber'] . "\n";
        $this->certSN = $certInfo['serialNumber'];

        // Get the certificate in DER format
        openssl_x509_export($certificate, $certData);
        $certData = str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\n", "\r"], '', $certData);
        $certBinary = base64_decode($certData);

        // Compute SHA-256 hash of the certificate
        $certHash = hash('sha256', $certBinary, true);
        $certDigest = base64_encode($certHash);

        echo "CertDigest: " . $certDigest . PHP_EOL;
        $this->certDigest = $certDigest;

        $signedProperties = [
            "Target" => "signature",
            "SignedProperties" => [
                [
                    "Id" => "id-xades-signed-props",
                    "SignedSignatureProperties" => [
                        [
                            "SigningTime" => [["_" => $this->utcTime]],
                            "SigningCertificate" => [
                                [
                                    "Cert" => [
                                        [
                                            "CertDigest" => [
                                                [
                                                    "DigestMethod" => [["_" => "", "Algorithm" => "http://www.w3.org/2001/04/xmlenc#sha256"]],
                                                    "DigestValue" => [["_" => $this->certDigest]]
                                                ]
                                            ],
                                            "IssuerSerial" => [
                                                [
                                                    "X509IssuerName" => [["_" => "CN=Trial LHDNM Sub CA V1, OU=Terms of use at http://www.posdigicert.com.my, O=LHDNM, C=MY"]],
                                                    "X509SerialNumber" => [["_" => $this->certSN]]
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        $signedPropsJson = json_encode($signedProperties, JSON_UNESCAPED_SLASHES);
        $signedPropsHash = hash('sha256', $signedPropsJson, true);
        $signedPropsDigest = base64_encode($signedPropsHash);

        echo "PropsDigest: " . $signedPropsDigest . PHP_EOL;
        $this->certProps = $signedPropsDigest;

        $this->submitDocument($updatedDocument);
    }

    public function submitDocument($updatedDocument, ?string $version = null): array
    {
        // Step 1: Convert to JSON and Base64 encode
        // $jsonEncoded = json_encode($document, JSON_UNESCAPED_SLASHES);
        $base64Encoded = base64_encode($updatedDocument);

        // Step 2: Generate SHA-256 hash
        $documentHash = hash('sha256', $updatedDocument);

        // Step 3: Prepare new request format
        $requestData = [
            "documents" => [
                [
                    "format" => "JSON",
                    "documentHash" => $documentHash,
                    "codeNumber" => "INV00000002",
                    "document" => $base64Encoded
                ]
            ]
        ];

        $this->checkRateLimit(
            'document_submission',
            $this->createRateLimitConfig('submitDocument', 50, 3600)
        );

        // Use provided version or current version
        // $version = $version ?? Config::INVOICE_CURRENT_VERSION;

        // Validate version is supported
        if (!Config::isVersionSupported('invoice', $version)) {
            throw new ValidationException('Unsupported document version');
        }

        // Set version in document
        $requestData['invoiceTypeCode'] = [
            'value' => '01', // Invoice type code
            'listVersionID' => $version,
        ];

        // The correct endpoint is /api/v1.0/documentsubmissions (not /documents)
        return $this->apiClient->request('POST', '/api/v1.0/documentsubmissions', [
            'json' => $requestData,
        ]);
    }

    public function getTaxpayerTin($ic)
    {
        $this->checkRateLimit(
            'tin_search',
            $this->createRateLimitConfig('searchTin', 60, 60) // 60 requests per minute
        );

        if (empty($ic)) {
            throw new \InvalidArgumentException('idValue (NRIC) must be provided');
        }

        $response = $this->apiClient->request('GET', '/api/v1.0/taxpayer/search/tin', [
            'query' => [
                'idType' => 'NRIC',
                'idValue' => $ic
            ]
        ]);

        return $response;
    }

    public function validateTaxpayerTin(string $tin, string $idValue)
    {
        // Validate input parameters
        if (empty($tin) || empty($idValue)) {
            throw new \InvalidArgumentException('TIN, idType, and idValue must be provided.');
        }

        // Make the API request
        $response = $this->apiClient->request('GET', "/api/v1.0/taxpayer/validate/{$tin}", [
            'query' => [
                'idType'  => 'NRIC',
                'idValue' => $idValue
            ]
        ]);
        return $response;
    }

    public function getDocument($uuid)
    {
        // Validate input parameters
        if (empty($uuid)) {
            throw new \InvalidArgumentException('Uuid must be provided.');
        }

        // Make the API request
        $response = $this->apiClient->request('GET', "/api/v1.0/documents/{$uuid}/raw");

        // Return only the longId
        return $response['longId'] ?? null; // Return null if longId is not found
    }

    public function generateQrCode($uuid)
    {
        $baseUrl = config('myinvois.base_url');
        $longid = $this->getDocument($uuid);

        $url = "{$baseUrl}/{$uuid}/share/{$longid}";
        // $url = 'https://www.google.com';

        // Create QR Code
        $qrCode = new QrCode($url);
        $qrCode->setSize(300); // Set QR Code size
        $qrCode->setMargin(10); // Set margin

        // Get QR Code as PNG binary data
        $pngData = $qrCode->writeString(); // Version 3.5.9 uses writeString() instead of write()

        // Encode as Base64
        return 'data:image/png;base64,' . base64_encode($pngData);
    }

    /**
     * Submit a new invoice document.
     *
     * @param  array  $invoice  Invoice data following MyInvois schema
     * @return array Response containing the document ID and status
     *
     * @throws ValidationException|ApiException
     */
    public function submitInvoice(array $invoice): array
    {
        $this->validateInvoiceData($invoice);

        $preparer = new InvoiceDataPreparer;
        $preparedInvoice = $preparer->prepare($invoice);

        return $this->apiClient->request('POST', '/documents', [
            'json' => $preparedInvoice,
        ]);
    }

    /**
     * Get the status of a document.
     *
     * @param  string  $documentId  The document ID
     * @return array Document status and details
     *
     * @throws ApiException
     */
    public function getDocumentStatus(string $documentId): array
    {
        $cacheKey = "document_status_{$documentId}";

        return $this->cache->get($cacheKey, function () use ($documentId) {
            return $this->apiClient->request('GET', "/documents/{$documentId}");
        });
    }

    /**
     * List documents with optional filtering.
     *
     * @param  array  $filters  Optional filters
     *
     *     @option string $startDate Start date (YYYY-MM-DD)
     *     @option string $endDate End date (YYYY-MM-DD)
     *     @option string $status Document status
     *     @option int $page Page number
     *     @option int $perPage Items per page
     *
     * @return array Paginated list of documents
     *
     * @throws ApiException
     */
    public function listDocuments(array $filters = []): array
    {
        $preparer = new DocumentFilterPreparer;
        $preparedFilters = $preparer->prepare($filters);

        return $this->apiClient->request('GET', '/documents', [
            'query' => $preparedFilters,
        ]);
    }

    /**
     * Cancel a document.
     *
     * @param  string  $documentId  The document ID to cancel
     * @param  string  $reason  Reason for cancellation
     * @return array Cancellation status
     *
     * @throws ApiException
     */
    public function cancelDocument(string $documentId)
    {
        return $this->apiClient->request('PUT', "/api/v1.0/documents/state/{$documentId}/state", [
            'json' => [
                'status' => 'cancelled',
                'reason' => 'Wrong invoice details',
            ],
        ]);
    }

    /**
     * Get document PDF.
     *
     * @param  string  $documentId  The document ID
     * @return string Binary PDF content
     *
     * @throws ApiException
     */
    public function getDocumentPdf(string $documentId): string
    {
        $response = $this->apiClient->request('GET', "/documents/{$documentId}/pdf", [
            'headers' => [
                'Accept' => 'application/pdf',
            ],
        ]);

        return base64_decode($response['content']);
    }

    /**
     * Get document events history.
     *
     * @param  string  $documentId  The document ID
     * @return array List of document events
     *
     * @throws ApiException
     */
    public function getDocumentHistory(string $documentId): array
    {
        return $this->apiClient->request('GET', "/documents/{$documentId}/history");
    }

    /**
     * Validate invoice data without submitting.
     *
     * @param  array  $invoice  Invoice data to validate
     * @return array Validation results
     *
     * @throws ApiException
     */
    public function validateInvoice(array $invoice): array
    {
        return $this->apiClient->request('POST', '/documents/validate', [
            'json' => $this->prepareInvoiceData($invoice),
        ]);
    }

    /**
     * Get current API status and service health.
     *
     * @return array API status information
     *
     * @throws ApiException
     */
    public function getApiStatus(): array
    {
        return $this->apiClient->request('GET', '/status');
    }

    private function validateInvoiceData(array $invoice): void
    {
        Assert::notEmpty($invoice['issueDate'] ?? null, 'Invoice issueDate is required');
        Assert::notEmpty($invoice['totalAmount'] ?? null, 'Invoice totalAmount is required');
        // Add more validation rules as needed
    }

    private function formatAmount(float $amount): string
    {
        return sprintf('%.2f', $amount);
    }

    private function prepareInvoiceData(array $invoice): array
    {
        return [
            'issueDate' => date('Y-m-d', strtotime($invoice['issueDate'] ?? '')),
            'dueDate' => $invoice['dueDate'] ? date('Y-m-d', strtotime($invoice['dueDate'])) : null,
            'serviceDate' => $invoice['serviceDate'] ? date('Y-m-d', strtotime($invoice['serviceDate'])) : null,
            'totalAmount' => $this->formatAmount($invoice['totalAmount'] ?? 0),
            'taxAmount' => $this->formatAmount($invoice['taxAmount'] ?? 0),
            'discountAmount' => $this->formatAmount($invoice['discountAmount'] ?? 0),
            'items' => $this->prepareInvoiceItems($invoice['items'] ?? []),
        ];
    }

    private function prepareInvoiceItems(array $items): array
    {
        return array_map(function ($item) {
            return [
                'description' => $item['description'] ?? '',
                'quantity' => $item['quantity'] ?? 1,
                'unitPrice' => $this->formatAmount($item['unitPrice'] ?? 0),
                'taxAmount' => $this->formatAmount($item['taxAmount'] ?? 0),
            ];
        }, $items);
    }

    /**
     * Prepare filters for listing documents.
     *
     * @param  array  $filters  Raw filters
     * @return array Prepared filters
     */
    private function prepareListFilters(array $filters): array
    {
        $prepared = [];

        // Handle date filters
        foreach (['startDate', 'endDate'] as $dateField) {
            if (isset($filters[$dateField])) {
                $prepared[$dateField] = date('Y-m-d', strtotime($filters[$dateField]));
            }
        }

        // Handle pagination
        $prepared['page'] = $filters['page'] ?? 1;
        $prepared['perPage'] = min($filters['perPage'] ?? 50, 100); // Limit max per page

        // Handle other filters
        if (isset($filters['status'])) {
            $prepared['status'] = $filters['status'];
        }

        return $prepared;
    }

    public function submitDebitNote(array $document, ?string $version = null): array
    {
        // Use provided version or fallback to current version
        $version = $version ?? Config::DEBIT_NOTE_CURRENT_VERSION;

        // Validate version is supported
        if (! in_array($version, Config::DEBIT_NOTE_SUPPORTED_VERSIONS)) {
            throw new ValidationException('Unsupported debit note version');
        }

        // Add version to document
        $document['invoiceTypeCode'] = [
            'value' => '03', // Debit note type code
            'listVersionID' => $version,
        ];

        // Submit document using existing logic
        return $this->submitDocument($document);
    }

    /**
     * Submit a refund note document.
     *
     * @param  array  $document  Refund note data following MyInvois schema
     * @param  ?string  $version  Version to use (defaults to current version)
     * @return array Submission response
     *
     * @throws ValidationException|ApiException
     */
    public function submitRefundNote(array $document, ?string $version = null): array
    {
        // Use provided version or current version
        $version = $version ?? Config::REFUND_NOTE_CURRENT_VERSION;

        // Validate version is supported
        if (! Config::isVersionSupported('refund_note', $version)) {
            throw new ValidationException('Unsupported refund note version');
        }

        // Add version to document
        $document['invoiceTypeCode'] = [
            'value' => Config::REFUND_NOTE_TYPE_CODE,
            'listVersionID' => $version,
        ];

        // Submit using base submission logic
        return $this->submitDocument($document);
    }
}

// Create a new class for invoice data preparation
class InvoiceDataPreparer
{
    public function prepare(array $invoice): array
    {
        $this->validate($invoice);

        // Format dates
        foreach (['issueDate', 'dueDate', 'serviceDate'] as $dateField) {
            if (isset($invoice[$dateField])) {
                $invoice[$dateField] = date('Y-m-d', strtotime($invoice[$dateField]));
            }
        }

        // Format amounts
        foreach (['totalAmount', 'taxAmount', 'discountAmount'] as $amountField) {
            if (isset($invoice[$amountField])) {
                $invoice[$amountField] = sprintf('%.2f', $invoice[$amountField]);
            }
        }

        // Prepare items if present
        if (isset($invoice['items'])) {
            $invoice['items'] = array_map([$this, 'prepareItem'], $invoice['items']);
        }

        return $invoice;
    }

    private function validate(array $invoice): void
    {
        Assert::notEmpty($invoice['issueDate'] ?? null, 'Issue date is required');
        Assert::notEmpty($invoice['totalAmount'] ?? null, 'Total amount is required');
        Assert::numeric($invoice['totalAmount'] ?? null, 'Total amount must be numeric');
        Assert::greaterThan($invoice['totalAmount'] ?? 0, 0, 'Total amount must be greater than 0');

        if (isset($invoice['items'])) {
            Assert::isArray($invoice['items'], 'Items must be an array');
            foreach ($invoice['items'] as $item) {
                $this->validateItem($item);
            }
        }
    }

    private function validateItem(array $item): void
    {
        Assert::notEmpty($item['description'] ?? null, 'Item description is required');
        Assert::numeric($item['quantity'] ?? null, 'Item quantity must be numeric');
        Assert::numeric($item['unitPrice'] ?? null, 'Item unit price must be numeric');
    }

    private function prepareItem(array $item): array
    {
        return [
            'description' => $item['description'],
            'quantity' => (int) $item['quantity'],
            'unitPrice' => sprintf('%.2f', $item['unitPrice']),
            'taxAmount' => sprintf('%.2f', $item['taxAmount'] ?? 0),
            'totalAmount' => sprintf('%.2f', $item['quantity'] * $item['unitPrice']),
        ];
    }
}

// Create a new class for document filter preparation
class DocumentFilterPreparer
{
    public function prepare(array $filters): array
    {
        $prepared = [];

        // Handle date filters
        foreach (['startDate', 'endDate'] as $dateField) {
            if (isset($filters[$dateField])) {
                $prepared[$dateField] = date('Y-m-d', strtotime($filters[$dateField]));
            }
        }

        // Handle pagination with validation
        $prepared['page'] = max(1, $filters['page'] ?? 1);
        $prepared['perPage'] = min(max(1, $filters['perPage'] ?? 50), 100);

        // Handle status filter with validation
        if (isset($filters['status'])) {
            Assert::inArray($filters['status'], [
                'PENDING',
                'COMPLETED',
                'FAILED',
                'CANCELLED',
            ], 'Invalid status value');
            $prepared['status'] = $filters['status'];
        }

        return $prepared;
    }
}

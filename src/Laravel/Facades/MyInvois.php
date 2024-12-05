<?php

namespace Nava\MyInvois\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array submitInvoice(array $invoice)
 * @method static array getDocumentStatus(string $documentId)
 * @method static array listDocuments(array $filters = [])
 * @method static array cancelDocument(string $documentId, string $reason)
 * @method static string getDocumentPdf(string $documentId)
 *
 * @see \Nava\MyInvois\MyInvoisClient
 */
class MyInvois extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'myinvois';
    }
}

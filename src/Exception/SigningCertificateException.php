<?php

namespace Nava\MyInvois\Exception;

/**
 * The signing certificate configured in myinvois.privatekey_path could not be
 * read or opened (missing path, unreadable file, wrong passphrase, not PKCS#12).
 */
class SigningCertificateException extends \RuntimeException
{
}

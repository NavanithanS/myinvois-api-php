<?php

namespace Nava\MyInvois\Exception;

class NetworkException extends ApiException
{
    public function __construct(string $message = 'Network error occurred', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

<?php

namespace Nava\MyInvois\Exception;

class ApiException extends \Exception
{
    protected array $context;

    public function __construct(string $message, array $errors = [], int $code = 422, ?\Throwable $previous = null)
    {
        $this->errors = $errors;
        parent::__construct($message, ['errors' => $errors], $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}

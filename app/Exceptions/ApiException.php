<?php

namespace App\Exceptions;

use Exception;

class ApiException extends Exception
{
    protected mixed $errors;

    public function __construct(string $message = 'Something went wrong', protected int $statusCode = 400, mixed $errors = null)
    {
        parent::__construct($message);

        $this->errors = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): mixed
    {
        return $this->errors;
    }
}

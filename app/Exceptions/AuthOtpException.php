<?php

namespace App\Exceptions;

use Exception;

class AuthOtpException extends Exception
{
    public function __construct(string $message, public readonly int $status = 422)
    {
        parent::__construct($message);
    }
}

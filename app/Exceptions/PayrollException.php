<?php

namespace App\Exceptions;

use RuntimeException;

class PayrollException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public array $errors, string $message = 'Payroll calculation failed.')
    {
        parent::__construct($message);
    }
}

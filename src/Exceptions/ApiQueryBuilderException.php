<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Exceptions;

use Exception;
use Throwable;

class ApiQueryBuilderException extends Exception
{
    public function __construct($message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

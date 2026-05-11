<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Exceptions;

/**
 * Thrown when an operator is used with incompatible column/value types.
 * The message is safe to be displayed directly to API consumers.
 */
class InvalidOperatorUsageException extends ApiQueryBuilderException {}

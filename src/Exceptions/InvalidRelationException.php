<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Exceptions;

/**
 * Thrown when a requested relation does not exist on a model.
 * The message is safe to be displayed directly to API consumers.
 */
class InvalidRelationException extends ApiQueryBuilderException {}

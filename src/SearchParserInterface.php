<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder;

interface SearchParserInterface
{
    public function getOperator(): string;
}

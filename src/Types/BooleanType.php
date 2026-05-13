<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Types;

use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

class BooleanType extends AbstractType
{
    public static function name(): string
    {
        return 'boolean';
    }

    /**
     * @param  array  $values
     * @return array
     *
     * @throws ApiQueryBuilderException
     */
    public function prepare(array $values): array
    {
        foreach ($values as &$value) {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($value === null) {
                throw new ApiQueryBuilderException('Wrong argument type provided');
            }
        }

        return $values;
    }
}

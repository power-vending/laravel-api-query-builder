<?php

namespace PowerVending\LaravelApiQueryBuilder\SQLProviders;

use PowerVending\LaravelApiQueryBuilder\Exceptions\ApiQueryBuilderException;

class SQLFunctions
{
    public const DB_FUNCTIONS = [
        'avg',
        'count',
        'max',
        'min',
        'sum',
        'distinct',

        'year',
        'month',
        'day',
    ];

    final public static function __callStatic($fn, $args)
    {
        if (!in_array($fn, self::DB_FUNCTIONS)) {
            throw new ApiQueryBuilderException(
                "Invalid function: $fn."
            );
        }

        if (method_exists(self::class, $fn)) {
            return self::$fn($args);
        }

        return $fn . "($args[0])";
    }

    final public static function validateArgument(string $argument): void
    {
        $columnRegex = "/^(((_*)?([A-Za-z0-9]+))+|\*)$/";

        if (strpos($argument, " as ")) {
            [$argument, $alias] = explode(" as ", $argument);

            if (!preg_match($columnRegex, $alias)) {
                throw new ApiQueryBuilderException(
                    "Invalid alias name: {$alias}."
                );
            }
        }

        $split = explode(':', $argument);
        $column = array_pop($split);

        if (!preg_match($columnRegex, $column) || in_array($column, self::DB_FUNCTIONS)) {
            throw new ApiQueryBuilderException(
                "Invalid column name: {$column}."
            );
        }

        if ($invalidFns = array_diff($split, self::DB_FUNCTIONS)) {
            throw new ApiQueryBuilderException(
                'Invalid function: ' . join(',', $invalidFns) . '.'
            );
        }
    }
}

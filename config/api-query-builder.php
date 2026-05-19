<?php

use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;
use PowerVending\LaravelApiQueryBuilder\RequestParameters\{
    CountParameter,
    DoesntHaveRelationsParameter,
    ExceptsParameter,
    GroupByParameter,
    LimitParameter,
    OffsetParameter,
    OrderByParameter,
    RelationsParameter,
    ReturnsParameter,
    SearchParameter,
    SoftDeletedParameter};
use PowerVending\LaravelApiQueryBuilder\SearchCallbacks\{
    Between,
    EndsWith,
    Equals,
    GreaterThan,
    GreaterThanOrEqual,
    JsonSearch,
    LessThan,
    LessThanOrEqual,
    Like,
    NotBetween,
    NotEquals,
    StartsWith};
use PowerVending\LaravelApiQueryBuilder\Types\{BooleanType, GenericType};

return [
    /**
     * Routes exposed by the package.
     * Key is the full route name.
     *
     * Each route accepts:
     *  - method: HTTP method
     *  - uri: full URI path
     *  - action: [Controller::class, 'method']
     *  - middlewares: array of middleware aliases
     */
    'routes' => [
        'api.query_builder.schema.show' => [
            'method' => 'get',
            'uri' => 'api-query-builder/{resource}/schema',
            'action' => [SchemaController::class, 'show'],
            'middlewares' => ['api'],
        ],
    ],

    /**
     * Registered request parameters.
     */
    'request_parameters' => [
        SearchParameter::class,
        ReturnsParameter::class,
        ExceptsParameter::class,
        OrderByParameter::class,
        RelationsParameter::class,
        LimitParameter::class,
        OffsetParameter::class,
        CountParameter::class,
        GroupByParameter::class,
        SoftDeletedParameter::class,
        DoesntHaveRelationsParameter::class,
    ],

    /**
     * Registered operators/callbacks. Operator order matters!
     * Callbacks having more const OPERATOR characters must come before those with less.
     */
    'operators' => [
        StartsWith::class,       // STARTS_WITH: (13 chars)
        JsonSearch::class,       // JSON_SEARCH: (12 chars)
        EndsWith::class,         // ENDS_WITH: (10 chars)
        Like::class,             // LIKE: (5 chars)
        NotEquals::class,        // NE: (3 chars)
        NotBetween::class,       // NB: (3 chars)
        LessThanOrEqual::class,  // LE: (3 chars)
        GreaterThanOrEqual::class, // GE: (3 chars)
        Between::class,          // BT: (3 chars)
        Equals::class,           // EQ: (3 chars)
        LessThan::class,         // LT: (3 chars)
        GreaterThan::class,      // GT: (3 chars)
    ],

    /**
     * Operators allowed per cast type.
     * If defined for a cast, only those operators will be available for fields with that cast type.
     * Cast names must match Laravel cast types (e.g. 'integer', 'boolean', 'string').
     * If a cast type is not listed here, the normal operator resolution flow is used.
     *
     * Example:
     *   'cast_operators' => [
     *       'integer' => [Equals::class, NotEquals::class, LessThan::class, LessThanOrEqual::class, GreaterThan::class, GreaterThanOrEqual::class, Between::class, NotBetween::class],
     *       'boolean' => [Equals::class],
     *   ],
     */
    'cast_operators' => [
        // 'integer' => [Equals::class, NotEquals::class, ...],
    ],

    /**
     * Registered types. Generic type is the default one and should be used if
     * no special care for type value is needed.
     */
    'types' => [
        GenericType::class,
        BooleanType::class,
    ],

    /**
     * List of globally forbidden columns to search on.
     * Searching by forbidden columns will throw an exception
     * This takes precedence before other exclusions.
     */
    'global_forbidden_columns' => [
        // 'id', 'created_at' ...
    ],

    /**
     * List of globally forbidden relations.
     * Forbidden relations are treated as not found and are never auto-discovered.
     */
    'global_forbidden_relations' => [
        // 'company.users', 'internalRelation' ...
    ],

    /**
     * TODO: these options are currently disabled and will not work
     * Refined options for a single model.
     * Use if you want to enforce rules on a specific model without affecting globally all models.
     */
    'model_options' => [
        /**
         * For real usage, use real models without quotes. This is only meant to show the available options.
         */
        'SomeModel::class' => [
            /**
             * If enabled, this will read from model guarded/fillable properties
             * and decide whether it is allowed to search by these parameters.
             * If guarded property is present, fillable won't be taken. Laravel standard
             * is to use one or the other, not both.
             * This takes precedence before forbidden columns, but if both are used, it
             * will behave like union of columns to be excluded.
             * Searching on forbidden columns will throw an exception.
             */
            'eloquent_exclusion' => false,
            /**
             * Disable search on specific columns. Searching on forbidden columns will throw an exception.
             */
            'forbidden_columns' => ['column', 'column2'],
            /**
             * Array of columns to order by in 'column => direction' format.
             * 'order-by' from query string takes precedence before these values.
             */
            'order_by' => [
                'id' => 'asc',
                'created_at' => 'desc',
            ],
            /**
             * List of columns to return. Return values forwarded within the request will
             * override these values. This acts as a 'SELECT /return only columns/' from.
             * By default, 'SELECT *' will be ran.
             */
            'returns' => ['column', 'column2'],
            /**
             * List of relations to load by default. These will be overridden if provided within query string.
             */
            'relations' => ['rel1', 'rel2'],

            /**
             * Relations that are forbidden for this model.
             * Forbidden relations are treated as not found and are never auto-discovered.
             */
            'forbidden_relations' => ['internal_relation'],

            /**
             * TBD
             * Some column names may be different on frontend than on backend.
             * It is possible to map such columns so that the true ORM
             * property stays hidden.
             */
            'column_mapping' => [
                'frontend_column' => 'backend_column',
            ],
        ],
    ],
];

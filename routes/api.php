<?php

use Illuminate\Support\Facades\Route;
use PowerVending\LaravelApiQueryBuilder\Http\Controllers\SchemaController;

Route::get('/{resource}/schema', [SchemaController::class, 'show'])
    ->name('schema.show');

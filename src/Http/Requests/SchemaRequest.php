<?php

declare(strict_types = 1);

namespace PowerVending\LaravelApiQueryBuilder\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SchemaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'relations' => ['sometimes', 'array'],
            'relations.*' => ['string'],
        ];
    }
}

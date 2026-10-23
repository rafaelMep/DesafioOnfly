<?php

namespace App\Http\Requests;

use App\Enums\TravelStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTravelOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TravelStatus::class)],
            'destination' => ['nullable', 'string', 'max:150'],
            'date_type' => ['nullable', Rule::in(['created', 'travel'])],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('date_from'), ['after_or_equal:date_from'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}

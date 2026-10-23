<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTravelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'destination' => ['required', 'string', 'max:150'],
            'departure_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'return_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:departure_date'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}

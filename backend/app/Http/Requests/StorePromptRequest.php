<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePromptRequest extends FormRequest
{
    /** All authenticated users may create prompts. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug'        => 'required|string|max:255|unique:prompts,slug|regex:/^[a-z0-9\-]+$/',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'variables'   => 'nullable|array',
            'variables.*' => 'string|max:100',
        ];
    }
}

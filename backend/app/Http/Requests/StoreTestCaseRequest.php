<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTestCaseRequest extends FormRequest
{
    /** All authenticated users may create test cases. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'            => 'required|string|max:255',
            'description'     => 'nullable|string',
            'input_variables' => 'nullable|array',
            'expected_output' => 'required|string',
            'assertion_type'  => ['required', Rule::in(['contains', 'not_contains', 'regex', 'llm_judge'])],
        ];
    }
}

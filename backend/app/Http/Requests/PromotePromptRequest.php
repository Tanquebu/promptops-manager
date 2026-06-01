<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromotePromptRequest extends FormRequest
{
    /** All authenticated users may promote versions. */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version_id'  => 'required|string|exists:prompt_versions,id',
            'environment' => ['required', Rule::in(['development', 'staging', 'production'])],
            'promoted_by' => 'nullable|string|max:255',
        ];
    }
}

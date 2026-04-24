<?php

namespace App\Http\Requests\Employee;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLoeReportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'in:draft,submitted'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.project_id' => ['required', 'distinct', 'exists:projects,id'],
            'entries.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}

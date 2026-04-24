<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportFilterRequest extends FormRequest
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
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
            'user_id' => ['nullable', 'exists:users,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'format' => ['nullable', Rule::in(['json', 'pdf', 'xlsx'])],
            'type' => ['nullable', Rule::in(['employee-monthly', 'employee-yearly', 'project-summary', 'missing-submissions', 'allocation-variance', 'compliance-scorecard', 'employee-consistency', 'time-off-impact', 'reviewer-effectiveness', 'system-effectiveness-summary'])],
        ];
    }
}

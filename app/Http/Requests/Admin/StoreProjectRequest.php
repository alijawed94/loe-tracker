<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectEngagementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255', 'unique:projects,name'],
            'engagement' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'engagement_type' => ['required', Rule::enum(ProjectEngagementType::class)],
            'status' => ['required', 'boolean'],
        ];
    }
}

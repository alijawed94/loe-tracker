<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProjectEngagementType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
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
        $projectId = $this->route('project')?->id;

        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('projects', 'name')->ignore($projectId)],
            'engagement' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'engagement_type' => ['required', Rule::enum(ProjectEngagementType::class)],
            'status' => ['required', 'boolean'],
        ];
    }
}

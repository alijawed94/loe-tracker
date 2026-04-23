<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAllocationRequest extends FormRequest
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
            'user_id' => ['required', 'exists:users,id'],
            'project_id' => ['required', 'exists:projects,id', 'unique:allocations,project_id,NULL,id,user_id,'.$this->input('user_id').',deleted_at,NULL'],
            'percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}

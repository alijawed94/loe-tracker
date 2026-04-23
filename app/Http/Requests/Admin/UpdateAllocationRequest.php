<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAllocationRequest extends FormRequest
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
        $allocationId = $this->route('allocation')?->id;

        return [
            'user_id' => ['required', 'exists:users,id'],
            'project_id' => [
                'required',
                'exists:projects,id',
                Rule::unique('allocations', 'project_id')
                    ->ignore($allocationId)
                    ->where(fn ($query) => $query->where('user_id', $this->input('user_id'))->whereNull('deleted_at')),
            ],
            'percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}

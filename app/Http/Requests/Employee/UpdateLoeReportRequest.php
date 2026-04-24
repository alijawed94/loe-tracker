<?php

namespace App\Http\Requests\Employee;

use App\Models\LoeEntry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'entries.*.entry_type' => ['required', 'in:project,time_off'],
            'entries.*.project_id' => ['nullable', 'exists:projects,id'],
            'entries.*.time_off_type' => ['nullable', 'in:'.implode(',', LoeEntry::TIME_OFF_TYPES)],
            'entries.*.percentage' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $entries = collect($this->input('entries', []));
                $seenProjects = [];
                $seenTimeOffTypes = [];

                $entries->each(function (array $entry, int $index) use (&$seenProjects, &$seenTimeOffTypes, $validator) {
                    $entryType = $entry['entry_type'] ?? null;
                    $projectId = $entry['project_id'] ?? null;
                    $timeOffType = $entry['time_off_type'] ?? null;

                    if ($entryType === LoeEntry::ENTRY_TYPE_PROJECT) {
                        if (! $projectId) {
                            $validator->errors()->add("entries.{$index}.project_id", 'A project is required for project entries.');
                        }

                        if ($projectId && in_array($projectId, $seenProjects, true)) {
                            $validator->errors()->add("entries.{$index}.project_id", 'Duplicate projects are not allowed in a single LOE.');
                        }

                        $seenProjects[] = $projectId;
                    }

                    if ($entryType === LoeEntry::ENTRY_TYPE_TIME_OFF) {
                        if (! $timeOffType) {
                            $validator->errors()->add("entries.{$index}.time_off_type", 'A time off type is required for time off entries.');
                        }

                        if ($timeOffType && in_array($timeOffType, $seenTimeOffTypes, true)) {
                            $validator->errors()->add("entries.{$index}.time_off_type", 'Duplicate time off types are not allowed in a single LOE.');
                        }

                        $seenTimeOffTypes[] = $timeOffType;
                    }
                });
            },
        ];
    }
}

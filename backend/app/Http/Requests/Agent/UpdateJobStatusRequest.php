<?php

namespace App\Http\Requests\Agent;

use App\Enums\PrintJobStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobStatusRequest extends FormRequest
{
    /**
     * Ownership of the job is checked by the controller's scoped lookup.
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
            'status' => ['required', Rule::enum(PrintJobStatus::class)->only([
                PrintJobStatus::Printing,
                PrintJobStatus::Printed,
                PrintJobStatus::Failed,
            ])],
            'error' => ['nullable', 'string', 'max:2000', 'required_if:status,failed'],
        ];
    }

    public function status(): PrintJobStatus
    {
        return $this->enum('status', PrintJobStatus::class);
    }
}

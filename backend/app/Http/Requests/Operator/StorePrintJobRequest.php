<?php

namespace App\Http\Requests\Operator;

use App\Enums\PrinterStatus;
use App\Http\Requests\PrintOptionsRequest;
use App\Models\Printer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StorePrintJobRequest extends PrintOptionsRequest
{
    /**
     * Any logged-in operator may print; the route requires auth.
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
            ...parent::rules(),
            'file' => $this->pdfRules(),
            'printer_id' => ['required', 'integer', Rule::exists('printers', 'id')->where('status', PrinterStatus::Active->value)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            ...$this->pdfMessages(),
            'printer_id.exists' => 'Elige una impresora activa.',
        ];
    }

    public function printer(): Printer
    {
        return Printer::findOrFail($this->integer('printer_id'));
    }

    protected function supportsColor(): bool
    {
        return $this->printer()->supports_color;
    }
}

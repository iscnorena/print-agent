<?php

namespace App\Http\Requests\Operator;

use App\Enums\Duplex;
use App\Enums\PrinterStatus;
use App\Models\Printer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePrintJobRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:pdf', 'max:'.config('printing.max_upload_kb')],
            'printer_id' => ['required', 'integer', Rule::exists('printers', 'id')->where('status', PrinterStatus::Active->value)],
            'copies' => ['required', 'integer', 'min:1', 'max:100'],
            'color' => ['boolean'],
            'duplex' => ['required', Rule::enum(Duplex::class)],
            'page_range' => ['nullable', 'string', 'max:100', 'regex:/^\s*\d+(\s*-\s*\d+)?(\s*,\s*\d+(\s*-\s*\d+)?)*\s*$/'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || ! $this->boolean('color')) {
                    return;
                }

                if (! $this->printer()->supports_color) {
                    $validator->errors()->add('color', 'Esta impresora solo imprime en blanco y negro.');
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Elige un archivo PDF.',
            'file.mimes' => 'Por ahora solo se aceptan archivos PDF.',
            'file.max' => 'El archivo pesa demasiado (máximo '.intdiv(config('printing.max_upload_kb'), 1024).' MB).',
            'file.uploaded' => 'No se pudo subir el archivo; puede que pese demasiado.',
            'printer_id.exists' => 'Elige una impresora activa.',
            'page_range.regex' => 'Escribe las páginas así: 1-3, 5',
        ];
    }

    public function printer(): Printer
    {
        return Printer::findOrFail($this->integer('printer_id'));
    }

    /**
     * Print options in the shape PrintJobService::queueDirect() expects.
     *
     * @return array{copies: int, color: bool, duplex: Duplex, page_range: ?string}
     */
    public function printOptions(): array
    {
        $pageRange = preg_replace('/\s+/', '', (string) $this->input('page_range'));

        return [
            'copies' => $this->integer('copies'),
            'color' => $this->boolean('color'),
            'duplex' => $this->enum('duplex', Duplex::class),
            'page_range' => $pageRange === '' ? null : $pageRange,
        ];
    }
}

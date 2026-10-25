<?php

namespace App\Http\Requests;

use App\Enums\Duplex;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Copies, color, sides and page range, validated the same way wherever they are
 * chosen: operator upload, customer upload and the operator's "Imprimir" button.
 */
abstract class PrintOptionsRequest extends FormRequest
{
    /**
     * Whether the printer that will take the job can print in color.
     */
    abstract protected function supportsColor(): bool;

    protected function colorUnavailableMessage(): string
    {
        return 'Esta impresora solo imprime en blanco y negro.';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
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

                if (! $this->supportsColor()) {
                    $validator->errors()->add('color', $this->colorUnavailableMessage());
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
            'copies.required' => 'Indica cuántas copias.',
            'copies.min' => 'Pide al menos una copia.',
            'copies.max' => 'Máximo 100 copias por trabajo.',
            'page_range.regex' => 'Escribe las páginas así: 1-3, 5',
        ];
    }

    /**
     * Print options in the shape PrintJobService expects.
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

    /**
     * Rules for the uploaded PDF, shared by the operator and customer pages.
     *
     * @return array<int, string>
     */
    protected function pdfRules(): array
    {
        return ['required', 'file', 'mimes:pdf', 'max:'.config('printing.max_upload_kb')];
    }

    /**
     * @return array<string, string>
     */
    protected function pdfMessages(): array
    {
        return [
            'file.required' => 'Elige un archivo PDF.',
            'file.mimes' => 'Por ahora solo se aceptan archivos PDF.',
            'file.max' => 'El archivo pesa demasiado (máximo '.intdiv(config('printing.max_upload_kb'), 1024).' MB).',
            'file.uploaded' => 'No se pudo subir el archivo; puede que pese demasiado.',
        ];
    }
}

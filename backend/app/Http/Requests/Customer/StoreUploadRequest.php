<?php

namespace App\Http\Requests\Customer;

use App\Http\Requests\PrintOptionsRequest;
use App\Models\Customer;
use App\Models\Location;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StoreUploadRequest extends PrintOptionsRequest
{
    /**
     * Public page: anyone with the link may upload. The route is rate limited.
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
            'phone' => ['required', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || Customer::normalizePhone($value) === null) {
                    $fail('Escribe tu número de celular a 10 dígitos.');
                }
            }],
            'name' => ['nullable', 'string', 'max:60'],
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
            'phone.required' => 'Escribe tu número de celular.',
        ];
    }

    public function phone(): string
    {
        return Customer::normalizePhone($this->string('phone')->toString());
    }

    protected function supportsColor(): bool
    {
        return Location::forCustomerUploads()?->supportsColor() ?? false;
    }

    protected function colorUnavailableMessage(): string
    {
        return 'Por ahora solo imprimimos en blanco y negro.';
    }
}

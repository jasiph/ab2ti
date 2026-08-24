<?php

namespace App\Http\Requests\Membership;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('member')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'legal_name' => trim((string) $this->input('legal_name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'phone' => trim((string) $this->input('phone', '')),
            'address' => trim((string) $this->input('address', '')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'legal_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32', $this->phoneRule()],
            'address' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'legal_name' => 'nama lengkap',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'address' => 'alamat',
        ];
    }

    /**
     * @return array{legal_name: string, email: string, phone: string, address: string}
     */
    public function contactPayload(): array
    {
        return [
            'legal_name' => $this->validated('legal_name'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'address' => $this->validated('address'),
        ];
    }

    private function phoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $phone = (string) $value;

            if (! preg_match('/^[\d+\s().-]+$/', $phone)) {
                $fail('Nomor telepon hanya boleh berisi angka dan tanda + - ( ).');

                return;
            }

            $digits = preg_replace('/\D/', '', $phone) ?? '';

            if (strlen($digits) < 8) {
                $fail('Nomor telepon harus berisi minimal 8 angka.');
            }
        };
    }
}

<?php

namespace App\Http\Requests\Membership;

use App\Enums\MembershipDocumentType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StorePublicMembershipApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $notes = trim((string) $this->input('applicant_notes', ''));

        $this->merge([
            'legal_name' => trim((string) $this->input('legal_name', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'phone' => trim((string) $this->input('phone', '')),
            'address' => trim((string) $this->input('address', '')),
            'applicant_notes' => $notes === '' ? null : $notes,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) config('membership.max_document_kilobytes', 5120);
        $mimes = config('membership.document_mimes', []);

        $fileRule = function (string $type) use ($maxKb, $mimes): array {
            $allowed = $mimes[$type] ?? ['jpg', 'jpeg', 'png', 'pdf'];

            return [
                'nullable',
                File::types($allowed)->max($maxKb),
            ];
        };

        return [
            'intake_token' => ['required', 'uuid'],
            'legal_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32', $this->phoneRule()],
            'address' => ['required', 'string', 'max:2000'],
            'applicant_notes' => ['nullable', 'string', 'max:2000'],
            MembershipDocumentType::ApplicantPhoto->value => $fileRule(MembershipDocumentType::ApplicantPhoto->value),
            MembershipDocumentType::IdentityDocument->value => $fileRule(MembershipDocumentType::IdentityDocument->value),
            MembershipDocumentType::PaymentProof->value => $fileRule(MembershipDocumentType::PaymentProof->value),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'intake_token' => 'formulir',
            'legal_name' => 'nama lengkap',
            'email' => 'email',
            'phone' => 'nomor telepon',
            'address' => 'alamat',
            'applicant_notes' => 'catatan',
            'applicant_photo' => 'foto pemohon',
            'identity_document' => 'dokumen identitas',
            'payment_proof' => 'bukti pembayaran',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'intake_token.required' => 'Formulir ini sudah tidak berlaku. Muat ulang halaman, lalu kirim lagi.',
            'intake_token.uuid' => 'Formulir ini sudah tidak berlaku. Muat ulang halaman, lalu kirim lagi.',
            'legal_name.required' => 'Nama lengkap wajib diisi.',
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Masukkan alamat email yang valid.',
            'phone.required' => 'Nomor telepon wajib diisi.',
            'address.required' => 'Alamat wajib diisi.',
            'applicant_photo.max' => 'Ukuran foto pemohon terlalu besar. Maksimal 5 MB.',
            'identity_document.max' => 'Ukuran dokumen identitas terlalu besar. Maksimal 5 MB.',
            'payment_proof.max' => 'Ukuran bukti pembayaran terlalu besar. Maksimal 5 MB.',
        ];
    }

    /**
     * @return array{legal_name: string, email: string, phone: string, address: string, applicant_notes: ?string}
     */
    public function applicantPayload(): array
    {
        return [
            'legal_name' => $this->validated('legal_name'),
            'email' => $this->validated('email'),
            'phone' => $this->validated('phone'),
            'address' => $this->validated('address'),
            'applicant_notes' => $this->validated('applicant_notes'),
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

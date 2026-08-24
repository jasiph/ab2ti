<?php

namespace Tests\Concerns;

use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

trait SubmitsPublicMembershipApplication
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postPublicMembership(array $payload, ?string $token = null): TestResponse
    {
        $token ??= (string) Str::uuid();

        return $this->withSession(['membership_intake_token' => $token])
            ->post(route('membership.apply.store'), array_merge([
                'intake_token' => $token,
            ], $payload));
    }

    /**
     * @return array<string, string>
     */
    protected function validPublicMembershipPayload(): array
    {
        return [
            'legal_name' => 'Sari Petani',
            'email' => 'sari@example.com',
            'phone' => '081234567890',
            'address' => 'Desa Sukamaju, Jawa Barat',
            'applicant_notes' => 'Petani benih.',
        ];
    }
}

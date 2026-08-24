<?php

namespace App\Http\Requests\Membership;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\Member;
use App\Support\SecureCapability\SecureCapabilityAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ActivateMemberSiteAccessRequest extends FormRequest
{
    use PasswordValidationRules;

    public function authorize(): bool
    {
        $secret = (string) $this->route('secret');
        $purpose = (string) config('membership.account_link_capability_purpose', 'member-account-link');
        $access = app(SecureCapabilityAccess::class);
        $grant = $access->findLiveBySecret($secret);

        if (
            $grant === null
            || ! $access->assertUsable($grant, $purpose, 'member', (int) $grant->target_id)
        ) {
            abort(404);
        }

        $member = Member::query()->find($grant->target_id);

        if ($member === null || $member->isLinkedToAccount()) {
            abort(404);
        }

        $payload = is_array($grant->payload) ? $grant->payload : [];

        if (($payload['existing_account'] ?? false) === true || isset($payload['intended_user_id'])) {
            abort(404);
        }

        return $this->user() === null;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->passwordRules(),
        ];
    }
}

<?php

namespace App\Http\Requests\Membership;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class IssueMemberSiteAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->route('member');

        return $member instanceof Member
            && ($this->user()?->can('update', $member) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('intended_email');

        if (is_string($email)) {
            $this->merge(['intended_email' => Str::lower(trim($email))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'intended_email' => ['required', 'string', 'email', 'max:255'],
        ];
    }
}

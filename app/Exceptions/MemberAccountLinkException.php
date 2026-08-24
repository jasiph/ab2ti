<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

class MemberAccountLinkException extends RuntimeException
{
    public static function alreadyLinked(): self
    {
        return new self('Anggota ini sudah terhubung ke akun lain.');
    }

    public static function userAlreadyLinked(): self
    {
        return new self('Akun ini sudah terhubung ke anggota AB2TI lain.');
    }

    public static function staffCannotClaim(): self
    {
        return new self('Akun staf tidak dapat dihubungkan ke keanggotaan. Gunakan akun pribadi.');
    }

    public static function invitationUnavailable(): self
    {
        return new self('Undangan tautan akun tidak berlaku.');
    }

    public static function invalidAccountEmail(): self
    {
        return new self('Masukkan email akun situs yang valid.');
    }

    public static function staffAccountCannotBeInvited(): self
    {
        return new self('Email itu milik akun staf. Gunakan email akun anggota, bukan akun staf.');
    }

    public static function accountAlreadyExists(): self
    {
        return new self('Akun dengan email ini sudah ada. Masuk, lalu hubungkan akun.');
    }

    public static function emailDoesNotMatchInvitation(): self
    {
        return new self('Gunakan email akun yang dikonfirmasi pada undangan.');
    }

    public function render(): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}

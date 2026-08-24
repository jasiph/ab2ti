<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

class UniqueMemberLapakException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Anggota ini sudah memiliki Lapak. Satu anggota hanya boleh punya satu Lapak.');
    }

    public function render(): RedirectResponse
    {
        return back()
            ->withInput()
            ->withErrors(['member_id' => $this->getMessage()]);
    }
}

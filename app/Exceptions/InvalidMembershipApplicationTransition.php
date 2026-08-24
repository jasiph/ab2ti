<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use RuntimeException;

class InvalidMembershipApplicationTransition extends RuntimeException
{
    public function render(): RedirectResponse
    {
        return back()->with('error', $this->getMessage());
    }
}

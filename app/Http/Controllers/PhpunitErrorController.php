<?php

namespace App\Http\Controllers;

use RuntimeException;

class PhpunitErrorController extends Controller
{
    public function expired(): never
    {
        abort(419);
    }

    public function serverError(): never
    {
        throw new RuntimeException('internal-secret-should-not-leak');
    }
}

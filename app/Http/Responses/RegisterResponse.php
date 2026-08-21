<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Fortify;

class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request)
    {
        $user = Auth::user();

        if ($user && $user->isGuest()) {
            return redirect()->route('dashboard-guest');
        }

        return redirect()->intended(Fortify::redirects('register'));
    }
}

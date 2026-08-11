<?php

namespace EliteDevSquad\SidecarLaravel\Http\Controllers;

use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\Auth;

readonly class LoginAsRedirectController
{
    public function __invoke(Request $request, int|string $user): RedirectResponse
    {
        if (! config('devsquad-sidecar.enabled') || app()->isProduction()) {
            abort(403, 'Sidecar is disabled.');
        }

        if (! $request->hasValidSignatureWhileIgnoring(['redirect'])) {
            abort(403);
        }

        session()->put('fake_login', true);

        Auth::loginUsingId($user);

        $redirect = $request->query('redirect');

        if (is_string($redirect) && $redirect !== '' && parse_url($redirect, PHP_URL_HOST) === $request->getHost()) {
            return redirect()->to($redirect);
        }

        return redirect('/');
    }
}

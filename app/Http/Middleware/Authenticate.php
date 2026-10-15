<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    protected function authenticate($request, array $guards)
    {
        parent::authenticate($request,$guards);
        $usuario=$request->user();
        $token=$request->session()->get('cuenta_token');
        if (!$usuario->estado || $usuario->trashed() ||
            ($usuario->session_token !== null && (!is_string($token) || !hash_equals($usuario->session_token,$token)))) {
            auth()->logout();$request->session()->invalidate();$request->session()->regenerateToken();
            throw new \Illuminate\Auth\AuthenticationException('La cuenta inició sesión en otro dispositivo o fue desactivada.',$guards,route('login'));
        }
        if ($usuario->session_token && is_string($token)) {
            \App\Models\User::whereKey($usuario->id)->where('session_token', $token)
                ->update(['session_last_activity' => now()->timestamp]);
        }
    }
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        return $request->expectsJson() ? null : route('login');
    }
}

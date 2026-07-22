<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * Bloquea rutas que solo debe ver el admin/owner. Agent y viewer → 403.
 * Registrado como alias 'admin.only' en bootstrap/app.php.
 */
class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->hasRoleAtLeast(User::ROLE_ADMIN), 403,
            'Sección solo para administradores.');

        return $next($request);
    }
}

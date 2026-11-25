<?php

namespace App\Http\Middleware;

use App\Support\Acceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Administrador: puede ejecutar TODOS los métodos. Uso: ->middleware('is.administrator:pagarCargo') */
class IsAdministrator
{
    public function handle(Request $request, Closure $next, string $metodo = ''): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->role !== 'administrador') {
            return Acceso::denegado(); // aborta antes del controlador
        }

        return $next($request);
    }
}

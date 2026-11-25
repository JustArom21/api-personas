<?php

namespace App\Http\Middleware;

use App\Support\Acceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Recepcionista: login, consultarEstadoCliente y verificarPazYSalvo. */
class CanCheckClearance
{
    private const ROL = 'recepcionista';

    private const METODOS = ['consultarEstadoCliente', 'verificarPazYSalvo'];

    public function handle(Request $request, Closure $next, string $metodo = ''): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->role !== self::ROL || ! in_array($metodo, self::METODOS, true)) {
            return Acceso::denegado();
        }

        return $next($request);
    }
}

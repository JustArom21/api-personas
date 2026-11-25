<?php

namespace App\Http\Middleware;

use App\Support\Acceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Cajero: login, pagarCargo y verificarPazYSalvo. Sin acceso al SOAP consultarReservas(). */
class CanProcessPayments
{
    private const ROL = 'cajero';

    private const METODOS = ['pagarCargo', 'verificarPazYSalvo'];

    public function handle(Request $request, Closure $next, string $metodo = ''): Response
    {
        $usuario = $request->user();

        if (! $usuario || $usuario->role !== self::ROL || ! in_array($metodo, self::METODOS, true)) {
            return Acceso::denegado();
        }

        return $next($request);
    }
}

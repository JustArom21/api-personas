<?php

namespace App\Http\Middleware;

use App\Support\Acceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Alias 'perfil'. Varias rutas son compartidas por varios perfiles, y los middlewares de Laravel
 * se encadenan en AND. Este despachador elige, según $request->user()->role, cuál de los cuatro
 * middlewares de perfil se ejecuta; ese middleware valida su fila de la matriz y devuelve el 403.
 * Uso: ->middleware('perfil:registrarCargo')
 */
class DespacharPorRol
{
    private const MIDDLEWARE_POR_ROL = [
        'administrador' => IsAdministrator::class,
        'estilista' => CanRegisterCharges::class,
        'cajero' => CanProcessPayments::class,
        'recepcionista' => CanCheckClearance::class,
    ];

    public function handle(Request $request, Closure $next, string $metodo): Response
    {
        $rol = $request->user()?->role ?? '';
        $clase = self::MIDDLEWARE_POR_ROL[$rol] ?? null;

        if ($clase === null) {
            return Acceso::denegado();
        }

        return app($clase)->handle($request, $next, $metodo);
    }
}

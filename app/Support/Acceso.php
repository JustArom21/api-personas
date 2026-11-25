<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/** Respuesta única de denegación (403) usada por todos los middlewares de permisos. */
final class Acceso
{
    public const MENSAJE = 'Permisos insuficientes para ejecutar este método';

    public static function denegado(): JsonResponse
    {
        return response()->json(['error' => self::MENSAJE], 403);
    }
}

<?php

namespace App\Http\Middleware;

use App\Support\Acceso;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de acceso al SOAP consultarReservas() (alias 'soap.acceso').
 *
 *  administrador -> todas las operaciones
 *  estilista     -> solo al ejecutar registrarCargo o consultarEstadoCliente
 *  cajero        -> NO permitido
 *  recepcionista -> solo al ejecutar consultarEstadoCliente
 *
 * La operación de origen la fija el controlador REST (ReservasSoapService). En la llamada directa
 * al endpoint /api/soap se toma del header X-Operacion-Origen.
 */
class AccesoSoapReservas
{
    public const ORIGEN_HEADER = 'X-Operacion-Origen';

    public static function permite(?string $rol, ?string $origen): bool
    {
        return match ($rol) {
            'administrador' => true,
            'estilista' => in_array($origen, ['registrarCargo', 'consultarEstadoCliente'], true),
            'recepcionista' => $origen === 'consultarEstadoCliente',
            default => false, // cajero y roles desconocidos
        };
    }

    public function handle(Request $request, Closure $next): Response
    {
        $rol = $request->user()?->role;

        $origen = $request->header(self::ORIGEN_HEADER) ?? $request->attributes->get('soap_origen');

        if (! self::permite($rol, $origen)) {
            return Acceso::denegado(); // aborta antes de llegar al servicio SOAP
        }

        return $next($request);
    }
}

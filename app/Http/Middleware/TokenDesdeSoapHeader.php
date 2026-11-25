<?php

namespace App\Http\Middleware;

use Closure;
use DOMDocument;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class TokenDesdeSoapHeader
{
    /** @return array{token: ?string, origen: ?string} */
    public static function extraer(string $sobreXml): array
    {
        $vacio = ['token' => null, 'origen' => null];

        if (trim($sobreXml) === '') {
            return $vacio;
        }

        $previo = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        // LIBXML_NONET: sin acceso a red. No se usa LIBXML_NOENT, así que no se expanden entidades externas (XXE).
        $valido = $dom->loadXML($sobreXml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);

        if (! $valido) {
            return $vacio;
        }

        $auth = $dom->getElementsByTagNameNS('*', 'AuthHeader')->item(0);
        if ($auth === null) {
            return $vacio;
        }

        $leer = function (string $nombre) use ($auth): ?string {
            $nodo = $auth->getElementsByTagNameNS('*', $nombre)->item(0);
            $valor = $nodo ? trim($nodo->textContent) : '';

            return $valor !== '' ? $valor : null;
        };

        return ['token' => $leer('token'), 'origen' => $leer('origen')];
    }

    public function handle(Request $request, Closure $next): Response
    {
        $datos = self::extraer($request->getContent());

        // El header HTTP Authorization tiene prioridad; el SOAP Header solo se usa si no viene.
        if (! $request->bearerToken() && $datos['token'] !== null) {
            $request->headers->set('Authorization', 'Bearer '.$datos['token']);
        }

        if ($datos['origen'] !== null) {
            $request->attributes->set('soap_origen', $datos['origen']);
        }

        return $next($request);
    }
}

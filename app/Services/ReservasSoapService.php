<?php

namespace App\Services;

use App\Http\Middleware\AccesoSoapReservas;
use App\Models\Reserva;
use App\Soap\ReservasSoapServer;
use App\Support\Acceso;
use DOMDocument;
use Illuminate\Http\Exceptions\HttpResponseException;
use RuntimeException;

/**
 * Punto único de entrada al SOAP consultarReservas(). Los controladores REST lo reciben por
 * constructor (inyección de dependencias) y le indican la operación REST de origen.
 */
class ReservasSoapService
{
    private function servidor(): ReservasSoapServer
    {
        return new ReservasSoapServer(url('/api/soap'), function (int $clienteId): string {
            return Reserva::where('cliente_id', $clienteId)
                ->orderBy('fecha_hora')
                ->get(['id', 'cliente_id', 'servicio', 'fecha_hora', 'estado'])
                ->toJson(JSON_UNESCAPED_UNICODE);
        });
    }

    public function wsdl(): string
    {
        return $this->servidor()->wsdl();
    }

    /** @return array{0:int,1:string} [código HTTP, sobre SOAP] — usado por el endpoint /api/soap. */
    public function procesarSobre(string $sobreXml): array
    {
        return $this->servidor()->responder($sobreXml);
    }

    /**
     * Invoca consultarReservas() desde una operación REST.
     * Aplica la misma regla de acceso del middleware 'soap.acceso' (defensa en profundidad).
     *
     * @return array<int, array<string, mixed>>
     */
    public function consultarReservas(?string $rol, int $clienteId, string $origen): array
    {
        if (! AccesoSoapReservas::permite($rol, $origen)) {
            throw new HttpResponseException(Acceso::denegado());
        }

        $sobre = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<soapenv:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
            .' xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sal="'.ReservasSoapServer::NAMESPACE_URI.'">'
            .'<soapenv:Body><sal:consultarReservas soapenv:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/">'
            .'<clienteId xsi:type="xsd:int">'.$clienteId.'</clienteId>'
            .'</sal:consultarReservas></soapenv:Body></soapenv:Envelope>';

        [$estado, $respuesta] = $this->procesarSobre($sobre);

        if ($estado !== 200) {
            throw new RuntimeException('El servicio SOAP devolvió un error');
        }

        $dom = new DOMDocument;
        $dom->loadXML($respuesta);
        $nodo = $dom->getElementsByTagName('return')->item(0);

        return $nodo ? (json_decode($nodo->textContent, true) ?? []) : [];
    }
}

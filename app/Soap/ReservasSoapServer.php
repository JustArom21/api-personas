<?php

namespace App\Soap;

use Closure;
use RuntimeException;

/**
 * Servidor SOAP (NuSOAP, rpc/encoded) con la operación consultarReservas(clienteId): string JSON.
 * No depende de Laravel: recibe un proveedor de datos (Closure) y el endpoint público del servicio.
 */
class ReservasSoapServer
{
    public const NAMESPACE_URI = 'SalonBellezaSOAP';

    private static ?Closure $proveedor = null;

    public function __construct(private string $endpoint, Closure $proveedor, ?string $rutaNusoap = null)
    {
        $nusoap = $rutaNusoap ?? base_path('vendor/econea/nusoap/src/nusoap.php');
        if (! class_exists('soap_server', false)) {
            if (! is_file($nusoap)) {
                throw new RuntimeException('NuSOAP no está instalado. Ejecute: composer require econea/nusoap');
            }
            require_once $nusoap;
        }
        require_once __DIR__.'/consultarReservas.php';

        self::$proveedor = $proveedor;
    }

    /** Llamado por la función global consultarReservas(). */
    public static function consultar(int $clienteId): string
    {
        return (self::$proveedor)($clienteId);
    }

    private function crearServidor(): \soap_server
    {
        $server = new \soap_server();
        $server->soap_defencoding = 'UTF-8';
        $server->decode_utf8 = false;
        $server->configureWSDL('ReservasService', self::NAMESPACE_URI, $this->endpoint);

        $server->register(
            'consultarReservas',
            ['clienteId' => 'xsd:int'],
            ['return' => 'xsd:string'],
            self::NAMESPACE_URI,
            self::NAMESPACE_URI.'#consultarReservas', // SOAPAction explícito: evita depender de $_SERVER (CLI/tests)
            'rpc',
            'encoded',
            'Consulta las reservas de un cliente (JSON)'
        );

        return $server;
    }

    public function wsdl(): string
    {
        return $this->crearServidor()->wsdl->serialize();
    }

    /** @return array{0:int,1:string} [código HTTP, sobre SOAP de respuesta] */
    public function responder(string $sobreXml): array
    {
        try {
            $server = $this->crearServidor();
            // parseRequest() recibe los headers explícitos: no depende de $_SERVER ni de getallheaders()
            $server->parseRequest(['content-type' => 'text/xml; charset=UTF-8'], $sobreXml);

            if (! $server->fault) {
                $server->invoke_method();
            }
            if (! $server->fault) {
                $server->serialize_return();
            }

            if ($server->fault) {
                return [500, $server->fault->serialize()];
            }

            return [200, $server->responseSOAP];
        } catch (\Throwable $e) {
            // Sobre mal formado u otro error interno de NuSOAP: se responde con un SOAP Fault, no con una traza.
            return [400, self::fault('SOAP-ENV:Client', 'Sobre SOAP inválido o no procesable')];
        }
    }

    private static function fault(string $codigo, string $mensaje): string
    {
        $mensaje = htmlspecialchars($mensaje, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            .'<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body>'
            ."<SOAP-ENV:Fault><faultcode>{$codigo}</faultcode><faultstring>{$mensaje}</faultstring></SOAP-ENV:Fault>"
            .'</SOAP-ENV:Body></SOAP-ENV:Envelope>';
    }
}

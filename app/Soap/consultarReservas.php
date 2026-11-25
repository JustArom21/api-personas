<?php

// Función global que NuSOAP invoca para la operación SOAP consultarReservas.
// Delegamos en el proveedor que configure ReservasSoapServer (así la consulta a datos queda fuera de NuSOAP).

if (! function_exists('consultarReservas')) {
    function consultarReservas($clienteId)
    {
        return \App\Soap\ReservasSoapServer::consultar((int) $clienteId);
    }
}

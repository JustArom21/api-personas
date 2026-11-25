<?php

namespace App\Http\Controllers;

use App\Services\ReservasSoapService;
use Illuminate\Http\Request;

class SoapController extends Controller
{
    private const XML = ['Content-Type' => 'text/xml; charset=utf-8'];

    /** GET /api/soap — WSDL del servicio (requiere token Bearer). */
    public function wsdl(ReservasSoapService $soap)
    {
        return response($soap->wsdl(), 200, self::XML);
    }

    /** POST /api/soap — recibe el sobre SOAP. El middleware 'soap.acceso' ya validó el rol. */
    public function servir(Request $request, ReservasSoapService $soap)
    {
        [$estado, $cuerpo] = $soap->procesarSobre($request->getContent());

        return response($cuerpo, $estado, self::XML);
    }
}

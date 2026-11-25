<?php

namespace App\Http\Controllers;

use App\Models\Cargo;
use App\Models\Cliente;
use App\Services\ReservasSoapService;
use Illuminate\Http\Request;
use RuntimeException;

class FacturacionController extends Controller
{
    public function __construct(private ReservasSoapService $soap) {}

    /** POST /api/registrarCargo — administrador y estilista. Consulta el SOAP para validar la reserva. */
    public function registrarCargo(Request $request)
    {
        $datos = $request->validate([
            'cliente_id' => 'required|integer|exists:clientes,id',
            'descripcion' => 'required|string|max:150',
            'valor' => 'required|numeric|min:0.01',
        ]);

        try {
            $reservas = $this->soap->consultarReservas($request->user()->role, (int) $datos['cliente_id'], 'registrarCargo');
        } catch (RuntimeException) {
            return response()->json(['error' => 'El servicio SOAP no respondió correctamente'], 502);
        }

        $activas = array_values(array_filter($reservas, fn ($r) => ($r['estado'] ?? '') === 'activa'));

        if ($activas === []) {
            return response()->json(['error' => 'El cliente no tiene reservas activas'], 422);
        }

        $cargo = Cargo::create($datos + ['registrado_por' => $request->user()->id]);

        return response()->json([
            'mensaje' => 'Cargo registrado exitosamente',
            'data' => $cargo,
            'reservas_activas' => count($activas),
        ], 201);
    }

    /** GET /api/consultarEstadoCliente/{id} — administrador, estilista y recepcionista. */
    public function consultarEstadoCliente(Request $request, int $id)
    {
        $cliente = Cliente::findOrFail($id);

        try {
            $reservas = $this->soap->consultarReservas($request->user()->role, $cliente->id, 'consultarEstadoCliente');
        } catch (RuntimeException) {
            return response()->json(['error' => 'El servicio SOAP no respondió correctamente'], 502);
        }

        $pendientes = $cliente->cargos()->where('estado', 'pendiente')->get();

        return response()->json([
            'cliente' => $cliente,
            'reservas' => $reservas,
            'cargos_pendientes' => $pendientes,
            'total_pendiente' => $pendientes->sum('valor'),
        ], 200);
    }

    /** POST /api/pagarCargo/{id} — administrador y cajero. Es idempotente. */
    public function pagarCargo(int $id)
    {
        $cargo = Cargo::findOrFail($id);

        if ($cargo->estado === 'pagado') {
            return response()->json(['mensaje' => 'El cargo ya estaba pagado', 'data' => $cargo], 200);
        }

        $cargo->update(['estado' => 'pagado', 'pagado_at' => now()]);

        return response()->json(['mensaje' => 'Cargo pagado exitosamente', 'data' => $cargo], 200);
    }

    /** GET /api/verificarPazYSalvo/{id} — administrador, cajero y recepcionista. */
    public function verificarPazYSalvo(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $pendientes = $cliente->cargos()->where('estado', 'pendiente')->count();

        return response()->json([
            'cliente_id' => $cliente->id,
            'paz_y_salvo' => $pendientes === 0,
            'cargos_pendientes' => $pendientes,
        ], 200);
    }
}

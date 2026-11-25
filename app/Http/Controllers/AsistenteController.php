<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Asistente;

class AsistenteController extends Controller
{
    public function store(Request $request)
    {
        // Validación según los requerimientos del parcial
        $validatedData = $request->validate([
            'evento' => 'required|string|max:100',
            'tipo_entrada' => 'nullable|string|max:50',
        ]);

        // Inserción con Eloquent
        $asistente = Asistente::create($validatedData);

        // Respuesta JSON de exito
        return response()->json([
            'mensaje' => 'Asistente registrado exitosamente',
            'data' => $asistente
        ], 201);
    }
}
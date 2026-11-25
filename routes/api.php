<?php

use App\Http\Controllers\AsistenteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\SoapController;
use Illuminate\Support\Facades\Route;

// Única ruta pública: permite obtener el token.
Route::post('/login', [AuthController::class, 'login']);

// Todo lo demás exige token Bearer válido (sin token: 401 JSON).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Facturación: el alias 'perfil' ejecuta el middleware del rol del usuario
    // (IsAdministrator, CanRegisterCharges, CanProcessPayments o CanCheckClearance).
    Route::post('/registrarCargo', [FacturacionController::class, 'registrarCargo'])
        ->middleware('perfil:registrarCargo');
    Route::get('/consultarEstadoCliente/{id}', [FacturacionController::class, 'consultarEstadoCliente'])
        ->whereNumber('id')->middleware('perfil:consultarEstadoCliente');
    Route::post('/pagarCargo/{id}', [FacturacionController::class, 'pagarCargo'])
        ->whereNumber('id')->middleware('perfil:pagarCargo');
    Route::get('/verificarPazYSalvo/{id}', [FacturacionController::class, 'verificarPazYSalvo'])
        ->whereNumber('id')->middleware('perfil:verificarPazYSalvo');

    // WSDL del servicio SOAP (requiere token Bearer).
    Route::get('/soap', [SoapController::class, 'wsdl']);

    // Rutas de las guías anteriores, ahora protegidas por token.
    Route::post('/personas', [PersonaController::class, 'store']);
    Route::post('/registro-asistentes', [AsistenteController::class, 'store']);
});

// Servicio SOAP consultarReservas(). El token puede ir en Authorization: Bearer o en el SOAP Header <AuthHeader><token>.
// Orden: soap.token (lee el SOAP Header) -> auth:sanctum (401) -> soap.acceso (403 según rol y operación de origen).
Route::post('/soap', [SoapController::class, 'servir'])
    ->middleware(['soap.token', 'auth:sanctum', 'soap.acceso']);

<?php

use App\Http\Middleware\AccesoSoapReservas;
use App\Http\Middleware\CanCheckClearance;
use App\Http\Middleware\CanProcessPayments;
use App\Http\Middleware\CanRegisterCharges;
use App\Http\Middleware\DespacharPorRol;
use App\Http\Middleware\IsAdministrator;
use App\Http\Middleware\TokenDesdeSoapHeader;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'is.administrator' => IsAdministrator::class,
            'can.register.charges' => CanRegisterCharges::class,
            'can.process.payments' => CanProcessPayments::class,
            'can.check.clearance' => CanCheckClearance::class,
            'perfil' => DespacharPorRol::class,
            'soap.acceso' => AccesoSoapReservas::class,
            'soap.token' => TokenDesdeSoapHeader::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();

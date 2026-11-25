<?php

namespace Database\Seeders;

use App\Models\Cargo;
use App\Models\Cliente;
use App\Models\Reserva;
use Illuminate\Database\Seeder;

class FacturacionSeeder extends Seeder
{
    /** Datos de prueba: clientes 1 y 2 con reservas; cliente 1 con cargos pendientes, cliente 2 al día. */
    public function run(): void
    {
        $ana = Cliente::updateOrCreate(['documento' => '1001'], ['nombre' => 'Ana Gómez', 'telefono' => '3001234567']);
        $luis = Cliente::updateOrCreate(['documento' => '1002'], ['nombre' => 'Luis Pérez', 'telefono' => '3007654321']);

        if (Reserva::count() === 0) {
            Reserva::create(['cliente_id' => $ana->id, 'servicio' => 'Corte y peinado', 'fecha_hora' => '2026-12-01 10:00:00']);
            Reserva::create(['cliente_id' => $luis->id, 'servicio' => 'Manicure', 'fecha_hora' => '2026-12-02 15:30:00']);
        }

        if (Cargo::count() === 0) {
            Cargo::create(['cliente_id' => $ana->id, 'descripcion' => 'Tratamiento capilar adicional', 'valor' => 45000]);
            Cargo::create(['cliente_id' => $ana->id, 'descripcion' => 'Productos de cuidado', 'valor' => 32000]);
            Cargo::create(['cliente_id' => $luis->id, 'descripcion' => 'Esmaltado semipermanente', 'valor' => 28000, 'estado' => 'pagado', 'pagado_at' => now()]);
        }
    }
}

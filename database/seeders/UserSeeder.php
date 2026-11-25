<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /** Usuarios de laboratorio: una cuenta por perfil. Contraseña común: ClaveDemo123! */
    public function run(): void
    {
        $usuarios = [
            ['name' => 'Administrador Demo', 'email' => 'administrador@salon.test', 'role' => 'administrador'],
            ['name' => 'Estilista Demo',     'email' => 'estilista@salon.test',     'role' => 'estilista'],
            ['name' => 'Cajero Demo',        'email' => 'cajero@salon.test',        'role' => 'cajero'],
            ['name' => 'Recepcionista Demo', 'email' => 'recepcionista@salon.test', 'role' => 'recepcionista'],
        ];

        foreach ($usuarios as $datos) {
            User::updateOrCreate(
                ['email' => $datos['email']],
                $datos + ['password' => 'ClaveDemo123!'] // el cast 'hashed' del modelo la encripta
            );
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cargo extends Model
{
    use HasFactory;

    protected $fillable = ['cliente_id', 'descripcion', 'valor', 'estado', 'registrado_por', 'pagado_at'];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'pagado_at' => 'datetime',
        ];
    }
}

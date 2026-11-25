<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory;

    protected $fillable = ['nombre', 'documento', 'telefono'];

    public function reservas(): HasMany
    {
        return $this->hasMany(Reserva::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }
}

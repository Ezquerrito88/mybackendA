<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Signature extends Model
{
    use HasFactory;

    // Campos que permitimos llenar masivamente
    protected $fillable = [
        'user_id',
        'petition_id'
    ];

    /**
     * Relación: Una firma pertenece a una petición
     */
    public function petition()
    {
        return $this->belongsTo(Petitions::class, 'petition_id');
    }

    /**
     * Relación: Una firma pertenece a un usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
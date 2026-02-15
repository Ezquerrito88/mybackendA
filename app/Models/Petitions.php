<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Petitions extends Model
{
    use HasFactory;

    protected $fillable = [
        'titulo',
        'descripcion',
        'destinatario',
        'estado',
        'firmantes',
        'user_id',
        'file',
        'categoria_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        
        return $this->belongsTo(Categories::class, 'categoria_id');
    }

    public function files()
    {
        return $this->hasMany(Files::class, 'petition_id');
    }

    public function firmas()
    {
        return $this->belongsToMany(User::class, 'petition_users', 'petition_id', 'user_id');
    }
}
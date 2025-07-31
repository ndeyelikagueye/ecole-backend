<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentModel extends Model
{
    use HasFactory;

    protected $table = 'parents';

    protected $fillable = [
        'user_id',
        'telephone',
        'adresse',
        'profession',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function enfants()
    {
        return $this->hasMany(Eleve::class, 'parent_id');
    }

    // Méthodes utiles
    public function getNomCompletAttribute()
    {
        return $this->user->nom . ' ' . $this->user->prenom;
    }
}
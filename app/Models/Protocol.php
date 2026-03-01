<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Protocol extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'default_port',
        'description'
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Manager extends Model
{
    //
     protected $fillable = [
        'name',
        'phone',
        'create_access',
        'view_access',
        'edit_access',
        'delete_access',
        'is_partner',
    ];
}

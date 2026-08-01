<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'module',
        'description',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    public static function getModules()
    {
        return self::select('module')->distinct()->pluck('module');
    }

    public static function getByModule($module)
    {
        return self::where('module', $module)->get();
    }
}

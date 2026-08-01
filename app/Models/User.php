<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    // use  HasFactory, Notifiable;
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    // protected $guraded = [];
    protected $guarded = [];
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'is_super_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */ 
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_super_admin' => 'boolean',
        // 'password' => 'hashed',
    ];

    // ===== Role & Permission Methods =====

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles->contains('slug', $role);
        }

        return $this->roles->contains($role);
    }

    public function hasAnyRole(array $roles)
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    public function assignRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching($role);

        return $this;
    }

    public function removeRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }

        $this->roles()->detach($role);

        return $this;
    }

    public function syncRoles(array $roles)
    {
        $this->roles()->sync($roles);

        return $this;
    }

    public function getAllPermissions()
    {
        return $this->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    public function hasPermission($permission)
    {
        if ($this->is_super_admin) {
            return true;
        }

        if (is_string($permission)) {
            return $this->getAllPermissions()->contains('slug', $permission);
        }

        return $this->getAllPermissions()->contains($permission);
    }

    public function hasAnyPermission(array $permissions)
    {
        if ($this->is_super_admin) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllPermissions(array $permissions)
    {
        if ($this->is_super_admin) {
            return true;
        }

        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    public function isSuperAdmin()
    {
        return $this->is_super_admin === true;
    }

    // ===== Relationships =====

    /**
     * User can own multiple hatcheries
     */
    public function hatcheries()
    {
        return $this->hasMany(Hatchery::class, 'owner_id');
    }

    /**
     * User can have multiple bookings
     */
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    /**
     * User can have multiple OTPs
     */
    public function otps()
    {
        return $this->hasMany(UserOtp::class);
    }

    /**
     * User can have multiple notifications
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }


        public function adminOtps()
    {
        return $this->hasMany(AdminLoginOtp::class);
    }


}

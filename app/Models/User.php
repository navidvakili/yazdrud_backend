<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public $incrementing = false;

    protected $primaryKey = 'username';

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'username',
        'fname',
        'lname',
        'kodmeli',
        'mobile',
        'email',
        'password',
        'role',
        'sign',
        'theme',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the user's full name.
     */
    public function getName(): string
    {
        return $this->attributes['fname'] . ' ' . $this->attributes['lname'];
    }

    /**
     * Get all roles for the user from the roles table.
     */
    public function getRolesAttribute()
    {
        $roles = [];
        $rolesDb = Role::where('username', $this->username)->select('role')->get();
        foreach ($rolesDb as $item) {
            $roles[$item->role] = $item->role;
        }
        return $roles;
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    public function rolesRelation()
    {
        return $this->hasMany(Role::class, 'username', 'username');
    }
}

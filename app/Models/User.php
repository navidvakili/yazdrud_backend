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

    public function thesis()
    {
        return $this->hasOne(Thesis::class, 'id_edu', 'username');
    }

    public function student()
    {
        return $this->hasOne(Student::class, 'id_edu', 'username');
    }

    public function teacher()
    {
        return $this->hasOne(Teacher::class, 'id_ostad', 'username');
    }

    public function fields()
    {
        return $this->belongsToMany(Field::class, Accessgroup::class, 'username', 'field_id', 'username', 'id');
    }

    public function phd()
    {
        return $this->hasOne(Phd::class, 'username', 'username');
    }

    public function rolesRelation()
    {
        return $this->hasMany(Role::class, 'username', 'username');
    }

    public function accessGroups()
    {
        return $this->hasMany(Accessgroup::class, 'username', 'username');
    }

    public function accessColleges()
    {
        return $this->hasMany(Accesscollege::class, 'username', 'username');
    }
}

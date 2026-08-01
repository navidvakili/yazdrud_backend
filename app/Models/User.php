<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * Spatie: Use 'api' guard since this app uses Passport.
     */
    public string $guard_name = 'api';

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
        'is_active',
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
            'is_active' => 'boolean',
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
     * Get all roles for the user as an associative array.
     * Used by AuthController for role listing.
     */
    public function getAllRolesArray(): array
    {
        return $this->getRoleNames()->toArray();
    }

    /**
     * Check if the user is a super user (username 'admin' or 'support').
     * Super users bypass ALL permission checks and have full access to every section.
     */
    public function isSuperUser(): bool
    {
        return in_array($this->username, ['admin', 'support']);
    }
}

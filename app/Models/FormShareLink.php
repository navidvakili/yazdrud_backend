<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;

class FormShareLink extends Model
{
    protected $fillable = [
        'form_id',
        'slug',
        'password',
        'expires_at',
        'is_active',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function setPasswordAttribute(?string $value): void
    {
        $this->attributes['password'] = $value === null ? null : Hash::make($value);
    }

    public function verifyPassword(string $plain): bool
    {
        return $this->getRawPassword() !== null && Hash::check($plain, $this->getRawPassword());
    }

    public function hasPassword(): bool
    {
        return $this->getRawPassword() !== null;
    }

    private function getRawPassword(): ?string
    {
        return $this->getAttributes()['password'] ?? null;
    }

    public function isValid(): bool
    {
        return $this->is_active && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}

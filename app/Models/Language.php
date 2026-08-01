<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'name_en',
        'dir',
        'is_active',
        'is_default',
        'ordering',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Scope: active languages only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default language (fallback to fa if none marked).
     */
    public static function defaultLanguage(): string
    {
        $default = static::where('is_default', true)->where('is_active', true)->first();
        return $default ? $default->code : 'fa';
    }

    /**
     * Resolve a requested lang code against active languages.
     * Falls back to the default language when the requested code is missing/inactive.
     */
    public static function resolve(?string $lang): string
    {
        if ($lang) {
            $exists = static::where('code', $lang)->where('is_active', true)->exists();
            if ($exists) {
                return $lang;
            }
        }
        return static::defaultLanguage();
    }

    /**
     * Resolve the language code from a request.
     * Accepts either `lang` or `language` (query/body) — `lang` takes precedence.
     * Falls back to the default language when absent or invalid.
     */
    public static function resolveRequest(\Illuminate\Http\Request $request): string
    {
        return static::resolve($request->input('lang') ?? $request->input('language') ?? null);
    }
}

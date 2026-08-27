<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Form extends Model
{
    protected $fillable = [
        'language',
        'translation_group',
        'title',
        'slug',
        'description',
        'type',
        'status',
        'category',
        'owner_username',
        'version',
        'published_at',
        'tags',
        'steps',
        'fields',
        'layout_blocks',
        'logic_rules',
        'quiz_config',
        'theme',
        'settings',
        'views_count',
        'submissions_count',
        'avg_completion_time_seconds',
    ];

    protected $casts = [
        'tags'         => 'array',
        'steps'        => 'array',
        'fields'       => 'array',
        'layout_blocks' => 'array',
        'logic_rules'  => 'array',
        'quiz_config'  => 'array',
        'theme'        => 'array',
        'settings'     => 'array',
        'published_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    /**
     * فرم‌های «قابل استفاده» — هم published (لینک عمومی مستقل دارد) و هم
     * page_builder_only (فقط برای جاسازی در صفحه‌ساز؛ لینک مستقل خودش ندارد).
     * برای پرکردن/ارسال پاسخ و واکشی جهت جاسازی در ویجت صفحه‌ساز استفاده می‌شود —
     * برخلاف scopePublished که مسیر مستقل /forms/{slug} را عمداً محدود نگه می‌دارد.
     */
    public function scopeLive($query)
    {
        return $query->whereIn('status', ['published', 'page_builder_only']);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function shareLink(): HasOne
    {
        return $this->hasOne(FormShareLink::class);
    }
}

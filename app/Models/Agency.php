<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agency extends Model
{
    protected $fillable = [
        'name',
        'logo',
        'verified',
        'relation',
        'badge',
        'badge_type',
        'hq',
        'branches',
        'rating',
        'years_active',
        'partner_developers',
        'phone',
        'email',
        'about_title',
        'about_description',
        'about_sub_description',
    ];

    protected $casts = [
        'verified'     => 'boolean',
        'rating'       => 'float',
        'years_active' => 'integer',
    ];

    // ─── Relationships ──────────────────────────────────────────────

    /**
     * الوكلاء (المستخدمون) التابعون للوكالة.
     */
    public function agents(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * الوحدات العقارية المُدارة بواسطة الوكالة.
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    // ─── Scopes ─────────────────────────────────────────────────────

    /**
     * فلترة الوكالات الموثقة فقط.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true);
    }

    /**
     * فلترة حسب نوع الشارة (badge_type).
     */
    public function scopeByBadgeType($query, string $badgeType)
    {
        return $query->where('badge_type', $badgeType);
    }
}

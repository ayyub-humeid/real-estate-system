<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'company_id',
        'employee_id',
        'avatar',
        'position',
        'department',
        'hire_date',
        'salary',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'status',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'salary' => 'decimal:2',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return $this->user->name ?? 'N/A';
    }

    public function getEmailAttribute(): string
    {
        return $this->user->email ?? 'N/A';
    }

    public function getPhoneAttribute(): string
    {
        return $this->user->phone ?? 'N/A';
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }
}

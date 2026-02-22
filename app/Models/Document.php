<?php
// app/Models/Document.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'documentable_id',
        'documentable_type',
        'title',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'extension',
        'document_type',
        'description',
        'document_date',
        'uploaded_by',
    ];

    protected $casts = [
        'document_date' => 'date',
        'file_size' => 'integer',
    ];

    // Relationships
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // Accessors
    protected $appends = ['file_url'];

public function getFileUrlAttribute(): string
{
    // Cache the result for this model instance
    return $this->attributes['file_url'] ??= 
        asset('storage/' . $this->file_path);
}
    // public function getFileUrlAttribute(): string
    // {
    //         return asset('storage/' . $this->file_path); // No disk check

    // }

    public function getFileSizeHumanAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getIsImageAttribute(): bool
    {
        return in_array($this->extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
    }

    public function getIsPdfAttribute(): bool
    {
        return $this->extension === 'pdf';
    }

    // Methods
    public function download(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::download($this->file_path, $this->file_name);
    }

    protected static function boot()
    {
        parent::boot();

        // Auto-delete file when document is deleted
        static::deleting(function ($document) {
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
        });
    }
}
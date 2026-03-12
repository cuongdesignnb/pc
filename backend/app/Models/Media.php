<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    protected $fillable = [
        'name',
        'file_name',
        'path',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt',
        'folder',
        'uploaded_by',
    ];

    protected $appends = ['url', 'thumbnail_url', 'human_size'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ---- Accessors ----

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function getThumbnailUrlAttribute(): string
    {
        // If a thumbnail exists, use it; otherwise use original
        $thumbPath = 'thumbnails/' . $this->path;
        if (Storage::disk($this->disk)->exists($thumbPath)) {
            return Storage::disk($this->disk)->url($thumbPath);
        }
        return $this->url;
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }

    // ---- Helpers ----

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Get distinct folders for folder tree
     */
    public static function folders(): array
    {
        return static::distinct()->pluck('folder')->sort()->values()->toArray();
    }
}

<?php

namespace App\Models;

use App\Support\PublicAssetUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Media extends Model
{
    protected $fillable = [
        'name',
        'file_name',
        'path',
        'public_slug',
        'disk',
        'mime_type',
        'size',
        'width',
        'height',
        'alt',
        'caption',
        'folder',
        'uploaded_by',
    ];

    protected $appends = ['url', 'thumbnail_url', 'human_size', 'fixed_url'];

    protected static function booted(): void
    {
        // Every new file gets its fixed link (/files/{public_slug}) right away.
        static::creating(function (Media $media): void {
            if (blank($media->public_slug)) {
                $media->public_slug = static::uniquePublicSlug((string) ($media->name ?: pathinfo((string) $media->file_name, PATHINFO_FILENAME)));
            }
        });
    }

    /**
     * "Driver WiFi Intel" → "driver-wifi-intel". The fixed link carries no
     * file extension on purpose: aaPanel's nginx serves *.jpg/*.png paths as
     * static files, so /files/x.png would never reach Laravel. The download
     * still gets its real extension through Content-Disposition.
     */
    public static function slugCandidate(string $name): string
    {
        return Str::limit(Str::slug($name), 150, '') ?: 'file';
    }

    public static function uniquePublicSlug(string $name, ?int $ignoreId = null): string
    {
        $base = static::slugCandidate($name);
        $slug = $base;
        for ($i = 2; static::query()->where('public_slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /** File name for downloads: the fixed slug plus the stored file's extension. */
    public function downloadName(): string
    {
        $extension = strtolower((string) pathinfo((string) $this->path, PATHINFO_EXTENSION));

        return ($this->public_slug ?: 'file').($extension !== '' ? '.'.$extension : '');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // ---- Accessors ----

    public function getUrlAttribute(): string
    {
        return (string) PublicAssetUrl::normalize(Storage::disk($this->disk)->url($this->path));
    }

    /** The link that never changes, even after "Thay file". */
    public function getFixedUrlAttribute(): ?string
    {
        return filled($this->public_slug) ? url('/files/'.rawurlencode((string) $this->public_slug)) : null;
    }

    public function getThumbnailUrlAttribute(): string
    {
        // If a thumbnail exists, use it; otherwise use original
        $thumbPath = 'thumbnails/'.$this->path;
        if (Storage::disk($this->disk)->exists($thumbPath)) {
            return (string) PublicAssetUrl::normalize(Storage::disk($this->disk)->url($thumbPath));
        }

        return $this->url;
    }

    public function getHumanSizeAttribute(): string
    {
        $bytes = $this->size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class StudentWorkFile extends Model
{
    protected $fillable = [
        'work_log_id', 'user_id', 'category',
        'original_name', 'path', 'disk', 'mime', 'size', 'lat', 'lng',
    ];

    protected $casts = ['size' => 'integer'];

    protected $appends = ['is_image'];

    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    /** ลบไฟล์จริงบนดิสก์เมื่อลบ record */
    protected static function booted(): void
    {
        static::deleting(function (StudentWorkFile $f) {
            try {
                Storage::disk($f->disk ?: 'local')->delete($f->path);
            } catch (\Throwable $e) { /* ignore */ }
        });
    }

    public function workLog(): BelongsTo { return $this->belongsTo(StudentWorkLog::class, 'work_log_id'); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}

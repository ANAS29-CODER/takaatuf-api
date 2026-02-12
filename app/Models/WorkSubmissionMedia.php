<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class WorkSubmissionMedia extends Model
{
    use HasFactory;
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    const TYPE_DOCUMENT = 'document';

    public static function getTypes(): array
    {
        return [
            self::TYPE_IMAGE,
            self::TYPE_VIDEO,
            self::TYPE_DOCUMENT,
        ];
    }

    protected $fillable = [
        'work_submission_id',
        'file_path',
        'original_name',
        'type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function workSubmission(): BelongsTo
    {
        return $this->belongsTo(WorkSubmission::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::url($this->file_path);
    }

    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }
}

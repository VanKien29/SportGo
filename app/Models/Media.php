<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'media';

    public $incrementing = false;

    protected $keyType = 'string';

    public const UPDATED_AT = null;

    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'collection',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Accessor: tự động thêm prefix /storage/ cho file_path
     * để ảnh có thể truy cập qua symlink public/storage.
     */
    public function getFilePathAttribute(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // Nếu đã là URL đầy đủ hoặc đã có prefix /storage/ thì giữ nguyên
        if (str_starts_with($value, 'http') || str_starts_with($value, '/storage/')) {
            return $value;
        }

        return '/storage/' . ltrim($value, '/');
    }
}

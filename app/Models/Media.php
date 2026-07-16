<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

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

        // Giữ nguyên URL đầy đủ và đường dẫn tuyệt đối trong public/.
        if (str_starts_with($value, 'http') || str_starts_with($value, '/')) {
            return $value;
        }

        return '/storage/'.ltrim($value, '/');
    }
}

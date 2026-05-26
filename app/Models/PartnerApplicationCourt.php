<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartnerApplicationCourt extends Model
{
    use HasFactory;

    protected $fillable = [
        'partner_application_id',
        'court_type_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'court_type_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function courtType()
    {
        return $this->belongsTo(CourtType::class, 'court_type_id');
    }

    public function partnerApplication()
    {
        return $this->belongsTo(PartnerApplication::class, 'partner_application_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeedRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'packing_date' => 'date',
        'handled_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_REJECTED = 'rejected';

    public static function getStatuses()
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public function farmer()
    {
        return $this->belongsTo(Farmer::class);
    }

    public function seed()
    {
        return $this->belongsTo(Seed::class);
    }

    public function category()
    {
        return $this->belongsTo(HatcheryCategory::class, 'category_id');
    }

    public function hatchery()
    {
        return $this->belongsTo(Hatchery::class, 'hatchery_id');
    }

    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}

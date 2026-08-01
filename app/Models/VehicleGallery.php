<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VehicleGallery extends Model
{
    use HasFactory;

    protected $table = 'vehicle_gallery';

    protected $fillable = [
        'vehicle_availability_id',
        'file_path',
        'file_type',
        'original_name',
        'sort_order',
    ];

    public function vehicleAvailability()
    {
        return $this->belongsTo(VehicleAvailability::class);
    }

    public function getFileUrlAttribute()
    {
        if (str_starts_with($this->file_path, 'http')) {
            return $this->file_path;
        }
        return asset($this->file_path);
    }

    public function isImage()
    {
        return $this->file_type === 'image';
    }

    public function isVideo()
    {
        return $this->file_type === 'video';
    }
}

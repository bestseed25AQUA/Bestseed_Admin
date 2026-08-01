<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnnouncementRead extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'popup_shown_at' => 'datetime',
        'read_at'        => 'datetime',
    ];

    public function announcement()
    {
        return $this->belongsTo(Announcement::class);
    }
}

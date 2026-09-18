<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A record that one expiry warning was sent, so it is never sent twice.
 *
 * @see \App\Console\Commands\NotifySubscriptionExpiry
 */
class SubscriptionReminder extends Model
{
    protected $fillable = [
        'subscription_id',
        'days_before',
        'sent_at',
    ];

    protected $casts = [
        'sent_at'     => 'datetime',
        'days_before' => 'integer',
    ];

    public function subscription()
    {
        return $this->belongsTo(FarmSubscription::class, 'subscription_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    use HasFactory;

    protected $table = 'event_logs';

    protected $fillable = [
        'user_id',
        'mobile_number',
        'geofence_id',
        'action',
        'acknowledged',
        'attachments_uploaded',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'geofence_id' => 'integer',
        'acknowledged' => 'boolean',
        'attachments_uploaded' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

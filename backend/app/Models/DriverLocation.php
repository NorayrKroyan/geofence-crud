<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverLocation extends Model
{
    use HasFactory;

    protected $table = 'driver_locations';

    protected $fillable = [
        'user_id',
        'device_id',
        'timestamp',
        'latitude',
        'longitude',
        'speed',
        'direction',
        'device_dtm',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'timestamp' => 'datetime',
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
        'speed' => 'decimal:2',
        'direction' => 'decimal:2',
        'device_dtm' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Geofence extends Model
{
    use HasFactory;

    protected $table = 'geofences';

    protected $fillable = [
        'trigger_zone',
        'bounding_box',
        'bounding_box_center',
        'name',
        'center_point_lat',
        'center_point_lng',
        'speed_limit_kph',
        'entry_action',
        'exit_action',
        'color',
        'is_active',
        'is_delete',
        'expire_date',
        'notes',
        'geometry_json',
        'polygon_points',
    ];

    protected $casts = [
        'center_point_lat' => 'decimal:7',
        'center_point_lng' => 'decimal:7',
        'speed_limit_kph' => 'integer',
        'is_active' => 'boolean',
        'is_delete' => 'boolean',
        'expire_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

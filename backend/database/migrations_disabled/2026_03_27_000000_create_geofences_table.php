<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->longText('trigger_zone')->nullable();
            $table->longText('bounding_box')->nullable();
            $table->longText('bounding_box_center')->nullable();
            $table->string('name', 255);
            $table->decimal('center_point_lat', 10, 7)->nullable();
            $table->decimal('center_point_lng', 10, 7)->nullable();
            $table->unsignedInteger('speed_limit_kph')->nullable();
            $table->string('entry_action', 40)->nullable();
            $table->string('exit_action', 40)->nullable();
            $table->string('color', 20)->default('#2563eb');
            $table->boolean('is_active')->default(1);
            $table->boolean('is_delete')->default(0);
            $table->timestamp('expire_date')->nullable();
            $table->text('notes')->nullable();
            $table->longText('geometry_json');
            $table->longText('polygon_points')->nullable();
            $table->timestamps();

            $table->index('name');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};

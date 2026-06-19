<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse-geocoding cache. Events cluster around a fixed set of city
     * anchors with small jitter, so coordinates are rounded to a grid cell
     * (`lat_key`/`lng_key`) and looked up here before hitting any external
     * geocoder — turning ~millions of potential lookups into a few hundred.
     */
    public function up(): void
    {
        Schema::create('geocoded_locations', function (Blueprint $table) {
            $table->id();
            $table->decimal('lat_key', 8, 3);
            $table->decimal('lng_key', 8, 3);
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('timezone')->nullable();
            $table->timestamps();

            $table->unique(['lat_key', 'lng_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geocoded_locations');
    }
};

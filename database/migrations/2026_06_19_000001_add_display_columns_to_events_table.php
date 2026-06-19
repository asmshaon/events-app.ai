<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Promote the fields we filter/sort/display on out of the JSON payload into
     * real columns. `created_time` (unix) stays the source of truth for the
     * start time; `starts_at` mirrors it as an indexed datetime so date
     * filtering/sorting doesn't pay JSON-extraction cost across the dataset.
     * Address/timezone are reverse-geocoded from latitude/longitude.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dateTime('starts_at')->nullable()->after('longitude');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->string('timezone')->nullable()->after('ends_at');
            $table->string('address')->nullable()->after('timezone');
            $table->string('city')->nullable()->after('address');
            $table->string('country', 2)->nullable()->after('city');

            $table->index('starts_at');
            $table->index(['country', 'city']);
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropIndex(['starts_at']);
            $table->dropIndex(['country', 'city']);
            $table->dropColumn(['starts_at', 'ends_at', 'timezone', 'address', 'city', 'country']);
        });
    }
};

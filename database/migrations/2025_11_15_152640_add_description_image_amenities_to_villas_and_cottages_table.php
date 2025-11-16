<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('villas_and_cottages', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('image')->nullable()->after('description');
            $table->json('amenities')->nullable()->after('image');
        });

        // Update type enum to include 'Room' - using DB facade for better compatibility
        DB::statement("ALTER TABLE villas_and_cottages MODIFY COLUMN type ENUM('Villa', 'Cottage', 'Room') DEFAULT 'Villa'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('villas_and_cottages', function (Blueprint $table) {
            $table->dropColumn(['description', 'image', 'amenities']);
        });

        // Revert type enum back to original
        DB::statement("ALTER TABLE villas_and_cottages MODIFY COLUMN type ENUM('Villa', 'Cottage') DEFAULT 'Villa'");
    }
};

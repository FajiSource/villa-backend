<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('villas_and_cottages', function (Blueprint $table) {
            $table->id();
            $table->enum('type',['Villa','Cottage'])->default('Villa');
            $table->string('name');
            $table->integer('capacity');
            $table->decimal('price_per_night',10,2);
            $table->enum('status',['Available','Booked']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('villas_and_cottages');
    }
};

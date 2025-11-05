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
        // Schema::create('bookings', function (Blueprint $table) {
        //     $table->id();
        //     $table->unsignedBigInteger('user_id');
        //     $table->unsignedBigInteger('rc_id');
        //     $table->string('name');
        //     $table->string('contact');
        //     $table->dateTime('check_in');
        //     $table->dateTime('check_out');
        //     $table->integer('pax');
        //     $table->string('special_req');
        //     $table->timestamps();

        //     $table->foreign('user_id')->key('id')->on("users")->onDelete('cascade');
        //     $table->foreign('rc_id')->key('id')->on("villas_and_cottages")->onDelete('cascade');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('bookings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('destination', 150);
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('status', 20)->default('solicitado');
            $table->timestamps();
            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['departure_date', 'return_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('travel_orders');
    }
};

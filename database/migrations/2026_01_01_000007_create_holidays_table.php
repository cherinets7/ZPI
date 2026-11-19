<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('name_am')->nullable();
            $table->unsignedSmallInteger('eth_year')->nullable();
            $table->unsignedTinyInteger('eth_month')->nullable();
            $table->unsignedTinyInteger('eth_day')->nullable();
            $table->date('gregorian_date');
            $table->boolean('is_recurring')->default(false);
            $table->timestamps();

            $table->unique(['gregorian_date', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};

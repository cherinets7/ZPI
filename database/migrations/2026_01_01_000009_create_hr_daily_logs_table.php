<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_daily_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->date('work_date');
            $table->unsignedSmallInteger('eth_year');
            $table->unsignedTinyInteger('eth_month');
            $table->unsignedTinyInteger('eth_day');
            $table->string('status', 20);
            $table->decimal('hours', 5, 2)->default(0);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'work_date']);
            $table->index(['department_id', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_daily_logs');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_weekly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->unsignedSmallInteger('eth_year');
            $table->unsignedSmallInteger('eth_period');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('expected_hours', 10, 2)->default(0);
            $table->decimal('worked_hours', 10, 2)->default(0);
            $table->unsignedInteger('absent_days')->default(0);
            $table->unsignedInteger('leave_days')->default(0);
            $table->unsignedInteger('holiday_days')->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->unique(['department_id', 'eth_year', 'eth_period'], 'hr_weekly_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_weekly_summaries');
    }
};

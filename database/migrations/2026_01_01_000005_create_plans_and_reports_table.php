<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans_and_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('kpi_indicator_id')->constrained('kpi_indicators')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('period_type', 20);
            $table->unsignedSmallInteger('eth_year');
            $table->unsignedSmallInteger('eth_period');
            $table->string('title');
            $table->decimal('target_value', 14, 2)->default(0);
            $table->decimal('achieved_value', 14, 2)->nullable();
            $table->decimal('performance_score', 8, 2)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('locked_at')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->boolean('is_rollup')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('verification_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['eth_year', 'period_type', 'eth_period']);
            $table->unique(
                ['user_id', 'kpi_indicator_id', 'type', 'period_type', 'eth_year', 'eth_period', 'is_rollup'],
                'plans_period_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans_and_reports');
    }
};

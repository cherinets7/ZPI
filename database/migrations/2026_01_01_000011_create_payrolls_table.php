<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->unsignedSmallInteger('eth_year');
            $table->unsignedTinyInteger('eth_month');
            $table->decimal('base_salary', 12, 2);
            $table->decimal('performance_score', 8, 2)->default(0);
            $table->decimal('attendance_ratio', 8, 4)->default(0);
            $table->decimal('bonus_or_adjustment', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2);
            $table->string('status', 20)->default('calculated');
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->string('verification_hash', 64)->nullable()->unique();
            $table->timestamps();

            $table->unique(['user_id', 'eth_year', 'eth_month']);
            $table->index(['department_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payrolls');
    }
};

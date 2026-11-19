<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('type', 30);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 6, 1)->default(0);
            $table->text('reason');
            $table->string('status', 20)->default('draft');
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['department_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_and_report_id')->nullable()->constrained('plans_and_reports')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('receiver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 20)->default('feedback');
            $table->text('comment');
            $table->text('action_taken')->nullable();
            $table->timestamps();

            $table->index(['receiver_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};

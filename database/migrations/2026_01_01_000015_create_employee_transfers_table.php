<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('from_department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('to_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('transferred_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('effective_on');
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_transfers');
    }
};

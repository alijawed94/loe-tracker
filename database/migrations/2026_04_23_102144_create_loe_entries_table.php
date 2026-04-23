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
        Schema::create('loe_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('loe_report_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('project_id')->constrained()->restrictOnDelete();
            $table->decimal('percentage', 5, 2)->unsigned();
            $table->timestamps();

            $table->unique(['loe_report_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loe_entries');
    }
};

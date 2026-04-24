<?php

use App\Models\LoeEntry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loe_entries', function (Blueprint $table) {
            $table->dropUnique(['loe_report_id', 'project_id']);
        });

        Schema::table('loe_entries', function (Blueprint $table) {
            $table->enum('entry_type', [LoeEntry::ENTRY_TYPE_PROJECT, LoeEntry::ENTRY_TYPE_TIME_OFF])
                ->default(LoeEntry::ENTRY_TYPE_PROJECT)
                ->after('loe_report_id');
            $table->foreignUlid('project_id')->nullable()->change();
            $table->enum('time_off_type', LoeEntry::TIME_OFF_TYPES)->nullable()->after('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('loe_entries')
            ->where('entry_type', LoeEntry::ENTRY_TYPE_TIME_OFF)
            ->delete();

        Schema::table('loe_entries', function (Blueprint $table) {
            $table->dropColumn(['entry_type', 'time_off_type']);
        });

        Schema::table('loe_entries', function (Blueprint $table) {
            $table->foreignUlid('project_id')->nullable(false)->change();
            $table->unique(['loe_report_id', 'project_id']);
        });
    }
};

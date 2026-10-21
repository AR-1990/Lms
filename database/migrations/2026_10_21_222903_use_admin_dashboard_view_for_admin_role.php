<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('roles')
            ->where('slug', 'admin')
            ->update(['dashboard_view' => 'dashboards.admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')
            ->where('slug', 'admin')
            ->update(['dashboard_view' => 'dashboards.portal']);
    }
};

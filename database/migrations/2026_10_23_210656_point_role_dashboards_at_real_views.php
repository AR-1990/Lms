<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teacher, student, parent and accounts open their own dashboards (built from school records)
 * instead of the generic placeholder page.
 */
return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $views = [
        'teacher' => 'dashboards.teacher',
        'student' => 'dashboards.student',
        'parent' => 'dashboards.parent',
        'accounts' => 'dashboards.accounts',
    ];

    public function up(): void
    {
        foreach ($this->views as $slug => $view) {
            DB::table('roles')->where('slug', $slug)->where('dashboard_view', 'dashboards.portal')->update(['dashboard_view' => $view]);
        }
    }

    public function down(): void
    {
        foreach ($this->views as $slug => $view) {
            DB::table('roles')->where('slug', $slug)->where('dashboard_view', $view)->update(['dashboard_view' => 'dashboards.portal']);
        }
    }
};

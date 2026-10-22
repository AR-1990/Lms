<?php

use App\Models\AcademicSession;
use App\Models\ExamTerm;
use App\Services\PerformanceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('performance:refresh', function (PerformanceService $performanceService) {
    $terms = ExamTerm::whereIn('academic_session_id', AcademicSession::where('is_current', true)->select('id'))->get();

    foreach ($terms as $term) {
        $this->info($term->name.': '.$performanceService->compute($term).' students');
    }
})->purpose('Rebuild performance snapshots and at-risk alerts for the current session');

Schedule::command('performance:refresh')->dailyAt('02:00');

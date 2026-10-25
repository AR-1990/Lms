<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_backups_are_made_listed_downloaded_and_pruned(): void
    {
        $this->seed(DatabaseSeeder::class);
        Storage::fake('local');
        $admin = User::where('email', 'admin@lms.test')->first();

        foreach (range(1, BackupService::KEEP) as $day) {
            Storage::disk('local')->put(sprintf('backups/backup-2026-01-%02d-010000.sql.gz', $day), 'old');
        }

        $file = $this->actingAs($admin, 'sanctum')->postJson('/api/erp/admin/backups')->assertCreated()->assertJsonPath('success', true)->json('data.file');

        $sql = gzdecode(Storage::disk('local')->get('backups/'.$file));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('INSERT INTO `students`', $sql);

        $this->getJson('/api/erp/admin/backups')->assertOk()->assertJsonCount(BackupService::KEEP, 'data.backups')->assertJsonPath('data.backups.0.file', $file);
        Storage::disk('local')->assertMissing('backups/backup-2026-01-01-010000.sql.gz');

        $this->get("/api/erp/admin/backups/{$file}")->assertOk()->assertDownload($file);
        $this->get('/api/erp/admin/backups/backup-2099-01-01-000000.sql.gz')->assertUnprocessable();
        $this->assertDatabaseHas('activity_logs', ['module' => 'backups', 'action' => 'created']);

        $teacher = User::where('email', 'teacher@lms.test')->first();
        $this->actingAs($teacher, 'sanctum')->postJson('/api/erp/admin/backups')->assertForbidden();
        $this->actingAs($teacher, 'sanctum')->get("/api/erp/admin/backups/{$file}")->assertForbidden();
    }
}

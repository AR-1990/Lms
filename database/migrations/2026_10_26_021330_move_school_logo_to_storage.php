<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * The school logo moves out of the database into file storage (rule 8): an uploaded logo is written
 * to branding/ on the local disk and only its path stays in system_settings.
 */
return new class extends Migration
{
    private const EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('currency_id');
        });

        foreach (DB::table('system_settings')->whereNotNull('logo_binary')->get(['id', 'logo_binary', 'logo_mime_type']) as $row) {
            $path = 'branding/logo-'.$row->id.'.'.(self::EXTENSIONS[$row->logo_mime_type] ?? 'png');
            Storage::disk('local')->put($path, $row->logo_binary);
            DB::table('system_settings')->where('id', $row->id)->update(['logo_path' => $path]);
        }

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_binary', 'logo_mime_type', 'logo_file_name']);
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->binary('logo_binary')->nullable();
            $table->string('logo_mime_type')->nullable();
            $table->string('logo_file_name')->nullable();
        });

        foreach (DB::table('system_settings')->whereNotNull('logo_path')->get(['id', 'logo_path']) as $row) {
            if (Storage::disk('local')->exists($row->logo_path)) {
                DB::table('system_settings')->where('id', $row->id)->update([
                    'logo_binary' => Storage::disk('local')->get($row->logo_path),
                    'logo_mime_type' => Storage::disk('local')->mimeType($row->logo_path),
                ]);
            }
        }

        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};

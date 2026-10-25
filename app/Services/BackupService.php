<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Nightly database backup written in PHP (no mysqldump or shell access needed on shared hosting): every table's
 * structure and rows as one gzipped SQL file on the private disk, keeping the newest KEEP files.
 */
class BackupService
{
    public const DIRECTORY = 'backups';

    public const KEEP = 14;

    public function __construct(private ActivityLogger $activityLogger) {}

    /**
     * @return array{file: string, size: int, tables: int}
     */
    public function run(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $pdo = $connection->getPdo();
        $name = self::DIRECTORY.'/backup-'.now()->format('Y-m-d-His').'.sql.gz';
        $path = Storage::disk('local')->path($name);
        Storage::disk('local')->makeDirectory(self::DIRECTORY);

        $gz = gzopen($path, 'wb6');
        gzwrite($gz, '-- '.config('app.name').' backup '.now()->toDateTimeString()." ({$driver})\n");
        $driver === 'mysql' && gzwrite($gz, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n");

        $tables = $this->tables($driver);

        foreach ($tables as $table) {
            gzwrite($gz, "\n-- {$table}\nDROP TABLE IF EXISTS `{$table}`;\n".$this->createStatement($driver, $table).";\n");

            foreach (DB::table($table)->cursor() as $row) {
                $values = array_map(fn ($value): string => $value === null ? 'NULL' : $pdo->quote((string) $value), (array) $row);
                gzwrite($gz, "INSERT INTO `{$table}` (`".implode('`, `', array_keys((array) $row)).'`) VALUES ('.implode(', ', $values).");\n");
            }
        }

        $driver === 'mysql' && gzwrite($gz, "SET FOREIGN_KEY_CHECKS=1;\n");
        gzclose($gz);

        $this->prune();
        $size = Storage::disk('local')->size($name);
        $this->activityLogger->record('backups', 'created', 'Database backup '.basename($name).' ('.round($size / 1048576, 2).' MB, '.count($tables).' tables)');

        return ['file' => basename($name), 'size' => $size, 'tables' => count($tables)];
    }

    /**
     * @return Collection<int, array{file: string, size: int, created_at: string}>
     */
    public function list(): Collection
    {
        return collect(Storage::disk('local')->files(self::DIRECTORY))
            ->filter(fn (string $file): bool => str_ends_with($file, '.sql.gz'))
            ->map(fn (string $file): array => ['file' => basename($file), 'size' => Storage::disk('local')->size($file), 'created_at' => date('c', Storage::disk('local')->lastModified($file))])
            ->sortByDesc('file')
            ->values();
    }

    public function download(string $file): StreamedResponse
    {
        $path = self::DIRECTORY.'/'.basename($file);

        if (! preg_match('/^backup-[0-9-]+\.sql\.gz$/', basename($file)) || ! Storage::disk('local')->exists($path)) {
            throw ValidationException::withMessages(['file' => ['This backup does not exist.']]);
        }

        $this->activityLogger->record('backups', 'exported', 'Downloaded database backup '.basename($file));

        return Storage::disk('local')->download($path);
    }

    private function prune(): void
    {
        $this->list()->slice(self::KEEP)->each(fn (array $backup) => Storage::disk('local')->delete(self::DIRECTORY.'/'.$backup['file']));
    }

    /**
     * @return array<int, string>
     */
    private function tables(string $driver): array
    {
        return match ($driver) {
            'sqlite' => collect(DB::select("select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"))->pluck('name')->all(),
            default => collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))->map(fn ($row): string => (string) array_values((array) $row)[0])->all(),
        };
    }

    private function createStatement(string $driver, string $table): string
    {
        return match ($driver) {
            'sqlite' => (string) DB::selectOne('select sql from sqlite_master where type = ? and name = ?', ['table', $table])->sql,
            default => (string) array_values((array) DB::selectOne("SHOW CREATE TABLE `{$table}`"))[1],
        };
    }
}

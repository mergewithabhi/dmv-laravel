<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

#[Signature('cms:backup {--no-uploads : Back up only the database}')]
#[Description('Back up the CMS database and managed uploads to the configured backup disk')]
class BackupCms extends Command
{
    public function handle(): int
    {
        $diskName = (string) config('cms.backup.disk', 'local');
        $root = trim((string) config('cms.backup.path', 'backups'), '/');
        $stamp = now()->format('Y-m-d_His');
        $prefix = "{$root}/{$stamp}";
        $disk = Storage::disk($diskName);

        try {
            $databaseFile = $this->backupDatabase($disk, $prefix);
            $uploadCount = $this->option('no-uploads')
                ? 0
                : $this->backupUploads($disk, $prefix);

            $disk->put($prefix.'/manifest.json', json_encode([
                'application' => config('app.name'),
                'created_at' => now()->toIso8601String(),
                'environment' => app()->environment(),
                'database_connection' => config('database.default'),
                'database_file' => $databaseFile,
                'upload_count' => $uploadCount,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $removed = $this->purgeExpiredBackups($disk, $root);
            $this->info("Backup written to [{$diskName}:{$prefix}] with {$uploadCount} upload(s).");
            $this->line("Removed {$removed} expired backup(s).");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $disk->deleteDirectory($prefix);
            report($exception);
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function backupDatabase($disk, string $prefix): string
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}", []);

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException("Backup does not support the [{$connection}] database connection.");
        }

        $binary = (string) config('cms.backup.mysqldump_binary', 'mysqldump');
        $xamppBinary = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
        if (
            PHP_OS_FAMILY === 'Windows'
            && strtolower($binary) === 'mysqldump'
            && is_file($xamppBinary)
        ) {
            $binary = $xamppBinary;
        }

        $command = [
            $binary,
            '--single-transaction',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--host='.(string) ($config['host'] ?? '127.0.0.1'),
            '--port='.(string) ($config['port'] ?? 3306),
            '--user='.(string) ($config['username'] ?? ''),
            '--default-character-set=utf8mb4',
            (string) ($config['database'] ?? ''),
        ];
        $process = new Process($command, base_path(), [
            'MYSQL_PWD' => (string) ($config['password'] ?? ''),
        ]);
        $process->setTimeout(300)->mustRun();
        $target = $prefix.'/database.sql.gz';
        $disk->put($target, gzencode($process->getOutput(), 9));

        return 'database.sql.gz';
    }

    private function backupUploads($backupDisk, string $prefix): int
    {
        $sourceDisk = Storage::disk((string) config('media-library.disk_name', 'public'));
        $files = $sourceDisk->allFiles();

        foreach ($files as $file) {
            $stream = $sourceDisk->readStream($file);
            if (! is_resource($stream)) {
                throw new RuntimeException("Could not read managed upload [{$file}].");
            }

            $backupDisk->put($prefix.'/uploads/'.$file, $stream);
            fclose($stream);
        }

        return count($files);
    }

    private function purgeExpiredBackups($disk, string $root): int
    {
        $cutoff = now()->subDays((int) config('cms.backup.retention_days', 30));
        $removed = 0;

        foreach ($disk->directories($root) as $directory) {
            try {
                $date = CarbonImmutable::createFromFormat('Y-m-d_His', basename($directory));
            } catch (Throwable) {
                continue;
            }
            if ($date && $date->lt($cutoff)) {
                $disk->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }
}

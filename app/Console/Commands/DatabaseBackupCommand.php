<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup {--local-only : Store backup locally without uploading}';

    protected $description = 'Create a PostgreSQL backup and upload to Backblaze B2';

    public function handle(): int
    {
        $filename = 'backup-'.now()->format('Y-m-d_His').'.dump';
        $localPath = storage_path('app/backups/'.$filename);

        if (! is_dir(dirname($localPath))) {
            mkdir(dirname($localPath), 0755, true);
        }

        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port');
        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $command = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -Fc %s -f %s',
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($localPath),
        );

        $this->info('Running pg_dump...');

        $result = null;
        $output = [];
        exec($command, $output, $result);

        if ($result !== 0) {
            $this->error('pg_dump failed.');

            return self::FAILURE;
        }

        $this->info("Backup created: {$localPath}");

        if ($this->option('local-only')) {
            return self::SUCCESS;
        }

        $disk = config('backup.disk', 'b2');

        if (! config("filesystems.disks.{$disk}")) {
            $this->warn('Backup disk not configured; keeping local copy only.');

            return self::SUCCESS;
        }

        try {
            $remotePath = 'backups/'.$filename;
            Storage::disk($disk)->put($remotePath, fopen($localPath, 'r'));

            $this->info("Uploaded to {$disk}:{$remotePath}");

            $this->pruneOldBackups($disk);
        } catch (\Throwable $e) {
            Log::error('Backup upload failed', ['error' => $e->getMessage()]);
            $this->error('Upload failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function pruneOldBackups(string $disk): void
    {
        $retentionDays = (int) config('backup.retention_days', 30);
        $files = collect(Storage::disk($disk)->files('backups'))
            ->filter(fn (string $path): bool => str_ends_with($path, '.dump'));

        foreach ($files as $path) {
            $lastModified = Storage::disk($disk)->lastModified($path);

            if ($lastModified < now()->subDays($retentionDays)->timestamp) {
                Storage::disk($disk)->delete($path);
                $this->line("Deleted old backup: {$path}");
            }
        }
    }
}

<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupMongoDBCommand extends Command
{
    /** @var string */
    protected $signature = 'backup:mongodb
        {--cleanup : Remover backups antigos após o dump}
        {--keep-days=7 : Número de dias para manter backups}';

    /** @var string */
    protected $description = 'Backup do MongoDB via mongodump com upload para DigitalOcean Spaces';

    public function handle(): int
    {
        $now = Carbon::now('America/Sao_Paulo');
        $day = $now->format('d');
        $month = $now->format('m');
        $year = $now->format('Y');
        $filename = "{$day}-{$month}-{$year}-maisvendas.gz";
        $remotePath = "mongodb/{$day}/{$month}/{$year}/{$filename}";

        $tempDir = '/tmp/mongodb-backup';
        $tempFile = "{$tempDir}/{$filename}";

        $this->info("Iniciando backup do MongoDB: {$remotePath}");

        try {
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $uri = (string) config('database.connections.mongodb.dsn');

            if (empty($uri)) {
                $this->error('MongoDB URI não configurada.');
                Log::error('Backup MongoDB falhou: URI não configurada');

                return self::FAILURE;
            }

            $result = Process::timeout(300)->run([
                'mongodump',
                "--uri={$uri}",
                '--archive='.$tempFile,
                '--gzip',
            ]);

            if ($result->failed()) {
                $this->error('mongodump falhou: '.$result->errorOutput());
                Log::error('Backup MongoDB falhou', ['error' => $result->errorOutput()]);

                return self::FAILURE;
            }

            $disk = Storage::disk('backups');
            $disk->put($remotePath, (string) file_get_contents($tempFile));

            $size = round(filesize($tempFile) / 1024 / 1024, 2);

            $this->info("Backup enviado com sucesso: {$remotePath} ({$size} MB)");
            Log::info('Backup MongoDB concluído', [
                'path' => $remotePath,
                'size_mb' => $size,
            ]);

            if ($this->option('cleanup')) {
                $this->cleanup((int) $this->option('keep-days'));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro no backup do MongoDB: '.$e->getMessage());
            Log::error('Backup MongoDB falhou com exceção', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (is_dir($tempDir) && count((array) scandir($tempDir)) <= 2) {
                rmdir($tempDir);
            }
        }
    }

    private function cleanup(int $keepDays): void
    {
        $this->info("Removendo backups com mais de {$keepDays} dias...");

        $disk = Storage::disk('backups');
        $cutoff = Carbon::now('America/Sao_Paulo')->subDays($keepDays);
        $removed = 0;

        /** @var array<int, string> $files */
        $files = $disk->allFiles('mongodb');

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));

            if ($lastModified->isBefore($cutoff)) {
                $disk->delete($file);
                $removed++;
            }
        }

        $this->info("Removidos {$removed} backups antigos.");
        Log::info('Cleanup de backups MongoDB concluído', ['removed' => $removed]);
    }
}

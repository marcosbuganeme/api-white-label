<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BackupPostgreSQLCommand extends Command
{
    /** @var string */
    protected $signature = 'backup:pgsql
        {--cleanup : Remover backups antigos após o dump}
        {--keep-days=7 : Número de dias para manter backups}';

    /** @var string */
    protected $description = 'Backup do PostgreSQL via pg_dump com upload para DigitalOcean Spaces';

    public function handle(): int
    {
        $now = Carbon::now('America/Sao_Paulo');
        $day = $now->format('d');
        $month = $now->format('m');
        $year = $now->format('Y');
        $filename = "{$day}-{$month}-{$year}-maisvendas.sql";
        $remotePath = "postgresql/{$day}/{$month}/{$year}/{$filename}";

        $tempDir = sys_get_temp_dir().'/pgsql-backup-'.bin2hex(random_bytes(4));
        $tempFile = "{$tempDir}/{$filename}";

        $this->info("Iniciando backup do PostgreSQL: {$remotePath}");

        try {
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $host = (string) config('database.connections.pgsql.host', '127.0.0.1');
            $port = (string) config('database.connections.pgsql.port', '5432');
            $database = (string) config('database.connections.pgsql.database', 'maisvendas');
            $username = (string) config('database.connections.pgsql.username', 'postgres');
            $password = (string) config('database.connections.pgsql.password', '');

            if (empty($database)) {
                $this->error('Database não configurado.');
                Log::error('Backup PostgreSQL falhou: database não configurado');

                return self::FAILURE;
            }

            $pgpassFile = tempnam(sys_get_temp_dir(), 'pgpass_');
            if ($pgpassFile === false) {
                $this->error('Falha ao criar arquivo .pgpass temporário.');

                return self::FAILURE;
            }
            chmod($pgpassFile, 0600);
            file_put_contents($pgpassFile, "{$host}:{$port}:{$database}:{$username}:{$password}\n");

            try {
                $result = Process::timeout(300)
                    ->env(['PGPASSFILE' => $pgpassFile])
                    ->run([
                        'pg_dump',
                        '-h', $host,
                        '-p', $port,
                        '-U', $username,
                        '--no-password',
                        '--format=c',
                        '--compress=6',
                        '-f', $tempFile,
                        $database,
                    ]);
            } finally {
                if (file_exists($pgpassFile)) {
                    unlink($pgpassFile);
                }
            }

            if ($result->failed()) {
                $this->error('pg_dump falhou: '.$result->errorOutput());
                Log::error('Backup PostgreSQL falhou', ['error' => $result->errorOutput()]);

                return self::FAILURE;
            }

            $disk = Storage::disk('backups');
            $stream = fopen($tempFile, 'r');

            if ($stream === false) {
                $this->error('Falha ao abrir arquivo temporário para upload.');
                Log::error('Backup PostgreSQL falhou: não foi possível abrir o arquivo temp');

                return self::FAILURE;
            }

            try {
                $disk->put($remotePath, $stream);
            } finally {
                fclose($stream);
            }

            $rawSize = filesize($tempFile);
            $size = $rawSize !== false ? round($rawSize / 1024 / 1024, 2) : 0.0;

            $this->info("Backup enviado com sucesso: {$remotePath} ({$size} MB)");
            Log::info('Backup PostgreSQL concluído', [
                'path' => $remotePath,
                'size_mb' => $size,
            ]);

            if ($this->option('cleanup')) {
                $this->cleanup((int) $this->option('keep-days'));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Erro no backup do PostgreSQL: '.$e->getMessage());
            Log::error('Backup PostgreSQL falhou com exceção', [
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
        if ($keepDays < 1) {
            $this->error('--keep-days deve ser no mínimo 1.');
            Log::error('Backup cleanup abortado: keep-days deve ser >= 1', ['keep_days' => $keepDays]);

            return;
        }

        $this->info("Removendo backups com mais de {$keepDays} dias...");

        $disk = Storage::disk('backups');
        $cutoff = Carbon::now('America/Sao_Paulo')->subDays($keepDays);
        $removed = 0;

        /** @var array<int, string> $files */
        $files = $disk->allFiles('postgresql');

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));

            if ($lastModified->isBefore($cutoff)) {
                $disk->delete($file);
                $removed++;
            }
        }

        $this->info("Removidos {$removed} backups antigos.");
        Log::info('Cleanup de backups PostgreSQL concluído', ['removed' => $removed]);
    }
}

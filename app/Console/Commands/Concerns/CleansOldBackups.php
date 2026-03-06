<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait CleansOldBackups
{
    private function cleanupOldBackups(string $prefix, int $keepDays): void
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
        $files = $disk->allFiles($prefix);

        foreach ($files as $file) {
            $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));

            if ($lastModified->isBefore($cutoff)) {
                $disk->delete($file);
                $removed++;
            }
        }

        $this->info("Removidos {$removed} backups antigos.");
        Log::info("Cleanup de backups {$prefix} concluído", ['removed' => $removed]);
    }
}

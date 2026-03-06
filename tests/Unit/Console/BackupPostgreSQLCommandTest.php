<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class BackupPostgreSQLCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config([
            'database.connections.pgsql.host' => '127.0.0.1',
            'database.connections.pgsql.port' => '5432',
            'database.connections.pgsql.database' => 'maisvendas',
            'database.connections.pgsql.username' => 'postgres',
            'database.connections.pgsql.password' => 'secret',
        ]);
    }

    /**
     * Fake Process that creates the dump file pg_dump would produce.
     */
    private function fakeSuccessfulPgDump(): void
    {
        Process::fake(function (PendingProcess $process) {
            // @phpstan-ignore foreach.nonIterable
            foreach ($process->command as $i => $arg) {
                if ($arg === '-f' && isset($process->command[$i + 1])) {
                    $path = (string) $process->command[$i + 1];
                    $dir = dirname($path);
                    if (! is_dir($dir)) {
                        mkdir($dir, 0755, true);
                    }
                    file_put_contents($path, 'fake-backup-data');
                }
            }

            return Process::result(output: 'done');
        });
    }

    public function test_successful_backup_uploads_to_storage(): void
    {
        $this->fakeSuccessfulPgDump();

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:pgsql');
        $command->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Backup enviado com sucesso');
    }

    public function test_fails_when_database_is_empty(): void
    {
        config(['database.connections.pgsql.database' => '']);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:pgsql');
        $command->expectsOutputToContain('não configurado')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fails_when_pg_dump_process_fails(): void
    {
        Process::fake(function () {
            return Process::result(exitCode: 1, errorOutput: 'pg_dump: error');
        });

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:pgsql');
        $command->expectsOutputToContain('pg_dump falhou')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_cleanup_removes_old_files(): void
    {
        $this->fakeSuccessfulPgDump();

        Storage::disk('backups')->put('postgresql/01/01/2020/01-01-2020-maisvendas.sql', 'old-backup');

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:pgsql', ['--cleanup' => true, '--keep-days' => 1]);
        $command->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Removidos');
    }

    public function test_cleanup_validates_keep_days_minimum(): void
    {
        $this->fakeSuccessfulPgDump();

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:pgsql', ['--cleanup' => true, '--keep-days' => 0]);
        $command->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('mínimo 1');
    }

    public function test_cleanup_actually_deletes_old_files(): void
    {
        $disk = Storage::disk('backups');
        $disk->put('postgresql/01/01/2020/01-01-2020-maisvendas.sql', 'old-backup');

        $this->travelTo(now()->addDays(30));

        $cmd = new \App\Console\Commands\BackupPostgreSQLCommand;
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $cmd->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $method = new \ReflectionMethod($cmd, 'cleanup');
        $method->invoke($cmd, 7);

        $this->assertStringContainsString('Removidos 1 backups antigos', $output->fetch());
        $this->assertEmpty($disk->allFiles('postgresql'));
    }

    public function test_handles_exception_gracefully(): void
    {
        $this->fakeSuccessfulPgDump();

        Storage::shouldReceive('disk')
            ->with('backups')
            ->andThrow(new \RuntimeException('Disk unavailable'));

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:pgsql');
        $command->expectsOutputToContain('Erro no backup do PostgreSQL')
            ->assertExitCode(Command::FAILURE);
    }
}

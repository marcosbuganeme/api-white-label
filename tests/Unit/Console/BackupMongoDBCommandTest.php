<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class BackupMongoDBCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('backups');
        config(['database.connections.mongodb.dsn' => 'mongodb://localhost:27017/test']);
    }

    /**
     * Fake Process that creates the archive file mongodump would produce.
     */
    private function fakeSuccessfulMongodump(): void
    {
        Process::fake(function (PendingProcess $process) {
            // @phpstan-ignore foreach.nonIterable
            foreach ($process->command as $arg) {
                if (str_starts_with((string) $arg, '--archive=')) {
                    $path = substr($arg, 10);
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
        $this->fakeSuccessfulMongodump();

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:mongodb');
        $command->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Backup enviado com sucesso');
    }

    public function test_fails_when_uri_is_empty(): void
    {
        config(['database.connections.mongodb.dsn' => '']);

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:mongodb');
        $command->expectsOutputToContain('MongoDB URI')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_fails_when_mongodump_process_fails(): void
    {
        Process::fake(function () {
            return Process::result(exitCode: 1, errorOutput: 'mongodump: command not found');
        });

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:mongodb');
        $command->expectsOutputToContain('mongodump falhou')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_cleanup_removes_old_files(): void
    {
        $this->fakeSuccessfulMongodump();

        Storage::disk('backups')->put('mongodb/01/01/2020/01-01-2020-maisvendas.gz', 'old-backup');

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:mongodb', ['--cleanup' => true, '--keep-days' => 1]);
        $command->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('Removidos');
    }

    public function test_cleanup_validates_keep_days_minimum(): void
    {
        $this->fakeSuccessfulMongodump();

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:mongodb', ['--cleanup' => true, '--keep-days' => 0]);
        $command->assertExitCode(Command::SUCCESS)
            ->expectsOutputToContain('mínimo 1');
    }

    public function test_cleanup_actually_deletes_old_files(): void
    {
        $disk = Storage::disk('backups');
        $disk->put('mongodb/01/01/2020/01-01-2020-maisvendas.gz', 'old-backup');

        $this->travelTo(now()->addDays(30));

        $cmd = new \App\Console\Commands\BackupMongoDBCommand;
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $cmd->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $method = new \ReflectionMethod($cmd, 'cleanup');
        $method->invoke($cmd, 7);

        $this->assertStringContainsString('Removidos 1 backups antigos', $output->fetch());
        $this->assertEmpty($disk->allFiles('mongodb'));
    }

    public function test_handles_exception_gracefully(): void
    {
        $this->fakeSuccessfulMongodump();

        Storage::shouldReceive('disk')
            ->with('backups')
            ->andThrow(new \RuntimeException('Disk unavailable'));

        /** @var \Illuminate\Testing\PendingCommand $command */
        $command = $this->artisan('backup:mongodb');
        $command->expectsOutputToContain('Erro no backup do MongoDB')
            ->assertExitCode(Command::FAILURE);
    }
}

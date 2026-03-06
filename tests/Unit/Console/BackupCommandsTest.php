<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    public function test_mongodb_command_is_registered(): void
    {
        $commands = \Illuminate\Support\Facades\Artisan::all();
        $this->assertTrue(isset($commands['backup:mongodb']), 'backup:mongodb command should be registered');
    }

    public function test_mongodb_command_has_expected_options(): void
    {
        $command = \Illuminate\Support\Facades\Artisan::all()['backup:mongodb'];
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('cleanup'), 'Should have --cleanup option');
        $this->assertTrue($definition->hasOption('keep-days'), 'Should have --keep-days option');
        $this->assertSame('7', $definition->getOption('keep-days')->getDefault(), '--keep-days should default to 7');
    }

    public function test_pgsql_command_is_registered(): void
    {
        $commands = \Illuminate\Support\Facades\Artisan::all();
        $this->assertTrue(isset($commands['backup:pgsql']), 'backup:pgsql command should be registered');
    }

    public function test_pgsql_command_has_expected_options(): void
    {
        $command = \Illuminate\Support\Facades\Artisan::all()['backup:pgsql'];
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('cleanup'), 'Should have --cleanup option');
        $this->assertTrue($definition->hasOption('keep-days'), 'Should have --keep-days option');
        $this->assertSame('7', $definition->getOption('keep-days')->getDefault(), '--keep-days should default to 7');
    }

    public function test_mongodb_backup_fails_when_uri_is_empty(): void
    {
        config(['database.connections.mongodb.dsn' => '']);

        $this->artisan('backup:mongodb')
            ->expectsOutputToContain('MongoDB URI')
            ->assertExitCode(\Symfony\Component\Console\Command\Command::FAILURE);
    }

    public function test_pgsql_backup_fails_when_database_is_empty(): void
    {
        config(['database.connections.pgsql.database' => '']);

        $this->artisan('backup:pgsql')
            ->expectsOutputToContain('não configurado')
            ->assertExitCode(\Symfony\Component\Console\Command\Command::FAILURE);
    }

    public function test_mongodb_cleanup_validates_keep_days(): void
    {
        $command = new \App\Console\Commands\BackupMongoDBCommand;

        $method = new \ReflectionMethod($command, 'cleanup');
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $method->invoke($command, 0);

        $this->assertStringContainsString('mínimo 1', $output->fetch());
    }

    public function test_pgsql_cleanup_validates_keep_days(): void
    {
        $command = new \App\Console\Commands\BackupPostgreSQLCommand;

        $method = new \ReflectionMethod($command, 'cleanup');
        $output = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle(
            new \Symfony\Component\Console\Input\ArrayInput([]),
            $output,
        ));

        $method->invoke($command, 0);

        $this->assertStringContainsString('mínimo 1', $output->fetch());
    }
}

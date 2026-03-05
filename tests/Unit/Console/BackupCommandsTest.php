<?php

namespace Tests\Unit\Console;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackupCommandsTest extends TestCase
{
    public function test_mongodb_command_is_registered(): void
    {
        $commands = collect(Artisan::all());

        $this->assertTrue($commands->has('backup:mongodb'));
    }

    public function test_mongodb_command_has_expected_options(): void
    {
        $command = Artisan::all()['backup:mongodb'];
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('cleanup'));
        $this->assertTrue($definition->hasOption('keep-days'));
        $this->assertSame('7', $definition->getOption('keep-days')->getDefault());
    }

    public function test_pgsql_command_is_registered(): void
    {
        $commands = collect(Artisan::all());

        $this->assertTrue($commands->has('backup:pgsql'));
    }

    public function test_pgsql_command_has_expected_options(): void
    {
        $command = Artisan::all()['backup:pgsql'];
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('cleanup'));
        $this->assertTrue($definition->hasOption('keep-days'));
        $this->assertSame('7', $definition->getOption('keep-days')->getDefault());
    }
}

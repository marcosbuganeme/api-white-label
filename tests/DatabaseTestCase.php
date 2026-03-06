<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

abstract class DatabaseTestCase extends TestCase
{
    use LazilyRefreshDatabase;
}

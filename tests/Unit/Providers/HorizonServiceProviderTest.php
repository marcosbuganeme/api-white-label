<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonServiceProviderTest extends TestCase
{
    public function test_horizon_gate_denies_null_user(): void
    {
        $result = Gate::check('viewHorizon');

        $this->assertFalse($result, 'Horizon gate should deny when no user is authenticated');
    }

    public function test_horizon_gate_denies_user_without_allowed_email(): void
    {
        config(['horizon.allowed_emails' => 'admin@example.com']);

        $user = new \App\Models\User;
        $user->email = 'other@example.com';

        $result = Gate::forUser($user)->check('viewHorizon');

        $this->assertFalse($result, 'Horizon gate should deny users not in allowed_emails');
    }

    public function test_horizon_gate_allows_user_with_allowed_email(): void
    {
        config(['horizon.allowed_emails' => 'admin@example.com,other@example.com']);

        $user = new \App\Models\User;
        $user->email = 'admin@example.com';

        $result = Gate::forUser($user)->check('viewHorizon');

        $this->assertTrue($result, 'Horizon gate should allow users in allowed_emails');
    }

    public function test_horizon_gate_denies_when_allowed_emails_is_empty(): void
    {
        config(['horizon.allowed_emails' => '']);

        $user = new \App\Models\User;
        $user->email = 'admin@example.com';

        $result = Gate::forUser($user)->check('viewHorizon');

        $this->assertFalse($result, 'Horizon gate should deny when allowed_emails is empty');
    }
}

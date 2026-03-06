<?php

namespace Tests\Feature\Storage;

use Tests\TestCase;

class SpacesConnectionTest extends TestCase
{
    public function test_spaces_disk_is_configured(): void
    {
        $config = config('filesystems.disks.spaces');

        $this->assertIsArray($config);
        $this->assertSame('s3', $config['driver']);
        $this->assertSame('api/assets', $config['root']);
        $this->assertSame('public', $config['visibility']);
        $this->assertTrue($config['throw']);
        $this->assertFalse($config['use_path_style_endpoint']);
    }

    public function test_backups_disk_is_configured(): void
    {
        $config = config('filesystems.disks.backups');

        $this->assertIsArray($config);
        $this->assertSame('s3', $config['driver']);
        $this->assertSame('infra', $config['root']);
        $this->assertSame('private', $config['visibility']);
        $this->assertTrue($config['throw']);
        $this->assertFalse($config['use_path_style_endpoint']);
    }

    public function test_spaces_and_backups_share_same_bucket(): void
    {
        $this->assertSame(
            config('filesystems.disks.spaces.bucket'),
            config('filesystems.disks.backups.bucket'),
            'Spaces and backups should share the same bucket'
        );
    }

    public function test_spaces_disk_has_cdn_url(): void
    {
        $url = config('filesystems.disks.spaces.url');

        $this->assertNotNull($url);
        $this->assertStringContainsString('cdn.digitaloceanspaces.com', (string) $url);
    }

    public function test_backups_disk_has_no_cdn_url(): void
    {
        $this->assertNull(config('filesystems.disks.backups.url'));
    }
}

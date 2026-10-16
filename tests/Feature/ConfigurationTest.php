<?php

namespace Tests\Feature;

use Illuminate\Support\Env;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_configurations_match_environment_defaults(): void
    {
        $this->assertEquals('log', config('broadcasting.default'));
        $this->assertEquals('array', config('cache.default'));
        $this->assertEquals('array', config('mail.default'));
        $this->assertEquals('sync', config('queue.default'));
    }

    public function test_database_routes_to_dedicated_testing_connection(): void
    {
        // PHPUnit pins the default connection to the dedicated `testing`
        // connection so the suite can never touch the primary dev database.
        $this->assertEquals('testing', config('database.default'));
        $this->assertEquals('testing', config('queue.failed.database'));
    }

    public function test_backup_notifications_are_disabled_without_an_explicit_valid_recipient(): void
    {
        $this->assertSame([], config('backup.notifications.notifications'));
        $this->assertSame('backup-notifications-disabled@example.invalid', config('backup.notifications.mail.to'));
    }

    public function test_backup_database_source_matches_the_configured_connection(): void
    {
        $this->assertSame([env('DB_CONNECTION', 'mysql')], config('backup.backup.source.databases'));
    }

    public function test_backup_mail_notifications_require_a_valid_explicit_recipient(): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('BACKUP_NOTIFICATION_EMAIL');

        try {
            $repository->set('BACKUP_NOTIFICATION_EMAIL', 'backup-ops@example.com');
            $backupConfig = require config_path('backup.php');

            $this->assertSame('backup-ops@example.com', $backupConfig['notifications']['mail']['to']);
            $this->assertSame(['mail'], $backupConfig['notifications']['notifications'][
                BackupHasFailedNotification::class
            ]);
        } finally {
            if ($previous === null) {
                $repository->clear('BACKUP_NOTIFICATION_EMAIL');
            } else {
                $repository->set('BACKUP_NOTIFICATION_EMAIL', $previous);
            }
        }
    }
}

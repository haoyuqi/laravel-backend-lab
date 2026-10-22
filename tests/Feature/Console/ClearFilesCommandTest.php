<?php

namespace Tests\Feature\Console;

use App\Console\Commands\ClearFilesCommand;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ClearFilesCommandTest extends TestCase
{
    public function test_it_deletes_only_files_older_than_the_configured_retention_period(): void
    {
        $directory = storage_path('framework/testing/clear-files');
        File::ensureDirectoryExists($directory);
        $expired = $directory.'/expired.log';
        $recent = $directory.'/recent.log';
        $gitignore = $directory.'/.gitignore';
        File::put($expired, 'expired');
        File::put($recent, 'recent');
        File::put($gitignore, '*');
        touch($expired, time() - 7200);
        touch($gitignore, time() - 7200);

        try {
            $command = new ClearFilesCommand;
            (new \ReflectionProperty(ClearFilesCommand::class, 'paths'))
                ->setValue($command, [['path' => $directory, 'expires' => 3600]]);

            $command->handle();

            $this->assertFileDoesNotExist($expired);
            $this->assertFileExists($recent);
            $this->assertFileExists($gitignore);
        } finally {
            File::deleteDirectory($directory);
        }
    }
}

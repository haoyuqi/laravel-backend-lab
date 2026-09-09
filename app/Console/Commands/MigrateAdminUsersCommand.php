<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateAdminUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:migrate-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate legacy admin_users accounts to the users table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Schema::hasTable('admin_users')) {
            $this->info("Table 'admin_users' does not exist. Nothing to migrate.");

            return self::SUCCESS;
        }

        $total = DB::table('admin_users')->count();

        if ($total === 0) {
            $this->info("No legacy admin accounts found in 'admin_users'. Nothing to migrate.");

            return self::SUCCESS;
        }

        if (! $this->input->isInteractive()) {
            $this->error('This command requires interactive user input to assign email addresses.');

            return self::FAILURE;
        }

        $this->info("Found {$total} legacy admin account(s) in 'admin_users'.");

        $legacyUsers = DB::table('admin_users')->orderBy('id')->get();

        foreach ($legacyUsers as $legacyUser) {
            $this->line("--- [ID: {$legacyUser->id}] Username: {$legacyUser->username} | Name: {$legacyUser->name} ---");

            while (true) {
                $action = $this->ask("Enter a valid email for '{$legacyUser->username}', [s] to skip, or [d] to discard/delete");

                if ($action === null || trim($action) === '') {
                    $this->warn('Input cannot be empty. Please enter an email, "s" to skip, or "d" to discard.');

                    continue;
                }

                $trimmedAction = trim($action);
                $lowerAction = strtolower($trimmedAction);

                if ($lowerAction === 's') {
                    $this->line("Skipped account '{$legacyUser->username}'.");
                    break;
                }

                if ($lowerAction === 'd') {
                    if ($this->confirm("Are you sure you want to permanently discard legacy account '{$legacyUser->username}'?", true)) {
                        DB::table('admin_users')->where('id', $legacyUser->id)->delete();
                        $this->info("Discarded legacy account '{$legacyUser->username}'.");
                        break;
                    }

                    continue;
                }

                $email = $trimmedAction;

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $this->error("Invalid email address: '{$email}'.");

                    continue;
                }

                $existingUser = DB::table('users')->where('email', $email)->first();

                if ($existingUser) {
                    $choice = $this->choice(
                        "A user with email '{$email}' already exists (Name: {$existingUser->name}). What would you like to do?",
                        ['Overwrite existing user', 'Skip this account', 'Enter another email'],
                        2
                    );

                    if ($choice === 'Overwrite existing user') {
                        if (! $this->confirm("WARNING: This will overwrite the password and name for existing user '{$existingUser->name}' <{$email}>. Are you sure you want to proceed?", false)) {
                            $this->line('Overwrite cancelled.');

                            continue;
                        }
                    }

                    if ($choice === 'Skip this account') {
                        $this->line("Skipped account '{$legacyUser->username}'.");
                        break;
                    }

                    if ($choice === 'Enter another email') {
                        continue;
                    }
                }

                DB::transaction(function () use ($legacyUser, $email, $existingUser) {
                    DB::table('users')->updateOrInsert(
                        ['email' => $email],
                        [
                            'name' => $legacyUser->name,
                            'password' => $legacyUser->password,
                            'remember_token' => $legacyUser->remember_token,
                            'email_verified_at' => now(),
                            'created_at' => $existingUser ? $existingUser->created_at : ($legacyUser->created_at ?? now()),
                            'updated_at' => now(),
                        ]
                    );

                    DB::table('admin_users')->where('id', $legacyUser->id)->delete();
                });

                $this->info("Successfully migrated '{$legacyUser->username}' to <{$email}>.");
                break;
            }
        }

        $remaining = DB::table('admin_users')->count();

        $this->newLine();

        if ($remaining === 0) {
            $this->info('All legacy admin accounts have been migrated or discarded.');
            $this->info("You may now run 'php artisan migrate' to clean up legacy admin tables.");
        } else {
            $this->warn("{$remaining} legacy account(s) remain in 'admin_users'.");
            $this->warn('Legacy tables will not be dropped until all accounts are migrated or discarded.');
        }

        return self::SUCCESS;
    }
}

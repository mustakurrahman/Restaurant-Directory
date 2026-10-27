<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Creates the owner's sign-in for /admin: php artisan admin:create you@example.com
 * Run it again for the same email to set a new password. The password is typed (hidden), never saved in a file.
 */
class CreateAdmin extends Command
{
    protected $signature = 'admin:create {email : The email address used to sign in} {--name=Owner : Shown nowhere public, only stored}';

    protected $description = 'Create the admin sign-in (or reset its password)';

    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => 'required|email|max:255'])->fails()) {
            $this->error('That does not look like an email address.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (at least 12 characters; nothing shows while you type)');

        if (mb_strlen($password) < 12) {
            $this->error('The password must be at least 12 characters long. Nothing was saved.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Type the password again')) {
            $this->error('The two passwords are different. Nothing was saved.');

            return self::FAILURE;
        }

        // updateOrCreate: running this again for the same email resets the password instead of failing
        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => (string) $this->option('name'), 'password' => $password],
        );

        $this->info($user->wasRecentlyCreated ? "Admin created: {$email}" : "Password changed for {$email}");
        $this->line('Sign in at '.route('admin.login', absolute: true));

        return self::SUCCESS;
    }
}

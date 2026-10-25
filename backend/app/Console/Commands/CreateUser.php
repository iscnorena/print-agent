<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user:create
    {name : Display name, e.g. "Mostrador"}
    {email : Used to log in}')]
#[Description('Create an operator who can log in to the web panel, or reset an existing one\'s password')]
class CreateUser extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error("Not a valid email: {$email}");

            return self::FAILURE;
        }

        $user = User::firstWhere('email', $email);
        if ($user !== null && ! $this->confirm("User {$email} already exists. Change its password?")) {
            return self::FAILURE;
        }

        $password = (string) $this->secret('Password (min. 8 characters)');
        if (strlen($password) < 8) {
            $this->error('The password must have at least 8 characters.');

            return self::FAILURE;
        }
        if ($password !== $this->secret('Repeat the password')) {
            $this->error('The passwords do not match.');

            return self::FAILURE;
        }

        $user ??= new User(['email' => $email]);
        $user->name = $this->argument('name');
        $user->password = $password;
        $user->save();

        $this->info("User {$email} saved. Log in at ".route('login'));

        return self::SUCCESS;
    }
}

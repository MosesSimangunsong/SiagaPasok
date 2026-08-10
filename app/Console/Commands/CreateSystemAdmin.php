<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CreateSystemAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Password intentionally has no CLI option so it does not end up
     * visibly embedded in the command or shell history.
     *
     * @var string
     */
    protected $signature = 'siagapasok:create-admin
                            {--name= : Nama System Admin}
                            {--email= : Email System Admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a SiagaPasok System Admin account';

    public function handle(): int
    {
        $name = trim((string) (
            $this->option('name')
            ?: $this->ask('Nama System Admin')
        ));

        $email = trim((string) (
            $this->option('email')
            ?: $this->ask('Email System Admin')
        ));

        if ($name === '') {
            $this->error('Nama wajib diisi.');

            return self::FAILURE;
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');

            return self::FAILURE;
        }

        if (User::query()->where('email', $email)->exists()) {
            $this->error('Email tersebut sudah digunakan.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Password awal');

        if (mb_strlen($password) < 8) {
            $this->error('Password minimal 8 karakter.');

            return self::FAILURE;
        }

        $passwordConfirmation = (string) $this->secret(
            'Konfirmasi password awal'
        );

        if ($password !== $passwordConfirmation) {
            $this->error('Konfirmasi password tidak sesuai.');

            return self::FAILURE;
        }

        $user = User::create([
            'organization_id' => null,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => UserRole::SYSTEM_ADMIN,
            'is_active' => true,
        ]);

        $this->newLine();

        $this->info('System Admin berhasil dibuat.');
        $this->line("Nama  : {$user->name}");
        $this->line("Email : {$user->email}");

        return self::SUCCESS;
    }
}
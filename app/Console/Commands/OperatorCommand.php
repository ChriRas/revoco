<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotently provision the single operator account from environment variables.
 *
 * Reads OPERATOR_EMAIL and OPERATOR_PASSWORD from the environment.
 * Run once after deploy (or at any time) — safe to call repeatedly.
 * The password env var can be removed from .env after the first run.
 */
final class OperatorCommand extends Command
{
    protected $signature = 'app:operator';

    protected $description = 'Provision or update the operator account from OPERATOR_EMAIL and OPERATOR_PASSWORD.';

    public function handle(): int
    {
        $email = is_string(config('operator.email')) ? config('operator.email') : '';
        $password = is_string(config('operator.password')) ? config('operator.password') : '';

        if ($email === '') {
            $this->error('OPERATOR_EMAIL is not set. Aborting.');

            return self::FAILURE;
        }

        if ($password === '') {
            $this->error('OPERATOR_PASSWORD is not set. Aborting.');

            return self::FAILURE;
        }

        /** @var User $user */
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Operator',
                'password' => Hash::make($password),
                'is_operator' => true,
            ],
        );

        $action = $user->wasRecentlyCreated ? 'created' : 'updated';
        $this->info("Operator account {$action}: {$email}");

        return self::SUCCESS;
    }
}

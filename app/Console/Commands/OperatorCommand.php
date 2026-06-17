<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Idempotently provision the single operator account.
 *
 * Supply credentials via --email / --password options, or let the command
 * prompt interactively when the session is a TTY. In non-interactive mode
 * (CI / automated deploy) with missing credentials the command fails cleanly.
 *
 * Run once after deploy (or at any time) — safe to call repeatedly.
 * An existing operator's name is preserved on update.
 */
final class OperatorCommand extends Command
{
    protected $signature = 'app:operator
                            {--email= : Operator e-mail address}
                            {--password= : Operator password (plain-text; will be hashed)}
                            {--name= : Display name (default: Operator; only applied on create)}';

    protected $description = 'Provision or update the operator account (options or interactive prompt).';

    public function handle(): int
    {
        $email = $this->resolveEmail();
        if ($email === null) {
            return self::FAILURE;
        }

        $password = $this->resolvePassword();
        if ($password === null) {
            return self::FAILURE;
        }

        $errors = $this->validate($email, $password);
        if ($errors !== []) {
            foreach ($errors as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();

        /** @var User $user */
        $user = User::updateOrCreate(
            ['email' => $email],
            array_filter([
                // Preserve the name on update; apply default only on create.
                'name' => $existing === null ? $this->resolveName() : null,
                'password' => Hash::make($password),
            ], fn (mixed $v): bool => $v !== null),
        );

        $action = $user->wasRecentlyCreated ? 'created' : 'updated';
        $this->info("Operator account {$action}: {$email}");

        return self::SUCCESS;
    }

    /** Resolve the display name for a new operator (--name option or default 'Operator'). */
    private function resolveName(): string
    {
        $name = $this->option('name');

        return is_string($name) && $name !== '' ? $name : 'Operator';
    }

    /** Resolve the e-mail from option or interactive prompt. Returns null on failure. */
    private function resolveEmail(): ?string
    {
        $email = $this->option('email');

        if (is_string($email) && $email !== '') {
            return $email;
        }

        if (! $this->input->isInteractive()) {
            $this->error('--email is required in non-interactive mode.');

            return null;
        }

        return text(
            label: 'Operator e-mail address',
            required: true,
        );
    }

    /** Resolve the password from option or interactive prompt. Returns null on failure. */
    private function resolvePassword(): ?string
    {
        $password = $this->option('password');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        if (! $this->input->isInteractive()) {
            $this->error('--password is required in non-interactive mode.');

            return null;
        }

        return password(
            label: 'Operator password',
            required: true,
        );
    }

    /**
     * Validate the resolved email and password.
     *
     * @return list<string>
     */
    private function validate(string $email, string $password): array
    {
        $validator = Validator::make(
            ['email' => $email, 'password' => $password],
            ['email' => ['required', 'email'], 'password' => ['required']],
            [
                // Explicit messages so the CLI shows readable text regardless of the
                // active locale (no validation lang file is published for the backend).
                'email.required' => 'An e-mail address is required.',
                'email.email' => 'A valid e-mail address is required.',
                'password.required' => 'A password is required.',
            ],
        );

        if ($validator->fails()) {
            /** @var list<string> $messages */
            $messages = $validator->errors()->all();

            return $messages;
        }

        return [];
    }
}

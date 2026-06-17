<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WithdrawalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored § 356a withdrawal declaration.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $order_number
 * @property string $subject
 * @property string $locale
 * @property bool $spam
 * @property string|null $spam_reason
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Withdrawal extends Model
{
    /** @use HasFactory<WithdrawalFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'order_number',
        'subject',
        'locale',
        'spam',
        'spam_reason',
        // handled_at is intentionally excluded — set exclusively via the
        // operator toggle action, never mass-assigned (immutable-record invariant).
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spam' => 'boolean',
            'handled_at' => 'datetime',
        ];
    }

    /** Whether the operator has marked this withdrawal as handled. */
    public function isHandled(): bool
    {
        return $this->handled_at !== null;
    }
}

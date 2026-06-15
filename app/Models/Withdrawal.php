<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A stored § 356a withdrawal declaration.
 *
 * @property string $name
 * @property string $email
 * @property string|null $order_number
 * @property string $subject
 * @property string $locale
 * @property bool $spam
 * @property string|null $spam_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
final class Withdrawal extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'order_number',
        'subject',
        'locale',
        'spam',
        'spam_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'spam' => 'boolean',
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalConfirmedAsSpam;
use App\Services\AbusedAddressMemory;

/**
 * Remembers the hashed address of a confirmed spam row and slides its expiry. All confirm
 * paths (panel action, bulk action, mail link) go through `MarkWithdrawalAsSpam`, which
 * fires the event once per row.
 */
final class RememberAbusedAddressOnSpam
{
    public function __construct(private readonly AbusedAddressMemory $memory) {}

    public function handle(WithdrawalConfirmedAsSpam $event): void
    {
        // The memory is a soft signal: a failure is reported and must not take the verdict transition down with it.
        rescue(fn () => $this->memory->remember($event->withdrawal->email));
    }
}

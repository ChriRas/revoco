<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\WithdrawalMarkedNotSpam;
use App\Services\AbusedAddressMemory;

/** "Not spam" takes the row's address out of the memory, even when other rows of it stay confirmed; a later confirmation re-adds it. */
final class ForgetAbusedAddressOnNotSpam
{
    public function __construct(private readonly AbusedAddressMemory $memory) {}

    public function handle(WithdrawalMarkedNotSpam $event): void
    {
        // The memory is a soft signal: a failure is reported and must not stop the listeners after this one (the acknowledgment re-send).
        rescue(fn () => $this->memory->forget($event->withdrawal->email));
    }
}

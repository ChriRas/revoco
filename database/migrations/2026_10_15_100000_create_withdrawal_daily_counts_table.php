<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_daily_counts', function (Blueprint $table) {
            /** Receipt day (`created_at` in the application time zone). */
            $table->date('day');
            /** The system's spam level at receipt: green | yellow | red. */
            $table->string('level');
            /** none | spam_auto | spam_operator | not_spam, derived from the verdict and its source. */
            $table->string('outcome');
            /** A fired signal key; '' is the total bucket (NOT NULL: SQLite treats NULLs as distinct in a unique index). */
            $table->string('signal')->default('');
            $table->integer('count')->default(0);

            $table->unique(['day', 'level', 'outcome', 'signal']);
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_daily_counts');
    }

    /**
     * Counts every existing withdrawal the way live recording would. Self-contained on purpose
     * (query builder, inline state derivation, no app classes), so later refactors never break
     * `migrate` on a fresh install; a parity test pins it to the live recorder.
     */
    private function backfill(): void
    {
        /** @var array<string, array{day: string, level: string, outcome: string, signal: string, count: int}> $counts */
        $counts = [];

        DB::table('withdrawals')
            ->select(['id', 'created_at', 'spam_level', 'spam_verdict', 'spam_verdict_source', 'spam_reason'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$counts): void {
                foreach ($rows as $row) {
                    if (! is_string($row->created_at) || ! is_string($row->spam_level)) {
                        continue;
                    }

                    $outcome = match ($row->spam_verdict) {
                        'not_spam' => 'not_spam',
                        'spam' => $row->spam_verdict_source === 'auto' ? 'spam_auto' : 'spam_operator',
                        default => 'none',
                    };

                    $signals = [''];

                    if (is_string($row->spam_reason)) {
                        foreach (explode(',', $row->spam_reason) as $key) {
                            $key = trim($key);

                            if ($key !== '' && ! in_array($key, $signals, true)) {
                                $signals[] = $key;
                            }
                        }
                    }

                    $day = substr($row->created_at, 0, 10);

                    foreach ($signals as $signal) {
                        $id = implode('|', [$day, $row->spam_level, $outcome, $signal]);
                        $counts[$id] ??= ['day' => $day, 'level' => $row->spam_level, 'outcome' => $outcome, 'signal' => $signal, 'count' => 0];
                        $counts[$id]['count']++;
                    }
                }
            });

        foreach (array_chunk(array_values($counts), 100) as $chunk) {
            DB::table('withdrawal_daily_counts')->insert($chunk);
        }
    }
};

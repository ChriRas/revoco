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
        Schema::create('withdrawal_acknowledgments', function (Blueprint $table) {
            $table->id();
            /** One entry per withdrawal; removed together with the row (7-day spam purge). */
            $table->foreignId('withdrawal_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            /** What queued the acknowledgment: submit | not_spam. */
            $table->string('trigger');
        });

        // The acknowledgment went out at submit for every row that was not red, whatever
        // the operator's verdict is today. Red rows (and honeypot rows since 1.0.3) got none.
        DB::table('withdrawals')
            ->where('spam_level', '!=', 'red')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('withdrawal_acknowledgments')->insertOrIgnore([
                    'withdrawal_id' => $row->id,
                    'sent_at' => $row->created_at ?? now(),
                    'trigger' => 'submit',
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_acknowledgments');
    }
};

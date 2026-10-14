<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            /** The system's classification: green | yellow | red. */
            $table->string('spam_level')->default('green');
            /** The operator's word: spam | not_spam. Null means no verdict yet. */
            $table->string('spam_verdict')->nullable();
            $table->timestamp('spam_verdict_at')->nullable();
        });

        DB::table('withdrawals')->where('spam', true)->update(['spam_level' => 'red']);

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('spam');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->boolean('spam')->default(false);
        });

        // The operator's verdict wins over the level; unjudged yellow and red rows count as spam.
        DB::table('withdrawals')
            ->where(fn (Builder $query) => $query->where('spam_verdict', 'spam')
                ->orWhere(fn (Builder $query) => $query->whereNull('spam_verdict')->where('spam_level', '!=', 'green')))
            ->update(['spam' => true]);

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['spam_level', 'spam_verdict', 'spam_verdict_at']);
        });
    }
};

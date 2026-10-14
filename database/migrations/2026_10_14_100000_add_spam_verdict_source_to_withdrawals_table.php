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
        Schema::table('withdrawals', function (Blueprint $table) {
            /** Who gave the verdict: auto | operator. Null while there is no verdict. */
            $table->string('spam_verdict_source')->nullable()->after('spam_verdict_at');
        });

        // Every verdict before this column came from the operator.
        DB::table('withdrawals')->whereNotNull('spam_verdict')->update(['spam_verdict_source' => 'operator']);
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('spam_verdict_source');
        });
    }
};

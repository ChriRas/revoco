<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            /**
             * Timestamp set when the operator marks the withdrawal as handled.
             * Null means unhandled. No handled_by column — single operator, low value now.
             */
            $table->timestamp('handled_at')->nullable()->after('spam_reason');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('handled_at');
        });
    }
};

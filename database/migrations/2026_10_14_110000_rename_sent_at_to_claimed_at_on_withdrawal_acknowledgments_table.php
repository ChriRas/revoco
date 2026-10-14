<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The log records the once-only claim, written before the mail is queued — not a delivery receipt. */
    public function up(): void
    {
        Schema::table('withdrawal_acknowledgments', function (Blueprint $table) {
            $table->renameColumn('sent_at', 'claimed_at');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawal_acknowledgments', function (Blueprint $table) {
            $table->renameColumn('claimed_at', 'sent_at');
        });
    }
};

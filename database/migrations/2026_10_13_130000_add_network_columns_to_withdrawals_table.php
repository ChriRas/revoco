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
            /** Country and network derived locally from the client IP. The IP itself is never stored. */
            $table->string('country', 2)->nullable()->after('spam_reason');
            $table->unsignedInteger('asn')->nullable()->after('country');
            $table->string('asn_org')->nullable()->after('asn');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['country', 'asn', 'asn_org']);
        });
    }
};

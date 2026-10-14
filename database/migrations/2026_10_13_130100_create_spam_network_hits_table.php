<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spam_network_hits', function (Blueprint $table) {
            /** One hit per confirmed withdrawal. No foreign key: confirmed rows are purged, the hits outlive them. */
            $table->unsignedBigInteger('withdrawal_id')->primary();
            $table->unsignedInteger('asn')->index();
            $table->timestamp('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_network_hits');
    }
};

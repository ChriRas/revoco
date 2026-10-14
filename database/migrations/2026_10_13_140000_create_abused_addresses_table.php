<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abused_addresses', function (Blueprint $table) {
            $table->id();
            /** HMAC-SHA256 of the normalised address, never the address itself. No foreign key: confirmed rows are purged, the memory outlives them. */
            $table->char('hash', 64)->unique();
            $table->dateTime('last_seen_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abused_addresses');
    }
};

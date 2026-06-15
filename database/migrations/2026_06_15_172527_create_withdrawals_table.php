<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            // The four § 356a Abs. 2 fields (subject = goods/service free-text).
            $table->string('name');
            $table->string('email');
            $table->string('order_number')->nullable();
            $table->text('subject');
            // Consumer locale at submit time — drives the Phase 4 acknowledgment language.
            $table->string('locale', 12);
            // Non-blocking triage signal only; never gates acceptance (§ 356a).
            $table->boolean('spam')->default(false);
            $table->string('spam_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};

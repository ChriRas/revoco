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
            /** Sum of the weights of the spam signals that fired; null for legacy, unscored rows. */
            $table->unsignedSmallInteger('spam_score')->nullable()->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('spam_score');
        });
    }
};

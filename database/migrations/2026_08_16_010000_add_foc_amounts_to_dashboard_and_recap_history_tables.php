<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Total nilai transaksi FOC/Compliment — dipisah dari total_amount (gross/net).
     * Compliment paid_amount = 0 (diskon 100%), FOC = grand_total.
     */
    public function up(): void
    {
        Schema::table('dashboard', function (Blueprint $table) {
            $table->decimal('total_foc_amount', 18, 2)->default(0)->after('total_qris');
            $table->decimal('total_compliment_amount', 18, 2)->default(0)->after('total_foc_amount');
        });

        Schema::table('recap_history', function (Blueprint $table) {
            $table->decimal('total_foc_amount', 18, 2)->default(0)->after('total_qris');
            $table->decimal('total_compliment_amount', 18, 2)->default(0)->after('total_foc_amount');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard', function (Blueprint $table) {
            $table->dropColumn(['total_foc_amount', 'total_compliment_amount']);
        });

        Schema::table('recap_history', function (Blueprint $table) {
            $table->dropColumn(['total_foc_amount', 'total_compliment_amount']);
        });
    }
};

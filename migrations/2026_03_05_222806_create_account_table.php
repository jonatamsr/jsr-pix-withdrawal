<?php

declare(strict_types=1);

use Hyperf\Database\Migrations\Migration;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Schema\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->string('name', 255);
            $table->unsignedDecimal('balance', 15, 2)->default(0);
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account');
    }
};

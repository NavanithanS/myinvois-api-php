<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('rate_limits', function (Blueprint $table) {
            $table->id();
            $table->string('identifier'); // user_id, api_key_id, or ip_address
            $table->string('type', 20); // 'user', 'api_key', 'ip'
            $table->string('endpoint')->nullable();
            $table->integer('max_requests');
            $table->integer('window_seconds');
            $table->integer('current_requests')->default(0);
            $table->timestamp('window_start');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['identifier', 'type', 'endpoint']);
            $table->index(['identifier', 'type']);
            $table->index('window_start');
        });
    }

    public function down()
    {
        Schema::dropIfExists('rate_limits');
    }
};
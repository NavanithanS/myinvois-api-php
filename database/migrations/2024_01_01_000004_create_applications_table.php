<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('client_id', 32)->unique();
            $table->string('client_secret_hash');
            $table->json('redirect_uris')->nullable();
            $table->json('scopes')->nullable();
            $table->enum('grant_type', ['client_credentials', 'authorization_code'])->default('client_credentials');
            $table->boolean('is_active')->default(true);
            $table->string('environment', 20)->default('sandbox');
            $table->json('rate_limits')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'is_active']);
            $table->index('client_id');
            $table->index(['environment', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('applications');
    }
};
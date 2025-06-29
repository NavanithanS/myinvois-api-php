<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('documentation_sections', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('content');
            $table->string('category', 50);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            $table->index(['category', 'sort_order']);
            $table->index(['is_published', 'category']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('documentation_sections');
    }
};
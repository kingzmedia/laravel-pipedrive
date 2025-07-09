<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_pipelines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // Pipeline information
            $table->string('name');
            $table->string('url_title')->nullable();
            $table->integer('order_nr')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('deal_probability')->default(true);
            $table->boolean('selected')->nullable();
            
            // Pipedrive timestamps
            $table->timestamp('pipedrive_add_time')->nullable();
            $table->timestamp('pipedrive_update_time')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('pipedrive_id');
            $table->index('active');
            $table->index('order_nr');
            $table->index('selected');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_pipelines');
    }
};

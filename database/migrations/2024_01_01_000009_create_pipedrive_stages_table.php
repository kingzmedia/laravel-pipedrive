<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_stages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // Stage information
            $table->string('name');
            $table->unsignedBigInteger('pipeline_id');
            $table->integer('order_nr')->default(0);
            $table->boolean('active')->default(true);
            $table->integer('deal_probability')->nullable();
            $table->boolean('rotten_flag')->default(false);
            $table->integer('rotten_days')->nullable();
            
            // Additional fields
            $table->string('pipeline_name')->nullable();
            $table->string('pipeline_deal_probability')->nullable();
            
            // Pipedrive timestamps
            $table->timestamp('pipedrive_add_time')->nullable();
            $table->timestamp('pipedrive_update_time')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('pipedrive_id');
            $table->index('pipeline_id');
            $table->index('active');
            $table->index('order_nr');
            $table->index('rotten_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_stages');
    }
};

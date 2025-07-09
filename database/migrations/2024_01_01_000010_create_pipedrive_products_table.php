<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // Product information
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('tax', 5, 2)->nullable();
            $table->string('category')->nullable();
            
            // Pricing (JSON array)
            $table->json('prices')->nullable();
            
            // User fields
            $table->unsignedBigInteger('owner_id')->nullable();
            
            // Counters
            $table->integer('deals_count')->default(0);
            $table->integer('files_count')->default(0);
            $table->integer('followers_count')->default(0);
            
            // Additional fields
            $table->string('visible_to')->nullable();
            $table->integer('first_char')->nullable();
            $table->boolean('active_flag')->default(true);
            
            // Pipedrive timestamps
            $table->timestamp('pipedrive_add_time')->nullable();
            $table->timestamp('pipedrive_update_time')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('pipedrive_id');
            $table->index('owner_id');
            $table->index('category');
            $table->index('code');
            $table->index('active_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // User information
            $table->string('name');
            $table->string('email');
            $table->string('default_currency')->nullable();
            $table->string('locale')->nullable();
            $table->string('lang')->nullable();
            $table->string('phone')->nullable();
            
            // Role and permissions
            $table->boolean('activated')->nullable();
            $table->boolean('is_admin')->nullable();
            $table->integer('role_id')->nullable();
            $table->string('timezone_name')->nullable();
            $table->string('timezone_offset')->nullable();
            
            // Additional fields
            $table->integer('icon_url')->nullable();
            $table->boolean('is_you')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->timestamp('created')->nullable();
            $table->timestamp('modified')->nullable();
            $table->boolean('signup_flow_variation')->nullable();
            $table->boolean('has_created_company')->nullable();
            $table->boolean('access')->nullable();
            $table->boolean('active_flag')->default(true);
            
            // Pipedrive timestamps
            $table->timestamp('pipedrive_add_time')->nullable();
            $table->timestamp('pipedrive_update_time')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('pipedrive_id');
            $table->index('email');
            $table->index('activated');
            $table->index('is_admin');
            $table->index('role_id');
            $table->index('active_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_users');
    }
};

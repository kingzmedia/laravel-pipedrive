<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // File information
            $table->string('name')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('file_type')->nullable();
            $table->text('url')->nullable();
            $table->string('remote_location')->nullable();
            $table->string('remote_id')->nullable();
            $table->string('cid')->nullable();
            $table->string('s3_bucket')->nullable();
            $table->string('mail_message_id')->nullable();
            $table->string('mail_template_id')->nullable();
            
            // Related entities
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('activity_id')->nullable();
            $table->unsignedBigInteger('note_id')->nullable();
            $table->unsignedBigInteger('log_id')->nullable();
            
            // User fields
            $table->unsignedBigInteger('user_id')->nullable();
            
            // Additional fields
            $table->text('description')->nullable();
            $table->boolean('inline_flag')->default(false);
            $table->boolean('active_flag')->default(true);
            
            // Pipedrive timestamps
            $table->timestamp('pipedrive_add_time')->nullable();
            $table->timestamp('pipedrive_update_time')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('pipedrive_id');
            $table->index('deal_id');
            $table->index('person_id');
            $table->index('org_id');
            $table->index('activity_id');
            $table->index('user_id');
            $table->index('file_type');
            $table->index('active_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_files');
    }
};

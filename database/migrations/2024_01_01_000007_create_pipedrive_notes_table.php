<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_notes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // Note content
            $table->longText('content')->nullable();
            $table->string('subject')->nullable();
            
            // Related entities
            $table->unsignedBigInteger('deal_id')->nullable();
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('org_id')->nullable();
            $table->unsignedBigInteger('lead_id')->nullable();
            
            // User fields
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('last_update_user_id')->nullable();
            
            // Pinned flags
            $table->boolean('pinned_to_deal_flag')->default(false);
            $table->boolean('pinned_to_person_flag')->default(false);
            $table->boolean('pinned_to_organization_flag')->default(false);
            $table->boolean('pinned_to_lead_flag')->default(false);
            
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
            $table->index('lead_id');
            $table->index('user_id');
            $table->index('active_flag');
            $table->index('pinned_to_deal_flag');
            $table->index('pinned_to_person_flag');
            $table->index('pinned_to_organization_flag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_notes');
    }
};

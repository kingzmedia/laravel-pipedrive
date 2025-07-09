<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipedrive_goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pipedrive_id')->unique();
            
            // Goal information
            $table->string('title')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('type')->nullable(); // deals_won, activities_completed, etc.
            $table->string('assignee_type')->nullable(); // person, team, company
            $table->string('interval')->nullable(); // weekly, monthly, quarterly, yearly
            $table->date('duration_start')->nullable();
            $table->date('duration_end')->nullable();
            $table->decimal('expected_outcome', 15, 2)->nullable();
            $table->string('currency')->nullable();
            $table->boolean('active')->default(true);
            
            // Progress tracking
            $table->decimal('outcome', 15, 2)->nullable();
            $table->decimal('progress', 5, 2)->nullable(); // percentage
            
            // Pipeline specific
            $table->integer('pipeline_id')->nullable();
            $table->integer('stage_id')->nullable();
            $table->integer('activity_type_id')->nullable();
            
            // Pipedrive timestamps
            $table->timestamp('pipedrive_add_time')->nullable();
            $table->timestamp('pipedrive_update_time')->nullable();
            
            // Laravel timestamps
            $table->timestamps();
            
            // Indexes
            $table->index('pipedrive_id');
            $table->index('owner_id');
            $table->index('type');
            $table->index('interval');
            $table->index('active');
            $table->index('pipeline_id');
            $table->index(['duration_start', 'duration_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipedrive_goals');
    }
};

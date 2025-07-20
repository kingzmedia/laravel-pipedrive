<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('pipedrive_custom_fields', function (Blueprint $table) {
            // Drop the old unique constraint on pipedrive_id only
            $table->dropUnique(['pipedrive_id']);

            // Add new unique constraint on pipedrive_id + entity_type
            $table->unique(['pipedrive_id', 'entity_type'], 'unique_field_per_entity');
        });
    }

    public function down()
    {
        Schema::table('pipedrive_custom_fields', function (Blueprint $table) {
            // Drop the combined unique constraint
            $table->dropUnique('unique_field_per_entity');

            // Restore the old unique constraint (this might fail if there are duplicates)
            $table->unique('pipedrive_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // First, clean up any invalid datetime values
        DB::table('pipedrive_custom_fields')
            ->where('pipedrive_add_time', '1970-01-01 00:00:00')
            ->update(['pipedrive_add_time' => null]);
            
        DB::table('pipedrive_custom_fields')
            ->where('pipedrive_update_time', '1970-01-01 00:00:00')
            ->update(['pipedrive_update_time' => null]);

        // The columns are already nullable in the original migration
        // This migration just cleans up existing invalid data
    }

    public function down()
    {
        // Nothing to revert - we just cleaned up invalid data
    }
};

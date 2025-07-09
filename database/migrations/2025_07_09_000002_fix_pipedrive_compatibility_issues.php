<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Fix pipedrive_files table
        if (Schema::hasTable('pipedrive_files')) {
            Schema::table('pipedrive_files', function (Blueprint $table) {
                // Add active_flag if missing
                if (!Schema::hasColumn('pipedrive_files', 'active_flag')) {
                    $table->boolean('active_flag')->default(true);
                }
                
                // Fix remote_location type if it's boolean
                if (Schema::hasColumn('pipedrive_files', 'remote_location')) {
                    $table->string('remote_location')->nullable()->change();
                }
                
                // Fix file_size type if it's string
                if (Schema::hasColumn('pipedrive_files', 'file_size')) {
                    $table->unsignedBigInteger('file_size')->nullable()->change();
                }
            });
        }

        // Fix pipedrive_notes table
        if (Schema::hasTable('pipedrive_notes')) {
            Schema::table('pipedrive_notes', function (Blueprint $table) {
                // Add active_flag if missing
                if (!Schema::hasColumn('pipedrive_notes', 'active_flag')) {
                    $table->boolean('active_flag')->default(true);
                }
            });
        }

        // Fix pipedrive_stages table
        if (Schema::hasTable('pipedrive_stages')) {
            Schema::table('pipedrive_stages', function (Blueprint $table) {
                // Add pipeline_name if missing
                if (!Schema::hasColumn('pipedrive_stages', 'pipeline_name')) {
                    $table->string('pipeline_name')->nullable();
                }
                
                // Add pipeline_deal_probability if missing
                if (!Schema::hasColumn('pipedrive_stages', 'pipeline_deal_probability')) {
                    $table->string('pipeline_deal_probability')->nullable();
                }
            });
        }

        // Fix pipedrive_products table
        if (Schema::hasTable('pipedrive_products')) {
            Schema::table('pipedrive_products', function (Blueprint $table) {
                // Add first_char if missing
                if (!Schema::hasColumn('pipedrive_products', 'first_char')) {
                    $table->string('first_char', 1)->nullable();
                }
            });
        }

        // Fix pipedrive_users table
        if (Schema::hasTable('pipedrive_users')) {
            Schema::table('pipedrive_users', function (Blueprint $table) {
                // Fix timezone_offset type if it's integer
                if (Schema::hasColumn('pipedrive_users', 'timezone_offset')) {
                    $table->string('timezone_offset', 10)->nullable()->change();
                }
            });
        }
    }

    public function down()
    {
        // Reverse the changes if needed
        if (Schema::hasTable('pipedrive_files')) {
            Schema::table('pipedrive_files', function (Blueprint $table) {
                if (Schema::hasColumn('pipedrive_files', 'active_flag')) {
                    $table->dropColumn('active_flag');
                }
            });
        }

        if (Schema::hasTable('pipedrive_notes')) {
            Schema::table('pipedrive_notes', function (Blueprint $table) {
                if (Schema::hasColumn('pipedrive_notes', 'active_flag')) {
                    $table->dropColumn('active_flag');
                }
            });
        }

        if (Schema::hasTable('pipedrive_stages')) {
            Schema::table('pipedrive_stages', function (Blueprint $table) {
                if (Schema::hasColumn('pipedrive_stages', 'pipeline_name')) {
                    $table->dropColumn('pipeline_name');
                }
                if (Schema::hasColumn('pipedrive_stages', 'pipeline_deal_probability')) {
                    $table->dropColumn('pipeline_deal_probability');
                }
            });
        }

        if (Schema::hasTable('pipedrive_products')) {
            Schema::table('pipedrive_products', function (Blueprint $table) {
                if (Schema::hasColumn('pipedrive_products', 'first_char')) {
                    $table->dropColumn('first_char');
                }
            });
        }
    }
};

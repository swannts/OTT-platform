<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Drop existing FK constraints referencing entertainments
        try {
            DB::statement('ALTER TABLE pay_per_views DROP FOREIGN KEY pay_per_views_movie_id_foreign');
        } catch (\Throwable $e) {}

        try {
            DB::statement('ALTER TABLE subtitles DROP FOREIGN KEY subtitles_entertainment_id_foreign');
        } catch (\Throwable $e) {}

        // 1. Core Primary Key columns -> CHAR(36)
        $primaryKeyTables = [
            'entertainments',
            'episodes',
            'seasons',
            'genres',
            'cast_crew',
            'clips',
            'banners',
            'live_tv_category',
            'live_tv_channel',
            'livetvs',
            'entertainment_stream_content_mapping',
            'entertainment_gener_mapping',
            'entertainment_talent_mapping',
            'entertainment_download_mapping',
            'episode_stream_content_mapping',
            'episode_download_mapping',
            'live_tv_stream_content_mapping',
        ];

        foreach ($primaryKeyTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'id')) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `id` CHAR(36) NOT NULL");
            }
        }

        // 2. Foreign Key & Reference columns -> CHAR(36) / VARCHAR(36)
        $columnModifications = [
            'episodes' => [
                'entertainment_id' => 'CHAR(36) NULL',
                'season_id' => 'CHAR(36) NULL',
            ],
            'seasons' => [
                'entertainment_id' => 'CHAR(36) NULL',
            ],
            'clips' => [
                'content_id' => 'VARCHAR(36) NULL',
            ],
            'live_tv_channel' => [
                'category_id' => 'CHAR(36) NULL',
            ],
            'entertainment_stream_content_mapping' => [
                'entertainment_id' => 'CHAR(36) NOT NULL',
            ],
            'entertainment_gener_mapping' => [
                'entertainment_id' => 'CHAR(36) NOT NULL',
                'genre_id' => 'CHAR(36) NOT NULL',
            ],
            'entertainment_talent_mapping' => [
                'entertainment_id' => 'CHAR(36) NOT NULL',
                'talent_id' => 'CHAR(36) NOT NULL',
            ],
            'entertainment_download_mapping' => [
                'entertainment_id' => 'CHAR(36) NOT NULL',
            ],
            'episode_stream_content_mapping' => [
                'episode_id' => 'CHAR(36) NOT NULL',
            ],
            'episode_download_mapping' => [
                'episode_id' => 'CHAR(36) NOT NULL',
            ],
            'live_tv_stream_content_mapping' => [
                'tv_channel_id' => 'CHAR(36) NOT NULL',
            ],
            'subtitles' => [
                'entertainment_id' => 'CHAR(36) NULL',
            ],
            'pay_per_views' => [
                'movie_id' => 'CHAR(36) NOT NULL',
            ],
            'watchlists' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
            ],
            'continue_watch' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
                'episode_id' => 'VARCHAR(36) NULL',
            ],
            'likes' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
            ],
            'reviews' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
            ],
            'user_watch_histories' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
            ],
            'user_search_histories' => [
                'search_id' => 'VARCHAR(36) NULL',
            ],
            'entertainment_views' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
            ],
            'entertainment_downloads' => [
                'entertainment_id' => 'VARCHAR(36) NULL',
            ],
        ];

        foreach ($columnModifications as $table => $columns) {
            if (Schema::hasTable($table)) {
                foreach ($columns as $column => $definition) {
                    if (Schema::hasColumn($table, $column)) {
                        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` {$definition}");
                    }
                }
            }
        }

        // Re-add foreign key constraints
        try {
            DB::statement('ALTER TABLE pay_per_views ADD CONSTRAINT pay_per_views_movie_id_foreign FOREIGN KEY (movie_id) REFERENCES entertainments(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {}

        try {
            DB::statement('ALTER TABLE subtitles ADD CONSTRAINT subtitles_entertainment_id_foreign FOREIGN KEY (entertainment_id) REFERENCES entertainments(id) ON DELETE CASCADE');
        } catch (\Throwable $e) {}

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reversal would require bigint auto_increment restoration
    }
};

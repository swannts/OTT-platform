<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        \Illuminate\Support\Facades\Cache::forget('roles_cache');
        \Illuminate\Support\Facades\Cache::forget('permissions_cache');
        app()['cache']->forget('spatie.permission.cache');

        // 1. Channel Manager Role
        $channelManagerRole = Role::firstOrCreate(
            ['name' => 'channel_manager'],
            ['title' => 'Channel Manager', 'is_fixed' => false]
        );

        $channelPermissions = [
            'view_livetv', 'add_livetv', 'edit_livetv', 'delete_livetv', 'restore_livetv',
            'view_tvchannel', 'add_tvchannel', 'edit_tvchannel', 'delete_tvchannel', 'restore_tvchannel',
            'view_tvcategory', 'add_tvcategory', 'edit_tvcategory', 'delete_tvcategory', 'restore_tvcategory',
            'view_media', 'add_media',
            'view_banners', 'add_banners', 'edit_banners',
        ];

        $validChannelPermissions = Permission::whereIn('name', $channelPermissions)->get();
        $channelManagerRole->syncPermissions($validChannelPermissions);

        // 2. Video Editor Role
        $videoEditorRole = Role::firstOrCreate(
            ['name' => 'video_editor'],
            ['title' => 'Video Editor', 'is_fixed' => false]
        );

        $editorPermissions = [
            'view_movies', 'add_movies', 'edit_movies', 'delete_movies', 'restore_movies',
            'view_tvshow', 'add_tvshow', 'edit_tvshow', 'delete_tvshow', 'restore_tvshow',
            'view_tvshows', 'add_tvshows', 'edit_tvshows', 'delete_tvshows', 'restore_tvshows',
            'view_seasons', 'add_seasons', 'edit_seasons', 'delete_seasons', 'restore_seasons',
            'view_episodes', 'add_episodes', 'edit_episodes', 'delete_episodes', 'restore_episodes',
            'view_videos', 'add_videos', 'edit_videos', 'delete_videos', 'restore_videos',
            'view_video', 'add_video', 'edit_video', 'delete_video',
            'view_clips', 'add_clips', 'edit_clips', 'delete_clips',
            'view_genres', 'add_genres', 'edit_genres',
            'view_castcrew', 'add_castcrew', 'edit_castcrew',
            'view_actor', 'add_actor', 'edit_actor',
            'view_director', 'add_director', 'edit_director',
            'view_media', 'add_media', 'edit_media',
            'view_banners', 'add_banners', 'edit_banners',
        ];

        $validEditorPermissions = Permission::whereIn('name', $editorPermissions)->get();
        $videoEditorRole->syncPermissions($validEditorPermissions);

        // 3. Create Sample Staff Accounts for Testing / Review
        $channelManager = User::updateOrCreate(
            ['email' => 'channel.manager@noorcinema.com'],
            [
                'first_name' => 'Channel',
                'last_name' => 'Manager',
                'user_type' => 'channel_manager',
                'password' => Hash::make('12345678'),
                'status' => 1,
                'email_verified_at' => now(),
            ]
        );
        $channelManager->syncRoles(['channel_manager']);

        $videoEditor = User::updateOrCreate(
            ['email' => 'video.editor@noorcinema.com'],
            [
                'first_name' => 'Video',
                'last_name' => 'Editor',
                'user_type' => 'video_editor',
                'password' => Hash::make('12345678'),
                'status' => 1,
                'email_verified_at' => now(),
            ]
        );
        $videoEditor->syncRoles(['video_editor']);

        \Illuminate\Support\Facades\Cache::forget('roles_cache');
        \Illuminate\Support\Facades\Cache::forget('permissions_cache');
        app()['cache']->forget('spatie.permission.cache');
    }
}


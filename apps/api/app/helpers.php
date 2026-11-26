<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Setting;
use App\Models\Device;
use App\Models\UserMultiProfile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Modules\Currency\Models\Currency;
use Modules\Entertainment\Models\Entertainment;
use Modules\Page\Models\Page;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;

if (!function_exists('mail_footer')) {
function mail_footer($type)
{
    return [
        'notification_type' => $type,
        'logged_in_user_fullname' => auth()->user() ? auth()->user()->full_name ?? default_user_name() : '',
        'logged_in_user_role' => auth()->user() ? auth()->user()->getRoleNames()->first()->name ?? '-' : '',
        'company_name' => setting('app_name'),
        'company_contact_info' => implode('', [
            setting('helpline_number') . PHP_EOL,
            setting('inquriy_email'),
        ]),
    ];
}
}


function sendNotification($data)
{
    app(\App\Services\Notifications\PushNotificationService::class)->sendNotification((array) $data);
}

function sendNotifications($data)
{
    return app(\App\Services\Notifications\PushNotificationService::class)->sendPushNotifications((array) $data);
}

function fcm($fields)
{
    app(\App\Services\Notifications\PushNotificationService::class)->sendFcm((array) $fields);
}

function getAccessToken()
{
    return app(\App\Services\Notifications\PushNotificationService::class)->getFirebaseAccessToken();
}

function formatOffset($offset)
{
    $hours = $offset / 3600;
    $remainder = $offset % 3600;
    $sign = $hours > 0 ? '+' : '-';
    $hour = (int) abs($hours);
    $minutes = (int) abs($remainder / 60);

    if ($hour == 0 and $minutes == 0) {
        $sign = ' ';
    }

    return 'GMT' . $sign . str_pad($hour, 2, '0', STR_PAD_LEFT)
        . ':' . str_pad($minutes, 2, '0');
}

function timeZoneList()
{
    $list = \DateTimeZone::listAbbreviations();
    $idents = \DateTimeZone::listIdentifiers();

    $data = $offset = $added = [];
    foreach ($list as $abbr => $info) {
        foreach ($info as $zone) {
            if (!empty($zone['timezone_id']) and !in_array($zone['timezone_id'], $added) and in_array($zone['timezone_id'], $idents)) {
                $z = new \DateTimeZone($zone['timezone_id']);
                $c = new \DateTime(null, $z);
                $zone['time'] = $c->format('H:i a');
                $offset[] = $zone['offset'] = $z->getOffset($c);
                $data[] = $zone;
                $added[] = $zone['timezone_id'];
            }
        }
    }

    array_multisort($offset, SORT_ASC, $data);
    $options = [];
    foreach ($data as $key => $row) {
        $options[$row['timezone_id']] = $row['time'] . ' - ' . formatOffset($row['offset']) . ' ' . $row['timezone_id'];
    }

    return $options;
}

/*
 * Global helpers file with misc functions.
 */
if (!function_exists('app_name')) {
    /**
     * Helper to grab the application name.
     *
     * @return mixed
     */
    function app_name()
    {
        return setting('app_name') ?? config('app.name');
    }
}
/**
 * Avatar Find By Gender
 */
if (!function_exists('default_user_avatar')) {
    function default_user_avatar()
    {
        return asset(config('app.avatar_base_path') . 'avatar.webp');
    }
    function default_user_name()
    {
        return __('messages.unknown_user');
    }
}
if (!function_exists('user_avatar')) {
    function user_avatar()
    {
        if (auth()->user()->file_url ?? null) {
            return auth()->user()->file_url;
        } else {
            return asset(config('app.avatar_base_path') . 'avatar.webp');
        }
    }
}

if (!function_exists('default_file_url')) {
    function default_file_url()
    {
        return asset(config('app.image_path') . 'default.webp');
    }
}

/*
 * Global helpers file with misc functions.
 */
if (!function_exists('user_registration')) {
    /**
     * Helper to grab the application name.
     *
     * @return mixed
     */
    function user_registration()
    {
        $user_registration = false;

        if (env('USER_REGISTRATION') == 'true') {
            $user_registration = true;
        }

        return $user_registration;
    }
}

/**
 * Global Json DD
 * !USAGE
 * return jdd($id);
 */
if (!function_exists('jdd')) {
    function jdd($data)
    {
        return response()->json($data, 500);
        exit();
    }
}
function GetcurrentCurrency()
{
    $currency = Currency::getDefaultCurrency();

    $currency_code = $currency ? strtolower($currency->currency_code) : 'usd';
    return $currency_code;
}


/*
 *
 * label_case
 *
 * ------------------------------------------------------------------------
 */
if (!function_exists('label_case')) {
    /**
     * Prepare the Column Name for Lables.
     */
    function label_case($text)
    {
        $order = ['_', '-'];
        $replace = ' ';

        $new_text = trim(\Illuminate\Support\Str::title(str_replace('"', '', $text)));
        $new_text = trim(\Illuminate\Support\Str::title(str_replace($order, $replace, $text)));
        $new_text = preg_replace('!\s+!', ' ', $new_text);

        return $new_text;
    }
}


if (!function_exists('validate_sort_direction')) {
    /**
     * Validate DataTables/API sort direction to prevent SQL injection.
     * Returns only 'asc' or 'desc'; defaults to 'asc' for invalid input.
     */
    function validate_sort_direction($direction)
    {
        $direction = strtolower(trim((string) $direction));
        return in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';
    }
}

if (!function_exists('fielf_required')) {
    /**
     * Prepare the Column Name for Lables.
     */
    function fielf_required($required)
    {
        $return_text = '';

        if ($required != '') {
            $return_text = '<span class="text-danger">*</span>';
        }

        return $return_text;
    }
}

/*
 * Get or Set the Settings Values
 *
 * @var [type]
 */
if (!function_exists('setting')) {
    function setting($key, $default = null)
    {

        if (is_null($key)) {
            return new App\Models\Setting();
        }

        if (is_array($key)) {
            return App\Models\Setting::set($key[0], $key[1]);
        }
        // dd($key);
        $value = App\Models\Setting::get($key);
        // dd($value);
        return is_null($value) ? value($default) : $value;
    }
}

function app_name()
{
        $value = App\Models\Setting::where('name','app_name')->select('val')->first();
        $app_name = $value->val ?? config('app.name');
        return is_null($app_name) ? : $app_name;
}



/*
 * Show Human readable file size
 *
 * @var [type]
 */
if (!function_exists('humanFilesize')) {
    function humanFilesize($size, $precision = 2)
    {
        $units = ['B', 'kB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $step = 1024;
        $i = 0;

        while (($size / $step) > 0.9) {
            $size = $size / $step;
            $i++;
        }

        return round($size, $precision) . $units[$i];
    }
}



/*
 *
 * Prepare a Slug for a given string
 * Laravel default str_slug does not work for Unicode
 *
 * ------------------------------------------------------------------------
 */
if (!function_exists('slug_format')) {
    /**
     * Format a string to Slug.
     */
    function slug_format($string)
    {
        $base_string = $string;

        $string = preg_replace('/\s+/u', '-', trim($string));
        $string = str_replace('/', '-', $string);
        $string = str_replace('\\', '-', $string);
        $string = strtolower($string);

        $slug_string = $string;

        return $slug_string;
    }
}

/*
 *
 * icon
 * A short and easy way to show icon fornts
 * Default value will be check icon from FontAwesome
 *
 * ------------------------------------------------------------------------
 */
if (!function_exists('icon')) {
    /**
     * Format a string to Slug.
     */
    function icon($string = 'fas fa-check')
    {
        $return_string = "<i class='" . $string . "'></i>";

        return $return_string;
    }
}



if (!function_exists('language_direction')) {
    /**
     * return direction of languages.
     *
     * @return string
     */
    function language_direction($language = null)
    {
        if (empty($language)) {
            $language = app()->getLocale();
        }
        $language = strtolower(substr($language, 0, 2));
        $rtlLanguages = [
            'ar', //  'العربية', Arabic
            'arc', //  'ܐܪܡܝܐ', Aramaic
            'bcc', //  'بلوچی مکرانی', Southern Balochi
            'bqi', //  'بختياري', Bakthiari
            'ckb', //  'Soranî / کوردی', Sorani Kurdish
            'dv', //  'ދިވެހިބަސް', Dhivehi
            'fa', //  'فارسی', Persian
            'glk', //  'گیلکی', Gilaki
            'he', //  'עברית', Hebrew
            'lrc', //- 'لوری', Northern Luri
            'mzn', //  'مازِرونی', Mazanderani
            'pnb', //  'پنجابی', Western Punjabi
            'ps', //  'پښتو', Pashto
            'sd', //  'سنڌي', Sindhi
            'ug', //  'Uyghurche / ئۇيغۇرچە', Uyghur
            'ur', //  'اردو', Urdu
            'yi', //  'ייִדיש', Yiddish
        ];
        if (in_array($language, $rtlLanguages)) {
            return 'rtl';
        }

        return 'ltr';
    }
}




function getCustomizationSetting($name, $key = 'customization_json')
{
    $settingObject = setting($key);
    if (isset($settingObject) && $key == 'customization_json') {
        try {
            $settings = (array) json_decode(html_entity_decode(stripslashes($settingObject)))->setting;

            if (isset($settings[$name])) {
                return collect($settings[$name])['value'];
            }

            return '';
        } catch (\Exception $e) {
            return '';
        }

        return '';
    } elseif ($key == 'root_color') {
        //
    }

    return '';
}

/**
 * Get footer copyright text for current locale using Laravel translation system
 *
 * @param string|null $locale Optional locale, defaults to current locale
 * @return string
 */

function str_slug($title, $separator = '-', $language = 'en')
{
    return Str::slug($title, $separator, $language);
}
function formatDuration($duration)
{
    if (strpos($duration, ':') !== false) {
        list($hours, $minutes) = explode(':', $duration);
        $hours = intval($hours);
        $minutes = intval($minutes);
        // Format as "05h 20m" with leading zeros for hours
        $hoursFormatted = str_pad($hours, 2, '0', STR_PAD_LEFT);
        $minutesFormatted = str_pad($minutes, 2, '0', STR_PAD_LEFT);
        return "{$hoursFormatted}h {$minutesFormatted}m";
    }

    return $duration;
}

function formatCurrency($number, $noOfDecimal, $decimalSeparator, $thousandSeparator, $currencyPosition, $currencySymbol)
{

    $formattedNumber = number_format($number, $noOfDecimal, '.', '');


    $parts = explode('.', $formattedNumber);
    $integerPart = $parts[0];
    $decimalPart = isset($parts[1]) ? $parts[1] : '';

    $integerPart = number_format($integerPart, 0, '', $thousandSeparator);


    $currencyString = '';

    if ($currencyPosition == 'left' || $currencyPosition == 'left_with_space') {
        $currencyString .= $currencySymbol;
        if ($currencyPosition == 'left_with_space') {
            $currencyString .= ' ';
        }

        $currencyString .= $integerPart;

        if ($noOfDecimal > 0) {
            $currencyString .= $decimalSeparator . $decimalPart;
        }
    }


    if ($currencyPosition == 'right' || $currencyPosition == 'right_with_space') {

        if ($noOfDecimal > 0) {
            $currencyString .= $integerPart . $decimalSeparator . $decimalPart;
        }
        if ($currencyPosition == 'right_with_space') {
            $currencyString .= ' ';
        }
        $currencyString .= $currencySymbol;
    }

    return $currencyString;
}


function formatUpdatedAt($updatedAt)
{
    $diff = Carbon::now()->diffInHours($updatedAt);
    return $diff < 25 ? $updatedAt->diffForHumans() : $updatedAt->isoFormat('llll');
}
function storeMediaFileAWS($module, $filePath, $key = 'file_url')
{
    // Clear existing media collection
    $module->clearMediaCollection($key);

    // Store the file using Laravel's media library
    $mediaItems = $module->addMedia($filePath)->toMediaCollection($key);

    // Get the stored file's path or name
    if ($mediaItems->count() > 0) {
        // Return the path or name of the stored file
        return $mediaItems[0]->file_name; // Adjust this based on your media library configuration
    }

    return null; // Return null or handle error as needed
}

function storeMediaFile($module, $files, $key = 'file_url', string $profile = 'image')
{
    $validator = app(\App\Services\Upload\FileUploadValidationService::class);

    $module->clearMediaCollection($key);

    if (is_array($files)) {
        foreach ($files as $file) {
            if (!empty($file)) {
                $validator->validate($file, $profile);
                $module->addMedia($file)->toMediaCollection($key);
            }
        }
    } else {
        $validator->validate($files, $profile);
        $module->addMedia($files)->toMediaCollection($key);
    }
}

function validateUploadedFile($file, string $profile)
{
    return app(\App\Services\Upload\FileUploadValidationService::class)->validate($file, $profile);
}

function sanitizeUploadedFilename($file, string $profile): string
{
    return app(\App\Services\Upload\FileUploadValidationService::class)->sanitizeFilename($file, $profile);
}

if (! function_exists('normalize_media_library_folder')) {
    /** Canonical storage folder slug for uploads (e.g. short_drama → short-drama). */
    function normalize_media_library_folder(string $name): string
    {
        if ($name === 'season') {
            return 'tvshow/season';
        }

        if ($name === 'episode') {
            return 'tvshow/episode';
        }

        $slug = strtolower(str_replace(['_', ' '], '-', trim($name, '/')));

        if (in_array($slug, ['short-drama', 'shortdrama'], true)) {
            return function_exists('short_drama_media_folder')
                ? short_drama_media_folder()
                : 'short-drama';
        }

        return trim($name, '/');
    }
}

if (! function_exists('media_library_root_folders')) {
    function media_library_root_folders(array $exclude = ['avatars', 'subtitles']): array
    {
        $root = storage_path('app/public');
        if (! is_dir($root)) {
            return [];
        }

        $folders = [];
        foreach (scandir($root) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $exclude, true) || ! is_dir($root . '/' . $entry)) {
                continue;
            }

            $name = normalize_media_library_folder($entry);
            if (isset($folders[$name])) {
                continue;
            }

            $labelKey = 'folder_' . strtolower($name);
            $folders[$name] = [
                'name' => $name,
                'display_name' => \Lang::has('messages.' . $labelKey) ? __('messages.' . $labelKey) : ucfirst(str_replace(['-', '_'], ' ', $name)),
                'path' => $name,
            ];
        }

        return array_values($folders);
    }
}

function getMediaUrls($searchQuery = null, $perPage = 21, $page = 1)
{
    // $activeDisk = DB::table('settings')->where('name', 'disc_type')->value('val') ?? env('ACTIVE_STORAGE','local');
    $activeDisk =  config('filesystems.active'); // set on live server

    $folder = $activeDisk === 'local' ? 'public/' : '';
    $files = Storage::disk($activeDisk)->allFiles($folder);

    if ($searchQuery) {
        $files = array_filter($files, function ($file) use ($searchQuery) {
            // Ensure $file is a string and check if it contains the search query
            return is_string($file) && stripos($file, $searchQuery) !== false;
        });
    }

    //     // Sort files in descending order (newest first)
    // // Convert to array if it's not already
    // $files = array_values($files);
    // // Sort by file modification time in descending order (newest first)
    // usort($files, function($a, $b) use ($activeDisk) {
    //     $timeA = Storage::disk($activeDisk)->lastModified($a);
    //     $timeB = Storage::disk($activeDisk)->lastModified($b);
    //     return $timeB - $timeA; // Descending order
    // });

    $totalFiles = count($files);
    $offset = ($page - 1) * $perPage;
    $paginatedFiles = array_slice($files, $offset, $perPage);

    $mediaUrls = array_map(function ($file) use ($activeDisk) {
        if ($activeDisk === 'local') {
            $file = str_replace('public/', '', $file);
            return asset('storage/' . $file);
        } else {
            return Storage::disk($activeDisk)->url($file);
        }
    }, $paginatedFiles);

    return [
        'mediaUrls' => $mediaUrls,
        'hasMore' => $offset + $perPage < $totalFiles,
    ];
}

if (!function_exists('setDefaultImage')) {
    function setDefaultImage($fileUrl = '')
    {
        $defaultImagePath = '/default-image/Default-Image.jpg';
        $defaultImage = asset($defaultImagePath);

        if (empty($fileUrl)) {
            return $defaultImage;
        }

        return $fileUrl;
    }
}



if (!function_exists('getImageUrlOrDefault')) {
    /**
     * Check if the image exists, return the file URL or the default image URL.
     *
     * @param string $fileUrl The full URL of the file to check
     * @return string The valid file URL or the default image URL
     */
    function getImageUrlOrDefault($fileUrl)
    {

        $fileUrl = setBaseUrlWithFileName($fileUrl);

        return $fileUrl;

    }
}


function formatDate($date)
{
    if (empty($date)) {
        return '-';
    }

    try {
        // Handle both date (Y-m-d) and datetime (Y-m-d H:i:s) formats

        $releaseDate = Carbon::parse($date);
        $defaultFormat = Setting::where('name', 'default_date_format')->where('datatype', 'misc')->value('val')  ?? 'Y-m-d';
        $userAgent = request()->header('user-agent');
        $isAppOrTv = false;

        $formatedDateApiRoutes = [
            'api.user-subscription_histroy',
            'api.account-setting',
            'api.v3.profile-details',
            'api.transaction-history',
            'api.cast_crew_details_v3',
        ];

        $currentRoute = \Illuminate\Support\Facades\Route::currentRouteName();

        $isWebFormat = in_array($currentRoute, $formatedDateApiRoutes);
        if($isWebFormat){
            return $releaseDate->format($defaultFormat);
        }



        if (!empty($userAgent)) {
            $ua = strtolower($userAgent);
            if (Str::contains($ua, ['android', 'iphone', 'ipad','androidtv','smarttv','firetv','tizen','apple tv','roku','tv'])) {
                $isAppOrTv = true;
            }
        }
        if ($isAppOrTv) {
            return $releaseDate->format('Y-m-d');
        }



        if (!empty($defaultFormat)) {
            return $releaseDate->format($defaultFormat);
        }

    } catch (\Exception $e) {
        return '-';
    }
}

/**
 * Convert PHP date format to Flatpickr date format
 * 
 * @param string $phpFormat PHP date format (e.g., 'Y-m-d', 'd/m/Y')
 * @return string Flatpickr date format
 */
function convertToFlatpickrFormat($phpFormat)
{
    // Flatpickr format mapping
    // Most formats are the same, but we handle special cases
    $formatMap = [
        'Y-m-d' => 'Y-m-d',
        'm-d-Y' => 'm-d-Y',
        'd-m-Y' => 'd-m-Y',
        'd/m/Y' => 'd/m/Y',
        'm/d/Y' => 'm/d/Y',
        'Y/m/d' => 'Y/m/d',
        'Y.m.d' => 'Y.m.d',
        'd.m.Y' => 'd.m.Y',
        'm.d.Y' => 'm.d.Y',
    ];
    
    // If exact match exists, return it
    if (isset($formatMap[$phpFormat])) {
        return $formatMap[$phpFormat];
    }
    
    // For formats with ordinal suffixes (jS F Y, etc.), remove the 'S' as flatpickr doesn't support it
    $flatpickrFormat = str_replace('S', '', $phpFormat);
    
    // Replace PHP format codes that flatpickr doesn't support with closest equivalents
    // j (day without leading zero) is supported
    // F (full month name) is supported
    // Y (4-digit year) is supported
    // M (short month name) is supported
    
    return $flatpickrFormat;
}

function formatTime($date)
{
    if (empty($date)) {
        return '-';
    }

    try {
        // Handle both date (Y-m-d) and datetime (Y-m-d H:i:s) formats in Dhaka timezone
        $timezone = Setting::where('name', 'default_time_zone')->value('val') ?? config('app.timezone', 'Asia/Dhaka');
        $releaseDate = Carbon::parse($date)->setTimezone($timezone);

        $defaultFormat = Setting::where('name', 'default_time_format')->where('datatype', 'misc')->value('val');

        if (!empty($defaultFormat)) {
            return $releaseDate->format($defaultFormat);
        }

        return $releaseDate->format('h:i A');
    } catch (\Exception $e) {
        return '-';
    }
}

function isenablemodule($key)
{
    $setting = Setting::where('name', $key)->value('val');
    return $setting !== null ? $setting : 0;
}

function gettmdbapiKey()
{
    $tbdb_key = Setting::where('name', 'tmdb_api_key')->value('val');
    return $tbdb_key !== null ? $tbdb_key : null;
}

function getCurrentProfile(?int $user_id, Request $request)  : int | null
{
    $token = $request->user()?->currentAccessToken();
    if (!$token instanceof \Laravel\Sanctum\PersonalAccessToken) {
        return null;
    }

    return Device::where('user_id', $user_id)
        ->where('sanctum_token_id', $token->getKey())
        ->value('active_profile');
}

function checkPlanLimit($user_id, $limit)
{
    $user = App\Models\User::where('id', $user_id)->first();

    if (isset($user->subscriptionPackage) && $user->subscriptionPackage->plan_id != null) {
        $subscription = $user->subscriptionPackage;

        if (isset($subscription['plan_type'])) {
            $planLimitations = json_decode($subscription['plan_type'], true);

            if (is_array($planLimitations)) {
                foreach ($planLimitations as $limitation) {
                    if (
                        isset($limitation['slug']) &&
                        $limitation['slug'] === $limit &&
                        isset($limitation['limitation_value'])
                    ) {
                        return $limitation['limitation_value'];
                    }
                }
            }
        }
    }
    return null;
}

function isSmtpConfigured()
{
    $host = config('mail.mailers.smtp.host');
    $port = config('mail.mailers.smtp.port');
    $username = config('mail.mailers.smtp.username');
    $password = config('mail.mailers.smtp.password');

    return !empty($host) &&
        !empty($port) &&
        !empty($username) &&
        !empty($password) &&
        $username !== 'null' &&
        $password !== 'null';
}

function decryptVideoUrl($encryptedUrl)
{
    try {
        // Remove escape characters
        $cleanUrl = stripslashes($encryptedUrl);


        // Decrypt the URL
        $decryptedUrl = Crypt::decryptString(urldecode($cleanUrl));
        // ✅ Normalize smart quotes to plain quotes
        $decryptedUrl = str_replace(
            ['“', '”', '‘', '’'],
            ['"', '"', "'", "'"],
            $decryptedUrl
        );

        // Check if the URL is a YouTube link
        preg_match("/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^\"&?\/ ]{11})/", $decryptedUrl, $youtubeMatches);
        if (isset($youtubeMatches[1])) {
            return ['platform' => 'youtube', 'videoId' => $youtubeMatches[1]];
        }

        // Check if the URL is a Vimeo link
        preg_match("/player\.vimeo\.com\/video\/(\d+)/", $decryptedUrl, $vimeoMatches);
        if (isset($vimeoMatches[1])) {
            return ['platform' => 'vimeo', 'videoId' => $vimeoMatches[1]];
        }

        preg_match('/(?:https?:\/\/)?(?:www\.)?(?:vimeo\.com\/(?:channels\/[\w]+\/|groups\/[\w]+\/videos\/|album\/\d+\/video\/|video\/|)(\d+)(?:$|\/|\?))/i', $decryptedUrl, $vimeoMatches);

        if (isset($vimeoMatches[1])) {
            return ['platform' => 'vimeo', 'videoId' => $vimeoMatches[1]];
        }

        // Check if the URL is an HLS stream (m3u8)
        if (preg_match('/\.m3u8$/', $decryptedUrl)) {
            return ['platform' => 'hls', 'url' => $decryptedUrl];
        }

        if (
            preg_match('/\.(workers\.dev|cloudfront\.net|amazonaws\.com|koyeb\.app)/', $decryptedUrl) ||
            preg_match('/filmager\.koyeb\.app\/(\d+)\?hash=/', $decryptedUrl)
        ) {

            return [
                'platform' => 'external',
                'videoId' => $decryptedUrl,
                'url' => $decryptedUrl
            ];
        }

        // Check if it's a local file
        $filePath = str_replace(url('/storage'), 'public', $decryptedUrl);
        if (Storage::exists($filePath)) {
            $actualPath = Storage::path($filePath);
            $fileMimeType = mime_content_type($actualPath);

            // Heuristic: check for x265/HEVC in filename or extension
            $isHEVC = false;
            if (preg_match('/\.(mkv|hevc)$/i', $actualPath) || stripos($actualPath, 'x265') !== false || stripos($actualPath, 'hevc') !== false) {
                $isHEVC = true;
            }

            return [
                'platform' => 'local',
                'url' => $actualPath,
                'mimeType' => $fileMimeType,
                'isHEVC' => $isHEVC
            ];
        }

        // If it's an external URL
        if (filter_var($decryptedUrl, FILTER_VALIDATE_URL)) {
            // Heuristic: check for x265/HEVC in URL
            $isHEVC = false;
            if (preg_match('/\.(mkv|hevc)$/i', $decryptedUrl) || stripos($decryptedUrl, 'x265') !== false || stripos($decryptedUrl, 'hevc') !== false) {
                $isHEVC = true;
            }

            return [
                'platform' => 'local',
                'url' => $decryptedUrl,
                'isHEVC' => $isHEVC
            ];
        }

        // Check for embedded iframe-type URL (e.g. short.icu, embedded players)
        if (preg_match('/<iframe.*?src=[\"\']([^\"\']+)[\"\'].*?>.*?<\/iframe>/i', $decryptedUrl, $embedMatch)) {
            return [
                'platform' => 'embedded',
                'url' => $embedMatch[1]
            ];
        }

        // OR: If the decrypted URL is directly an embeddable iframe source (no iframe tag)
        if (preg_match('/^(https?:\/\/)?(short\.icu|iframe\..+|embed\..+|player\..+)\//i', $decryptedUrl)) {
            return [
                'platform' => 'embedded',
                'url' => $decryptedUrl
            ];
        }


        // If no conditions are met
        return ['error' => 'File not found'];
    } catch (\Exception $e) {
        return ['error' => 'Invalid encrypted URL'];
    }
}

function extractFileNameFromUrl($url = '', $page_type = 'default')
{

    $normalizedFolder = $page_type;
    if ($page_type === 'season') {
        $normalizedFolder = 'tvshow/season';
    } elseif ($page_type === 'episode') {
        $normalizedFolder = 'tvshow/episode';
    }

    $isInCorrectFolder = strpos($url, '/storage/' . $normalizedFolder . '/video/') !== false
                      || strpos($url, '/storage/' . $normalizedFolder . '/image/') !== false;

    if (!$isInCorrectFolder) {
        $url = copyImageToFolder($url, $page_type);
    }

    return basename(parse_url($url, PHP_URL_PATH));
}

function copyImageToFolder($fileUrl, $folder = 'other')
{
    try {
        if (empty($fileUrl)) {
            return $fileUrl;
        }

        // Normalize folder names (same logic)
        if ($folder === 'season') {
            $folder = 'tvshow/season';
        } elseif ($folder === 'episode') {
            $folder = 'tvshow/episode';
        }

        // Get active storage disk
        $activeDisk = env('ACTIVE_STORAGE', 'local');
        $disk = Storage::disk($activeDisk);

        // Skip if already in correct folder
        if (strpos($fileUrl, '/storage/' . $folder . '/') !== false) {
            return $fileUrl;
        }

        // Parse URL and derive file path
        $parsedUrl = parse_url($fileUrl);
        $urlPath = ltrim($parsedUrl['path'] ?? '', '/');
        $sourcePath = null;

        if ($activeDisk === 'local') {
            if (($pos = strpos($urlPath, 'storage/')) !== false) {
                $sourcePath = 'public/' . substr($urlPath, $pos + 8);
            }
        } elseif ($activeDisk === 'bunny') {
            $bunnyCdnUrl = config('filesystems.bunny_pull_zone');
            if ($bunnyCdnUrl && str_starts_with($fileUrl, $bunnyCdnUrl)) {
                $sourcePath = ltrim(str_replace($bunnyCdnUrl, '', $fileUrl), '/');
            } else {
                $sourcePath = ltrim($urlPath, '/');
            }
        } else {
            if (($pos = strpos($urlPath, 'storage/')) !== false) {
                $sourcePath = ltrim(substr($urlPath, $pos + 8), '/');
            }
        }

        if (!$sourcePath) {
            return $fileUrl;
        }

        // Get file details
        $fileName = basename($sourcePath);
        $isVideo = preg_match('/\.(mp4|webm|avi|mov)$/i', $fileName);
        $type = $isVideo ? 'video' : 'image';

        // Build target path
        $targetPath = $activeDisk === 'local'
            ? "public/{$folder}/{$type}/{$fileName}"
            : "{$folder}/{$type}/{$fileName}";

        // If source file doesn’t exist, return as is
        if (!$disk->exists($sourcePath)) {
            return $fileUrl;
        }

        // Create target directory if needed
        $targetDir = dirname($targetPath);
        if (!$disk->exists($targetDir)) {
            if ($activeDisk === 'local') {
                $absoluteDir = storage_path('app/' . $targetDir);
                File::makeDirectory($absoluteDir, 0775, true, true);
            } else {
                $disk->makeDirectory($targetDir);
            }
        }

        // Copy file
        $copied = $disk->put($targetPath, $disk->get($sourcePath));

        if ($copied && $activeDisk === 'local') {
            $fullPath = storage_path('app/' . $targetPath);
            if (file_exists($fullPath)) {
                chmod($fullPath, 0664);
            }

            $dirPath = storage_path('app/' . $targetDir);
            if (is_dir($dirPath)) {
                chmod($dirPath, 0775);
            }
        }

        // Generate new URL
        if ($copied) {
            return $activeDisk === 'local'
                ? asset("storage/{$folder}/{$type}/{$fileName}")
                : $disk->url($targetPath);
        }

        return $fileUrl;
    } catch (\Throwable $e) {
        return $fileUrl;
    }
}




function setBaseUrlWithFileName($url = '', $type = 'image', $page_type = 'other')
{

    // Return a default image if the URL is empty
    if (empty($url)) {
        return setDefaultImage();
    }

    if($page_type == 'season'){
        $page_type = 'tvshow/season';
    }

    if($page_type == 'episode'){
        $page_type = 'tvshow/episode';
    }

    // Check if the URL is remote
    $isRemote = filter_var($url, FILTER_VALIDATE_URL) !== false;

    // Handle remote URL
    if ($isRemote) {
        // Return immediately if the remote image exists
        return $url;

        return checkImageExists($url) ? $url : setDefaultImage();
    }

    // Extract the file name
    $fileName = basename($url);

    $activeDisk =  config('filesystems.active');

    // Handle local storage
    if ($activeDisk === 'local') {
        $filePath = public_path("storage/$page_type/$type/$fileName");
        // Return local asset path if the file exists
        if (file_exists($filePath)) {
            return asset("storage/$page_type/$type/$fileName");
        }
    } elseif ($activeDisk == 'bunny') {
        $baseUrl = config('filesystems.bunny_pull_zone');
        $filePath = "$baseUrl/$page_type/$type/$fileName";
        return $filePath;

    } else {

        $baseUrl = rtrim(env('DO_SPACES_URL'), '/');
        $filePath = "$baseUrl/$page_type/$type/$fileName";

        if (checkImageExists($filePath)) {
            return $filePath;
        }
    }


    // Return a default image as fallback
   return setDefaultImage();

}

function setBaseUrlWithFileNameV2($url = '')
{
    // Check if the URL is remote
    $isRemote = filter_var($url, FILTER_VALIDATE_URL) !== false;

    // Handle remote URL
    if ($isRemote) {
        // Return immediately if the remote image exists
        return $url;

        return checkImageExists($url) ? $url : setDefaultImage();
    }

    // Extract the file name
    $fileName = basename($url);
    $activeDisk = env('ACTIVE_STORAGE', 'local');

    // Handle local storage
    if ($activeDisk === 'local') {
        $filePath = public_path("storage/swann-laravel/$fileName");

        // Return local asset path if the file exists
        if (file_exists($filePath)) {
            return asset("storage/swann-laravel/$fileName");
        }
    } elseif ($activeDisk == 'bunny') {
        $baseUrl = config('filesystems.bunny_pull_zone');
        $filePath = "$baseUrl/$page_type/$type/$fileName";
        return $filePath;

    } else {
        // Handle remote storage
        $baseUrl = rtrim(env('AWS_URL'), '/');
        $filePath = "$baseUrl/swann-laravel/$fileName";

        // Return remote file URL if it exists
        if (checkImageExists($filePath)) {
            return $filePath;
        }
    }

    // Return a default image as fallback
    return setDefaultImage();
}


function setBaseUrlSubtitleFile($url = '')
{

    $fileName = basename($url);
    $filePath = public_path("storage/subtitles/$fileName");

    if (file_exists($filePath)) {
        return asset("storage/subtitles/$fileName");
    }

    return null;

}


function checkImageExists($url)
{
    $headers = @get_headers($url);

    if ($headers && strpos($headers[0], '200') !== false) {
        return true;
    } else {
        return false;
    }
}


function getIdsBySlug($slug)
{
    return json_decode(App\Models\MobileSetting::getValueBySlug($slug));
}

function GetpaymentMethod($name)
{

    if ($name) {
        $payment_key = Setting::where('name', $name)->value('val');
        return $payment_key !== null ? $payment_key : null;
    }
    return null;
}

function GetSettingValue($key)
{

    if ($key) {
        $data = Setting::where('name', $key)->value('val');
        return $data !== null ? $data : null;
    }
    return null;
}


function getResourceCollection($model, $ids, $resource, $toArray = false)
{

    if (empty($ids) || !is_array($ids)) {
        return $toArray ? [] : collect();
    }
    $query = $model::whereIn('id', $ids);

    if (\Schema::hasColumn((new $model)->getTable(), 'status')) {
        $query->where('status', 1);
    }

    $items = $query->get();


    $collection = $resource::collection($items);

    return $toArray ? $collection->toArray(request()) : $collection;
}

function setavatarBaseUrl($url = '')
{

    if ($url != '') {

        $baseUrl = url('/');

        return $baseUrl . $url;

    } else {

        return setDefaultImage();
    }
}

function translate($text)
{

    $currentLang = app()->getLocale();
    return GoogleTranslate::trans($text, $currentLang);
}

/**
 * Translate a section/tab name and cache it per-locale.
 * - If locale is English, returns the original string
 * - Otherwise, uses GoogleTranslate and caches for 1 day
 */
if (!function_exists('translateTabName')) {
    function translateTabName(?string $name, ?string $locale = null): string
    {
        $name = (string) $name;
        if (trim($name) === '') {
            return $name;
        }

        $locale = $locale ?: app()->getLocale();
        if (strtolower((string) $locale) === 'en') {
            return $name;
        }

        $cacheKey = 'translated_tab_name:' . md5($name . '|' . $locale);

        return Cache::remember($cacheKey, 86400, function () use ($name, $locale) {
            try {
                return GoogleTranslate::trans($name, $locale);
            } catch (\Throwable $e) {
                Log::warning('Tab-name translation failed.', ['locale' => $locale, 'exception' => $e::class]);
                return $name;
            }
        });
    }
}


if (!function_exists('isActive')) {
    /**
     * Returns 'active' or 'done' class based on the current step.
     *
     * @param  string|array  $route
     * @param  string  $className
     * @return string
     */
    function isActive($route, $className = 'active')
    {
        $currentRoute = Route::currentRouteName();

        if (is_array($route)) {
            return in_array($currentRoute, $route) ? $className : '';
        }

        return $currentRoute == $route ? $className : '';
    }
}

function dbConnectionStatus(): bool
{
    try {
        DB::connection()->getPdo();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
function getThumbnail(string $name, string $type): ?string
{
    switch ($type) {
        case 'movie':
        case 'tv_show':
            $url = \Modules\Entertainment\Models\Entertainment::where('name', $name)->value('poster_url');
            break;

        case 'episode':
            $url = \Modules\Episode\Models\Episode::where('name', $name)->value('poster_url');
            break;

        case 'season':
            $url = \Modules\Season\Models\Season::where('name', $name)->value('poster_url');
            break;

        case 'video':
            $url = \Modules\Video\Models\Video::where('name', $name)->value('poster_url');
            break;

        default:
            return url('default-image/Default-Image.jpg');
    }

    $folder = match ($type) {
        'tv_show' => 'tvshow',
        default => $type,
    };

    return setBaseUrlWithFileName($url, 'image', $folder);
}

if (!function_exists('getFooterData')) {
    function getFooterData()
    {
        $cacheKey = 'footer_data';
        $data = Cache::get($cacheKey);
        if (!$data) {

            if (function_exists('isenablemodule') && isenablemodule('tvshow') == 1) {
                $data['premiumShows'] = \Modules\Entertainment\Models\Entertainment::where('movie_access', 'paid')
                    ->when(getCurrentProfileSession('is_child_profile') && getCurrentProfileSession('is_child_profile') != 0, function ($query) {
                        $query->where('is_restricted', 0);
                    })
                    ->where('status', 1)
                    ->take(4)
                    ->get();
            } else {
                $data['premiumShows'] = [];
            }

            if (function_exists('isenablemodule') && isenablemodule('movie') == 1) {
                $data['topMovies'] = \Modules\Entertainment\Models\Entertainment::where('type', 'movie')
                    ->when(getCurrentProfileSession('is_child_profile') && getCurrentProfileSession('is_child_profile') != 0, function ($query) {
                        $query->where('is_restricted', 0);
                    })
                    ->orderBy('release_date', 'desc')
                    ->where('status', 1)
                    ->take(4)
                    ->get();
            } else {
                $data['topMovies'] = [];
            }
            $data['pages'] = Page::where('status', 1)
                             ->where('deleted_at', '=', null)
                              ->get();

            $data['app_store_url'] = GetSettingValue('ios_url');
            $data['play_store_url'] = GetSettingValue('android_url');
            $data['helpline_number'] = GetSettingValue('helpline_number');
            $data['inquriy_email'] = GetSettingValue('inquriy_email');
            $data['short_description'] = GetSettingValue('short_description');
            $data['facebook_url'] = GetSettingValue('facebook_url');
            $data['instagram_url'] = GetSettingValue('instagram_url');
            $data['youtube_url'] = GetSettingValue('youtube_url');
            $data['x_url'] = GetSettingValue('x_url');

            Cache::put($cacheKey, $data);
        }
        return $data;
    }
}


function setEnvValue($key, $value)
{
    $path = base_path('.env');

    // Ensure the .env file exists
    if (file_exists($path)) {
        $envContent = file_get_contents($path);

        // Check if the key already exists
        if (strpos($envContent, "$key=") !== false) {
            // Replace the existing key value pair
            $envContent = preg_replace("/^$key=.*/m", "$key=$value", $envContent);
        } else {
            // Add the key value pair if not found
            $envContent .= "\n$key=$value";
        }

        // Write the content back to the .env file
        file_put_contents($path, $envContent);

        Artisan::call('config:clear');
        Artisan::call('config:cache');
    }
}

function removeCurrentProfileSession()
{
    \Session::forget('current_profile_' . auth()->id() . '');
}
function getActionPlan($slug)
{
    $plan_type = NULL;
    if (auth()->id()) {
        $activeSubscriptions = Modules\Subscriptions\Models\Subscription::where('user_id', auth()->id())
            ->with('subscription_transaction')
            ->where('status', 'active')
            ->where('end_date', '>', now())
            ->orderBy('id', 'desc')
            ->first(['id', 'plan_type', 'plan_id']);

        if (!empty($activeSubscriptions)) {
            $plan_type = collect(json_decode($activeSubscriptions->plan_type));
            $plan_type = $plan_type->where('slug', $slug)->where('status', '1');
        }
    }

    return $plan_type;

}


function setCurrentProfileSession($checkMultiProfile = 0, $id = NULL)
{
    // If no explicit profile ID provided, don't auto-select - set to null
    if ($checkMultiProfile == 0 && $id === NULL) {
        removeCurrentProfileSession();
        \Session::put('current_profile_' . auth()->id() . '', null);
        return;
    }
    
    if ($checkMultiProfile == 0) {
        $name = auth()->user()->first_name . " " . auth()->user()->last_name;
        $currentProfile = UserMultiProfile::where([
            'user_id' => auth()->user()->id,
            'name' => $name
        ])
            ->orderBy('id', 'DESC')->first();
    } else {
        $currentProfile = UserMultiProfile::where('id', $id)->first();
    }

    removeCurrentProfileSession();

    \Session::put('current_profile_' . auth()->id() . '', $currentProfile);
}

function getCurrentProfileSession($key = NULL)
{
    $current_profile = \Session::get('current_profile_' . auth()->id() . '');

    if (!empty($current_profile)) {
        (!empty($key)) && $current_profile = $current_profile->$key;
        return $current_profile;
    }
    
    // Don't auto-select by name - return NULL if no profile in session
    return NULL;
}

function getLoggedUserPin($profileId)
{
    if (!auth()->user()) {
        return "no";
    }
    ### check pin is empty or not
    $pincheck = DB::table('users')->where('id', auth()->user()->id)->value('pin_hash')
        ?? DB::table('users')->where('id', auth()->user()->id)->value('pin');
    if (empty($pincheck)) {
        return "no";
    }

    $parentUser = DB::table('users')->where('id', $profileId)->first();
    if (!$parentUser || $parentUser->is_parental_lock_enable == 0) {
        return "no";
    }

    $Ischild = DB::table('user_multi_profiles')
        ->where('id', $profileId)
        // ->where('is_child_profile',1)
        ->pluck('is_child_profile')
        ->first();

    if (($Ischild == 0 || $Ischild == 1) && getCurrentProfileSession('is_child_profile') == 0) {
        return "no";
    }

    if (getCurrentProfileSession('is_child_profile') == 1 && $Ischild == 0) {
        return "yes";
    } else {
        return "no";
    }

    return "no";
}



function pr($data)
{
    $result = print_r($data);
    exit();
    return $result;
}

function isenablemoduleV2($key)
{
    $responseData = Cache::remember('setting_v2', 3600, function () {
        return Setting::select(['id', 'val', 'name', 'type'])
            ->get()
            ->keyBy('name')
            ->toArray();
    });

    return (isset($responseData[$key]['val']) && !empty($responseData[$key]['val'])) ? $responseData[$key]['val'] : 0;
}

function loggedUserId()
{
    if (!empty(auth()->id())) {
        return auth()->id();
    } else {
        return 0;
    }
}

function getRequestedProfileId()
{
    if (isset(request()->profile_id) && !empty(request()->profile_id)) {
        return request()->profile_id;
    } else {
        return 0;
    }
}
function defaultCurrency()
{
    $currency = Currency::where('is_primary', 1)->first();
    $currency = $currency ? strtolower($currency->currency_code) : 'inr';
    return $currency;
}

function getCurrencySymbolByCurrency($currency)
{
    $currency = Currency::where('currency_code', strtoupper($currency))->first();
    $currency_symbol = $currency ? $currency->currency_symbol : '₹';
    return $currency_symbol;
}

function encryptId($id)
{
    return rtrim(strtr(base64_encode("{$id}"), '+/', '-_'), '=');
}

function decryptId($id)
{
    return base64_decode(strtr($id, '-_', '+/'));
}

/**
 * Convert SRT subtitle file to VTT format
 *
 * @param string $srtContent The content of the SRT file
 * @return string The converted VTT content
 */
function convertSrtToVtt($srtContent) {
    // Add VTT header
    $vttContent = "WEBVTT\n\n";

    // Split the SRT content into subtitle blocks
    $blocks = preg_split('/\n\s*\n/', trim($srtContent));

    foreach ($blocks as $block) {
        $lines = explode("\n", trim($block));

        // Skip if not enough lines
        if (count($lines) < 3) continue;

        // Get timestamp line
        $timestamp = $lines[1];

        // Convert SRT timestamp format to VTT format
        $timestamp = str_replace(',', '.', $timestamp);

        // Get subtitle text
        $text = implode("\n", array_slice($lines, 2));

        // Add to VTT content
        $vttContent .= $timestamp . "\n" . $text . "\n\n";
    }

    return $vttContent;
}

if (! function_exists('cacheApiResponse')) {
    function cacheApiResponse($key, $ttl, $callback) {
        $startTime = microtime(true);

        $data = Cache::remember($key, $ttl, $callback);

        $endTime = microtime(true);
        $responseTimeMs = ($endTime - $startTime) * config('constant.MICROSECOND_TO_SECOND',1000);

        return [
            'data' => $data,
            'response_time_ms' => round($responseTimeMs, 2)
        ];
    }
}

function dateFormatList()
{
    return [
        'Y-m-d' => date('Y-m-d'),
        'm-d-Y' => date('m-d-Y'),
        'd-m-Y' => date('d-m-Y'),
        'd/m/Y' => date('d/m/Y'),
        'm/d/Y' => date('m/d/Y'),
        'Y/m/d' => date('Y/m/d'),
        'Y.m.d' => date('Y.m.d'),
        'd.m.Y' => date('d.m.Y'),
        'm.d.Y' => date('m.d.Y'),
        'jS M Y' => date('jS M Y'),
        'M jS Y' => date('M jS Y'),
        'D, M d, Y' => date('D, M d, Y'),
        'D, d M, Y' => date('D, d M, Y'),
        'D, M jS Y' => date('D, M jS Y'),
        'D, jS M Y' => date('D, jS M Y'),
        'F j, Y' => date('F j, Y'),
        'd F, Y' => date('d F, Y'),
        'jS F, Y' => date('jS F, Y'),
        'l jS F Y' => date('l jS F Y'),
        'l, F j, Y' => date('l, F j, Y'),

    ];
}

function timeFormatList()
{
    $timeFormats = [
        "H:i",
        "H:i:s",
        "g:i A",
        "H:i:s T",
        "H:i:s.v",
        "U",
        "u",
        "G.i",
        "@BMT"
    ];

    return array_map(function ($format) {
        return ['format' => $format, 'time' => getTimeInFormat($format)];
    }, $timeFormats);
}

function getTimeInFormat($format)
{
    $now = new DateTime();
    $hours = $now->format('H');
    $minutes = $now->format('i');
    $seconds = $now->format('s');
    $milliseconds = $now->format('v');
    $totalSecondsSinceMidnight = ($hours * config('constant.HOUR_TO_SECOND',3600)) + ($minutes * 60) + $seconds;

    switch ($format) {
        case "H:i":
            return "$hours:$minutes";
        case "H:i:s":
            return "$hours:$minutes:$seconds";
        case "g:i A":
            $ampm = $hours >= 12 ? 'PM' : 'AM';
            $formattedHours = $hours % 12 || 12;
            return "$formattedHours:$minutes $ampm";
        case "H:i:s T":
            return "$hours:$minutes:$seconds UTC";
        case "H:i:s.v":
            return "$hours:$minutes:$seconds.$milliseconds";
        case "U":
            return $now->getTimestamp();
        case "u":
            return $milliseconds * config('constant.MICROSECOND_TO_SECOND',1000);
        case "G.i":
            return $hours + $minutes / 60;
        case "@BMT":
            $swatchBeat = floor($totalSecondsSinceMidnight / 86.4);
            return "@{$swatchBeat}BMT";
        default:
            return "Invalid format";
    }
}

function getDeviceType($request){
    $userAgent = $request->header('User-Agent', '');

        if (preg_match('/mobile/i', $userAgent)) {
            $device_type = 'mobile';
        } elseif (preg_match('/smart-tv|tv|hbbtv/i', $userAgent)) {
            $device_type = 'tv';
        } else {
            $device_type = 'desktop';
        }
        return $device_type;
}


    /**
     * Set content access and required plan level based on content type and user plan
     *
     * @param array $item The content item
     * @param int $user_id The user ID
     * @param int $userPlanLevel The user's plan level
     * @return array The updated item with access information
     */
    if (!function_exists('setContentAccess')) {
    /**
     * Set content access and required plan level based on content type and user plan
     *
     * @param array $item The content item
     * @param int $user_id The user ID
     * @param int $userPlanLevel The user's plan level
     * @return array The updated item with access information
     */
    function setContentAccess($item, $user_id, $userPlanLevel, $purchasedIds =null ) {


        $accessType = strtolower(trim($item['access'] ?? ''));
          switch ($accessType) {
              case 'free':
                  $item['has_content_access'] = 1;
                  $item['required_plan_level'] = 0;
                  break;

              case 'paid':
                  $requiredLevel = $item['plan_id'] ?? 0;
                  $item['has_content_access'] = ($userPlanLevel >= $requiredLevel) ? 1 : 0;
                  $item['required_plan_level'] = $requiredLevel;
                  break;

              case 'pay-per-view':
                    if (is_array($purchasedIds) && !empty($purchasedIds)) {
                        $contentType = strtolower($item['type'] ?? '');
                        if (isset($purchasedIds[$contentType]) && is_array($purchasedIds[$contentType])) {
                            $item['has_content_access'] = in_array($item['id'], $purchasedIds[$contentType]) ? 1 : 0;
                        } elseif (!isset($purchasedIds['movie']) && !isset($purchasedIds['tvshow']) && !isset($purchasedIds['video'])) {
                            $item['has_content_access'] = in_array($item['id'], $purchasedIds) ? 1 : 0;
                        } else {
                            $item['has_content_access'] = 0;
                        }
                    }                    
                    else {
                        $item['has_content_access'] = Entertainment::isPurchased($item['id'], $item['type'], $user_id) ? 1 : 0;
                    }
                    $item['required_plan_level'] = 0;
                  break;

              default:
                  $item['has_content_access'] = 0;
                  $item['required_plan_level'] = 0;
                  break;
          }
        return $item;
    }

}





function bunnyIngestAndGetM3u8(string $sourceUrl, string $title): ?string
{
    return app(\App\Services\Media\BunnyStreamService::class)->ingestAndGetM3u8($sourceUrl, $title);
}

function formatDateTimeWithTimezone($value, $type = null)
{
    if (empty($value)) {
        return null;
    }

    $settings = Cache::remember('app_datetime_settings', 3600, function () {
        return DB::table('settings')
            ->whereIn('name', ['default_date_format', 'default_time_format', 'default_time_zone'])
            ->pluck('val', 'name')
            ->toArray();
    });

    $timezone    = $settings['default_time_zone'] ?? config('app.timezone', 'Asia/Dhaka');
    $dateFormat  = $settings['default_date_format'] ?? 'Y-m-d';
    $timeFormat  = $settings['default_time_format'] ?? 'h:i A';

    try {
        $carbon = Carbon::parse($value)->setTimezone($timezone);

        switch ($type) {
            case 'date':
                return $carbon->format($dateFormat);
            case 'time':
                return $carbon->format($timeFormat);
            default:
                return $carbon->format("{$dateFormat} {$timeFormat}");
        }
    } catch (Exception $e) {
        return $value;
    }
}

/**
 * Delete video from Bunny Stream by GUID or title
 * 
 * @param string $identifier Video GUID or title
 * @param bool $isGuid Whether identifier is GUID (true) or title (false)
 * @return bool Success status
 */
function bunnyDeleteVideo(string $identifier, bool $isGuid = false): bool
{
    return app(\App\Services\Media\BunnyStreamService::class)->deleteVideo($identifier, $isGuid);
}

function extractBunnyGuid(string $m3u8Url): ?string
{
    return app(\App\Services\Media\BunnyStreamService::class)->extractGuid($m3u8Url);
}

function deleteBunnyStreamVideoByFile(string $fileName): bool
{
    return app(\App\Services\Media\BunnyStreamService::class)->deleteVideoByFile($fileName);
}

/**
 * Clear related cache items instead of flushing entire cache
 * 
 * @param string|array $keys Cache key(s) to clear
 * @param string|null $module Module name to clear related caches (e.g., 'banner', 'entertainment', 'episode')
 * @return void
 */
function clearRelatedCache($keys, $module = null)
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearRelated($keys, $module);
}

function clearCacheByPattern($pattern)
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearByPattern((string) $pattern);
}

function clearBannerDataCache()
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearBannerData();
}

if (!function_exists('clearWatchlistCache')) {
    function clearWatchlistCache()
    {
        app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearWatchlist();
    }
}

function clearSearchV3Cache()
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearSearchV3();
}

function clearDashboardCache()
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearDashboard();
}

function clearPayPerViewCache()
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearPayPerView();
}

function clearLiveTvDashboardCache()
{
    app(\App\Services\Cache\ApiCacheInvalidationService::class)->clearLiveTvDashboard();
}

/**
 * API Response Helper Functions
 * These functions provide a convenient way to use ApiResponse class
 */

/**
 * Return a successful API response
 * 
 * @param mixed $data The response data (optional)
 * @param string|null $message Success message (optional)
 * @param int $code HTTP status code (default: 200)
 * @param array $additional Additional fields to include in response
 * @return \Illuminate\Http\JsonResponse
 */
function apiSuccess($data = null, ?string $message = null, int $code = 200, array $additional = [])
{
    return \App\Http\Responses\ApiResponse::success($data, $message, $code, $additional);
}

/**
 * Return an error API response
 * 
 * @param string $message Error message
 * @param int $code HTTP status code (default: 400)
 * @param mixed $errors Validation errors or additional error data (optional)
 * @param array $additional Additional fields to include in response
 * @return \Illuminate\Http\JsonResponse
 */
function apiError(string $message, int $code = 400, $errors = null, array $additional = [])
{
    return \App\Http\Responses\ApiResponse::error($message, $code, $errors, $additional);
}

if (! function_exists('is_safe_login_redirect_url')) {
    /**
     * Whether a post-login redirect target is an internal, non-auth URL.
     */
    function is_safe_login_redirect_url(string $url): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if (preg_match('#/(login|otp-login|register|forgetpassword)(/|\?|$)#i', $url)) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        return str_starts_with($url, url('/'));
    }
}

if (! function_exists('store_login_intended_url')) {
    /**
     * Persist intended URL in session (used instead of ?redirect= query params).
     */
    function store_login_intended_url(?string $url): void
    {
        if (! is_string($url) || $url === '' || ! is_safe_login_redirect_url($url)) {
            return;
        }

        if (str_starts_with($url, '/')) {
            $url = url($url);
        }

        session(['url.intended' => $url]);
    }
}

if (! function_exists('pull_login_redirect_url')) {
    /**
     * Resolve and clear the post-login redirect URL.
     */
    function pull_login_redirect_url(): string
    {
        $default = route('manage-profile');
        $intended = session()->pull('url.intended');

        if (! is_string($intended) || $intended === '' || ! is_safe_login_redirect_url($intended)) {
            return $default;
        }

        if (str_starts_with($intended, '/')) {
            return url($intended);
        }

        return $intended;
    }
}

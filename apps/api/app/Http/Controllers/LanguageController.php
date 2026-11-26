<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;

class LanguageController extends Controller
{
    public function switch($language)
    {
        $language = strtolower($language);
        $availableLocales = array_keys(config('app.available_locales', ['en' => 'English (EN)']));
        abort_unless(in_array($language, $availableLocales, true), 404);

        app()->setLocale($language);

        session()->put('locale', $language);
        $dir = 'ltr';
        if (in_array($language, ['ar', 'dv', 'ff', 'ur', 'he', 'ku', 'fa'])) {
            $dir = 'rtl';
        }
        setlocale(LC_TIME, $language);

        Carbon::setLocale($language);
        session()->put('dir',  $dir);

        flash()->success(__('messages.language_changed_to').' '.strtoupper($language))->important();

        return redirect()->back();
    }
}

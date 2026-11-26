<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Entertainments (Movies & TV Shows)
        if (Schema::hasTable('entertainments')) {
            Schema::table('entertainments', function (Blueprint $table) {
                if (!Schema::hasColumn('entertainments', 'name_bn')) {
                    $table->string('name_bn')->nullable()->after('name');
                }
                if (!Schema::hasColumn('entertainments', 'description_bn')) {
                    $table->text('description_bn')->nullable()->after('description');
                }
            });
        }

        // 2. Episodes
        if (Schema::hasTable('episodes')) {
            Schema::table('episodes', function (Blueprint $table) {
                if (!Schema::hasColumn('episodes', 'name_bn')) {
                    $table->string('name_bn')->nullable()->after('name');
                }
                if (!Schema::hasColumn('episodes', 'description_bn')) {
                    $table->text('description_bn')->nullable()->after('description');
                }
            });
        }

        // 3. Genres
        if (Schema::hasTable('genres')) {
            Schema::table('genres', function (Blueprint $table) {
                if (!Schema::hasColumn('genres', 'name_bn')) {
                    $table->string('name_bn')->nullable()->after('name');
                }
                if (!Schema::hasColumn('genres', 'description_bn')) {
                    $table->text('description_bn')->nullable()->after('description');
                }
            });
        }

        // 4. Live TV Channel
        if (Schema::hasTable('live_tv_channel')) {
            Schema::table('live_tv_channel', function (Blueprint $table) {
                if (!Schema::hasColumn('live_tv_channel', 'name_bn')) {
                    $table->string('name_bn')->nullable()->after('name');
                }
                if (!Schema::hasColumn('live_tv_channel', 'description_bn')) {
                    $table->text('description_bn')->nullable()->after('description');
                }
            });
        }

        // 5. Videos
        if (Schema::hasTable('videos')) {
            Schema::table('videos', function (Blueprint $table) {
                if (!Schema::hasColumn('videos', 'name_bn')) {
                    $table->string('name_bn')->nullable()->after('name');
                }
                if (!Schema::hasColumn('videos', 'description_bn')) {
                    $table->text('description_bn')->nullable()->after('description');
                }
            });
        }

        // Populate initial Bengali translations for existing content
        $this->seedInitialBengaliContent();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('entertainments')) {
            Schema::table('entertainments', function (Blueprint $table) {
                if (Schema::hasColumn('entertainments', 'name_bn')) {
                    $table->dropColumn('name_bn');
                }
                if (Schema::hasColumn('entertainments', 'description_bn')) {
                    $table->dropColumn('description_bn');
                }
            });
        }

        if (Schema::hasTable('episodes')) {
            Schema::table('episodes', function (Blueprint $table) {
                if (Schema::hasColumn('episodes', 'name_bn')) {
                    $table->dropColumn('name_bn');
                }
                if (Schema::hasColumn('episodes', 'description_bn')) {
                    $table->dropColumn('description_bn');
                }
            });
        }

        if (Schema::hasTable('genres')) {
            Schema::table('genres', function (Blueprint $table) {
                if (Schema::hasColumn('genres', 'name_bn')) {
                    $table->dropColumn('name_bn');
                }
                if (Schema::hasColumn('genres', 'description_bn')) {
                    $table->dropColumn('description_bn');
                }
            });
        }

        if (Schema::hasTable('live_tv_channel')) {
            Schema::table('live_tv_channel', function (Blueprint $table) {
                if (Schema::hasColumn('live_tv_channel', 'name_bn')) {
                    $table->dropColumn('name_bn');
                }
                if (Schema::hasColumn('live_tv_channel', 'description_bn')) {
                    $table->dropColumn('description_bn');
                }
            });
        }

        if (Schema::hasTable('videos')) {
            Schema::table('videos', function (Blueprint $table) {
                if (Schema::hasColumn('videos', 'name_bn')) {
                    $table->dropColumn('name_bn');
                }
                if (Schema::hasColumn('videos', 'description_bn')) {
                    $table->dropColumn('description_bn');
                }
            });
        }
    }

    /**
     * Populate Bengali titles for existing media content.
     */
    private function seedInitialBengaliContent(): void
    {
        $entertainmentTranslations = [
            'The Life of Prophet Muhammad' => 'মহানবী হযরত মুহাম্মদ (সাঃ)-এর জীবনচরিত',
            'Stories of the Prophets' => 'নবীদের কাহিনী',
            'The Golden Age of Islamic Civilization' => 'ইসলামি সভ্যতার স্বর্ণযুগ',
            'Companions of the Prophet (The Rightly Guided Caliphs)' => 'খুলাফায়ে রাশিদীন (রাসূলের সাহাবীগণ)',
            'Halal Kids: Adventures of Noor & Bilal' => 'হালাল কিডস: নূর ও বিলালের অভিযান',
            'The Splendor of Al-Andalus: Muslim Spain' => 'আল-আন্দালুসের গৌরব: মুসলিম স্পেন',
            'Tafseer of Surah Yusuf: The Best of Stories' => 'সূরা ইউসুফের তাফসির: সেরা কাহিনী',
            'The Ottoman Era: Faith, Justice & Architecture' => 'উসমানীয় যুগ: ঈমান, ন্যায়বিচার ও স্থাপত্য',
            '40 Hadith of Imam An-Nawawi Explained' => 'ইমাম আন-নববীর ৪০ হাদিসের ব্যাখ্যা',
            'Journey of the Soul: From This Life to the Akhirah' => 'আত্মার যাত্রা: ইহকাল থেকে পরকাল',
            'Pioneering Muslim Women in History' => 'ইতিহাসের অগ্রগামী মুসলিম নারীরা',
            'Halal Kids: Quranic Animals & Moral Tales' => 'হালাল কিডস: কুরআনের প্রাণী ও নীতিকথা',
            'Islamic History' => 'ইসলামের ইতিহাস',
            'Bengali Waz Collection' => 'বাংলা ওয়াজ কালেকশন',
            'Ramadan Reflections' => 'রমজানের ভাবনা ও আমল',
            'Understanding the Quran' => 'কুরআনকে উপলব্ধি ও অনুধাবন',
            'The Holy Kaaba: Sanctuary of Monotheism' => 'পবিত্র কাবা: তাওহীদের কেন্দ্রস্থল',
            'The Farewell Sermon: Universal Human Rights' => 'বিদায় হজের ভাষণ: সর্বজনীন মানবাধিকার',
            'Guardians of the Sunnah: The Science of Hadith' => 'সুন্নাহর অতন্দ্র প্রহরী: হাদিস বিজ্ঞান',
            'Saladin and the Liberation of Jerusalem' => 'সুলতান সালাহউদ্দিন ও বায়তুল মুকাদ্দাস বিজয়',
            'The Heart\'s Medicine: Dhikr & Serenity' => 'অন্তরের আরোগ্য: জিকির ও প্রশান্তি',
            'Miracles of Creation: Signs in Nature' => 'সৃষ্টির বিস্ময়: প্রকৃতিতে মহান স্রষ্টার নিদর্শন',
            'Al-Quds: The Sanctity of Masjid Al-Aqsa' => 'আল-কুদস: পবিত্র মসজিদুল আকসা',
            'The Science of Astronomy in Islamic Civilization' => 'ইসলামি সভ্যতায় জ্যোতির্বিজ্ঞান',
            'Ibn Battuta: Master Traveler of the Medieval World' => 'ইবনে বতুতা: মধ্যযুগের বিশ্বপর্যটক',
            'The Sacred Geometry of Islamic Art & Mosques' => 'ইসলামি শিল্পকলা ও মসজিদের পবিত্র জ্যামিতি',
            'Imam Abu Hanifah: The Jurist of Integrity' => 'ইমাম আবু হানিফা: নীতিবান ফকীহ',
            'Imam Malik: The Scholar of Madinah and the Muwatta' => 'ইমাম মালিক: মদিনার আলেম ও মুয়াত্তা',
            'Imam Ash-Shafi\'i: Father of Legal Methodology' => 'ইমাম আশ-শাফেঈ: উসূলে ফিকহের জনক',
            'Imam Ahmad ibn Hanbal: Steadfastness in Truth' => 'ইমাম আহমদ ইবনে হাম্বল: সত্যের ওপর অবিচলতা',
            'The Miracle of Zamzam: Waters of Life' => 'জমজমের অলৌকিকতা: জীবনের বারিধারা',
            'Battle of Badr: The Day of Criterion' => 'বদরের যুদ্ধ: সত্য-মিথ্যার ফয়সালার দিন',
            'Avicenna (Ibn Sina): Master of Medicine and Healing' => 'ইবনে সিনা (আভিসেনা): চিকিৎসা বিজ্ঞানের পুরোধা',
            'Surah Al-Kahf: Friday Reflection & Recitation' => 'সূরা আল-কাহফ: জুমার দিনের তেলাওয়াত ও উপদেশ',
            'The Night of Power (Laylatul Qadr): Better Than a Thousand Months' => 'লাইলাতুল কদর: হাজার মাসের চেয়ে উত্তম রাত',
            'Tazkiyah: Cleansing the Heart of Arrogance & Envy' => 'তাযকিয়ায়ে নফস: অহংকার ও হিংসা থেকে অন্তরের শুদ্ধি',
            'The Ethics of Wealth: Zakat, Sadaqah & Halal Commerce' => 'সম্পদের নীতিশাস্ত্র: যাকাত, সাদাকাহ ও হালাল ব্যবসা',
            'Daughters of the Prophet: Grace, Dignity & Sacrifice' => 'রাসূলের কন্যাগণ: মর্যাদা, সম্মান ও ত্যাগ',
            'Islamic Cordoba: The Mezquita and the Forest of Marble' => 'ইসলামি কর্ডোভা: মেসকিতা ও মার্বেলের অরণ্য',
            'The Art of Arabic Calligraphy: Devotion in Ink' => 'আরবি ক্যালিগ্রাফি: কালিতে বিশ্বাসের রূপায়ন',
            'The Farewell Du\'a: Tearful Prayers from Arafat' => 'আরাফাতের অশ্রুসিক্ত মোনাজাত ও দোয়া',
            'Rumi & Al-Ghazali: The Pursuit of Inner Truth' => 'রুমি ও ইমাম গাজ্জালী: অভ্যন্তরীণ সত্যের অনুসন্ধান',
            'Spiritual Revival: Returning to Allah in Sincere Repentance' => 'আধ্যাত্মিক জাগরণ: আল্লাহর কাছে খাঁটি তওবা',
            'The Prophet\'s Kindness to Children & Orphans' => 'শিশুদের ও এতিমদের প্রতি রাসূলের পরম মমতা',
            'Signs of the Creator: Ocean Waves & Mountain Anchors' => 'মহান স্রষ্টার নিদর্শন: সমুদ্রের তরঙ্গ ও পর্বতমালার ভিত্তি',
        ];

        foreach ($entertainmentTranslations as $en => $bn) {
            DB::table('entertainments')
                ->where('name', $en)
                ->update(['name_bn' => $bn]);
        }

        $genreTranslations = [
            'Prophetic Biographies' => 'নবীদের জীবনী',
            'Quran & Tafseer' => 'কুরআন ও তাফসির',
            'Islamic History' => 'ইসলামের ইতিহাস',
            'Waz & Inspiring Bayan' => 'ওয়াজ ও বয়ান',
            'Ramadan & Worship' => 'রমজান ও ইবাদত',
            'Documentaries' => 'তথ্যচিত্র',
            'Faith & Family' => 'ঈমান ও পরিবার',
            'Sacred Sanctuaries' => 'পবিত্র স্থানসমূহ',
            'Hadith & Sunnah' => 'হাদিস ও সুন্নাহ',
        ];

        foreach ($genreTranslations as $en => $bn) {
            DB::table('genres')
                ->where('name', $en)
                ->update(['name_bn' => $bn]);
        }

        $liveTvTranslations = [
            'Jumma khutba from Masjid Al-Haram' => 'মসজিদুল হারাম থেকে জুমার খুতবা',
            'Peace TV' => 'পিস টিভি',
            'Huda TV' => 'হুদা টিভি',
            'iTV' => 'আই টিভি',
            'ATN Bangla' => 'এটিএন বাংলা',
            'Madinah Live - Al-Masjid An-Nabawi' => 'মদিনা লাইভ - মসজিদে নববী',
            'Quran Kareem TV' => 'কুরআন কারীম টিভি',
            'Islam Channel UK' => 'ইসলাম চ্যানেল ইউকে',
            'Masjid Al-Aqsa Live Broadcast' => 'মসজিদুল আকসা সরাসরি সম্প্রচার',
            'Eman Channel' => 'ঈমান চ্যানেল',
            'Bayan TV (Waz & Tilawah)' => 'বয়ান টিভি (ওয়াজ ও তেলাওয়াত)',
            'Noor TV International' => 'নূর টিভি ইন্টারন্যাশনাল',
        ];

        foreach ($liveTvTranslations as $en => $bn) {
            DB::table('live_tv_channel')
                ->where('name', $en)
                ->update(['name_bn' => $bn]);
        }

        // For videos: copy current name to name_bn if name is in Bengali
        if (Schema::hasTable('videos')) {
            DB::table('videos')->whereNull('name_bn')->update([
                'name_bn' => DB::raw('name'),
            ]);
        }

        // For episodes: provide Bengali episode prefix where applicable
        if (Schema::hasTable('episodes')) {
            $episodes = DB::table('episodes')->select('id', 'name')->get();
            $enNum = ['0','1','2','3','4','5','6','7','8','9'];
            $bnNum = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];

            foreach ($episodes as $ep) {
                if (preg_match('/^Episode\s+(\d+):\s*(.+)$/i', $ep->name, $matches)) {
                    $num = str_replace($enNum, $bnNum, $matches[1]);
                    $subTitle = $matches[2];
                    $bnName = "পর্ব {$num}: {$subTitle}";
                    DB::table('episodes')->where('id', $ep->id)->update(['name_bn' => $bnName]);
                } else {
                    DB::table('episodes')->where('id', $ep->id)->update(['name_bn' => $ep->name]);
                }
            }
        }
    }
};

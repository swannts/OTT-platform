#!/usr/bin/env python3
"""
Script to fetch authentic, verified Islamic YouTube thumbnails for all entertainments,
episodes, and banners, and update the database accordingly.
"""

import urllib.request
import urllib.parse
import re
import json
import time
import subprocess
import sys

# Targeted high-relevance search queries for every entertainment title
QUERY_MAP = {
    # TV Shows
    'The Life of Prophet Muhammad': 'The Life of Prophet Muhammad full documentary Seerah',
    'Stories of the Prophets': 'Stories of the Prophets Ibn Kathir full series',
    'The Golden Age of Islamic Civilization': 'Golden Age of Islamic Civilization documentary Al Jazeera',
    'Companions of the Prophet (The Rightly Guided Caliphs)': 'The Rightly Guided Caliphs Abu Bakr Umar Uthman Ali documentary',
    'Halal Kids: Adventures of Noor & Bilal': 'Zaky One4kids Islamic cartoon for children',
    'The Splendor of Al-Andalus: Muslim Spain': 'When The Moors Ruled In Europe Al Andalus Islamic Spain documentary',
    'Tafseer of Surah Yusuf: The Best of Stories': 'Tafseer Surah Yusuf Nouman Ali Khan Bayyinah',
    'The Ottoman Era: Faith, Justice & Architecture': 'Rise of Ottoman Empire Islamic history documentary',
    '40 Hadith of Imam An-Nawawi Explained': '40 Hadith of Imam An Nawawi explanation Islamic lecture',
    'Journey of the Soul: From This Life to the Akhirah': 'Journey of the Soul After Death Islamic lecture Yaqeen Institute',
    'Pioneering Muslim Women in History': 'Great Women in Islamic History Khadijah Aisha Fatima documentary',
    'Halal Kids: Quranic Animals & Moral Tales': 'Omar & Hana Islamic songs and stories for kids',

    # Movies
    'Islamic History': 'Lost Islamic History documentary full',
    'Bengali Waz Collection': 'Mizanur Rahman Azhari new waz full bangla',
    'Ramadan Reflections': 'Ramadan Reflections Mufti Menk spiritual reminder',
    'Understanding the Quran': 'Understanding The Quran Nouman Ali Khan',
    'The Holy Kaaba: Sanctuary of Monotheism': 'The Holy Kaaba Makkah 4K documentary history',
    'The Farewell Sermon: Universal Human Rights': 'The Farewell Sermon Prophet Muhammad Mount Arafat documentary',
    'Guardians of the Sunnah: The Science of Hadith': 'The Science of Hadith documentary Sahih al Bukhari',
    'Saladin and the Liberation of Jerusalem': 'Battle of Hattin 1187 Saladin Al Quds HistoryMarche',
    'The Heart\'s Medicine: Dhikr & Serenity': 'Dhikr and Remembrance of Allah spiritual serenity Islamic lecture',
    'Miracles of Creation: Signs in Nature': 'Signs of Allah in Creation Nature Universe Islamic documentary',
    'Al-Quds: The Sanctity of Masjid Al-Aqsa': 'Al Aqsa Mosque Jerusalem Islamic history documentary 4K',
    'The Science of Astronomy in Islamic Civilization': 'Science in a Golden Age Astronomy Jim Al-Khalili Al Jazeera',
    'Ibn Battuta: Master Traveler of the Medieval World': 'Ibn Battuta Greatest Muslim Traveler Islamic Guidance documentary',
    'The Sacred Geometry of Islamic Art & Mosques': 'The Glories of Islamic Art Damascus Jerusalem Architecture documentary',
    'Imam Abu Hanifah: The Jurist of Integrity': 'ইমাম আবু হানিফার জীবনী মিজানুর রহমান আজহারী waz',
    'Imam Malik: The Scholar of Madinah and the Muwatta': 'Imam Malik Scholar of Madinah Muwatta Islamic documentary',
    'Imam Ash-Shafi\'i: Father of Legal Methodology': 'Imam Shafi life and legacy Islamic jurisprudence documentary',
    'Imam Ahmad ibn Hanbal: Steadfastness in Truth': 'Imam Ahmad ibn Hanbal trial Mihna steadfastness documentary',
    'The Miracle of Zamzam: Waters of Life': 'The Story and Miracle of Zamzam Water Makkah documentary',
    'Battle of Badr: The Day of Criterion': 'Battle of Badr 624 AD Islamic history HistoryMarche',
    'Avicenna (Ibn Sina): Master of Medicine and Healing': 'Ibn Sina Avicenna Science in a Golden Age Al Jazeera documentary',
    'Surah Al-Kahf: Friday Reflection & Recitation': 'Surah Al Kahf recitation Mishary Rashid Alafasy',
    'The Night of Power (Laylatul Qadr): Better Than a Thousand Months': 'Laylatul Qadr Signs and Blessings Yaqeen Institute',
    'Tazkiyah: Cleansing the Heart of Arrogance & Envy': 'Tazkiyah Purifying the Heart spiritual lecture Yaqeen',
    'The Ethics of Wealth: Zakat, Sadaqah & Halal Commerce': 'Islamic Finance Zakat and Halal Wealth Mufti Taqi Usmani',
    'Daughters of the Prophet: Grace, Dignity & Sacrifice': 'Daughters of Prophet Muhammad Ruqayyah Umm Kulthum Fatima documentary',
    'Islamic Cordoba: The Mezquita and the Forest of Marble': 'Great Mosque of Cordoba Mezquita Islamic Architecture documentary',
    'The Art of Arabic Calligraphy: Devotion in Ink': 'The Art of Arabic Islamic Calligraphy Thuluth Naskh documentary',
    'The Farewell Du\'a: Tearful Prayers from Arafat': 'Dua of Arafat Hajj emotional tearful prayer Makkah',
    'Rumi & Al-Ghazali: The Pursuit of Inner Truth': 'Imam Al Ghazali Deliverance from Error documentary',
    'Spiritual Revival: Returning to Allah in Sincere Repentance': 'Tawbah Turning Back to Allah emotional Islamic lecture',
    'The Prophet\'s Kindness to Children & Orphans': 'Prophet Muhammad Kindness Mercy to Children and Orphans',
    'Signs of the Creator: Ocean Waves & Mountain Anchors': 'Deep Ocean Waves and Mountains in Quran Modern Science miracles'
}

DISALLOWED_IDS = {
    'V0dewBAf9Ds', # Broken/non-islamic thumbnail
    'pg1NpMmPv48', # 404 door thumbnail
    'Ff87j_ccnkY', # Western portrait
    'hBAqM7fmrA0', # Duplicate non-islamic portrait
}

def search_youtube(query, avoid_ids=None):
    if avoid_ids is None:
        avoid_ids = set()
    encoded = urllib.parse.quote_plus(query)
    url = f'https://www.youtube.com/results?search_query={encoded}'
    req = urllib.request.Request(
        url,
        headers={
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language': 'en-US,en;q=0.9'
        }
    )
    try:
        html = urllib.request.urlopen(req, timeout=10).read().decode('utf-8')
        m = re.search(r'var ytInitialData = ({.*?});</script>', html) or re.search(r'ytInitialData = ({.*?});', html)
        if not m:
            return None
        data = json.loads(m.group(1))
        contents = data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents']
        for section in contents:
            items = section.get('itemSectionRenderer', {}).get('contents', [])
            for item in items:
                v = item.get('videoRenderer')
                if not v:
                    continue
                vid = v.get('videoId')
                if not vid or vid in avoid_ids or vid in DISALLOWED_IDS:
                    continue
                title = v.get('title', {}).get('runs', [{}])[0].get('text', '')
                channel = v.get('ownerText', {}).get('runs', [{}])[0].get('text', '')
                thumb = f'https://i.ytimg.com/vi/{vid}/hqdefault.jpg'
                try:
                    chk = urllib.request.Request(thumb, headers={'User-Agent': 'Mozilla/5.0'})
                    with urllib.request.urlopen(chk, timeout=5) as r:
                        cl = int(r.headers.get('Content-Length', 0))
                        if r.status == 200 and cl > 10000:
                            return {
                                'id': vid,
                                'title': title,
                                'channel': channel,
                                'thumb': thumb,
                                'size': cl
                            }
                except Exception:
                    continue
    except Exception as e:
        print(f"Error querying '{query}': {e}", file=sys.stderr)
    return None

def run_mysql(sql):
    cmd = ['docker', 'exec', '-i', 'swann-mysql', 'mysql', '-u', 'root', 'swann', '-e', sql]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode != 0:
        raise RuntimeError(f"MySQL error: {res.stderr}")
    return res.stdout

def get_entertainments():
    output = run_mysql("SELECT id, name, type FROM entertainments ORDER BY id;")
    lines = output.strip().split('\n')[1:]
    items = []
    for line in lines:
        if line.strip():
            parts = line.split('\t')
            if len(parts) >= 3:
                items.append({'id': parts[0].strip(), 'name': parts[1].strip(), 'type': parts[2].strip()})
    return items

def main():
    print("=== Step 1: Retrieving Entertainments from DB ===")
    entertainments = get_entertainments()
    print(f"Found {len(entertainments)} entertainments.")

    used_vids = set()
    updates = {}

    print("\n=== Step 2: Searching YouTube for authentic Islamic thumbnails ===")
    for ent in entertainments:
        name = ent['name']
        query = QUERY_MAP.get(name, f"{name} Islamic documentary")
        print(f"Processing: [{ent['id']}] {name}")
        print(f"  Search query: '{query}'")

        res = search_youtube(query, avoid_ids=used_vids)
        if not res:
            # Fallback search
            res = search_youtube(f"{name} Islamic lecture waz", avoid_ids=used_vids)

        if res:
            used_vids.add(res['id'])
            thumb = res['thumb']
            updates[ent['id']] = {
                'name': name,
                'vid': res['id'],
                'channel': res['channel'],
                'title': res['title'],
                'thumb': thumb
            }
            print(f"  ✓ FOUND [{res['id']}] by {res['channel']}: {res['title'][:50]}")
            print(f"    Thumb: {thumb} ({res['size']} bytes)")
        else:
            print(f"  ✗ FAILED to find video for {name}")

        time.sleep(0.5)

    print(f"\nSuccessfully found thumbnails for {len(updates)}/{len(entertainments)} items.")

    print("\n=== Step 3: Updating MySQL Database ===")
    for eid, data in updates.items():
        thumb = data['thumb']
        # Escape quotes
        thumb_esc = thumb.replace("'", "''")
        eid_esc = eid.replace("'", "''")
        
        # 1. Update entertainments
        sql = f"UPDATE entertainments SET poster_url='{thumb_esc}', thumbnail_url='{thumb_esc}', poster_tv_url='{thumb_esc}' WHERE id='{eid_esc}';"
        run_mysql(sql)

        # 2. Update episodes linked to this entertainment
        sql_ep = f"UPDATE episodes SET poster_url='{thumb_esc}', poster_tv_url='{thumb_esc}' WHERE entertainment_id='{eid_esc}';"
        run_mysql(sql_ep)

    # 3. Update banners
    print("\n=== Step 4: Updating Banners Table ===")
    banner_rows = run_mysql("SELECT id, title, type_id FROM banners;").strip().split('\n')[1:]
    for row in banner_rows:
        if not row.strip():
            continue
        parts = row.split('\t')
        bid = parts[0].strip()
        type_id = parts[2].strip() if len(parts) >= 3 else ''
        if type_id in updates:
            thumb = updates[type_id]['thumb']
            sql_b = f"UPDATE banners SET file_url='{thumb}', poster_url='{thumb}', poster_tv_url='{thumb}' WHERE id='{bid}';"
            run_mysql(sql_b)
            print(f"  ✓ Updated Banner {bid} with {thumb}")

    print("\n=== Finished Updating Authentic Islamic Thumbnails ===")

if __name__ == '__main__':
    main()

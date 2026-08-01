<?php
declare(strict_types=1);

/** Останні відео каналу YouTube через публічний RSS (без API-ключів), кеш 1 година */
class YouTube
{
    public static function latest(int $limit = 3): array
    {
        $channel = trim((string)Settings::get('youtube_channel', ''));
        if ($channel === '') return [];

        $cache = json_decode((string)Settings::get('yt_cache', ''), true);
        $fresh = (int)Settings::get('yt_cache_time', '0') > time() - 3600;
        // старий формат кешу (без is_short) вважаємо застарілим
        if (is_array($cache) && $cache && !array_key_exists('is_short', $cache[0])) $fresh = false;
        if (is_array($cache) && $fresh) return array_slice($cache, 0, $limit);

        $videos = self::fetch($channel);
        if ($videos) {
            Settings::set('yt_cache', json_encode($videos, JSON_UNESCAPED_UNICODE));
            Settings::set('yt_cache_time', (string)time());
            return array_slice($videos, 0, $limit);
        }
        // мережа недоступна — віддаємо старий кеш, якщо був
        return is_array($cache) ? array_slice($cache, 0, $limit) : [];
    }

    private static function fetch(string $channel): array
    {
        $channelId = self::channelId($channel);
        if (!$channelId) return [];
        $xml = self::get('https://www.youtube.com/feeds/videos.xml?channel_id=' . urlencode($channelId));
        if (!$xml) return [];
        $doc = @simplexml_load_string($xml);
        if (!$doc) return [];
        $out = [];
        foreach ($doc->entry as $e) {
            $media = $e->children('http://search.yahoo.com/mrss/');
            $yt = $e->children('http://www.youtube.com/xml/schemas/2015');
            $vid = (string)$yt->videoId;
            if (!$vid) continue;
            // Пропускаємо шортси: /shorts/<id> віддає 200, звичайне відео — редірект
            if (self::headStatus('https://www.youtube.com/shorts/' . $vid) === 200) continue;
            $out[] = [
                'id' => $vid,
                'title' => (string)$e->title,
                'url' => 'https://www.youtube.com/watch?v=' . $vid,
                'thumb' => 'https://i.ytimg.com/vi/' . $vid . '/hqdefault.jpg',
                'published' => substr((string)$e->published, 0, 10),
                'is_short' => false,
            ];
            if (count($out) >= 8) break;
        }
        return $out;
    }

    /** Приймає ID (UC...), URL каналу або @handle — повертає channel_id */
    private static function channelId(string $input): ?string
    {
        if (preg_match('/^UC[\w-]{20,}$/', $input)) return $input;
        $cached = Settings::get('yt_channel_id_for');
        if ($cached) {
            [$src, $id] = array_pad(explode('|', (string)$cached, 2), 2, '');
            if ($src === $input && $id) return $id;
        }
        if (preg_match('~/channel/(UC[\w-]{20,})~', $input, $m)) {
            Settings::set('yt_channel_id_for', $input . '|' . $m[1]);
            return $m[1];
        }
        // handle або посилання на нього — дістаємо channelId зі сторінки каналу
        $handle = null;
        if (preg_match('~@([\w.\-]+)~u', $input, $m)) $handle = $m[1];
        elseif (preg_match('/^[\w.\-]+$/', $input)) $handle = $input;
        if (!$handle) return null;
        $html = self::get('https://www.youtube.com/@' . rawurlencode($handle) . '/about');
        foreach (['/"channelId":"(UC[\w-]{20,})"/', '/"externalId":"(UC[\w-]{20,})"/',
                  '/"browseId":"(UC[\w-]{20,})"/', '~youtube\.com/channel/(UC[\w-]{20,})~'] as $re) {
            if ($html && preg_match($re, $html, $m)) {
                Settings::set('yt_channel_id_for', $input . '|' . $m[1]);
                return $m[1];
            }
        }
        return null;
    }

    private static function headStatus(string $url): int
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            CURLOPT_COOKIE => 'SOCS=CAI; CONSENT=YES+cb.20240101-00-p0.uk+FX+000',
            CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code;
    }

    private static function get(string $url, bool $insecureRetry = true): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            CURLOPT_COOKIE => 'SOCS=CAI; CONSENT=YES+cb.20240101-00-p0.uk+FX+000',
            CURLOPT_HTTPHEADER => ['Accept-Language: uk-UA,uk;q=0.9,en;q=0.8'],
        ]);
        $resp = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($resp !== false && $code === 200) return (string)$resp;
        // XAMPP часто без CA-сертифікатів: повторюємо без перевірки SSL (лише публічний RSS)
        if ($insecureRetry && in_array($errno, [60, 77], true)) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
                CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                CURLOPT_COOKIE => 'SOCS=CAI; CONSENT=YES+cb.20240101-00-p0.uk+FX+000',
                CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => 0,
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($resp !== false && $code === 200) return (string)$resp;
        }
        @file_put_contents(BOFU_ROOT . '/storage/logs/youtube.log',
            '[' . now() . "] fail $url errno=$errno http=$code\n", FILE_APPEND);
        return null;
    }
}

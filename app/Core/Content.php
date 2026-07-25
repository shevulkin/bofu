<?php
declare(strict_types=1);

/** Редаговані блоки контенту сайту (тексти, банери, фото) */
class Content
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (DB::all('SELECT * FROM content_blocks') as $r) self::$cache[$r['key']] = $r;
        }
        return self::$cache;
    }

    public static function get(string $key, string $field = 'body', string $default = ''): string
    {
        $all = self::all();
        $v = $all[$key][$field] ?? null;
        return ($v !== null && $v !== '') ? $v : $default;
    }

    public static function title(string $key, string $default = ''): string
    { return self::get($key, 'title', $default); }

    public static function image(string $key, string $default = ''): string
    {
        $img = self::get($key, 'image', '');
        return $img !== '' ? $img : $default;
    }

    public static function set(string $key, array $fields): void
    {
        $exists = DB::row(DB::driver()==='sqlite' ? 'SELECT 1 FROM content_blocks WHERE "key"=?' : 'SELECT 1 FROM content_blocks WHERE `key`=?', [$key]);
        if ($exists) {
            DB::update('content_blocks', $fields, DB::driver()==='sqlite' ? '"key" = ?' : '`key` = ?', [$key]);
        } else {
            DB::insert('content_blocks', array_merge(['key' => $key], $fields));
        }
        self::$cache = null;
    }
}

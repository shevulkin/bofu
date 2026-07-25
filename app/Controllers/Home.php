<?php
declare(strict_types=1);

namespace Controllers;

use DB, View, Catalog, Content, Settings, Csrf;

class Home
{
    public static function index(): never
    {
        $featured = DB::all('SELECT * FROM products WHERE active = 1 AND featured = 1 ORDER BY id LIMIT 6');
        $gallery = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
        View::show('home/index', [
            'categories' => Catalog::categories(),
            'featured' => $featured,
            'gallery_preview' => array_slice($gallery, 0, 3),
            'faq' => json_decode(Content::get('faq', 'body', '[]'), true) ?: [],
            'page_title' => Settings::get('seo_title', cfg('app_name')),
            'meta_description' => Settings::get('seo_description', ''),
        ]);
    }

    public static function about(): never
    {
        $gallery = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
        View::show('home/about', [
            'gallery' => $gallery,
            'page_title' => 'Про мене — ' . cfg('app_name'),
        ]);
    }

    public static function courses(): never
    {
        View::show('home/courses', ['page_title' => 'Курси бджільництва — ' . cfg('app_name')]);
    }

    public static function gallery(): never
    {
        $gallery = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
        View::show('home/gallery', ['gallery' => $gallery, 'page_title' => 'Галерея — ' . cfg('app_name')]);
    }

    public static function social(): never
    {
        View::show('home/social', ['page_title' => 'Соцмережі — ' . cfg('app_name')]);
    }

    public static function diploma(): never
    {
        View::show('home/diploma', ['result' => null, 'page_title' => 'Перевірка диплому — ' . cfg('app_name')]);
    }

    public static function diplomaCheck(): never
    {
        Csrf::verify();
        $number = trim($_POST['number'] ?? '');
        $found = $number !== ''
            ? DB::row('SELECT * FROM diplomas WHERE number = ? AND active = 1', [$number])
            : null;
        View::show('home/diploma', [
            'result' => ['ok' => (bool)$found, 'number' => $number, 'diploma' => $found],
            'page_title' => 'Перевірка диплому — ' . cfg('app_name'),
        ]);
    }
}

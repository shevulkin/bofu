<?php
declare(strict_types=1);

namespace Controllers;

use DB, WebPush, Settings;

class Seo
{
    public static function robots(): never
    {
        header('Content-Type: text/plain; charset=utf-8');
        $host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        echo "User-agent: *\nDisallow: /admin\nDisallow: /cart\nDisallow: /checkout\n";
        echo "Sitemap: $scheme://$host" . base_url('/sitemap.xml') . "\n";
        exit;
    }

    public static function sitemap(): never
    {
        header('Content-Type: application/xml; charset=utf-8');
        $host = ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $abs = fn(string $p) => $scheme . '://' . $host . base_url($p);
        $urls = [['/', '1.0'], ['/shop', '0.9'], ['/about', '0.6'], ['/courses', '0.8'], ['/diploma', '0.5'], ['/social', '0.4'], ['/gallery', '0.4']];
        foreach (DB::all('SELECT slug, updated_at FROM products WHERE active = 1') as $p) {
            $urls[] = ['/product/' . $p['slug'], '0.8', $p['updated_at']];
        }
        foreach (DB::all('SELECT slug FROM categories WHERE active = 1') as $c) {
            $urls[] = ['/shop?cat=' . $c['slug'], '0.7'];
        }
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $u) {
            echo '<url><loc>' . htmlspecialchars($abs($u[0])) . '</loc><priority>' . $u[1] . '</priority>';
            if (!empty($u[2])) echo '<lastmod>' . date('Y-m-d', strtotime($u[2])) . '</lastmod>';
            echo "</url>\n";
        }
        echo '</urlset>';
        exit;
    }

    public static function manifest(): never
    {
        header('Content-Type: application/manifest+json; charset=utf-8');
        echo json_encode([
            'name' => cfg('app_name'), 'short_name' => 'BOFU',
            'start_url' => base_url('/admin'), 'scope' => base_url('/'),
            'display' => 'standalone', 'background_color' => '#141110', 'theme_color' => '#141110',
            'icons' => [
                ['src' => asset('img/avatar.png'), 'sizes' => '400x400', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function serviceWorker(): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: ' . base_url('/'));
        readfile(BOFU_ROOT . '/assets/js/sw.js');
        exit;
    }
}

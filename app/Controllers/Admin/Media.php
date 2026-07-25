<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Images;

class Media
{
    /** Список усіх фото сайту (для сторінки і для вікна вибору) */
    public static function listAll(): array
    {
        $dir = cfg('uploads_dir');
        $items = [];
        // завантажені фото
        foreach (glob($dir . '/*') ?: [] as $f) {
            $name = basename($f);
            if (str_contains($name, '-thumb.')) continue;
            $size = @getimagesize($f);
            $items[] = [
                'path' => 'uploads/' . $name, 'thumb' => 'uploads/' . preg_replace('/\.(\w+)$/', '-thumb.$1', $name),
                'width' => $size[0] ?? 0, 'height' => $size[1] ?? 0,
                'bytes' => filesize($f) ?: 0, 'mtime' => filemtime($f) ?: 0, 'builtin' => false,
            ];
        }
        usort($items, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        // вбудовані фото дизайну
        foreach (glob(BOFU_ROOT . '/assets/img/*.png') ?: [] as $f) {
            $size = @getimagesize($f);
            $items[] = [
                'path' => 'img/' . basename($f), 'thumb' => 'img/' . basename($f),
                'width' => $size[0] ?? 0, 'height' => $size[1] ?? 0,
                'bytes' => filesize($f) ?: 0, 'mtime' => 0, 'builtin' => true,
            ];
        }
        return $items;
    }

    public static function index(): never
    {
        if (($_GET['format'] ?? '') === 'json') {
            json_response(['items' => self::listAll()]);
        }
        if (is_post()) {
            $action = $_POST['_action'] ?? '';
            if ($action === 'upload') {
                $res = Images::saveUpload($_FILES['image'] ?? [], 'media');
                if ($res) {
                    [$path, $w, $h, $bytes] = $res;
                    if (($_POST['format'] ?? '') === 'json') json_response(['ok' => true, 'path' => $path, 'width' => $w, 'height' => $h, 'bytes' => $bytes]);
                    flash('success', "Фото додано ({$w}×{$h}, " . round($bytes/1024) . ' КБ)');
                } else {
                    if (($_POST['format'] ?? '') === 'json') json_response(['ok' => false], 422);
                    flash('error', 'Не вдалося завантажити фото');
                }
            }
            if ($action === 'delete') {
                $path = (string)($_POST['path'] ?? '');
                if (str_starts_with($path, 'uploads/') && !str_contains($path, '..')) {
                    Images::delete($path);
                    // прибрати всі використання
                    DB::delete('product_images', 'path = ?', [$path]);
                    DB::query('UPDATE products SET image = NULL WHERE image = ?', [$path]);
                    DB::query('UPDATE content_blocks SET image = NULL WHERE image = ?', [$path]);
                    $g = json_decode(\Content::get('gallery', 'body', '[]'), true) ?: [];
                    $g = array_values(array_filter($g, fn($x) => ($x[1] ?? '') !== $path));
                    \Content::set('gallery', ['body' => json_encode($g, JSON_UNESCAPED_UNICODE)]);
                    if (($_POST['format'] ?? '') === 'json') json_response(['ok' => true]);
                    flash('success', 'Фото видалено із сайту');
                } elseif (($_POST['format'] ?? '') === 'json') json_response(['ok' => false], 422);
            }
            redirect('/admin/media');
        }
        View::show('admin/media', [
            'items' => self::listAll(),
            'page_title' => 'Медіа-бібліотека — адмінка',
        ], 'layouts/admin');
    }
}

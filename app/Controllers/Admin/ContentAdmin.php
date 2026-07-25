<?php
declare(strict_types=1);

namespace Controllers\Admin;

use DB, View, Auth, Content, Images;

class ContentAdmin
{
    /** Групи блоків для зручного редагування */
    private const GROUPS = [
        'Головний екран' => ['hero_badge', 'hero_title', 'hero_text', 'hero_points'],
        'Лічильники' => ['counter_graduates', 'counter_courses', 'counter_founded'],
        'Про мене' => ['about_teaser_title', 'about_teaser', 'about_full'],
        'Чому я' => ['why_1', 'why_2', 'why_3'],
        'Курс' => ['course_1', 'course_1_link'],
        'Заклик' => ['final_cta_title', 'final_cta_text'],
        'Соцмережі' => ['social_instagram', 'social_youtube', 'social_tiktok'],
    ];

    public static function index(): never
    {
        Auth::requireAdmin();
        if (is_post()) {
            $action = $_POST['_action'] ?? 'save';
            if ($action === 'save') {
                foreach ((array)($_POST['block'] ?? []) as $key => $b) {
                    if (!preg_match('/^[a-z0-9_]+$/', (string)$key)) continue;
                    Content::set((string)$key, [
                        'title' => $b['title'] ?? null,
                        'body' => $b['body'] ?? null,
                    ]);
                }
                // FAQ окремо (пари питання/відповідь)
                if (isset($_POST['faq_q'])) {
                    $faq = [];
                    foreach ((array)$_POST['faq_q'] as $i => $q) {
                        $a = $_POST['faq_a'][$i] ?? '';
                        if (trim($q) !== '' && trim($a) !== '') $faq[] = [trim($q), trim($a)];
                    }
                    Content::set('faq', ['body' => json_encode($faq, JSON_UNESCAPED_UNICODE)]);
                }
                flash('success', 'Контент збережено');
            }
            if ($action === 'set_image') {
                $key = preg_match('/^[a-z0-9_]+$/', (string)($_POST['key'] ?? '')) ? $_POST['key'] : null;
                $path = (string)($_POST['media_path'] ?? '');
                if ($key && (str_starts_with($path, 'uploads/') || str_starts_with($path, 'img/')) && !str_contains($path, '..')) {
                    Content::set($key, ['image' => $path]);
                    flash('success', 'Фото оновлено');
                }
            }
            if ($action === 'gallery_pick') {
                $path = (string)($_POST['media_path'] ?? '');
                $title = trim($_POST['title'] ?? '');
                if ((str_starts_with($path, 'uploads/') || str_starts_with($path, 'img/')) && !str_contains($path, '..')) {
                    $g = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
                    $g[] = [$title ?: 'Фото', $path];
                    Content::set('gallery', ['body' => json_encode($g, JSON_UNESCAPED_UNICODE)]);
                    flash('success', 'Фото додано до галереї');
                }
            }
            if ($action === 'upload' ) {
                $key = preg_match('/^[a-z0-9_]+$/', (string)($_POST['key'] ?? '')) ? $_POST['key'] : null;
                if ($key) {
                    $res = Images::saveUpload($_FILES['image'] ?? [], 'content-' . $key);
                    if ($res) {
                        [$path, $w, $h, $bytes] = $res;
                        Content::set($key, ['image' => $path]);
                        flash('success', "Фото оновлено ({$w}×{$h}, " . round($bytes/1024) . ' КБ)');
                    } else flash('error', 'Не вдалося завантажити фото');
                }
            }
            if ($action === 'gallery_add') {
                $res = Images::saveUpload($_FILES['image'] ?? [], 'gallery');
                $title = trim($_POST['title'] ?? '');
                if ($res) {
                    [$path] = $res;
                    $g = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
                    $g[] = [$title ?: 'Фото', $path];
                    Content::set('gallery', ['body' => json_encode($g, JSON_UNESCAPED_UNICODE)]);
                    flash('success', 'Фото додано до галереї');
                }
            }
            if ($action === 'gallery_del') {
                $idx = (int)($_POST['index'] ?? -1);
                $g = json_decode(Content::get('gallery', 'body', '[]'), true) ?: [];
                if (isset($g[$idx])) {
                    if (str_starts_with($g[$idx][1] ?? '', 'uploads/')) Images::delete($g[$idx][1]);
                    array_splice($g, $idx, 1);
                    Content::set('gallery', ['body' => json_encode($g, JSON_UNESCAPED_UNICODE)]);
                    flash('success', 'Фото прибрано з галереї');
                }
            }
            redirect('/admin/content');
        }

        View::show('admin/content', [
            'groups' => self::GROUPS,
            'blocks' => Content::all(),
            'faq' => json_decode(Content::get('faq', 'body', '[]'), true) ?: [],
            'gallery' => json_decode(Content::get('gallery', 'body', '[]'), true) ?: [],
            'page_title' => 'Контент сайту — адмінка',
        ], 'layouts/admin');
    }
}

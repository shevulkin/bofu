<?php
declare(strict_types=1);

namespace Controllers;

use Auth, Csrf, Roles;

/**
 * Перемикання режиму перегляду: подивитись на систему очима іншої ролі.
 *
 * Маршрути свідомо лежать поза адмінкою: у режимі покупця людина втрачає
 * стафні права, і якби перемикач жив під гейтом адмінки — вийти з режиму
 * було б нічим.
 */
class RoleController
{
    public static function change(): never
    {
        Csrf::verify();
        $back = safe_back($_POST['back'] ?? null, '/');
        if (!Auth::check()) redirect($back);

        $role = (string)($_POST['role'] ?? '');
        $storeId = null;
        if ($role === Roles::SELLER) {
            $storeId = (int)($_POST['store_id'] ?? 0) ?: null;
            // без точки кабінет продавця показав би порожньо й це виглядало б як поломка
            if ($storeId === null) $storeId = Auth::authorityStoreIds()[0] ?? null;
        }

        if (!Auth::simulate($role, $storeId)) {
            flash('error', 'Ця роль недоступна для перегляду.');
            redirect($back);
        }
        flash('success', 'Режим перегляду: ' . Roles::label($role) . '. Права звужено до цієї ролі.');
        // у режимі покупця в адмінці робити нічого — ведемо на вітрину
        redirect($role === Roles::CUSTOMER ? '/' : $back);
    }

    public static function reset(): never
    {
        Csrf::verify();
        $back = safe_back($_POST['back'] ?? null, '/');
        Auth::stopSimulating();
        flash('success', 'Повернуто ваші звичайні права.');
        redirect($back);
    }
}

<?php
declare(strict_types=1);

/**
 * Покупець за номером телефону.
 *
 * Потрібно там, де замовлення оформлює не сам покупець, а продавець: по
 * телефону або на місці в точці. Питання щоразу одне — чий це запис.
 *
 * Номер — єдине стале імʼя покупця в цій системі: за ним він входить, за ним
 * рахуються ліміти промокодів, за ним склеюються записи з месенджерів (див.
 * BotAuth::resolveUser). Тому правило тут те саме: є номер — акаунт знайдеться
 * або зʼявиться, і замовлення ляже в історію тієї самої людини, а не в нікуди.
 *
 * Номера немає — замовлення лишається анонімним (user_id = NULL). Це не збій,
 * а нормальний продаж у точці: людина зайшла, взяла банку меду, розрахувалась
 * і пішла. Вигадувати їй акаунт нема з чого, а порожній акаунт «Покупець №17»
 * лише засмічує список користувачів і чіпляє чужі замовлення до одного запису.
 */
class Customers
{
    /**
     * Пошук покупця за номером — без жодних створень.
     *
     * Потрібен там, де продавець ще тільки питає «а ви в нас були?»: спершу
     * показуємо, кого знайшли, і лише за його підтвердженням щось робимо.
     *
     * @param ?string $phone нормалізований номер (AuthTokens::normPhoneAny)
     */
    public static function find(?string $phone): ?array
    {
        if ($phone === null || $phone === '') return null;
        return DB::row('SELECT * FROM users WHERE phone = ? ORDER BY id LIMIT 1', [$phone]);
    }

    /**
     * Скільки замовлень у цієї людини — щоб продавець упізнав постійного покупця
     * ще до того, як почне продаж.
     */
    public static function orderCount(int $userId): int
    {
        return (int)DB::val('SELECT COUNT(*) FROM orders WHERE user_id = ? AND parent_id IS NULL', [$userId]);
    }

    /**
     * Акаунт покупця за номером: знайти, а як немає — створити.
     *
     * @param ?string $phone вже нормалізований номер (AuthTokens::normPhoneAny) або null
     * @param string  $name  імʼя зі слів продавця; порожнє — не біда
     * @return ?int id акаунта; null — покупець анонімний (номера не було)
     */
    public static function resolve(?string $phone, string $name = ''): ?int
    {
        if ($phone === null || $phone === '') return null;
        $name = trim($name);

        $user = DB::row('SELECT * FROM users WHERE phone = ? ORDER BY id LIMIT 1', [$phone]);
        if ($user) {
            // Імʼя з трубки не затирає введене самим покупцем: він міг назватись
            // у профілі як йому треба, а продавець записав зі слуху.
            if (trim((string)$user['name']) === '' && $name !== '') {
                DB::update('users', ['name' => $name], 'id = ?', [(int)$user['id']]);
            }
            return (int)$user['id'];
        }

        // Пошта потрібна схемі (email unique), а не людині: справжньої ми не
        // знаємо й вигадувати не маємо права — лист на неї не піде. Домен
        // .local відсіює Newsletter::normEmail, тож у розсилку така адреса не
        // потрапить, а Checkout не покаже її як «свою» в формі.
        $uid = DB::insert('users', [
            'email' => substr(md5($phone . '|' . random_bytes(8)), 0, 12) . '@offline.local',
            'name' => $name !== '' ? $name : 'Покупець',
            'role' => 'customer', 'active' => 1,
            'phone' => $phone, 'created_at' => now(),
        ]);
        Notify::fire('user_new', ['name' => $name !== '' ? $name : 'Покупець', 'email' => $phone]);
        return $uid;
    }
}

<div class="admin-head"><h1 class="h-serif">Дипломи випускників</h1></div>
<form class="admin-card" method="post" action="<?= e(url('/admin/diplomas')) ?>" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
  <?= Csrf::field() ?><input type="hidden" name="_action" value="add">
  <div data-help-title="Номер диплома"
       data-help="Унікальний номер, який надрукований на самому дипломі.

Саме за ним будь-хто зможе перевірити диплом на сайті: роботодавець вводить номер і бачить, чи він дійсний.

Тому номер має збігатися з паперовим документом символ у символ — інакше перевірка не знайде запис.">
    <label>Номер</label><input type="text" name="number" required placeholder="BOFU-2026-001"></div>
  <div style="flex:1;min-width:180px" data-help-title="Випускник"
       data-help="ПІБ людини, яка отримала диплом, — так само, як у документі.

Це імʼя показується при перевірці: той, хто вводить номер, бачить, кому диплом виданий. Тому воно має точно збігатися з паперовим.">
    <label>Випускник</label><input type="text" name="student" required></div>
  <div data-help-title="Курс"
       data-help="Назва програми чи курсу, який закінчила людина.

Необовʼязкове поле, але корисне: при перевірці одразу видно, чого саме стосується диплом.">
    <label>Курс</label><input type="text" name="course" placeholder="Промислове бджільництво"></div>
  <div data-help-title="Дата видачі"
       data-help="Коли диплом видано. Показується при перевірці разом з іменем і курсом.

Ставте дату з документа, а не сьогоднішню, якщо вносите старі дипломи заднім числом.">
    <label>Дата видачі</label><input type="date" name="issued_at"></div>
  <button class="btn btn-gold btn-sm" type="submit" data-help-title="Кнопка «Додати»"
          data-help="Вносить диплом у реєстр — одразу зі станом «Дійсний».

Із цієї миті його можна перевірити на сайті за номером. Перевіряти може будь-хто, вхід для цього не потрібен.">+ Додати</button>
</form>
<table class="tbl">
  <tr><th>Номер</th><th>Випускник</th><th>Курс</th><th>Видано</th>
    <th data-help-title="Колонка «Стан»"
        data-help="«Дійсний» — при перевірці за номером сайт підтвердить диплом.

«Анульований» — запис лишається, але перевірка покаже, що диплом недійсний. Так роблять, коли документ відкликано або видано помилково.">Стан</th>
    <th></th></tr>
  <?php foreach ($diplomas as $d): ?>
    <tr>
      <td><b><?= e($d['number']) ?></b></td>
      <td><?= e($d['student']) ?></td>
      <td class="muted"><?= e($d['course'] ?: '—') ?></td>
      <td class="dim"><?= e($d['issued_at'] ?: '—') ?></td>
      <td><?= $d['active'] ? '<span class="status-pill st-processing">Дійсний</span>' : '<span class="status-pill st-canceled">Анульований</span>' ?></td>
      <td style="white-space:nowrap">
        <form method="post" action="<?= e(url('/admin/diplomas')) ?>" style="display:inline"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-line btn-xs" data-help-title="Анулювати / Відновити"
                  data-help="Перемикає диплом між «Дійсний» і «Анульований».

Анулювання не стирає запис: він лишається в реєстрі, і перевірка за номером чесно покаже, що документ недійсний. Саме це й потрібно, коли диплом відкликали.

Дію можна відкотити кнопкою «Відновити»."><?= $d['active'] ? 'Анулювати' : 'Відновити' ?></button>
        </form>
        <form method="post" action="<?= e(url('/admin/diplomas')) ?>" style="display:inline"><?= Csrf::field() ?>
          <input type="hidden" name="_action" value="delete"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
          <button class="btn btn-danger btn-xs" onclick="return confirm('Видалити запис?')"
                  data-help-title="Видалити запис"
                  data-help="Стирає диплом із реєстру назавжди. Після цього перевірка за його номером не знайде нічого — так, ніби документа ніколи не існувало.

Якщо диплом відкликано, правильніше «Анулювати»: тоді видно, що документ був і його скасували. Видалення лишіть для записів, внесених помилково.">✕</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

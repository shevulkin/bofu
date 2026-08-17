<?php
declare(strict_types=1);

/**
 * Таблиці: прочитати XLSX або CSV і скласти XLSX.
 *
 * Потрібне рівно для одного — обміну номенклатурою з кабінетом «Вчасно.Каси»:
 * товари туди заводять файлом, і назад вивантажують теж файлом. API для
 * номенклатури в них немає, тож інакше звірити наш каталог з їхнім не вийде.
 *
 * Чому власний XLSX, а не бібліотека: у проєкті немає залежностей — його
 * ставлять копіюванням на shared-хостинг, і composer там може не бути взагалі.
 * А чому не самий лише CSV: кабінет чекає Excel, і змушувати людину щоразу
 * перезберігати файл — це і зайвий крок, і зайва нагода зіпсувати кодування.
 *
 * ZipArchive теж не вимагаємо: розширення zip вимкнене в багатьох збірках
 * (зокрема в XAMPP за замовчуванням). Zip тут зроблений руками — формат
 * простий, а deflate уміє сам PHP (gzdeflate/gzinflate). Коли розширення є,
 * користуємось ним: чужий перевірений код кращий за свій.
 */
class Sheet
{
    /**
     * Прочитати таблицю. Формат визначаємо за вмістом, а не за назвою: файл
     * із кабінету цілком може приїхати як «expor.xls», будучи насправді CSV.
     *
     * @return array<int, array<int, string>> рядки клітинок
     */
    public static function read(string $path): array
    {
        $raw = (string)@file_get_contents($path);
        if ($raw === '') return [];
        return str_starts_with($raw, "PK\x03\x04") ? self::readXlsx($raw) : self::readCsv($raw);
    }

    // ─────────────────────────────────────────────────────────────────── CSV

    /**
     * CSV із будь-якої Excel-збірки.
     *
     * Дві пастки, через які «файл не читається»: роздільник (український
     * Excel пише крапку з комою) і кодування (він же зберігає у win-1251).
     * Обидві визначаємо самі — питати про це людину, яка просто натиснула
     * «Зберегти як CSV», немає сенсу.
     */
    private static function readCsv(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string)mb_convert_encoding($raw, 'UTF-8', 'Windows-1251');
        }
        // Роздільник рахуємо по всьому файлу, а не по першому рядку: над
        // таблицею у вивантаженнях буває назва звіту («Вивантаження товарів»),
        // у якій немає жодного роздільника — і вибір за нею завжди хибний.
        // Крапка з комою переважує кому й нарівні: у нашій локалі коми
        // трапляються в самих значеннях («Ціна, грн», «120,50»).
        $sep = substr_count($raw, ';') >= substr_count($raw, ',') ? ';' : ',';

        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        while (($cells = fgetcsv($fh, 0, $sep)) !== false) {
            if ($cells === [null]) continue;                      // порожній рядок
            $rows[] = array_map(fn($c) => trim((string)$c), $cells);
        }
        fclose($fh);
        return $rows;
    }

    public static function writeCsv(array $rows): string
    {
        $fh = fopen('php://temp', 'r+');
        // BOM і крапка з комою — щоб файл відкрився в Excel одразу, а не
        // «кракозябрами в одній колонці»
        fwrite($fh, "\xEF\xBB\xBF");
        foreach ($rows as $row) fputcsv($fh, array_map('strval', $row), ';');
        rewind($fh);
        $out = (string)stream_get_contents($fh);
        fclose($fh);
        return $out;
    }

    // ────────────────────────────────────────────────────────────────── XLSX

    /**
     * Аркуш із книги.
     *
     * Беремо перший — у вивантаженнях кабінету він один, а вгадувати «потрібний»
     * серед кількох ми однаково не вміємо. Значення читаємо як текст: артикул
     * «007» і штрихкод на 13 цифр не мають перетворитись на числа.
     */
    private static function readXlsx(string $raw): array
    {
        $sheet = self::zipRead($raw, 'xl/worksheets/sheet1.xml');
        if ($sheet === null) {
            // Аркуш може називатись інакше — шукаємо перший, що схожий
            foreach (self::zipList($raw) as $name) {
                if (preg_match('~^xl/worksheets/[^/]+\.xml$~', $name)) {
                    $sheet = self::zipRead($raw, $name);
                    break;
                }
            }
        }
        if ($sheet === null) return [];

        $shared = self::sharedStrings((string)self::zipRead($raw, 'xl/sharedStrings.xml'));

        $rows = [];
        if (!preg_match_all('~<row[^>]*>(.*?)</row>~s', $sheet, $rm)) return [];
        foreach ($rm[1] as $rowXml) {
            $cells = [];
            if (preg_match_all('~<c\b([^>]*)(?:/>|>(.*?)</c>)~s', $rowXml, $cm, PREG_SET_ORDER)) {
                foreach ($cm as $c) {
                    $attrs = $c[1];
                    $body = $c[2] ?? '';
                    // Колонка береться з посилання (A1, B1…), а не з порядку:
                    // порожні клітинки Excel просто не пише, і без цього
                    // «штрихкод» з’їхав би в колонку «ціна».
                    $idx = preg_match('~r="([A-Z]+)~', $attrs, $rr) ? self::colIndex($rr[1]) : count($cells);
                    $type = preg_match('~t="([^"]+)"~', $attrs, $tt) ? $tt[1] : 'n';

                    $val = '';
                    if ($type === 'inlineStr') {
                        $val = self::textOf($body);
                    } elseif (preg_match('~<v>(.*?)</v>~s', $body, $vm)) {
                        $val = $vm[1];
                        if ($type === 's') $val = $shared[(int)$val] ?? '';
                    } else {
                        $val = self::textOf($body);
                    }
                    $cells[$idx] = trim(self::unxml($val));
                }
            }
            if (!$cells) { $rows[] = []; continue; }
            // Дірки заповнюємо порожнім: далі рядок читають за номерами колонок
            $rows[] = array_map(fn($i) => $cells[$i] ?? '', range(0, max(array_keys($cells))));
        }
        return $rows;
    }

    /** Спільні рядки книги: Excel виносить повторювані тексти в окремий файл */
    private static function sharedStrings(string $xml): array
    {
        if ($xml === '') return [];
        $out = [];
        if (preg_match_all('~<si>(.*?)</si>~s', $xml, $m)) {
            foreach ($m[1] as $si) $out[] = self::unxml(self::textOf($si));
        }
        return $out;
    }

    /** Текст усіх <t> усередині вузла: рядок із форматуванням Excel ріже на шматки */
    private static function textOf(string $xml): string
    {
        if (!preg_match_all('~<t[^>]*>(.*?)</t>~s', $xml, $m)) return '';
        return implode('', $m[1]);
    }

    private static function unxml(string $s): string
    {
        return html_entity_decode($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    /** «AB» → 27 (нумерація з нуля) */
    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) $n = $n * 26 + (ord($ch) - 64);
        return max(0, $n - 1);
    }

    private static function colName(int $index): string
    {
        $s = '';
        $n = $index + 1;
        while ($n > 0) { $r = ($n - 1) % 26; $s = chr(65 + $r) . $s; $n = intdiv($n - 1, 26); }
        return $s;
    }

    /**
     * Скласти XLSX.
     *
     * Усе пишемо рядками (inlineStr): книга без спільних рядків трохи більша,
     * але вдвічі простіша, а головне — артикули з нулями попереду й довгі
     * штрихкоди лишаються тим, чим вони є, а не числами в експоненті.
     */
    public static function writeXlsx(array $rows, string $sheetName = 'Товари'): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
             . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        foreach ($rows as $i => $row) {
            $r = $i + 1;
            $xml .= '<row r="' . $r . '">';
            $col = 0;
            foreach ($row as $cell) {
                $ref = self::colName($col) . $r;
                $xml .= '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">'
                      . htmlspecialchars((string)$cell, ENT_QUOTES | ENT_XML1, 'UTF-8')
                      . '</t></is></c>';
                $col++;
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';

        $name = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_QUOTES | ENT_XML1, 'UTF-8');
        return self::zipWrite([
            '[Content_Types].xml' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '</Types>',
            '_rels/.rels' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>',
            'xl/workbook.xml' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
                . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                . '<sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' =>
                '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                . '</Relationships>',
            'xl/worksheets/sheet1.xml' => $xml,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────── ZIP

    /** @param array<string,string> $files */
    private static function zipWrite(array $files): string
    {
        if (class_exists('ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
            $zip = new ZipArchive();
            if ($zip->open($tmp, ZipArchive::OVERWRITE) === true) {
                foreach ($files as $name => $content) $zip->addFromString($name, $content);
                $zip->close();
                $out = (string)file_get_contents($tmp);
                @unlink($tmp);
                return $out;
            }
            @unlink($tmp);
        }

        // Свій ZIP: локальний заголовок на кожен файл, потім центральний
        // каталог і кінцевий запис. Дати ставимо однакові — вміст файлу від
        // часу створення не залежить, а однаковий байт у байт результат
        // зручніший, коли доводиться порівнювати два вивантаження.
        $local = '';
        $central = '';
        $offset = 0;
        $count = 0;
        [$dosTime, $dosDate] = [0, 0x21];   // 1980-01-01, найраніша дата формату

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $raw = strlen($content);
            $deflated = gzdeflate($content, 9);
            // Стиснення без виграшу лишає файл як є: метод 0 читає будь-що
            $useDeflate = $deflated !== false && strlen($deflated) < $raw;
            $data = $useDeflate ? $deflated : $content;
            $method = $useDeflate ? 8 : 0;
            $size = strlen($data);

            $header = pack('vvvvvVVVvv', 20, 0, $method, $dosTime, $dosDate, $crc, $size, $raw, strlen($name), 0);
            $local .= "PK\x03\x04" . $header . $name . $data;

            $central .= "PK\x01\x02" . pack('v', 20) . $header
                      . pack('vvvVV', 0, 0, 0, 0, $offset) . $name;
            $offset = strlen($local);
            $count++;
        }
        return $local . $central
             . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $count, $count, strlen($central), strlen($local), 0);
    }

    /** Назви всіх файлів усередині — читаємо з локальних заголовків */
    private static function zipList(string $zip): array
    {
        $names = [];
        $pos = 0;
        while (($pos = strpos($zip, "PK\x03\x04", $pos)) !== false) {
            $h = unpack('vver/vflag/vmethod/vtime/vdate/Vcrc/Vsize/Vraw/vnamelen/vextralen', substr($zip, $pos + 4, 26));
            if (!$h) break;
            $names[] = substr($zip, $pos + 30, $h['namelen']);
            $pos += 30 + $h['namelen'] + $h['extralen'] + $h['size'];
            // Розмір у локальному заголовку може бути нулем (потоковий запис) —
            // тоді далі йти наосліп не можна, і ми задовольняємось переліком
            if ($h['size'] === 0 && $h['raw'] > 0) break;
        }
        return $names;
    }

    private static function zipRead(string $zip, string $wanted): ?string
    {
        if (class_exists('ZipArchive')) {
            $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
            file_put_contents($tmp, $zip);
            $z = new ZipArchive();
            if ($z->open($tmp) === true) {
                $out = $z->getFromName($wanted);
                $z->close();
                @unlink($tmp);
                return $out === false ? null : $out;
            }
            @unlink($tmp);
        }

        $pos = 0;
        while (($pos = strpos($zip, "PK\x03\x04", $pos)) !== false) {
            $h = unpack('vver/vflag/vmethod/vtime/vdate/Vcrc/Vsize/Vraw/vnamelen/vextralen', substr($zip, $pos + 4, 26));
            if (!$h) return null;
            $name = substr($zip, $pos + 30, $h['namelen']);
            $start = $pos + 30 + $h['namelen'] + $h['extralen'];
            if ($name === $wanted) {
                $data = substr($zip, $start, $h['size']);
                if ($h['method'] === 0) return $data;
                $out = @gzinflate($data);
                return $out === false ? null : $out;
            }
            if ($h['size'] === 0 && $h['raw'] > 0) return null;   // потоковий запис — далі не пройдемо
            $pos = $start + $h['size'];
        }
        return null;
    }
}

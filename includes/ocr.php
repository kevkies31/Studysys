<?php
// Image preprocessing: grayscale + upscale + contrast, using GD (built into PHP)
function preprocess_schedule_image(string $srcPath, string $destPath): void {
    $info = getimagesize($srcPath);
    $src = match ($info['mime']) {
        'image/jpeg' => imagecreatefromjpeg($srcPath),
        'image/png'  => imagecreatefrompng($srcPath),
        default      => throw new Exception('Only JPG and PNG images are supported.'),
    };

    $w = imagesx($src);
    $h = imagesy($src);
    $scale = 3; // upscale small/low-res photos so OCR has more pixels to work with

    $dst = imagecreatetruecolor($w * $scale, $h * $scale);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w * $scale, $h * $scale, $w, $h);
    imagefilter($dst, IMG_FILTER_GRAYSCALE);
    imagefilter($dst, IMG_FILTER_CONTRAST, -30);

    imagepng($dst, $destPath);
    imagedestroy($src);
    imagedestroy($dst);
}

// Runs Tesseract and returns raw TSV text (word-by-word with pixel positions)
function run_tesseract_tsv(string $imagePath): string {
    $cmd = 'tesseract ' . escapeshellarg($imagePath) . ' stdout --psm 6 tsv 2>&1';
    return shell_exec($cmd) ?? '';
}

// Converts raw TSV text into an array of word rows
function parse_tsv(string $tsvText): array {
    $lines = explode("\n", trim($tsvText));
    $header = explode("\t", array_shift($lines));
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line) === '') continue;
        $fields = explode("\t", $line);
        if (count($fields) < count($header)) continue;
        $rows[] = array_combine($header, $fields);
    }
    return $rows;
}

// Assigns a word to a table column based on its horizontal position.
// These fractions were tuned against a real COE layout (code/name/schedule/room/faculty).
function bucket_column(int $x, int $imgWidth): string {
    $frac = $x / $imgWidth;
    if ($frac < 0.18) return 'code';
    if ($frac < 0.35) return 'name';
    if ($frac < 0.56) return 'schedule';
    if ($frac < 0.87) return 'room';
    return 'faculty';
}

// Groups words into visual lines (Tesseract already tags each word with block/par/line numbers)
function group_into_lines(array $wordRows, int $imgWidth): array {
    $lines = [];
    foreach ($wordRows as $r) {
        if (($r['level'] ?? '') !== '5' || trim($r['text']) === '') continue;
        $key = $r['block_num'] . '-' . $r['par_num'] . '-' . $r['line_num'];
        $lines[$key]['top'] ??= (int)$r['top'];
        $lines[$key]['words'][] = $r;
    }
    uasort($lines, fn($a, $b) => $a['top'] <=> $b['top']);

    $bucketed = [];
    foreach ($lines as $line) {
        usort($line['words'], fn($a, $b) => (int)$a['left'] <=> (int)$b['left']);
        $buckets = ['code' => [], 'name' => [], 'schedule' => [], 'room' => [], 'faculty' => []];
        foreach ($line['words'] as $w) {
            $col = bucket_column((int)$w['left'], $imgWidth);
            $buckets[$col][] = $w['text'];
        }
        $bucketed[] = array_map(fn($v) => trim(implode(' ', $v)), $buckets);
    }
    return $bucketed;
}

// Groups bucketed lines into subject rows (a subject can span multiple lines: lecture + lab)
function extract_subject_rows(array $bucketedLines): array {
    $results = [];
    $currentIndex = -1;
    $codePattern = '/^[A-Z]{2,6}\d{3,4}/';

    foreach ($bucketedLines as $line) {
        if (preg_match($codePattern, $line['code'], $m)) {
            $code = $m[0];
            $nameFromCodeCol = trim(substr($line['code'], strlen($code)));
            $results[] = [
                'subject_code' => $code,
                'subject_name' => trim($nameFromCodeCol . ' ' . $line['name']),
                'faculty' => $line['faculty'],
                'entries' => [],
            ];
            $currentIndex = count($results) - 1;
        }

        if ($currentIndex >= 0 && ($line['schedule'] !== '' || $line['room'] !== '')) {
            $results[$currentIndex]['entries'][] = [
                'schedule_raw' => $line['schedule'],
                'room_raw' => $line['room'],
            ];
            if ($line['faculty'] !== '') {
                $results[$currentIndex]['faculty'] = $line['faculty'];
            }
        }
    }
    return $results;
}

// Parses "TH 9:00AM-12:00PM(lab)" into day/start/end/lab flag
function parse_schedule_text(string $text): array {
    $isLab = stripos($text, 'lab') !== false;
    preg_match('/\b(MON|TUE|WED|THU|FRI|SAT|SUN|TH|SU|M|T|W|F|S)\b/i', $text, $dayMatch);
    preg_match('/(\d{1,2}:\d{2}\s?[AP]M)\s*-\s*(\d{1,2}:\d{2}\s?[AP]M)/i', $text, $timeMatch);

    $dayMap = [
        'M' => 'MON', 'MON' => 'MON', 'T' => 'TUE', 'TUE' => 'TUE',
        'W' => 'WED', 'WED' => 'WED', 'TH' => 'THU', 'THU' => 'THU',
        'F' => 'FRI', 'FRI' => 'FRI', 'S' => 'SAT', 'SAT' => 'SAT',
        'SU' => 'SUN', 'SUN' => 'SUN',
    ];
    $dayCode = isset($dayMatch[1]) ? strtoupper($dayMatch[1]) : '';

    return [
        'day' => $dayMap[$dayCode] ?? '',
        'start_time' => isset($timeMatch[1]) ? date('H:i', strtotime($timeMatch[1])) : '',
        'end_time' => isset($timeMatch[2]) ? date('H:i', strtotime($timeMatch[2])) : '',
        'is_lab' => $isLab,
    ];
}

// Parses "BSIT 3F*/B204" into section + room
function parse_room_text(string $text): array {
    if (strpos($text, '/') !== false) {
        [$section, $room] = array_map('trim', explode('/', $text, 2));
        return ['section' => $section, 'room' => trim(preg_replace('/\(lab\)?$/i', '', $room))];
    }
    return ['section' => '', 'room' => trim(preg_replace('/\(lab\)?$/i', '', $text))];
}
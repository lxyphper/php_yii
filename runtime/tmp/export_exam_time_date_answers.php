<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require dirname(__DIR__, 2) . '/vendor/autoload.php';
require dirname(__DIR__, 2) . '/vendor/yiisoft/yii2/Yii.php';
require dirname(__DIR__, 2) . '/common/config/bootstrap.php';
require dirname(__DIR__, 2) . '/console/config/bootstrap.php';

$root = dirname(__DIR__, 2);
$config = yii\helpers\ArrayHelper::merge(
    require $root . '/common/config/main.php',
    require $root . '/common/config/main-local.php',
    require $root . '/console/config/main.php',
    require $root . '/console/config/main-local.php'
);

new yii\console\Application($config);

$output = $argv[1] ?? ($root . '/runtime/tmp/exam_time_date_answers.json');

function decodeExamJsonValue($value)
{
    if ($value === null || $value === '') {
        return [];
    }
    if (!is_string($value)) {
        return $value;
    }
    $decoded = json_decode($value, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
}

function stringifyExamValue($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (is_scalar($value)) {
        return trim((string)$value);
    }
    if (is_array($value) && array_key_exists('text', $value)) {
        return stringifyExamValue($value['text']);
    }

    $parts = [];
    foreach ((array)$value as $item) {
        $text = stringifyExamValue($item);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
}

function flattenExamValues($value): array
{
    $value = decodeExamJsonValue($value);
    if ($value === null || $value === '') {
        return [];
    }
    if (!is_array($value)) {
        return [stringifyExamValue($value)];
    }

    $values = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            foreach (flattenExamValues($item) as $subItem) {
                if ($subItem !== '') {
                    $values[] = $subItem;
                }
            }
            continue;
        }
        $text = stringifyExamValue($item);
        if ($text !== '') {
            $values[] = $text;
        }
    }

    return $values;
}

function splitExamAnswerAlternatives(array $values): array
{
    $parts = [];
    foreach ($values as $value) {
        foreach (preg_split('/\R+|\s+\/\s+|[,;]\s*/', stringifyExamValue($value)) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $parts[] = $part;
            }
        }
    }

    return array_values(array_unique($parts));
}

function classifyExamDateTimeAnswer(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    $normalized = preg_replace('/\s+/', ' ', $value);

    if (preg_match('/^(?:at\s+)?(\d{1,2})[:：](\d{2})(?:\s*(a\.?m\.?|p\.?m\.?))?$/i', $normalized, $matches)) {
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        $hasMeridiem = !empty($matches[3]);
        if ($minute <= 59 && (($hasMeridiem && $hour >= 1 && $hour <= 12) || (!$hasMeridiem && $hour <= 23))) {
            return 'time';
        }
    }
    if (preg_match('/^(?:at\s+)?(\d{1,2})\.(\d{2})\s*(a\.?m\.?|p\.?m\.?)$/i', $normalized, $matches)) {
        $hour = (int)$matches[1];
        $minute = (int)$matches[2];
        if ($hour >= 1 && $hour <= 12 && $minute <= 59) {
            return 'time';
        }
    }
    if (preg_match('/^(?:at\s+)?(\d{1,2})\s*(a\.?m\.?|p\.?m\.?)$/i', $normalized, $matches)) {
        $hour = (int)$matches[1];
        if ($hour >= 1 && $hour <= 12) {
            return 'time';
        }
    }
    if (preg_match('/^(?:at\s+)?(\d{1,2})\s*o[’\']?clock$/i', $normalized, $matches)) {
        $hour = (int)$matches[1];
        if ($hour >= 1 && $hour <= 12) {
            return 'time';
        }
    }
    if (preg_match('/^(?:half past|quarter past|quarter to)\s+[a-z]+$/i', $normalized)) {
        return 'time';
    }
    if (preg_match('/^(?:上午|下午|早上|晚上|中午|凌晨)?\s*(\d{1,2})\s*(?:点|时)(?:\s*(\d{1,2})\s*分?)?$/u', $normalized, $matches)) {
        $hour = (int)$matches[1];
        $minute = isset($matches[2]) && $matches[2] !== '' ? (int)$matches[2] : 0;
        if ($hour <= 23 && $minute <= 59) {
            return 'time';
        }
    }

    if (preg_match('/^(?:from\s+)?(.+?)\s*(?:-|–|—|to)\s*(.+)$/iu', $normalized, $matches)) {
        $leftKind = classifyExamDateTimeAnswer(trim($matches[1]));
        $rightKind = classifyExamDateTimeAnswer(trim($matches[2]));
        if ($leftKind && $rightKind && $leftKind === $rightKind) {
            return $leftKind . '_range';
        }
    }

    $month = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)';
    $weekday = '(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)';
    $datePatterns = [
        '/^\d{1,2}(?:st|nd|rd|th)?\s+' . $month . '(?:\s+\d{2,4})?$/i',
        '/^' . $month . '\s+\d{1,2}(?:st|nd|rd|th)?(?:,?\s+\d{2,4})?$/i',
        '/^' . $weekday . '$/i',
        '/^(?:星期|周)[一二三四五六日天]$/u',
        '/^\d{2,4}\s*年\s*\d{1,2}\s*月(?:\s*\d{1,2}\s*(?:日|号))?$/u',
        '/^\d{1,2}\s*月\s*\d{1,2}\s*(?:日|号)?$/u',
    ];
    foreach ($datePatterns as $pattern) {
        if (preg_match($pattern, $normalized)) {
            return 'date';
        }
    }
    if (preg_match('/^(\d{4})[\/.-](\d{1,2})[\/.-](\d{1,2})$/', $normalized, $matches)) {
        if (checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1])) {
            return 'date';
        }
    }
    if (preg_match('/^(\d{1,2})[\/-](\d{1,2})[\/-](\d{2,4})$/', $normalized, $matches)) {
        $year = (int)$matches[3];
        if ($year < 100) {
            $year += $year >= 70 ? 1900 : 2000;
        }
        $first = (int)$matches[1];
        $second = (int)$matches[2];
        if (checkdate($second, $first, $year) || checkdate($first, $second, $year)) {
            return 'date';
        }
    }
    if (preg_match('/^(?:19|20)\d{2}$/', $normalized)) {
        return 'year';
    }

    return null;
}

function matchedExamAnswers(array $answerValues): array
{
    $matches = [];
    foreach (splitExamAnswerAlternatives($answerValues) as $value) {
        $kind = classifyExamDateTimeAnswer($value);
        if ($kind !== null) {
            $matches[] = [
                'value' => $value,
                'type' => $kind,
            ];
        }
    }

    return $matches;
}

function fetchExamOptions(string $table, array $questionIds, array $groupIds): array
{
    $map = [
        'question' => [],
        'group' => [],
    ];
    if (empty($questionIds) && empty($groupIds)) {
        return $map;
    }

    $db = Yii::$app->db;
    $clauses = [];
    $params = [];
    if (!empty($questionIds)) {
        $placeholders = [];
        foreach (array_values(array_unique($questionIds)) as $index => $id) {
            $key = ':q' . $index;
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }
        $clauses[] = '(biz_type = 1 and biz_id in (' . implode(',', $placeholders) . '))';
    }
    if (!empty($groupIds)) {
        $placeholders = [];
        foreach (array_values(array_unique($groupIds)) as $index => $id) {
            $key = ':g' . $index;
            $placeholders[] = $key;
            $params[$key] = (int)$id;
        }
        $clauses[] = '(biz_type = 2 and biz_id in (' . implode(',', $placeholders) . '))';
    }

    $rows = $db->createCommand(
        'select id, biz_type, biz_id, title, content from ' . $table . ' where ' . implode(' or ', $clauses),
        $params
    )->queryAll();

    foreach ($rows as $row) {
        $scope = (int)$row['biz_type'] === 1 ? 'question' : 'group';
        $bizId = (int)$row['biz_id'];
        $map[$scope][$bizId][] = [
            'id' => (int)$row['id'],
            'title' => (string)$row['title'],
            'content' => (string)$row['content'],
        ];
    }

    return $map;
}

function optionAnswerValues($rawAnswer, string $displayAnswer, array $options): array
{
    if (empty($options)) {
        return [];
    }

    $byId = [];
    $byTitle = [];
    foreach ($options as $option) {
        $byId[(int)$option['id']] = $option['content'];
        $title = strtoupper(trim((string)$option['title']));
        if ($title !== '') {
            $byTitle[$title] = $option['content'];
        }
    }

    $values = [];
    foreach (flattenExamValues($rawAnswer) as $rawValue) {
        $rawValue = trim((string)$rawValue);
        if (ctype_digit($rawValue) && isset($byId[(int)$rawValue])) {
            $values[] = $byId[(int)$rawValue];
            continue;
        }
        $key = strtoupper($rawValue);
        if (isset($byTitle[$key])) {
            $values[] = $byTitle[$key];
        }
    }

    foreach (preg_split('/\s*,\s*|\s*\/\s*|\s*;\s*|\s+and\s+|\s+or\s+|\s+/i', $displayAnswer, -1, PREG_SPLIT_NO_EMPTY) as $token) {
        $token = trim($token, " \t\n\r\0\x0B()[]{}");
        if ($token === '') {
            continue;
        }
        if (ctype_digit($token) && isset($byId[(int)$token])) {
            $values[] = $byId[(int)$token];
            continue;
        }
        $key = strtoupper($token);
        if (isset($byTitle[$key])) {
            $values[] = $byTitle[$key];
        }
    }

    return array_values(array_unique(array_filter($values, static function ($value) {
        return trim((string)$value) !== '';
    })));
}

function addExamItemIfMatched(array &$items, array $row, array $optionValues = []): void
{
    $answerValues = [];
    foreach ([$row['display_answer'] ?? '', $row['parsed_answer'] ?? ''] as $text) {
        if (trim((string)$text) !== '') {
            $answerValues[] = (string)$text;
        }
    }
    foreach (flattenExamValues($row['raw_answer'] ?? '') as $value) {
        $answerValues[] = $value;
    }
    foreach ($optionValues as $value) {
        $answerValues[] = $value;
    }

    $answerValues = array_values(array_unique(array_filter($answerValues, static function ($value) {
        return trim((string)$value) !== '';
    })));
    $matches = matchedExamAnswers($answerValues);
    if (empty($matches)) {
        return;
    }

    $row['answer_values'] = $answerValues;
    if (!empty($optionValues)) {
        $row['option_answer_values'] = array_values($optionValues);
    }
    $row['matched_answers'] = $matches;
    $items[] = $row;
}

$db = Yii::$app->db;
$items = [];

$listeningRows = $db->createCommand(
    'select q.id, q.paper_id, q.group_id, q.number, q.sub_essay_code, q.title, q.answer, q.display_answer, q.parsed_answer,
            p.title as paper_title, p.complete_title as paper_complete_title, p.part as paper_part, p.status as paper_status,
            g.type as group_type, g.title as group_title, g.question_title as group_question_title, t.name as question_type_name
     from listening_exam_question q
     left join listening_exam_paper p on p.id = q.paper_id
     left join listening_exam_question_group g on g.id = q.group_id
     left join listening_exam_question_type t on t.id = g.type
     order by q.paper_id asc, q.number asc, q.id asc'
)->queryAll();
$listeningOptions = fetchExamOptions(
    'listening_exam_question_option',
    array_column($listeningRows, 'id'),
    array_filter(array_column($listeningRows, 'group_id'))
);
foreach ($listeningRows as $question) {
    $questionOptions = $listeningOptions['question'][(int)$question['id']] ?? [];
    $groupOptions = $listeningOptions['group'][(int)$question['group_id']] ?? [];
    $optionValues = optionAnswerValues($question['answer'], (string)($question['display_answer'] ?? ''), array_merge($questionOptions, $groupOptions));
    addExamItemIfMatched($items, [
        'subject' => '听力',
        'table' => 'listening_exam_question',
        'question_id' => (int)$question['id'],
        'paper_id' => (int)$question['paper_id'],
        'paper_title' => $question['paper_title'] ?? '',
        'paper_complete_title' => $question['paper_complete_title'] ?? '',
        'paper_part' => isset($question['paper_part']) ? (int)$question['paper_part'] : null,
        'paper_status' => isset($question['paper_status']) ? (int)$question['paper_status'] : null,
        'group_id' => (int)$question['group_id'],
        'group_type' => $question['question_type_name'] ?? (string)($question['group_type'] ?? ''),
        'group_title' => $question['group_title'] ?? '',
        'group_question_title' => $question['group_question_title'] ?? '',
        'number' => (int)$question['number'],
        'sub_essay_code' => $question['sub_essay_code'] ?? '',
        'title' => $question['title'] ?? '',
        'raw_answer' => $question['answer'],
        'display_answer' => $question['display_answer'] ?? '',
        'parsed_answer' => $question['parsed_answer'] ?? '',
    ], $optionValues);
}

$readingRows = $db->createCommand(
    'select q.id, q.paper_id, q.group_id, q.number, q.sub_essay_code, q.title, q.answer, q.display_answer, q.parsed_answer,
            p.title as paper_title, p.complete_title as paper_complete_title, p.essay_title, p.status as paper_status,
            g.type as group_type, g.title as group_title, t.name as question_type_name
     from reading_exam_question q
     left join reading_exam_paper p on p.id = q.paper_id
     left join reading_exam_question_group g on g.id = q.group_id
     left join reading_exam_question_type t on t.id = g.type
     order by q.paper_id asc, q.number asc, q.id asc'
)->queryAll();
$readingOptions = fetchExamOptions(
    'reading_exam_question_option',
    array_column($readingRows, 'id'),
    array_filter(array_column($readingRows, 'group_id'))
);
foreach ($readingRows as $question) {
    $questionOptions = $readingOptions['question'][(int)$question['id']] ?? [];
    $groupOptions = $readingOptions['group'][(int)$question['group_id']] ?? [];
    $optionValues = optionAnswerValues($question['answer'], (string)($question['display_answer'] ?? ''), array_merge($questionOptions, $groupOptions));
    addExamItemIfMatched($items, [
        'subject' => '阅读',
        'table' => 'reading_exam_question',
        'question_id' => (int)$question['id'],
        'paper_id' => (int)$question['paper_id'],
        'paper_title' => $question['paper_title'] ?? '',
        'paper_complete_title' => $question['paper_complete_title'] ?? '',
        'essay_title' => $question['essay_title'] ?? '',
        'paper_status' => isset($question['paper_status']) ? (int)$question['paper_status'] : null,
        'group_id' => (int)$question['group_id'],
        'group_type' => $question['question_type_name'] ?? (string)($question['group_type'] ?? ''),
        'group_title' => $question['group_title'] ?? '',
        'number' => (int)$question['number'],
        'sub_essay_code' => $question['sub_essay_code'] ?? '',
        'title' => $question['title'] ?? '',
        'raw_answer' => $question['answer'],
        'display_answer' => $question['display_answer'] ?? '',
        'parsed_answer' => $question['parsed_answer'] ?? '',
    ], $optionValues);
}

$summary = [
    '听力' => 0,
    '阅读' => 0,
];
foreach ($items as $item) {
    $summary[$item['subject']] = ($summary[$item['subject']] ?? 0) + 1;
}

$payload = [
    'generated_at' => date('c'),
    'criteria' => '真题听力/阅读答案项本身匹配时间、日期、星期、年月日或 1900-2099 年份格式；优先使用 display_answer/parsed_answer，选择题用选项表将答案 ID 或选项字母映射为选项文本后再筛选。',
    'total' => count($items),
    'summary_by_subject' => $summary,
    'items' => $items,
];

$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false) {
    throw new RuntimeException('JSON 编码失败：' . json_last_error_msg());
}

$dir = dirname($output);
if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
    throw new RuntimeException('无法创建目录：' . $dir);
}
if (file_put_contents($output, $json) === false) {
    throw new RuntimeException('写入失败：' . $output);
}

echo "导出成功：{$output}\n";
echo "共 " . count($items) . " 条\n";
foreach ($summary as $subject => $count) {
    echo "{$subject}: {$count}\n";
}


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

$output = $argv[1] ?? ($root . '/runtime/tmp/basic_training_time_date_answers.json');

$listeningTypeLabels = [
    1 => '单句独白',
    2 => '单轮对话',
    3 => '段落独白',
    4 => '多轮对话',
    5 => '基础场景',
];
$readingTypeLabels = [
    1 => '定位练习',
    2 => '长难句理解',
    3 => '句子精读',
    5 => '扫读练习',
    6 => '同义替换',
];
$readingSubTypeLabels = [
    1 => '识别关键词',
    2 => '识别中心句',
    3 => '识别同义替换',
];
$writingTypeLabels = [
    1 => '连词成句',
    2 => '翻译练习',
    3 => '语法纠错',
    4 => '句子合并',
    5 => '句子改写',
    6 => '词汇搭配选择',
];
$speakingTypeLabels = [
    1 => '句子跟读',
    2 => '段落跟读',
    3 => '句子汉译英',
    4 => '段落汉译英',
    5 => '合并简单句',
];

function decodeJsonValue($value)
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

function stringifyValue($value): string
{
    if ($value === null || $value === '') {
        return '';
    }
    if (is_scalar($value)) {
        return trim((string)$value);
    }
    if (is_array($value) && array_key_exists('text', $value)) {
        return stringifyValue($value['text']);
    }

    $parts = [];
    foreach ((array)$value as $item) {
        $text = stringifyValue($item);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(preg_replace('/\s+/', ' ', implode(' ', $parts)));
}

function flattenValues($value): array
{
    $value = decodeJsonValue($value);
    if ($value === null || $value === '') {
        return [];
    }
    if (!is_array($value)) {
        return [stringifyValue($value)];
    }

    $values = [];
    foreach ($value as $item) {
        if (is_array($item)) {
            foreach (flattenValues($item) as $subItem) {
                if ($subItem !== '') {
                    $values[] = $subItem;
                }
            }
            continue;
        }
        $text = stringifyValue($item);
        if ($text !== '') {
            $values[] = $text;
        }
    }

    return $values;
}

function defaultOptionLabel(int $index): string
{
    return chr(65 + ($index % 26));
}

function buildOptions($items): array
{
    $items = decodeJsonValue($items);
    if (!is_array($items)) {
        return [];
    }

    $options = [];
    foreach (array_values($items) as $index => $item) {
        if (is_scalar($item)) {
            $options[] = [
                'label' => defaultOptionLabel($index),
                'text' => stringifyValue($item),
            ];
            continue;
        }
        if (!is_array($item)) {
            continue;
        }
        $text = '';
        foreach (['value', 'content', 'text', 'title'] as $key) {
            if (isset($item[$key]) && $item[$key] !== '') {
                $text = stringifyValue($item[$key]);
                break;
            }
        }
        if ($text === '' && (!empty($item['word']) || !empty($item['translation']))) {
            $text = trim(($item['word'] ?? '') . ' - ' . ($item['translation'] ?? ''), ' -');
        }
        if ($text === '') {
            $text = stringifyValue($item);
        }

        $options[] = [
            'label' => isset($item['label']) || isset($item['name']) ? stringifyValue($item['label'] ?? $item['name']) : defaultOptionLabel($index),
            'text' => $text,
        ];
    }

    return $options;
}

function indexedAnswerValues($answer, array $options): array
{
    $values = [];
    foreach (flattenValues($answer) as $index) {
        if (is_numeric($index) && isset($options[(int)$index])) {
            $values[] = $options[(int)$index]['text'];
            continue;
        }
        $values[] = stringifyValue($index);
    }

    return array_values(array_filter($values, static function ($value) {
        return $value !== '';
    }));
}

function indexedFragmentAnswerValues($answer, array $fragments): array
{
    $values = [];
    foreach (flattenValues($answer) as $index) {
        if (is_numeric($index) && array_key_exists((int)$index, $fragments)) {
            $values[] = stringifyValue($fragments[(int)$index]);
            continue;
        }
        $values[] = stringifyValue($index);
    }

    return array_values(array_filter($values, static function ($value) {
        return $value !== '';
    }));
}

function splitAnswerAlternatives(array $values): array
{
    $parts = [];
    foreach ($values as $value) {
        foreach (preg_split('/\R+|\s+\/\s+/', stringifyValue($value)) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $parts[] = $part;
            }
        }
    }

    return array_values(array_unique($parts));
}

function classifyDateTimeAnswer(string $value): ?string
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
        $left = trim($matches[1]);
        $right = trim($matches[2]);
        $leftKind = classifyDateTimeAnswer($left);
        $rightKind = classifyDateTimeAnswer($right);
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

function matchedAnswers(array $answerValues): array
{
    $matches = [];
    foreach (splitAnswerAlternatives($answerValues) as $value) {
        $kind = classifyDateTimeAnswer($value);
        if ($kind !== null) {
            $matches[] = [
                'value' => $value,
                'type' => $kind,
            ];
        }
    }

    return $matches;
}

function addIfMatched(array &$items, array $row, array $answerValues): void
{
    $matches = matchedAnswers($answerValues);
    if (empty($matches)) {
        return;
    }
    $row['answer_values'] = array_values(array_unique(array_map('strval', $answerValues)));
    $row['matched_answers'] = $matches;
    $items[] = $row;
}

$items = [];
$db = Yii::$app->db;

$listeningRows = $db->createCommand(
    'select q.id, q.type, q.grammar, q.question_type, q.title, q.content, q.answer, q.audio_url, q.source_id, g.name as grammar_name
     from basic_training_listening_question q
     left join basic_training_listening_grammar g on g.id = q.grammar
     order by q.id asc'
)->queryAll();
foreach ($listeningRows as $question) {
    $base = [
        'subject' => '听力',
        'table' => 'basic_training_listening_question',
        'question_id' => (int)$question['id'],
        'type' => $listeningTypeLabels[(int)$question['type']] ?? (string)$question['type'],
        'knowledge' => $question['grammar_name'] ?? '',
        'topic' => '',
        'title' => $question['title'] ?? '',
        'raw_answer' => $question['answer'],
        'source_id' => $question['source_id'] ?? '',
        'audio_url' => $question['audio_url'] ?? '',
    ];
    if ((int)$question['question_type'] === 2) {
        $contentItems = decodeJsonValue($question['content']);
        $answers = decodeJsonValue($question['answer']);
        foreach ((array)$contentItems as $index => $item) {
            $options = buildOptions($item['option'] ?? []);
            $answerValues = indexedAnswerValues($answers[$index] ?? null, $options);
            $row = $base;
            $row['sub_index'] = $index + 1;
            $row['title'] = stringifyValue($item['title'] ?? ($question['title'] ?: '子题 ' . ($index + 1)));
            addIfMatched($items, $row, $answerValues);
        }
        continue;
    }
    addIfMatched($items, $base, flattenValues($question['answer']));
}

$readingRows = $db->createCommand(
    'select q.id, q.type, q.group_id, q.stem, q.content, q.answer, q.locating_words, g.type as group_type,
            g.title as group_title, g.source_id, rg.name as grammar_name
     from basic_training_reading_question q
     left join basic_training_reading_group g on g.id = q.group_id
     left join basic_training_reading_grammar rg on rg.id = g.grammar
     where g.type <> 4
     order by q.id asc'
)->queryAll();
foreach ($readingRows as $question) {
    $groupType = (int)($question['group_type'] ?? 0);
    $questionType = (int)($question['type'] ?? 0);
    $typeLabel = $readingTypeLabels[$groupType] ?? (string)$groupType;
    if ($groupType === 1 && isset($readingSubTypeLabels[$questionType])) {
        $typeLabel .= '-' . $readingSubTypeLabels[$questionType];
    }
    $contentItems = decodeJsonValue($question['content']);
    $options = $groupType === 2 ? buildOptions($question['locating_words']) : [];
    if ($groupType === 1) {
        $answerValues = indexedFragmentAnswerValues($question['answer'], is_array($contentItems) ? $contentItems : []);
    } elseif (!empty($options)) {
        $answerValues = indexedAnswerValues($question['answer'], $options);
    } else {
        $answerValues = flattenValues($question['answer']);
    }
    addIfMatched($items, [
        'subject' => '阅读',
        'table' => 'basic_training_reading_question',
        'question_id' => (int)$question['id'],
        'group_id' => (int)$question['group_id'],
        'type' => $typeLabel,
        'knowledge' => $question['grammar_name'] ?? '',
        'topic' => $question['group_title'] ?? '',
        'title' => $question['stem'] ?? '',
        'raw_answer' => $question['answer'],
        'source_id' => $question['source_id'] ?? '',
    ], $answerValues);
}

$writingRows = $db->createCommand(
    'select q.id, q.group_id, q.stem, q.content, q.answer, g.type as group_type, g.title as group_title,
            g.source_id, wg.name as grammar_name, wt.name as topic_name
     from basic_training_writing_question q
     left join basic_training_writing_group g on g.id = q.group_id
     left join basic_training_writing_grammar wg on wg.id = g.grammar
     left join basic_training_writing_topic wt on wt.id = g.topic
     order by q.id asc'
)->queryAll();
foreach ($writingRows as $question) {
    $groupType = (int)($question['group_type'] ?? 0);
    $answerValues = $groupType === 6
        ? indexedAnswerValues($question['answer'], buildOptions($question['content']))
        : flattenValues($question['answer']);
    addIfMatched($items, [
        'subject' => '写作',
        'table' => 'basic_training_writing_question',
        'question_id' => (int)$question['id'],
        'group_id' => (int)$question['group_id'],
        'type' => $writingTypeLabels[$groupType] ?? (string)$groupType,
        'knowledge' => $question['grammar_name'] ?? '',
        'topic' => $question['topic_name'] ?? '',
        'title' => $question['stem'] ?: ($question['group_title'] ?? ''),
        'raw_answer' => $question['answer'],
        'source_id' => $question['source_id'] ?? '',
    ], $answerValues);
}

$speakingRows = $db->createCommand(
    'select q.id, q.title, q.answer, q.group_id, g.title as group_title, t.name as topic_name, t.type as topic_type
     from speaking_special_item_question q
     left join speaking_special_item_group g on g.id = q.group_id
     left join speaking_special_item_topic t on t.id = g.topic
     order by q.id asc'
)->queryAll();
foreach ($speakingRows as $question) {
    addIfMatched($items, [
        'subject' => '口语',
        'table' => 'speaking_special_item_question',
        'question_id' => (int)$question['id'],
        'group_id' => (int)$question['group_id'],
        'type' => $speakingTypeLabels[(int)($question['topic_type'] ?? 0)] ?? (string)($question['topic_type'] ?? ''),
        'knowledge' => $question['topic_name'] ?? '',
        'topic' => $question['group_title'] ?? '',
        'title' => $question['title'] ?? '',
        'raw_answer' => $question['answer'],
    ], flattenValues($question['answer']));
}

$summary = [
    '听力' => 0,
    '阅读' => 0,
    '写作' => 0,
    '口语' => 0,
];
foreach ($items as $item) {
    $subject = $item['subject'];
    $summary[$subject] = ($summary[$subject] ?? 0) + 1;
}

$payload = [
    'generated_at' => date('c'),
    'criteria' => '答案项本身匹配时间、日期、星期、年月日或 1900-2099 年份格式；阅读/听力/写作选择题先把下标映射为实际选项文本。',
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

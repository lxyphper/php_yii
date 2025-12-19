<?php

$inputFile = '/Users/lixiangyang/php-project/my-yii2/console/runtime/tmp/COCA_with_translation.txt';
$outputFile = '/Users/lixiangyang/php-project/my-yii2/console/runtime/tmp/extracted_words.txt';

$words = [];
$lines = file($inputFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$inWordGroup = false;

foreach ($lines as $line) {
    if ($line === '————————————————') {
        $inWordGroup = false;
        continue;
    }
    
    if (!$inWordGroup && !preg_match('/^\d+→/', $line) && !preg_match('/^[a-z]+\./', $line) && !preg_match('/^n\./', $line) && !preg_match('/^vt\./', $line) && !preg_match('/^vi\./', $line) && !preg_match('/^conj\./', $line) && !preg_match('/^prep\./', $line) && !preg_match('/^adv\./', $line) && !preg_match('/^adj\./', $line) && !preg_match('/^pron\./', $line) && !preg_match('/^art\./', $line)) {
        $word = trim($line);
        if (!empty($word) && $word !== '————————————————') {
            $words[] = $word;
            $inWordGroup = true;
        }
    }
}

$cleanWords = [];
foreach ($words as $word) {
    if (preg_match('/^(\d+)→(.+)$/', $word, $matches)) {
        $cleanWords[] = trim($matches[2]);
    } else {
        $cleanWords[] = $word;
    }
}

file_put_contents($outputFile, implode("\n", $cleanWords));

echo "Extracted " . count($cleanWords) . " words to $outputFile\n";

?>
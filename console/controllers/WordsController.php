<?php

namespace console\controllers;

use app\models\Vocabulary;
use app\models\VocabularyBook;
use app\models\VocabularyBookUnit;
use app\models\VocabularyExt;
use app\models\VocabularyQuiz;
use app\models\VocabularyQuizOption;
use app\models\VocabularyUnitRelation;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\db\Expression;
use yii\db\Query;
use yii\helpers\FileHelper;
use yii\helpers\Json;
use OSS\OssClient;
use OSS\Core\OssException;
use Yii;
use Throwable;

//处理单词数据
class WordsController extends Controller
{
    /*
     * 单词脚本列表（php yii words/<command>）：
     * - export-english-units [outputFile]：导出 vocabulary_book_unit 表中英文名称的记录
     * - sync-english-units-to-chinese [dryRun]：按照提供的中文列表批量更新英文单元名称
     * - compare-chinese-unit-translations [outputFile]：对比中英文单元翻译并输出差异
     * - export-quiz-type2-mismatch [outputFile]：导出拼写题(quiz_type=2) 的分段不匹配数据
     * - export-quiz-type1-options [minBookId outputFile]：导出看词示意题(quiz_type=1) 的选项数据
     * - export-missing-quiz-words [minBookId outputFile]：导出题型 1/2 题目不足两个的单词名称
     * - fix-quiz-type2-segments [inputFile]：根据 JSON 修复数据库中拼写题的分段
     * - apply-quiz-type2-json-fix [inputFile]：把 JSON 中的拼写题修复写回数据库
     * - fix-quiz-type2-case-in-json [inputFile outputFile]：修复 JSON 内拼写题内容的大小写
     * - fix-quiz-type2-segments-in-json [inputFile outputFile]：修正 JSON 内拼写题的分段结构
     * - fetch-unipus-keyword [name]：调用 Unipus 接口拉取单词释义
     * - deal-with-word-list：批量初始化词表基础数据
     * - sync-word-data [filePath]：同步 word_data.json 中目标词书的单词与关联
     * - import-langeek-units [bookId filePath]：导入 Langeek JSON 中的单元、单词及关联
     * - update-word-data-from-json [filePath]：用 JSON 数据补全翻译缺失的单词
     * - init-word-list-quizzes [wordListFile chunkDir sentenceDir]：批量生成拼写题与看词示意题
     * - analyze-co-ca：分析 COCA 导出的单词列表
     * - init-word-info：根据生成的卡片 JSON 初始化单词 OSS 数据
     * - fix-word-info [id]：下载并拆解 OSS 卡片数据覆盖到字段
     * - generate-book-relation：批量生成词书与单词的关联关系
     * - init-from-test-words：根据测试词表初始化词书/单词
     * - update-quiz-chunks [directory]：更新拼写题的音频、分段与内容
     * - import-sentence-quizzes [directory]：导入看词示意题
     * - import-assembled [filePath]：导入整合后的 Langeek 单词数据
     * - update-core-meanings-from-pos [filePath dryRun]：用 parts_of_speech 批量更新 core_meanings
     * - export-vocabulary-ext [bookIds output]：导出指定词书的 vocabulary_ext 数据
     * - import-vocabulary-ext-translations [inputFile dryRun]：导入扩展信息的翻译
     * - import-quiz-chunks-from-split [directory]：导入 words_new_split 目录下的拼写题
     * - update-vocabulary-ext-from-batch [batchFile translationDir]：批量写入 batch JSON 与翻译目录中的扩展字段
     * - fix-spelling-quiz-spaces [bookIds threshold]：修复拼写题中多余或缺失的空格
     * - strip-quiz-chunk-spaces [bookIds]：移除拼写题 chunk 文本中的多余空格
     * - clean-quiz-chunk-duplicates [bookIds]：清理重复的拼写题记录
     * - fix-quiz-answer-from-options [minBookId]：根据选项内容修复拼写题答案
     * - fix-quiz-type1-answers-for-book [bookId]：修复指定词书看词示意题的答案
     * - fix-quiz-type1-option-correctness [inputFile dryRun]：修复看词示意题的正确选项标记
     * - update-unit-names-from-file [inputFile dryRun]：按文件更新词书单元名称
     * - update-unit-cover-images [filePath]：批量更新单元封面图
     * - match-unit-cover-images [inputDir dryRun]：匹配本地图片并更新单元封面
     * - update-pronunciation-audio-paths [filePath]：从 pronunciation_data.json 同步 uk/us 音频路径到 syllable_ipa_uk/us
     * - clean-vocabulary-unit-relation-duplicates [batchSize]：清理重复的词书-单词关联
     * - normalize-core-meanings-pos [batchSize limit dryRun]：规范 core_meanings 中的词性展示
     * - normalize-collocations [batchSize limit dryRun]：规范 collocations 内容格式
     * - fix-collocation-usage-translations [batchSize limit dryRun]：修复搭配例句的翻译
     */
    private const UNIPUS_ENDPOINT = 'https://open.unipus.cn/openapi/dict/v1/keyword';
    private const UNIPUS_LAN_ID = 1;
    private const UNIPUS_APP_ID = 'yf_test_2rruoul4ihwalflr';
    private const UNIPUS_SECRET = 'F7724780E4BB3C9FC0F1D88B8E106CC7';
    private const WORD_DATA_FILE = '/runtime/tmp/word_data.json';
    private const TARGET_BOOK_IDS = [6, 7, 183, 184, 185];
    // Skip all sentence quiz imports until this word is reached (case-insensitive match)
    private const SENTENCE_IMPORT_SKIP_UNTIL = '';
    private const UNIT_TRANSLATION_ENDPOINT = 'http://100.64.0.2:8085/translate-to-english/invoke';
    private const DEFAULT_SELECTED_WORD_DETAILS_FILE = '@console/runtime/tmp/selected_word_details.json';
    private const DEFAULT_LANGEEK_UNIT_FILE = '@console/runtime/tmp/langeek_output_new1281_integrated.json';
    private const CORE_MEANING_POS_ABBREVIATION_MAP = [
        'n' => 'n.',
        'noun' => 'n.',
        'nouns' => 'n.',
        'countable noun' => 'n.',
        'countable nouns' => 'n.',
        'uncountable noun' => 'n.',
        'uncountable nouns' => 'n.',
        'plural noun' => 'n.',
        'plural nouns' => 'n.',
        'proper noun' => 'n.',
        'proper nouns' => 'n.',
        'collective noun' => 'n.',
        'collective nouns' => 'n.',
        'adj' => 'adj.',
        'adjective' => 'adj.',
        'adjectives' => 'adj.',
        'predicative adjective' => 'adj.',
        'comparative adjective' => 'adj.',
        'superlative adjective' => 'adj.',
        'v' => 'v.',
        'verb' => 'v.',
        'verbs' => 'v.',
        'linking verb' => 'v.',
        'auxiliary verb' => 'aux.',
        'auxiliary verbs' => 'aux.',
        'auxiliary' => 'aux.',
        'modal verb' => 'modal v.',
        'modal verbs' => 'modal v.',
        'phrasal verb' => 'phr.v.',
        'phrasal verbs' => 'phr.v.',
        'adv' => 'adv.',
        'adverb' => 'adv.',
        'adverbs' => 'adv.',
        'prep' => 'prep.',
        'preposition' => 'prep.',
        'prepositions' => 'prep.',
        'pron' => 'pron.',
        'pronoun' => 'pron.',
        'pronouns' => 'pron.',
        'conj' => 'conj.',
        'conjunction' => 'conj.',
        'conjunctions' => 'conj.',
        'int' => 'int.',
        'interjection' => 'int.',
        'interjections' => 'int.',
        'exclamation' => 'excl.',
        'exclamations' => 'excl.',
        'det' => 'det.',
        'determiner' => 'det.',
        'determiners' => 'det.',
        'art' => 'art.',
        'article' => 'art.',
        'articles' => 'art.',
        'num' => 'num.',
        'numeral' => 'num.',
        'numerals' => 'num.',
        'idm' => 'idm.',
        'idiom' => 'idm.',
        'idioms' => 'idm.',
        'phr' => 'phr.',
        'phrase' => 'phr.',
        'phrases' => 'phr.',
        'expr' => 'expr.',
        'expression' => 'expr.',
        'expressions' => 'expr.',
        'abbr' => 'abbr.',
        'abbreviation' => 'abbr.',
        'abbreviations' => 'abbr.',
        'pref' => 'pref.',
        'prefix' => 'pref.',
        'prefixes' => 'pref.',
        'suf' => 'suf.',
        'suffix' => 'suf.',
        'suffixes' => 'suf.',
        'aux' => 'aux.',
        'gerund' => 'v-ing',
        'present participle' => 'v-ing',
        'past participle' => 'p.p.',
        'participle' => 'part.',
        'participles' => 'part.',
        'part' => 'part.',
        'comparative' => 'comp.',
        'comp' => 'comp.',
        'superlative' => 'sup.',
        'sup' => 'sup.',
        'plural' => 'pl.',
        'pl' => 'pl.',
    ];
    private const CORE_MEANING_POS_ALREADY_SHORT = [
        'n.' => true,
        'adj.' => true,
        'adv.' => true,
        'v.' => true,
        'v-ing' => true,
        'p.p.' => true,
        'phr.v.' => true,
        'phr.' => true,
        'prep.' => true,
        'pron.' => true,
        'conj.' => true,
        'int.' => true,
        'excl.' => true,
        'det.' => true,
        'art.' => true,
        'num.' => true,
        'abbr.' => true,
        'idm.' => true,
        'pref.' => true,
        'suf.' => true,
        'aux.' => true,
        'modal v.' => true,
        'expr.' => true,
        'part.' => true,
        'pl.' => true,
        'comp.' => true,
        'sup.' => true,
    ];

    /** @var array<string, Vocabulary> */
    private array $vocabularyCache = [];
    private bool $unitTranslationCacheDirty = false;

    /**
     * 导出 vocabulary_book_unit 表中 name 为英文的记录
     */
    public function actionExportEnglishUnits(string $outputFile = ''): int
    {
        $outputPath = $outputFile !== '' ? Yii::getAlias($outputFile) : Yii::getAlias('@console/runtime/tmp/vocabulary_book_units_english.json');
        $englishUnits = $this->collectEnglishUnits();

        FileHelper::createDirectory(dirname($outputPath));
        file_put_contents($outputPath, Json::encode($englishUnits, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->stdout(sprintf("Found %d units with English names. Output saved to %s\n", count($englishUnits), $outputPath));

        return ExitCode::OK;
    }

    /**
     * 将英文单元名称依照提供的中文列表进行更新
     */
    public function actionSyncEnglishUnitsToChinese(bool $dryRun = false): int
    {
        $translations = $this->getChineseUnitNameList();
        $englishUnits = $this->collectEnglishUnits();

        $expected = count($translations);
        $actual = count($englishUnits);
        if ($expected !== $actual) {
            $this->stderr(sprintf("数据条数不一致：期望 %d 条中文名称，实际匹配到 %d 条英文记录\n", $expected, $actual));
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $updated = 0;
        foreach ($englishUnits as $index => $unit) {
            $newName = $translations[$index];
            $oldName = (string)$unit['name'];
            $unitId = (int)$unit['id'];

            if ($oldName === $newName) {
                continue;
            }

            if ($dryRun) {
                $this->stdout(sprintf("[dry-run] #%d book:%d %s -> %s\n", $unitId, (int)$unit['book_id'], $oldName, $newName));
                $updated++;
                continue;
            }

            $affected = VocabularyBookUnit::updateAll(
                ['name' => $newName, 'update_time' => time()],
                ['id' => $unitId]
            );

            if ($affected > 0) {
                $this->stdout(sprintf("已更新 #%d book:%d %s -> %s\n", $unitId, (int)$unit['book_id'], $oldName, $newName));
                $updated++;
            } else {
                $this->stderr(sprintf("更新失败 #%d book:%d %s -> %s\n", $unitId, (int)$unit['book_id'], $oldName, $newName));
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        $this->stdout(sprintf("完成，合计处理 %d 条记录（dryRun=%s）\n", $updated, $dryRun ? 'true' : 'false'));
        return ExitCode::OK;
    }

    /**
     * 调用翻译接口翻译中文单元名称并与英文名称进行比对
     */
    public function actionCompareChineseUnitTranslations(string $outputFile = ''): int
    {
        $englishUnits = $this->collectEnglishUnits();
        $chineseNames = $this->getChineseUnitNameList();

        if (count($englishUnits) !== count($chineseNames)) {
            $this->stderr(sprintf(
                "英文单位数量 (%d) 与中文名称数量 (%d) 不一致，可能存在无法一一对应的情况。\n",
                count($englishUnits),
                count($chineseNames)
            ));
        }

        $client = new Client(['timeout' => 10]);
        $translationCache = [];
        $results = [];
        $matchCount = 0;
        $mismatchCount = 0;

        foreach ($chineseNames as $index => $chineseName) {
            $cacheKey = $this->buildUnitTranslationCacheKey($chineseName);
            if ($cacheKey !== '' && array_key_exists($cacheKey, $translationCache)) {
                $translated = $translationCache[$cacheKey];
            } else {
                $translated = $this->requestUnitNameTranslation($chineseName, $client);
                if ($cacheKey !== '') {
                    $translationCache[$cacheKey] = $translated;
                }
            }

            $englishUnit = $englishUnits[$index] ?? null;
            $englishName = is_array($englishUnit) ? (string)($englishUnit['name'] ?? '') : '';

            $translatedNormalized = $translated !== null ? $this->normalizeUnitName($translated) : '';
            $englishNormalized = $englishName !== '' ? $this->normalizeUnitName($englishName) : '';
            $match = $translatedNormalized !== '' && $translatedNormalized === $englishNormalized;
            if ($match) {
                $matchCount++;
            } else {
                $mismatchCount++;
            }

            $results[] = [
                'index' => $index,
                'chinese' => $chineseName,
                'translated' => $translated,
                'english_unit_name' => $englishName,
                'unit_id' => $englishUnit['id'] ?? null,
                'book_id' => $englishUnit['book_id'] ?? null,
                'match' => $match,
                'translated_normalized' => $translatedNormalized,
                'english_normalized' => $englishNormalized,
            ];
        }

        $outputPath = $outputFile !== '' ? Yii::getAlias($outputFile) : Yii::getAlias('@console/runtime/tmp/chinese_unit_translation_check.json');
        FileHelper::createDirectory(dirname($outputPath));
        file_put_contents($outputPath, Json::encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->stdout(sprintf(
            "翻译完成，共 %d 条记录，匹配 %d 条，不匹配 %d 条。结果保存在 %s\n",
            count($results),
            $matchCount,
            $mismatchCount,
            $outputPath
        ));

        return ExitCode::OK;
    }

    /**
     * @return VocabularyBookUnitQuery|\yii\db\ActiveQuery
     */
    private function buildEnglishUnitQuery()
    {
        return VocabularyBookUnit::find()
            ->select(['id', 'name', 'book_id', 'status', 'sort_order'])
            ->where(['>=', 'book_id', 195])
            ->orderBy(['book_id' => SORT_ASC, 'sort_order' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray();
    }

    /**
     * @return array<int,array{id:int,name:string,book_id:int,status:int,sort_order:?float}>
     */
    private function collectEnglishUnits(): array
    {
        $units = [];
        foreach ($this->buildEnglishUnitQuery()->batch(500) as $rows) {
            foreach ($rows as $row) {
                $name = (string)$row['name'];
                if (!$this->isEnglishText($name)) {
                    continue;
                }

                $units[] = [
                    'id' => (int)$row['id'],
                    'name' => $name,
                    'book_id' => (int)$row['book_id'],
                    'status' => (int)$row['status'],
                    'sort_order' => isset($row['sort_order']) ? (float)$row['sort_order'] : null,
                ];
            }
        }

        return $units;
    }

    /**
     * 导出 quiz_type=2 的题目拼接结果与单词不一致的数据
     */
    public function actionExportQuizType2Mismatch(string $outputFile = ''): int
    {
        $outputPath = $outputFile !== '' ? Yii::getAlias($outputFile) : Yii::getAlias('@console/runtime/tmp/quiz_type2_mismatch.json');
        $query = VocabularyQuiz::find()
            ->alias('quiz')
            ->select([
                'quiz.id',
                'quiz.vocabulary_id',
                'quiz.quiz_question',
                'word_name' => 'v.name',
            ])
            ->leftJoin(['v' => Vocabulary::tableName()], 'v.id = quiz.vocabulary_id')
            ->innerJoin(['vur' => VocabularyUnitRelation::tableName()], 'vur.vocabulary_id = quiz.vocabulary_id')
            ->where(['quiz.quiz_type' => 2])
            ->andWhere(['>=', 'vur.book_id', 195])
            ->distinct()
            ->asArray();

        $mismatches = [];
        foreach ($query->batch(500) as $rows) {
            foreach ($rows as $row) {
                $wordName = (string)($row['word_name'] ?? '');
                [$joinedQuestion, $segments, $parseError] = $this->buildQuizQuestionString($row['quiz_question'] ?? '');
                $segmentsWithSpace = $this->buildSegmentsWithSpaces($segments, $wordName);

                $normalizedWord = $this->normalizeWordWithoutSpaces($wordName);
                $normalizedQuestion = $this->normalizeWordWithoutSpaces($joinedQuestion);

                $shouldRecord = $parseError !== null || ($normalizedWord !== '' && $normalizedQuestion !== '' && $normalizedWord !== $normalizedQuestion);
                if (!$shouldRecord) {
                    continue;
                }

                $mismatches[] = [
                    'quiz_id' => (int)$row['id'],
                    'vocabulary_id' => (int)$row['vocabulary_id'],
                    'word' => $wordName,
                    'segments' => $segments,
                    'joined_question' => $joinedQuestion,
                    'normalized_word' => $normalizedWord,
                    'normalized_question' => $normalizedQuestion,
                    'quiz_question_raw' => $row['quiz_question'],
                    'segments_with_space' => $segmentsWithSpace,
                    'parse_error' => $parseError,
                ];
            }
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            FileHelper::createDirectory($directory);
        }

        $encoded = json_encode($mismatches, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            echo "写入 JSON 失败：无法编码结果\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (file_put_contents($outputPath, $encoded) === false) {
            echo "写入 JSON 失败：{$outputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo sprintf("检测完成，共发现 %d 条不一致的数据，已输出到 %s\n", count($mismatches), $outputPath);
        return ExitCode::OK;
    }

    /**
     * 导出 quiz_type=1 的题目以及所有选项
     */
    public function actionExportQuizType1Options(int $minBookId = 200, string $outputFile = ''): int
    {
        $outputPath = $outputFile !== '' ? Yii::getAlias($outputFile) : Yii::getAlias('@console/runtime/tmp/new_quiz_type1_options.json');

        $query = VocabularyQuiz::find()
            ->alias('quiz')
            ->select([
                'quiz.id',
                'quiz.quiz_question',
                'quiz.vocabulary_id',
                'vocabulary_name' => 'v.name',
                'core_meanings' => 'ext.core_meanings',
            ])
            ->innerJoin(['vur' => VocabularyUnitRelation::tableName()], 'vur.vocabulary_id = quiz.vocabulary_id')
            ->innerJoin(['v' => Vocabulary::tableName()], 'v.id = quiz.vocabulary_id')
            ->leftJoin(['ext' => VocabularyExt::tableName()], 'ext.vocabulary_id = v.id')
            ->where(['quiz.quiz_type' => 1])
            ->andWhere(['>=', 'vur.book_id', $minBookId])
            ->distinct()
            ->asArray();

        $quizzes = [];
        foreach ($query->batch(500) as $rows) {
            foreach ($rows as $row) {
                $quizId = (int)$row['id'];
                if (isset($quizzes[$quizId])) {
                    continue;
                }

                $quizzes[$quizId] = [
                    'id' => $quizId,
                    'quiz_question' => $row['quiz_question'],
                    'vocabulary_id' => isset($row['vocabulary_id']) ? (int)$row['vocabulary_id'] : null,
                    'vocabulary_name' => $row['vocabulary_name'] ?? null,
                    'core_meanings' => $row['core_meanings'] ?? null,
                ];
            }
        }

        $quizIds = array_keys($quizzes);
        $optionsByQuiz = [];
        if ($quizIds !== []) {
            $optionQuery = VocabularyQuizOption::find()
                ->where(['quiz_id' => $quizIds])
                ->orderBy(['quiz_id' => SORT_ASC, 'id' => SORT_ASC])
                ->asArray();

            foreach ($optionQuery->batch(500) as $optionRows) {
                foreach ($optionRows as $option) {
                    $quizId = (int)$option['quiz_id'];
                    $optionsByQuiz[$quizId][] = [
                        'id' => (int)$option['id'],
                        'quiz_id' => $quizId,
                        'definition' => $option['definition'],
                        'is_correct' => isset($option['is_correct']) ? (int)$option['is_correct'] : null,
                    ];
                }
            }
        }

        $result = [];
        foreach ($quizzes as $quizId => $quiz) {
            $result[] = [
                'id' => $quiz['id'],
                'quiz_question' => $quiz['quiz_question'],
                'vocabulary_id' => $quiz['vocabulary_id'],
                'vocabulary_name' => $quiz['vocabulary_name'],
                'core_meanings' => $quiz['core_meanings'],
                'options' => $optionsByQuiz[$quizId] ?? [],
            ];
        }

        FileHelper::createDirectory(dirname($outputPath));
        file_put_contents($outputPath, Json::encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->stdout(sprintf("Exported %d quizzes to %s\n", count($result), $outputPath));

        return ExitCode::OK;
    }

    /**
     * 导出指定词书范围内缺失题型 1/2 题目的单词名称
     */
    public function actionExportMissingQuizWords(int $minBookId = 195, string $outputFile = '@console/runtime/tmp/missing_quiz_words.json'): int
    {
        $outputPath = Yii::getAlias($outputFile);
        $requiredQuizTypes = [1, 2];
        $requiredQuizCount = 1; // 每种题型至少一题

        $targetVocabularyQuery = (new Query())
            ->select(['vur.vocabulary_id'])
            ->from(['vur' => VocabularyUnitRelation::tableName()])
            ->where(['>=', 'vur.book_id', $minBookId])
            ->groupBy(['vur.vocabulary_id']);

        $wordRows = (new Query())
            ->select(['v.id', 'v.name'])
            ->from(['target_words' => $targetVocabularyQuery])
            ->innerJoin(['v' => Vocabulary::tableName()], 'v.id = target_words.vocabulary_id')
            ->orderBy(['v.id' => SORT_ASC])
            ->all();

        $wordNames = [];
        foreach ($wordRows as $row) {
            $wordId = (int)$row['id'];
            $wordNames[$wordId] = (string)$row['name'];
        }

        $missingNames = [];
        if ($wordNames !== []) {
            $counts = [];
            $quizCounts = (new Query())
                ->select([
                    'quiz.vocabulary_id',
                    'quiz.quiz_type',
                    'cnt' => new Expression('COUNT(quiz.id)'),
                ])
                ->from(['quiz' => VocabularyQuiz::tableName()])
                ->innerJoin(['target_words' => clone $targetVocabularyQuery], 'target_words.vocabulary_id = quiz.vocabulary_id')
                ->where(['quiz.quiz_type' => $requiredQuizTypes])
                ->andWhere(['quiz.status' => 1])
                ->groupBy(['quiz.vocabulary_id', 'quiz.quiz_type'])
                ->all();

            foreach ($quizCounts as $countRow) {
                $vocabularyId = (int)$countRow['vocabulary_id'];
                $quizType = (int)$countRow['quiz_type'];
                $counts[$vocabularyId][$quizType] = (int)$countRow['cnt'];
            }

            foreach ($wordNames as $wordId => $wordName) {
                foreach ($requiredQuizTypes as $quizType) {
                    $quizCount = $counts[$wordId][$quizType] ?? 0;
                    if ($quizCount < $requiredQuizCount) {
                        $missingNames[] = $wordName;
                        break;
                    }
                }
            }
        }

        $directory = dirname($outputPath);
        if (!is_dir($directory)) {
            FileHelper::createDirectory($directory);
        }

        file_put_contents($outputPath, Json::encode($missingNames, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->stdout(sprintf(
            "Found %d words without enough quizzes. Output: %s\n",
            count($missingNames),
            $outputPath
        ));

        return ExitCode::OK;
    }

    /**
     * @return array{string,array<int,string>,string|null}
     */
    private function buildQuizQuestionString(?string $quizQuestion): array
    {
        if ($quizQuestion === null || trim($quizQuestion) === '') {
            return ['', [], 'quiz_question 为空'];
        }

        $decoded = json_decode($quizQuestion, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $segments = array_map(static function ($segment): string {
                return (string)$segment;
            }, $decoded);
            return [implode('', $segments), $segments, null];
        }

        return [$quizQuestion, [], 'quiz_question 不是 JSON 数组'];
    }

    private function normalizeWordWithoutSpaces(?string $word): string
    {
        if ($word === null) {
            return '';
        }

        $trimmed = preg_replace('/\s+/u', '', $word);
        if ($trimmed === null || $trimmed === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($trimmed, 'UTF-8');
        }

        return strtolower($trimmed);
    }

    /**
     * 从 mismatch JSON 读取数据，自动删除 segments 中的多余字母并更新题目
     */
    public function actionFixQuizType2Segments(string $inputFile = '@console/runtime/tmp/quiz_type2_mismatch.json'): int
    {
        $filePath = Yii::getAlias($inputFile);
        if (!is_file($filePath)) {
            echo "未找到文件: {$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            echo "读取文件失败: {$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON 解析失败: " . json_last_error_msg() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!is_array($data)) {
            echo "JSON 内容不是数组，无法处理\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $updated = 0;
        $skipped = [];
        foreach ($data as $index => $row) {
            if (!is_array($row)) {
                $skipped[] = "row{$index}";
                continue;
            }

            $quizId = (int)($row['quiz_id'] ?? 0);
            $word = (string)($row['word'] ?? '');
            $segments = $row['segments'] ?? null;

            if ($quizId <= 0 || $word === '' || !is_array($segments) || empty($segments)) {
                $skipped[] = $quizId > 0 ? $quizId : "row{$index}";
                continue;
            }

            $segments = array_map(static function ($segment) {
                return (string)$segment;
            }, $segments);
            $fixedSegments = $this->rebuildSegmentsForWord($segments, $word);
            if ($fixedSegments === null || $fixedSegments === $segments) {
                $skipped[] = $quizId;
                continue;
            }

            $encoded = json_encode($fixedSegments, JSON_UNESCAPED_UNICODE);
            if ($encoded === false) {
                $skipped[] = $quizId;
                continue;
            }

            $affected = VocabularyQuiz::updateAll(
                [
                    'quiz_question' => $encoded,
                    'update_time' => time(),
                ],
                ['id' => $quizId]
            );

            if ($affected > 0) {
                $updated++;
                echo "已修复 quiz_id={$quizId}\n";
            } else {
                $skipped[] = $quizId;
            }
        }

        $skipped = array_unique($skipped);
        echo sprintf("处理完成：成功 %d 条，跳过 %d 条\n", $updated, count($skipped));
        if (!empty($skipped)) {
            echo '跳过的标识：' . implode(', ', $skipped) . "\n";
        }

        return ExitCode::OK;
    }

    /**
     * 根据 mismatch JSON 更新 vocabulary_quiz 的 quiz_question/quiz_answer
     */
    public function actionApplyQuizType2JsonFix(string $inputFile = '@console/runtime/tmp/quiz_type2_mismatch.json'): int
    {
        $filePath = Yii::getAlias($inputFile);
        if (!is_file($filePath)) {
            echo "未找到文件: {$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            echo "读取文件失败: {$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON 解析失败: " . json_last_error_msg() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (!is_array($data)) {
            echo "JSON 内容不是数组，无法处理\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $updated = 0;
        $skipped = [];
        foreach ($data as $index => $row) {
            if (!is_array($row)) {
                $skipped[] = "row{$index}";
                continue;
            }

            $quizId = (int)($row['quiz_id'] ?? 0);
            $segments = $row['segments'] ?? null;
            $segmentsWithSpace = $row['segments_with_space'] ?? null;
            $word = (string)($row['word'] ?? '');

            if ($quizId <= 0 || !is_array($segments) || empty($segments)) {
                $skipped[] = $quizId > 0 ? $quizId : "row{$index}";
                continue;
            }

            $segments = array_map(static function ($segment) {
                return (string)$segment;
            }, $segments);
            if (!is_array($segmentsWithSpace) || empty($segmentsWithSpace)) {
                $segmentsWithSpace = $this->buildSegmentsWithSpaces($segments, $word);
            } else {
                $segmentsWithSpace = array_map(static function ($segment) {
                    return (string)$segment;
                }, $segmentsWithSpace);
            }

            $questionJson = json_encode($segments, JSON_UNESCAPED_UNICODE);
            $answerJson = json_encode($segmentsWithSpace, JSON_UNESCAPED_UNICODE);
            if ($questionJson === false || $answerJson === false) {
                $skipped[] = $quizId;
                continue;
            }

            $affected = VocabularyQuiz::updateAll(
                [
                    'quiz_question' => $questionJson,
                    'quiz_answer' => $answerJson,
                    'update_time' => time(),
                ],
                ['id' => $quizId]
            );

            if ($affected > 0) {
                $updated++;
                echo "已覆盖 quiz_id={$quizId}\n";
            } else {
                $skipped[] = $quizId;
            }
        }

        $skipped = array_unique($skipped);
        echo sprintf("更新完成：成功 %d 条，跳过 %d 条\n", $updated, count($skipped));
        if (!empty($skipped)) {
            echo '跳过: ' . implode(', ', $skipped) . "\n";
        }

        return ExitCode::OK;
    }

    /**
     * 将 mismatch JSON 中大小写不一致的 segments 调整为与单词一致的大小写
     */
    public function actionFixQuizType2CaseInJson(
        string $inputFile = '@console/runtime/tmp/quiz_type2_mismatch.json',
        string $outputFile = ''
    ): int {
        $inputPath = Yii::getAlias($inputFile);
        $outputPath = $outputFile === '' ? $inputPath : Yii::getAlias($outputFile);

        if (!is_file($inputPath)) {
            echo "未找到文件: {$inputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($inputPath);
        if ($raw === false) {
            echo "读取文件失败: {$inputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON 解析失败: " . json_last_error_msg() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (!is_array($data)) {
            echo "JSON 内容不是数组\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $changes = 0;
        foreach ($data as $index => &$row) {
            if (!is_array($row)) {
                continue;
            }
            $word = (string)($row['word'] ?? '');
            $segments = $row['segments'] ?? null;
            if ($word === '' || !is_array($segments) || empty($segments)) {
                $row['segments_with_space'] = [];
                continue;
            }

            $segments = array_map(static function ($segment) {
                return (string)$segment;
            }, $segments);
            $joined = implode('', $segments);

            $needsUpdate = false;
            if ($joined !== '' && $joined !== $word && $this->equalsIgnoreCase($joined, $word)) {
                $fixedSegments = $this->rebuildSegmentsForWord($segments, $word);
                if ($fixedSegments !== null) {
                    $segments = $fixedSegments;
                    $needsUpdate = true;
                }
            }

            $row['segments_with_space'] = $this->buildSegmentsWithSpaces($segments, $word);

            if (!$needsUpdate) {
                continue;
            }

            $row['segments'] = $segments;
            $row['joined_question'] = implode('', $segments);
            $row['normalized_question'] = $this->normalizeWordWithoutSpaces($row['joined_question']);
            $row['normalized_word'] = $this->normalizeWordWithoutSpaces($word);
            $row['quiz_question_raw'] = json_encode($segments, JSON_UNESCAPED_UNICODE);
            $changes++;
        }
        unset($row);

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            echo "JSON 编码失败，无法写入\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (file_put_contents($outputPath, $encoded) === false) {
            echo "写入文件失败: {$outputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo sprintf("大小写修复完成，共处理 %d 条记录\n", $changes);
        return ExitCode::OK;
    }

    /**
     * 在 JSON 文件中删除/补齐字母并自动应用正确大小写
     */
    public function actionFixQuizType2SegmentsInJson(
        string $inputFile = '@console/runtime/tmp/quiz_type2_mismatch.json',
        string $outputFile = ''
    ): int {
        $inputPath = Yii::getAlias($inputFile);
        $outputPath = $outputFile === '' ? $inputPath : Yii::getAlias($outputFile);

        if (!is_file($inputPath)) {
            echo "未找到文件: {$inputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($inputPath);
        if ($raw === false) {
            echo "读取文件失败: {$inputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $data = json_decode($raw, true);
        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            echo "JSON 解析失败: " . json_last_error_msg() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
        if (!is_array($data)) {
            echo "JSON 内容不是数组\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $changes = 0;
        foreach ($data as $index => &$row) {
            if (!is_array($row)) {
                continue;
            }
            $word = (string)($row['word'] ?? '');
            $segments = $row['segments'] ?? null;
            if ($word === '' || !is_array($segments) || empty($segments)) {
                $row['segments_with_space'] = [];
                continue;
            }

            $segments = array_map(static function ($segment) {
                return (string)$segment;
            }, $segments);
            $rebuilt = $this->rebuildSegmentsForWord($segments, $word);
            $finalSegments = $rebuilt ?? $segments;

            $row['segments_with_space'] = $this->buildSegmentsWithSpaces($finalSegments, $word);

            if ($rebuilt === null || $rebuilt === $segments) {
                continue;
            }

            $row['segments'] = $finalSegments;
            $row['joined_question'] = implode('', $finalSegments);
            $row['normalized_question'] = $this->normalizeWordWithoutSpaces($row['joined_question']);
            $row['normalized_word'] = $this->normalizeWordWithoutSpaces($word);
            $row['quiz_question_raw'] = json_encode($finalSegments, JSON_UNESCAPED_UNICODE);
            if ($row['parse_error'] ?? null) {
                $row['parse_error'] = null;
            }
            $changes++;
        }
        unset($row);

        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            echo "JSON 编码失败，无法写入\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (file_put_contents($outputPath, $encoded) === false) {
            echo "写入文件失败: {$outputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo sprintf("segments 修复完成，共处理 %d 条记录\n", $changes);
        return ExitCode::OK;
    }

    private function rebuildSegmentsForWord(array $segments, string $word): ?array
    {
        $wordChars = $this->buildWordCharList($word);
        $targetCount = count($wordChars);
        if ($targetCount === 0) {
            return null;
        }

        $segmentLengths = $this->buildSegmentLengthList($segments);
        if (empty($segmentLengths)) {
            $segmentLengths = [$targetCount];
        }

        $sourceChars = $this->flattenSegmentsToNormalizedChars($segments);
        $targetCharsNormalized = array_column($wordChars, 'normalized');
        $sourceCount = count($sourceChars);
        $targetCountNormalized = count($targetCharsNormalized);

        if ($sourceCount === 0) {
            $segmentLengths = [$targetCountNormalized];
        } else {
            [$prefix, $suffix] = $this->calculateCommonPrefixSuffix($sourceChars, $targetCharsNormalized);
            $extraLen = max(0, $sourceCount - $prefix - $suffix);
            $missingLen = max(0, $targetCountNormalized - $prefix - $suffix);

            if ($extraLen > 0) {
                $segmentLengths = $this->removeFromSegmentLengths($segmentLengths, $prefix, $extraLen);
            }

            if ($missingLen > 0) {
                $segmentLengths = $this->insertIntoSegmentLengths($segmentLengths, $prefix, $missingLen);
            }
        }

        if (array_sum($segmentLengths) !== $targetCountNormalized) {
            return null;
        }

        return $this->buildSegmentsFromLengths($segmentLengths, $wordChars);
    }

    /**
     * @param string[] $segments
     * @return string[]
     */
    private function buildSegmentsWithSpaces(array $segments, string $word): array
    {
        if (empty($segments) || trim($word) === '') {
            return $segments;
        }

        $lengths = $this->buildSegmentLengthList($segments);
        if (empty($lengths)) {
            return $segments;
        }

        $result = array_fill(0, count($segments), '');
        $segmentIndex = 0;
        $consumed = 0;
        $lastSegmentWithChar = null;

        foreach ($this->splitStringToChars($word) as $char) {
            if ($char === '') {
                continue;
            }

            if (preg_match('/\s/u', $char)) {
                if ($lastSegmentWithChar !== null) {
                    $result[$lastSegmentWithChar] .= $char;
                }
                continue;
            }

            while (
                $segmentIndex < count($lengths)
                && $consumed >= ($lengths[$segmentIndex] ?? 0)
            ) {
                $segmentIndex++;
                $consumed = 0;
            }

            if ($segmentIndex >= count($lengths)) {
                return $segments;
            }

            $result[$segmentIndex] .= $char;
            $consumed++;
            $lastSegmentWithChar = $segmentIndex;

            if ($consumed >= $lengths[$segmentIndex]) {
                $segmentIndex++;
                $consumed = 0;
            }
        }

        foreach ($result as $index => $value) {
            if ($value === '') {
                $result[$index] = $segments[$index];
            }
        }

        return $result;
    }

    /**
     * @param array<int,array{original:string,translated:string}> $pairs
     * @param array<string,?string> $cache
     * @return array<int,array{original:string,translated:string}>
     */
    private function translateUsagePairs(array $pairs, Client $client, array &$cache, int &$translatedCount): array
    {
        foreach ($pairs as &$pair) {
            $original = isset($pair['original']) ? trim((string)$pair['original']) : '';
            if ($original === '') {
                continue;
            }

            $existingTranslation = $this->normalizeTranslationStringValue($pair['translated'] ?? null);
            if ($existingTranslation !== '') {
                $pair['translated'] = $existingTranslation;
                continue;
            }

            $translation = $this->translateSingleUsage($original, $client, $cache);
            if ($translation !== null && $translation !== '') {
                $pair['translated'] = $translation;
                $translatedCount++;
            } else {
                $pair['translated'] = '';
            }
        }
        unset($pair);

        return $pairs;
    }

    /**
     * @param array<string,?string> $cache
     */
    private function translateSingleUsage(string $text, Client $client, array &$cache): ?string
    {
        $key = $this->normalizeUsageTranslationCacheKey($text);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $translation = $this->requestUsageTranslation($text, $client);
        $cache[$key] = $translation;

        return $translation;
    }

    private function normalizeUsageTranslationCacheKey(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        $normalized = preg_replace('/\s+/u', ' ', $trimmed);
        if ($normalized === null) {
            $normalized = preg_replace('/\s+/', ' ', $trimmed);
        }

        $normalized = $normalized ?? $trimmed;

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        return trim($normalized);
    }

    private function requestUsageTranslation(string $text, Client $client): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        try {
            $response = $client->post(self::UNIT_TRANSLATION_ENDPOINT, [
                'json' => [
                    'input' => [
                        'text' => $trimmed,
                    ],
                ],
                'timeout' => 10,
            ]);
        } catch (GuzzleException $e) {
            echo "翻译 usage 失败: {$e->getMessage()}\n";
            return null;
        }

        $body = (string)$response->getBody();
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            echo "翻译接口返回无法解析的数据\n";
            return null;
        }

        $translation = $payload['output']['translation'] ?? null;
        if (!is_string($translation)) {
            echo "翻译接口响应缺少 translation 字段\n";
            return null;
        }

        $result = trim($translation);
        return $result === '' ? null : $result;
    }

    /**
     * @return array<int,array{original:string,normalized:string}>
     */
    private function buildWordCharList(string $word): array
    {
        $chars = $this->splitStringToChars($word);
        $result = [];
        foreach ($chars as $char) {
            if ($char === '' || preg_match('/\s/u', $char)) {
                continue;
            }

            $result[] = [
                'original' => $char,
                'normalized' => $this->toLower($char),
            ];
        }

        return $result;
    }

    /**
     * @return int[]
     */
    private function buildSegmentLengthList(array $segments): array
    {
        $lengths = [];
        foreach ($segments as $segment) {
            $count = $this->countVisibleChars($segment);
            if ($count > 0) {
                $lengths[] = $count;
            }
        }

        return $lengths;
    }

    /**
     * @return string[]
     */
    private function flattenSegmentsToNormalizedChars(array $segments): array
    {
        $chars = [];
        foreach ($segments as $segment) {
            $letters = $this->splitStringToChars($segment);
            foreach ($letters as $letter) {
                if ($letter === '' || preg_match('/\s/u', $letter)) {
                    continue;
                }
                $chars[] = $this->toLower($letter);
            }
        }

        return $chars;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function calculateCommonPrefixSuffix(array $sourceChars, array $targetChars): array
    {
        $sourceCount = count($sourceChars);
        $targetCount = count($targetChars);

        $prefix = 0;
        while ($prefix < $sourceCount && $prefix < $targetCount && $sourceChars[$prefix] === $targetChars[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while (
            $suffix < ($sourceCount - $prefix)
            && $suffix < ($targetCount - $prefix)
            && $sourceChars[$sourceCount - 1 - $suffix] === $targetChars[$targetCount - 1 - $suffix]
        ) {
            $suffix++;
        }

        return [$prefix, $suffix];
    }

    /**
     * @param int[] $lengths
     * @return int[]
     */
    private function removeFromSegmentLengths(array $lengths, int $start, int $length): array
    {
        if ($length <= 0) {
            return array_values(array_filter($lengths, static function ($value) {
                return $value > 0;
            }));
        }

        $result = [];
        $remainingStart = $start;
        $remaining = $length;
        $count = count($lengths);

        for ($i = 0; $i < $count; $i++) {
            $current = $lengths[$i];
            if ($current <= 0) {
                continue;
            }

            if ($remainingStart >= $current) {
                $result[] = $current;
                $remainingStart -= $current;
                continue;
            }

            if ($remainingStart > 0) {
                $result[] = $remainingStart;
                $current -= $remainingStart;
            }

            $removeNow = min($current, $remaining);
            $current -= $removeNow;
            $remaining -= $removeNow;

            if ($remaining <= 0) {
                if ($current > 0) {
                    $result[] = $current;
                }
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($lengths[$j] > 0) {
                        $result[] = $lengths[$j];
                    }
                }
                return array_values(array_filter($result, static function ($value) {
                    return $value > 0;
                }));
            }

            $remainingStart = 0;
        }

        return array_values(array_filter($result, static function ($value) {
            return $value > 0;
        }));
    }

    /**
     * @param int[] $lengths
     * @return int[]
     */
    private function insertIntoSegmentLengths(array $lengths, int $position, int $insertLength): array
    {
        if ($insertLength <= 0) {
            return array_values(array_filter($lengths, static function ($value) {
                return $value > 0;
            }));
        }

        $total = array_sum($lengths);
        if ($total <= 0) {
            return [$insertLength];
        }

        if ($position <= 0) {
            array_unshift($lengths, $insertLength);
            return array_values(array_filter($lengths, static function ($value) {
                return $value > 0;
            }));
        }

        if ($position >= $total) {
            $lengths[] = $insertLength;
            return array_values(array_filter($lengths, static function ($value) {
                return $value > 0;
            }));
        }

        $result = [];
        $remainingPos = $position;
        $count = count($lengths);

        for ($i = 0; $i < $count; $i++) {
            $current = $lengths[$i];
            if ($current <= 0) {
                continue;
            }

            if ($remainingPos > $current) {
                $result[] = $current;
                $remainingPos -= $current;
                continue;
            }

            if ($remainingPos === 0) {
                $result[] = $insertLength;
                $result[] = $current;
            } elseif ($remainingPos === $current) {
                $result[] = $current;
                $result[] = $insertLength;
            } else {
                $result[] = $remainingPos;
                $result[] = $insertLength;
                $result[] = $current - $remainingPos;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                if ($lengths[$j] > 0) {
                    $result[] = $lengths[$j];
                }
            }

            return array_values(array_filter($result, static function ($value) {
                return $value > 0;
            }));
        }

        $result[] = $insertLength;
        return array_values(array_filter($result, static function ($value) {
            return $value > 0;
        }));
    }

    /**
     * @param int[] $lengths
     * @param array<int,array{original:string,normalized:string}> $wordChars
     * @return array<int,string>|null
     */
    private function buildSegmentsFromLengths(array $lengths, array $wordChars): ?array
    {
        $segments = [];
        $cursor = 0;
        $targetCount = count($wordChars);

        foreach ($lengths as $length) {
            if ($length <= 0) {
                continue;
            }
            $slice = array_slice($wordChars, $cursor, $length);
            if (count($slice) !== $length) {
                return null;
            }
            $segments[] = implode('', array_column($slice, 'original'));
            $cursor += $length;
        }

        if ($cursor !== $targetCount) {
            return null;
        }

        return $segments;
    }

    private function countVisibleChars(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        $chars = $this->splitStringToChars($text);
        $count = 0;
        foreach ($chars as $char) {
            if ($char === '' || preg_match('/\s/u', $char)) {
                continue;
            }
            $count++;
        }

        return $count;
    }

    /**
     * @return string[]
     */
    private function splitStringToChars(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return str_split($text);
        }

        return $chars;
    }

    private function toLower(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

    private function equalsIgnoreCase(string $a, string $b): bool
    {
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($a, 'UTF-8') === mb_strtolower($b, 'UTF-8');
        }

        return strtolower($a) === strtolower($b);
    }

    /**
     * 请求 Unipus 单词接口
     *
     * @param string $name
     */
    public function actionFetchUnipusKeyword(string $name = 'technology'): void
    {
        $client = new Client([
            'timeout' => 10,
        ]);

        $dictIdList = [1, 338, 339, 336, 337, 351,  352, 353, 354];
        $bookIdList = [278, 284, 285, 287, 288, 279, 281, 283, 286, 290, 293, 289, 292, 291, 282, 280];
        $found = false;

        foreach ($dictIdList as $dictId) {
            foreach ($bookIdList as $bookId) {
                try {
                    $url = self::UNIPUS_ENDPOINT . '/' . self::UNIPUS_LAN_ID . "/{$dictId}";
                    $headers = [
                        'Content-Type' => 'application/json',
                        'p-app-id' => self::UNIPUS_APP_ID,
                        'p-cip-txt' => $this->generatePCipTxt(self::UNIPUS_SECRET),
                    ];
                    $jsonData = [
                        'name' => $name,
                        'bookId' => $bookId,
                    ];

                    // 打印请求参数
                    echo "================== 请求参数 ==================\n";
                    echo "URL: {$url}\n";
                    echo "Method: POST\n\n";

                    echo "Headers:\n";
                    foreach ($headers as $key => $value) {
                        echo "  {$key}: {$value}\n";
                    }
                    echo "\n";

                    echo "Body (JSON):\n";
                    echo json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
                    echo "\n";

                    // 生成 curl 请求
                    echo "================== CURL 请求 ==================\n";
                    $curlCmd = $this->generateCurlCommand($url, $headers, $jsonData);
                    echo $curlCmd . "\n";
                    echo "================================================\n\n";

                    $response = $client->post($url, [
                        'headers' => $headers,
                        'json' => $jsonData,
                    ]);
                } catch (GuzzleException $e) {
                    echo "Unipus request failed ({$dictId}, {$bookId}): " . $e->getMessage() . PHP_EOL;
                    continue;
                } catch (\RuntimeException $e) {
                    echo 'Header generation failed: ' . $e->getMessage() . PHP_EOL;
                    return;
                }

                $body = (string)$response->getBody();
                $payload = json_decode($body, true);
                var_dump($payload);
                die;

                if (!is_array($payload)) {
                    echo "Invalid response for dictId {$dictId}, bookId {$bookId}" . PHP_EOL;
                    continue;
                }

                if (
                    isset($payload['code'], $payload['value']['dictExist'])
                    && (int)$payload['code'] === 0
                    && $payload['value']['dictExist'] === true
                ) {
                    echo json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    echo "命中 dictId={$dictId}, bookId={$bookId}" . PHP_EOL;
                    $found = true;
                    break 2;
                }
            }
        }

        if (!$found) {
            echo '未找到 dictExist=true 的组合' . PHP_EOL;
        }
    }

    /**
     * 生成 curl 请求命令
     */
    private function generateCurlCommand(string $url, array $headers, array $jsonData): string
    {
        $curl = "curl -X POST \\\n";
        $curl .= "  '{$url}' \\\n";

        foreach ($headers as $key => $value) {
            $curl .= "  -H '{$key}: {$value}' \\\n";
        }

        $curl .= "  -d '" . json_encode($jsonData) . "'";

        return $curl;
    }

    /**
     * 生成 p-cip-txt header
     */
    private function generatePCipTxt(string $secret): string
    {
        $iv = pack('C*', 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16);
        $timestamp = (string)time();

        $encrypted = openssl_encrypt(
            $timestamp,
            'AES-256-CBC',
            $secret,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Unable to generate p-cip-txt header value.');
        }

        return base64_encode($encrypted);
    }

    public function actionDealWithWordList()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/word_index_map.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            //查询单词是否存在
            $model = Vocabulary::find()->where(['name' => $key])->one();
            if (!$model) {
                $model = new Vocabulary();
                $model->name = $key;
                $model->weight = $value;
                $model->save();
                echo "添加单词：" . $key . "\n";
            }
        }
        echo "处理完成！\n";
    }

    /**
     * 同步词书数据
     * php yii words/sync-word-data
     * Summary of actionSyncWordData
     * @param string $filePath
     * @return void
     */
    public function actionSyncWordData(string $filePath = ''): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $basePath = dirname(__FILE__, 2);
        $path = $filePath !== '' ? $filePath : $basePath . self::WORD_DATA_FILE;

        if (!is_file($path)) {
            echo "文件不存在: {$path}\n";
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            echo "读取文件失败: {$path}\n";
            return;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['books']) || !is_array($data['books'])) {
            echo "JSON解析失败，缺少books字段\n";
            return;
        }

        $processed = 0;
        $summaries = [];

        foreach ($data['books'] as $bookData) {
            if (!is_array($bookData)) {
                continue;
            }

            $jsonBookId = $bookData['id'] ?? null;
            if (!$jsonBookId || !in_array($jsonBookId, self::TARGET_BOOK_IDS, true)) {
                continue;
            }

            try {
                $result = $this->syncSingleBook($bookData);
                $summaries[] = sprintf(
                    "词书(JSON ID %d)《%s》: 新增单词 %d, 新增关联 %d, 更新关联 %d, 删除关联 %d",
                    $jsonBookId,
                    $bookData['name'] ?? '',
                    $result['createdWords'],
                    $result['createdRelations'],
                    $result['updatedRelations'],
                    $result['removedRelations']
                );
                $processed++;
            } catch (\Throwable $e) {
                echo "处理词书 {$jsonBookId} 失败: " . $e->getMessage() . PHP_EOL;
            }
        }

        if ($processed === 0) {
            echo "未找到需要处理的词书\n";
            return;
        }

        foreach ($summaries as $summaryLine) {
            echo $summaryLine . PHP_EOL;
        }

        echo "处理完成，共处理 {$processed} 本词书\n";
    }

    /**
     * 从 Langeek 词书 JSON 文件中导入指定词书下的单元与单词
     * 默认文件路径：@console/runtime/tmp/langeek_output_new1281_integrated.json
     * 命令示例：php yii words/import-langeek-units 200 "@console/runtime/tmp/langeek_output_new1281_integrated.json"
     */
    public function actionImportLangeekUnits(int $bookId = 200, string $filePath = ''): int
    {
        $targetFile = $filePath === '' ? self::DEFAULT_LANGEEK_UNIT_FILE : $filePath;
        $resolvedPath = $this->resolveFilePath($targetFile);
        if (!is_file($resolvedPath)) {
            echo "文件不存在: {$resolvedPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($resolvedPath);
        if ($raw === false) {
            echo "读取文件失败: {$resolvedPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            echo "JSON 数据格式不正确，期望为数组。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $book = VocabularyBook::find()->where(['id' => $bookId])->one();
        if (!$book) {
            echo "未找到 ID 为 {$bookId} 的词书。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $existingUnits = VocabularyBookUnit::find()
            ->where(['book_id' => $book->id])
            ->indexBy('name')
            ->all();

        $unitSort = 0;
        $processedUnits = 0;
        $summary = [
            'createdUnits' => 0,
            'updatedUnits' => 0,
            'createdWords' => 0,
            'createdRelations' => 0,
            'updatedRelations' => 0,
            'removedRelations' => 0,
            'skippedUnits' => 0,
            'failedUnits' => 0,
        ];
        $failures = [];

        foreach ($decoded as $index => $unitData) {
            if (!is_array($unitData)) {
                $summary['skippedUnits']++;
                continue;
            }

            $unitName = trim((string)($unitData['name'] ?? ''));
            if ($unitName === '') {
                $summary['skippedUnits']++;
                continue;
            }

            $unitSort++;
            $processedUnits++;

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $timestamp = time();
                if (isset($existingUnits[$unitName])) {
                    $unit = $existingUnits[$unitName];
                    $isNewUnit = false;
                } else {
                    $unit = new VocabularyBookUnit();
                    $unit->book_id = $book->id;
                    $unit->name = $unitName;
                    $unit->create_by = 0;
                    $unit->create_time = $timestamp;
                    $unit->status = 1;
                    $isNewUnit = true;
                }

                $description = $this->truncateString($unitData['description'] ?? null, 500);
                $unit->desc = $description ?? '';
                if ($unit->hasAttribute('word_count')) {
                    $unit->word_count = (int)($unitData['word_count'] ?? 0);
                }
                $unit->sort_order = $unitSort;
                $unit->update_by = 0;
                $unit->update_time = $timestamp;
                if ($unit->status === null) {
                    $unit->status = 1;
                }

                if (!$unit->save()) {
                    throw new \RuntimeException('保存词书单元失败: ' . json_encode($unit->errors, JSON_UNESCAPED_UNICODE));
                }
                $existingUnits[$unitName] = $unit;

                if ($isNewUnit) {
                    $summary['createdUnits']++;
                    echo "创建词书单元: {$book->name} -> {$unitName} (#{$unit->id})\n";
                } else {
                    $summary['updatedUnits']++;
                    echo "更新词书单元: {$book->name} -> {$unitName} (#{$unit->id})\n";
                }

                $words = $unitData['words'] ?? [];
                if (!is_array($words)) {
                    $words = [];
                }

                [$createdWords, $createdRelations, $updatedRelations, $removedRelations] = $this->syncUnitWords(
                    $book,
                    $unit,
                    $words
                );

                $summary['createdWords'] += $createdWords;
                $summary['createdRelations'] += $createdRelations;
                $summary['updatedRelations'] += $updatedRelations;
                $summary['removedRelations'] += $removedRelations;

                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                $summary['failedUnits']++;
                $failures[] = "{$unitName} (index {$index}) 导入失败: {$e->getMessage()}";
                echo "导入单元失败 {$unitName}: {$e->getMessage()}\n";
            }
        }

        echo "\n====== 导入完成 ======\n";
        echo "处理单元: {$processedUnits}，新增 {$summary['createdUnits']}，更新 {$summary['updatedUnits']}，失败 {$summary['failedUnits']}，跳过 {$summary['skippedUnits']}\n";
        echo "单词：新增 {$summary['createdWords']}，新增关联 {$summary['createdRelations']}，更新关联 {$summary['updatedRelations']}，删除关联 {$summary['removedRelations']}\n";

        if (!empty($failures)) {
            echo "失败详情：\n";
            foreach ($failures as $failure) {
                echo "- {$failure}\n";
            }
        }

        return $summary['failedUnits'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 读取 word_data.json，更新翻译缺失的词汇信息
     * 只处理绑定词书ID >= 195 且 translation 为空的单词
     * php yii words/update-word-data-from-json [filePath]
     */
    public function actionUpdateWordDataFromJson(string $filePath = ''): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $basePath = dirname(__FILE__, 2);
        $path = $filePath !== '' ? $filePath : $basePath . self::WORD_DATA_FILE;

        if (!is_file($path)) {
            echo "文件不存在: {$path}\n";
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            echo "读取文件失败: {$path}\n";
            return;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['books']) || !is_array($payload['books'])) {
            echo "JSON解析失败，缺少books字段\n";
            return;
        }

        $wordLookup = $this->buildWordDataLookup($payload['books']);
        if (empty($wordLookup)) {
            echo "word_data.json 中未找到单词数据\n";
            return;
        }

        $targetVocabularyIds = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['>=', 'book_id', 195])
            ->column();

        if (empty($targetVocabularyIds)) {
            echo "未找到词书ID大于等于195的单词关联记录\n";
            return;
        }

        $query = Vocabulary::find()
            ->where(['translation' => ''])
            ->andWhere(['id' => $targetVocabularyIds])
            ->orderBy(['id' => SORT_ASC]);

        $total = (clone $query)->count();
        if ($total == 0) {
            echo "没有符合条件需要更新的单词\n";
            return;
        }

        echo "待处理单词数量: {$total}\n";

        $batchSize = 100;
        $processed = 0;
        $updatedVocabulary = 0;
        $createdExt = 0;
        $updatedExt = 0;
        $missingWordData = [];
        $failed = [];

        foreach ($query->batch($batchSize) as $batch) {
            /** @var Vocabulary[] $batch */
            foreach ($batch as $vocabulary) {
                $processed++;
                $wordName = $vocabulary->name;
                $wordData = $wordLookup[$wordName] ?? null;

                if (!$wordData) {
                    $missingWordData[] = $wordName;
                    continue;
                }

                $transaction = Yii::$app->db->beginTransaction();
                try {
                    $now = time();
                    $translation = $this->truncateString($wordData['translation'] ?? null, 100) ?? '';
                    $definition = $this->truncateString($wordData['definition'] ?? null, 300) ?? '';
                    $ukIpa = $this->truncateString($wordData['uk_pronunciation'] ?? null, 100) ?? '';
                    $usIpa = $this->truncateString($wordData['us_pronunciation'] ?? null, 100) ?? '';

                    $cardPayload = json_encode($wordData, JSON_UNESCAPED_UNICODE);
                    if ($cardPayload === false) {
                        throw new \RuntimeException('无法序列化单词数据');
                    }
                    $cardInfoPath = $this->uploadData($cardPayload);
                    if (empty($cardInfoPath)) {
                        throw new \RuntimeException('上传单词卡片数据失败');
                    }

                    $vocabulary->translation = $translation;
                    $vocabulary->definition = $definition;
                    $vocabulary->card_info = $cardInfoPath;
                    $vocabulary->uk_ipa = $ukIpa;
                    $vocabulary->us_ipa = $usIpa;
                    $vocabulary->update_by = 0;
                    $vocabulary->update_time = $now;

                    if (!$vocabulary->save()) {
                        throw new \RuntimeException('保存 vocabulary 失败: ' . json_encode($vocabulary->errors, JSON_UNESCAPED_UNICODE));
                    }
                    $updatedVocabulary++;

                    $vocabularyExt = VocabularyExt::find()->where(['vocabulary_id' => $vocabulary->id])->one();
                    $isNewExt = false;
                    if (!$vocabularyExt) {
                        $vocabularyExt = new VocabularyExt();
                        $vocabularyExt->vocabulary_id = $vocabulary->id;
                        $vocabularyExt->create_by = 0;
                        $vocabularyExt->create_time = $now;
                        $isNewExt = true;
                    }

                    $vocabularyExt->update_by = 0;
                    $vocabularyExt->update_time = $now;
                    if (array_key_exists('examples', $wordData)) {
                        $vocabularyExt->example_sentences = $wordData['examples'];
                    }

                    if (!$vocabularyExt->save()) {
                        throw new \RuntimeException('保存 vocabulary_ext 失败: ' . json_encode($vocabularyExt->errors, JSON_UNESCAPED_UNICODE));
                    }

                    if ($isNewExt) {
                        $createdExt++;
                    } else {
                        $updatedExt++;
                    }

                    $transaction->commit();
                    echo "更新单词: {$wordName} (#{$vocabulary->id})\n";
                } catch (\Throwable $e) {
                    $transaction->rollBack();
                    $failed[] = "{$wordName}: {$e->getMessage()}";
                    echo "处理失败 {$wordName}: {$e->getMessage()}\n";
                }
            }
        }

        echo "处理完成，成功更新 {$updatedVocabulary} 条词汇记录\n";
        echo "新建扩展 {$createdExt} 条，更新扩展 {$updatedExt} 条\n";

        if (!empty($missingWordData)) {
            $missingCount = count($missingWordData);
            echo "word_data.json 中未找到 {$missingCount} 个单词：" . implode(', ', array_slice($missingWordData, 0, 20)) . "\n";
            if ($missingCount > 20) {
                echo "... 其余 " . ($missingCount - 20) . " 个已省略\n";
            }
        }

        if (!empty($failed)) {
            echo "以下单词更新失败:\n";
            foreach ($failed as $line) {
                echo " - {$line}\n";
            }
        }
    }

    /**
     * 读取 pronunciation_data.json，若包含 uk_audio_path/us_audio_path，则同步到 vocabulary.syllable_ipa_uk/us
     * 同步时把路径中的 output 替换为 /vocabulary/pronunciation
     * php yii words/update-pronunciation-audio-paths [filePath]
     */
    public function actionUpdatePronunciationAudioPaths(string $filePath = '@console/runtime/tmp/pronunciation_data.json'): int
    {
        ini_set('memory_limit', '512M');
        set_time_limit(0);

        $path = $filePath !== '' ? Yii::getAlias($filePath) : Yii::getAlias('@console/runtime/tmp/pronunciation_data.json');
        if (!is_file($path)) {
            echo "文件不存在: {$path}\n";
            return ExitCode::NOINPUT;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            echo "读取文件失败: {$path}\n";
            return ExitCode::IOERR;
        }

        try {
            $rows = Json::decode($raw, true);
        } catch (\Throwable $e) {
            echo "JSON解析失败: {$e->getMessage()}\n";
            return ExitCode::DATAERR;
        }

        if (!is_array($rows)) {
            echo "JSON格式不正确，期望数组\n";
            return ExitCode::DATAERR;
        }

        $rewritePath = static function (string $audioPath): string {
            $audioPath = trim($audioPath);
            if ($audioPath === '') {
                return '';
            }

            if (strpos($audioPath, 'output') === 0) {
                return '/vocabulary/pronunciation' . substr($audioPath, strlen('output'));
            }

            return str_replace('output', '/vocabulary/pronunciation', $audioPath);
        };

        $total = count($rows);
        echo "待处理条目数: {$total}\n";

        $updated = 0;
        $missingVocabulary = [];
        $skippedNoAudioPath = 0;
        $skippedNotFoundWord = 0;
        $unchanged = 0;
        $failed = [];

        $cache = [];
        $now = time();

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $wordName = trim((string)($row['word'] ?? ''));
            $wordUsed = trim((string)($row['word_used'] ?? ''));
            $pronunciation = $row['pronunciation'] ?? null;

            if ($wordName === '' || !is_array($pronunciation)) {
                continue;
            }

            $ukAudioPath = trim((string)($pronunciation['uk_audio_path'] ?? ''));
            $usAudioPath = trim((string)($pronunciation['us_audio_path'] ?? ''));

            if ($ukAudioPath === '' && $usAudioPath === '') {
                $skippedNoAudioPath++;
                continue;
            }

            $lookupKey = strtolower($wordUsed !== '' ? $wordUsed : $wordName);
            if (array_key_exists($lookupKey, $cache)) {
                $vocabulary = $cache[$lookupKey];
            } else {
                $vocabulary = Vocabulary::find()->where(['name' => $wordName])->one();
                if (!$vocabulary && $wordUsed !== '') {
                    $vocabulary = Vocabulary::find()
                        ->where('LOWER([[name]]) = :name', [':name' => strtolower($wordUsed)])
                        ->one();
                }
                $cache[$lookupKey] = $vocabulary;
            }

            if (!$vocabulary) {
                $missingVocabulary[] = $wordName;
                $skippedNotFoundWord++;
                continue;
            }

            $updates = [];
            if ($ukAudioPath !== '') {
                $updates['syllable_ipa_uk'] = $rewritePath($ukAudioPath);
            }
            if ($usAudioPath !== '') {
                $updates['syllable_ipa_us'] = $rewritePath($usAudioPath);
            }

            $dirty = [];
            foreach ($updates as $attribute => $value) {
                if ((string)$vocabulary->getAttribute($attribute) !== (string)$value) {
                    $dirty[$attribute] = $value;
                }
            }

            if (empty($dirty)) {
                $unchanged++;
                continue;
            }

            if ($vocabulary->hasAttribute('update_time')) {
                $dirty['update_time'] = $now;
            }
            if ($vocabulary->hasAttribute('update_by')) {
                $dirty['update_by'] = 0;
            }

            try {
                $vocabulary->updateAttributes($dirty);
                $updated++;
                echo "更新 {$wordName} (#{$vocabulary->id})\n";
            } catch (\Throwable $e) {
                $failed[] = "{$wordName}: {$e->getMessage()}";
                echo "更新失败 {$wordName}: {$e->getMessage()}\n";
            }
        }

        echo "处理完成：更新 {$updated}，未变化 {$unchanged}，无音频路径 {$skippedNoAudioPath}，未找到单词 {$skippedNotFoundWord}\n";

        if (!empty($missingVocabulary)) {
            $missingCount = count($missingVocabulary);
            echo "数据库未找到 {$missingCount} 个单词：" . implode(', ', array_slice($missingVocabulary, 0, 20)) . "\n";
            if ($missingCount > 20) {
                echo "... 其余 " . ($missingCount - 20) . " 个已省略\n";
            }
        }

        if (!empty($failed)) {
            echo "失败详情：\n";
            foreach ($failed as $line) {
                echo " - {$line}\n";
            }
        }

        return !empty($failed) ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 根据单词列表为缺失的题目创建 quiz_type=1/2 的题目
     * php yii words/init-word-list-quizzes [wordListFile] [chunkDir] [sentenceDir]
     */
    public function actionInitWordListQuizzes(string $wordListFile = '', string $chunkDir = '', string $sentenceDir = ''): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $basePath = dirname(__FILE__, 2);
        $listPath = $wordListFile !== '' ? $wordListFile : $basePath . '/runtime/tmp/a_words.json';
        $chunkDirectory = $chunkDir !== '' ? $chunkDir : $basePath . '/runtime/tmp/单词切割';
        $sentenceDirectory = $sentenceDir !== '' ? $sentenceDir : $basePath . '/runtime/tmp/单词题目';

        if (!is_file($listPath)) {
            echo "单词列表不存在: {$listPath}\n";
            return;
        }

        $rawWords = file_get_contents($listPath);
        if ($rawWords === false) {
            echo "无法读取单词列表: {$listPath}\n";
            return;
        }

        $wordList = json_decode($rawWords, true);
        if (!is_array($wordList)) {
            echo "单词列表 JSON 解析失败\n";
            return;
        }

        $chunkLookup = $this->buildJsonFileLookup($chunkDirectory);
        $sentenceLookup = $this->buildJsonFileLookup($sentenceDirectory);

        $totalWords = 0;
        $chunkCreated = 0;
        $chunkExisting = 0;
        $chunkMissingFiles = [];
        $chunkFailures = [];

        $sentenceCreated = 0;
        $sentenceExisting = 0;
        $sentenceMissingFiles = [];
        $sentenceFailures = [];

        $missingVocabulary = [];

        foreach ($wordList as $wordNameRaw) {
            $wordName = trim((string)$wordNameRaw);
            if ($wordName === '') {
                continue;
            }

            $totalWords++;

            $vocabulary = Vocabulary::find()->where(['name' => $wordName])->one();
            if (!$vocabulary) {
                $missingVocabulary[] = $wordName;
                continue;
            }

            // 拼写题 (quiz_type = 2)
            $chunkQuizExists = VocabularyQuiz::find()
                ->where(['vocabulary_id' => $vocabulary->id, 'quiz_type' => 2])
                ->exists();

            if ($chunkQuizExists) {
                $chunkExisting++;
            } else {
                $chunkPath = $this->resolveWordFilePath($chunkLookup, $wordName);
                if ($chunkPath === null) {
                    $chunkMissingFiles[] = $wordName;
                } else {
                    try {
                        $this->createChunkQuizFromFile($vocabulary, $chunkPath);
                        $chunkCreated++;
                        echo "创建拼写题: {$wordName} (#{$vocabulary->id})\n";
                    } catch (\Throwable $e) {
                        $chunkFailures[] = "{$wordName}: {$e->getMessage()}";
                    }
                }
            }

            // 看词示意题 (quiz_type = 1)
            $sentenceQuizExists = VocabularyQuiz::find()
                ->where(['vocabulary_id' => $vocabulary->id, 'quiz_type' => 1])
                ->exists();

            if ($sentenceQuizExists) {
                $sentenceExisting++;
            } else {
                $sentencePath = $this->resolveWordFilePath($sentenceLookup, $wordName);
                if ($sentencePath === null) {
                    $sentenceMissingFiles[] = $wordName;
                } else {
                    try {
                        $this->createSentenceQuizFromFile($vocabulary, $sentencePath);
                        $sentenceCreated++;
                        echo "创建看词示意题: {$wordName} (#{$vocabulary->id})\n";
                    } catch (\Throwable $e) {
                        $sentenceFailures[] = "{$wordName}: {$e->getMessage()}";
                    }
                }
            }
        }

        echo "\n====== 处理完成 ======\n";
        echo "总单词数: {$totalWords}\n";
        echo "拼写题：新增 {$chunkCreated}，已存在 {$chunkExisting}，缺文件 " . count($chunkMissingFiles) . "，失败 " . count($chunkFailures) . "\n";
        echo "看词示意题：新增 {$sentenceCreated}，已存在 {$sentenceExisting}，缺文件 " . count($sentenceMissingFiles) . "，失败 " . count($sentenceFailures) . "\n";

        if (!empty($missingVocabulary)) {
            $this->outputList('数据库中未找到的单词', $missingVocabulary);
        }
        if (!empty($chunkMissingFiles)) {
            $this->outputList('缺少拼写题文件的单词', $chunkMissingFiles);
        }
        if (!empty($chunkFailures)) {
            $this->outputList('拼写题创建失败的单词', $chunkFailures);
        }
        if (!empty($sentenceMissingFiles)) {
            $this->outputList('缺少看词示意题文件的单词', $sentenceMissingFiles);
        }
        if (!empty($sentenceFailures)) {
            $this->outputList('看词示意题创建失败的单词', $sentenceFailures);
        }
    }

    /**
     * @param array<string,mixed> $bookData
     * @return array{createdWords:int,createdRelations:int,updatedRelations:int,removedRelations:int}
     */
    private function syncSingleBook(array $bookData): array
    {
        $jsonBookId = $bookData['id'] ?? null;
        if (!$jsonBookId) {
            throw new \InvalidArgumentException('词书ID缺失');
        }

        $bookName = trim($bookData['name'] ?? '');
        if ($bookName === '') {
            throw new \InvalidArgumentException("词书 {$jsonBookId} 名称缺失");
        }

        $now = time();
        $summary = [
            'createdWords' => 0,
            'createdRelations' => 0,
            'updatedRelations' => 0,
            'removedRelations' => 0,
        ];

        $book = VocabularyBook::find()->where(['name' => $bookName])->one();
        $bookWasNew = false;
        if (!$book) {
            $book = new VocabularyBook();
            $book->create_time = $now;
            $book->create_by = 0;
            $book->status = 1;
            $book->weight = 0;
            $bookWasNew = true;
        }

        $book->name = $bookName;
        $book->description = $bookData['description'] ?? '';
        $book->total_words = $bookData['word_count'] ?? 0;
        $book->cover_image_url = $bookData['image_url'] ?? '';
        if ($book->status === null) {
            $book->status = 1;
        }
        if ($book->weight === null) {
            $book->weight = 0;
        }
        $book->update_time = $now;
        $book->update_by = 0;

        if (!$book->save()) {
            throw new \RuntimeException('保存词书失败: ' . json_encode($book->errors, JSON_UNESCAPED_UNICODE));
        }
        if ($bookWasNew) {
            echo "创建词书(JSON ID {$jsonBookId}): {$bookName} (#{$book->id})\n";
        }

        $existingUnits = VocabularyBookUnit::find()
            ->where(['book_id' => $book->id])
            ->indexBy('name')
            ->all();

        $unitSort = 0;
        if (!empty($bookData['courses']) && is_array($bookData['courses'])) {
            foreach ($bookData['courses'] as $course) {
                if (!is_array($course)) {
                    continue;
                }

                $unitName = trim($course['name'] ?? '');
                if ($unitName === '') {
                    continue;
                }

                $unitSort++;

                $unitWasNew = false;
                if (isset($existingUnits[$unitName])) {
                    $unit = $existingUnits[$unitName];
                } else {
                    $unit = new VocabularyBookUnit();
                    $unit->book_id = $book->id;
                    $unit->name = $unitName;
                    $unit->create_time = $now;
                    $unit->create_by = 0;
                    $unit->status = 1;
                    $unitWasNew = true;
                }

                $unit->desc = $course['description'] ?? '';
                $unit->sort_order = $unitSort;
                $unit->update_time = $now;
                $unit->update_by = 0;
                if ($unit->status === null) {
                    $unit->status = 1;
                }

                if (!$unit->save()) {
                    throw new \RuntimeException('保存单元失败: ' . json_encode($unit->errors, JSON_UNESCAPED_UNICODE));
                }
                if ($unitWasNew) {
                    echo "创建词书单元: {$bookName} -> {$unitName} (#{$unit->id})\n";
                }
                $existingUnits[$unitName] = $unit;

                $words = $course['words'] ?? [];
                if (!is_array($words)) {
                    $words = [];
                }

                [$createdWords, $createdRelations, $updatedRelations, $removedRelations] = $this->syncUnitWords(
                    $book,
                    $unit,
                    $words
                );

                $summary['createdWords'] += $createdWords;
                $summary['createdRelations'] += $createdRelations;
                $summary['updatedRelations'] += $updatedRelations;
                $summary['removedRelations'] += $removedRelations;
            }
        }

        return $summary;
    }

    /**
     * @param array<int, array<string, mixed>> $words
     * @return array{0:int,1:int,2:int,3:int}
     */
    private function syncUnitWords(VocabularyBook $book, VocabularyBookUnit $unit, array $words): array
    {
        $createdWords = 0;
        $createdRelations = 0;
        $updatedRelations = 0;
        $removedRelations = 0;

        if (empty($words)) {
            $staleRelations = VocabularyUnitRelation::find()
                ->where(['book_id' => $book->id, 'unit_id' => $unit->id])
                ->all();
            if (!empty($staleRelations)) {
                $ids = array_map(static function (VocabularyUnitRelation $relation): int {
                    return (int)$relation->id;
                }, $staleRelations);
                VocabularyUnitRelation::deleteAll(['id' => $ids]);
                $removedRelations += count($ids);
            }

            return [$createdWords, $createdRelations, $updatedRelations, $removedRelations];
        }

        $names = [];
        foreach ($words as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $name = trim($entry['word'] ?? '');
            if ($name === '') {
                continue;
            }
            $names[] = $name;
        }

        if (empty($names)) {
            return [$createdWords, $createdRelations, $updatedRelations, $removedRelations];
        }

        $this->primeVocabularyCache($names);

        $existingRelations = VocabularyUnitRelation::find()
            ->where(['book_id' => $book->id, 'unit_id' => $unit->id])
            ->indexBy('vocabulary_id')
            ->all();

        $now = time();
        $order = 0;

        foreach ($words as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $wordName = trim($entry['word'] ?? '');
            if ($wordName === '') {
                continue;
            }

            $order++;

            $vocabulary = $this->vocabularyCache[$wordName] ?? null;
            if (!$vocabulary) {
                $vocabulary = Vocabulary::find()->where(['name' => $wordName])->one();
                if ($vocabulary) {
                    $this->vocabularyCache[$wordName] = $vocabulary;
                }
            }
            $wasNewVocabulary = false;
            if (!$vocabulary) {
                $vocabulary = $this->createVocabulary($wordName, $entry, $now);
                $createdWords++;
                $wasNewVocabulary = true;
            }

            if (!$wasNewVocabulary) {
                $this->updateVocabularyFromEntry($vocabulary, $entry, $now);
            }

            if (isset($existingRelations[$vocabulary->id])) {
                $relation = $existingRelations[$vocabulary->id];
                unset($existingRelations[$vocabulary->id]);

                $needsUpdate = (int)$relation->order !== $order || (int)$relation->status !== 1;
                $relation->order = $order;
                $relation->status = 1;
                $relation->update_time = $now;
                $relation->update_by = 0;

                if (!$relation->save()) {
                    throw new \RuntimeException('更新词书关联失败: ' . json_encode($relation->errors, JSON_UNESCAPED_UNICODE));
                }

                if ($needsUpdate) {
                    $updatedRelations++;
                }
            } else {
                $relation = new VocabularyUnitRelation();
                $relation->vocabulary_id = $vocabulary->id;
                $relation->book_id = $book->id;
                $relation->unit_id = $unit->id;
                $relation->order = $order;
                $relation->status = 1;
                $relation->create_time = $now;
                $relation->create_by = 0;
                $relation->update_time = $now;
                $relation->update_by = 0;

                if (!$relation->save()) {
                    throw new \RuntimeException('创建词书关联失败: ' . json_encode($relation->errors, JSON_UNESCAPED_UNICODE));
                }

                $createdRelations++;
                echo "新增词书关联: {$book->name} / {$unit->name} -> {$vocabulary->name} (#{$relation->id})\n";
            }
        }

        if (!empty($existingRelations)) {
            $idsToRemove = array_map(static function (VocabularyUnitRelation $relation): int {
                return (int)$relation->id;
            }, $existingRelations);
            VocabularyUnitRelation::deleteAll(['id' => $idsToRemove]);
            $removedRelations += count($idsToRemove);
        }

        return [$createdWords, $createdRelations, $updatedRelations, $removedRelations];
    }

    /**
     * @param string[] $names
     */
    private function primeVocabularyCache(array $names): void
    {
        $lookup = [];
        foreach ($names as $name) {
            $trimmed = trim($name);
            if ($trimmed === '') {
                continue;
            }
            if (!array_key_exists($trimmed, $this->vocabularyCache)) {
                $lookup[$trimmed] = true;
            }
        }

        if (empty($lookup)) {
            return;
        }

        $records = Vocabulary::find()
            ->where(['name' => array_keys($lookup)])
            ->all();

        foreach ($records as $record) {
            $this->vocabularyCache[$record->name] = $record;
        }
    }

    /**
     * @param array<string,mixed> $wordData
     */
    private function createVocabulary(string $wordName, array $wordData, int $timestamp): Vocabulary
    {
        $vocabulary = new Vocabulary();
        $vocabulary->name = $wordName;
        $vocabulary->status = 1;
        $vocabulary->weight = 0;
        $vocabulary->generate_card_status = 1;
        $vocabulary->generate_quiz_status = 1;
        $vocabulary->create_time = $timestamp;
        $vocabulary->update_time = $timestamp;
        $vocabulary->create_by = 0;
        $vocabulary->update_by = 0;
        $this->applyVocabularyWordFields($vocabulary, $wordData);

        if (!$vocabulary->save()) {
            throw new \RuntimeException('创建单词失败: ' . json_encode($vocabulary->errors, JSON_UNESCAPED_UNICODE));
        }

        $this->syncVocabularyExtFromEntry($vocabulary, $wordData, $timestamp);

        $this->vocabularyCache[$wordName] = $vocabulary;
        echo "创建单词: {$wordName} (#{$vocabulary->id})\n";

        return $vocabulary;
    }

    /**
     * @param array<string,mixed> $wordData
     */
    private function updateVocabularyFromEntry(Vocabulary $vocabulary, array $wordData, int $timestamp): void
    {
        $this->applyVocabularyWordFields($vocabulary, $wordData);
        $vocabulary->update_by = 0;
        $vocabulary->update_time = $timestamp;

        if (!$vocabulary->save()) {
            throw new \RuntimeException('更新单词失败: ' . json_encode($vocabulary->errors, JSON_UNESCAPED_UNICODE));
        }

        $this->syncVocabularyExtFromEntry($vocabulary, $wordData, $timestamp);
    }

    /**
     * @param array<string,mixed> $wordData
     */
    private function applyVocabularyWordFields(Vocabulary $vocabulary, array $wordData): void
    {
        $translation = $this->truncateString($wordData['translation'] ?? null, 100);
        $definition = $this->truncateString($wordData['definition'] ?? null, 300);
        $ukIpa = $this->truncateString($wordData['uk_pronunciation'] ?? null, 100);
        $usIpa = $this->truncateString($wordData['us_pronunciation'] ?? null, 100);

        if ($translation !== null) {
            $vocabulary->translation = $translation;
        }
        if ($definition !== null) {
            $vocabulary->definition = $definition;
        }
        if ($ukIpa !== null) {
            $vocabulary->uk_ipa = $ukIpa;
        }
        if ($usIpa !== null) {
            $vocabulary->us_ipa = $usIpa;
        }

        if ($translation === null && (array_key_exists('translation', $wordData) || $vocabulary->translation === null)) {
            $vocabulary->translation = '';
        }
        if ($definition === null && (array_key_exists('definition', $wordData) || $vocabulary->definition === null)) {
            $vocabulary->definition = '';
        }
        if ($ukIpa === null && (array_key_exists('uk_pronunciation', $wordData) || $vocabulary->uk_ipa === null)) {
            $vocabulary->uk_ipa = '';
        }
        if ($usIpa === null && (array_key_exists('us_pronunciation', $wordData) || $vocabulary->us_ipa === null)) {
            $vocabulary->us_ipa = '';
        }
    }

    /**
     * @param array<string,mixed> $wordData
     */
    private function syncVocabularyExtFromEntry(Vocabulary $vocabulary, array $wordData, int $timestamp): void
    {
        $hasCoreMeanings = array_key_exists('parts_of_speech', $wordData);
        $hasExamples = array_key_exists('examples', $wordData);

        if (!$hasCoreMeanings && !$hasExamples) {
            return;
        }

        $coreMeanings = $hasCoreMeanings ? $this->normalizePartsOfSpeechPayload($wordData['parts_of_speech']) : null;
        $exampleSentences = $hasExamples ? $this->normalizeExampleSentencesPayload($wordData['examples']) : null;

        $vocabularyExt = VocabularyExt::find()->where(['vocabulary_id' => $vocabulary->id])->one();
        if (!$vocabularyExt) {
            $vocabularyExt = new VocabularyExt();
            $vocabularyExt->vocabulary_id = $vocabulary->id;
            $vocabularyExt->create_by = 0;
            $vocabularyExt->create_time = $timestamp;
        }

        if ($hasCoreMeanings) {
            $vocabularyExt->core_meanings = $coreMeanings;
        }
        if ($hasExamples) {
            $vocabularyExt->example_sentences = $exampleSentences;
        }

        $vocabularyExt->update_by = 0;
        $vocabularyExt->update_time = $timestamp;

        if (!$vocabularyExt->save()) {
            throw new \RuntimeException('保存 vocabulary_ext 失败: ' . json_encode($vocabularyExt->errors, JSON_UNESCAPED_UNICODE));
        }
    }

    public function actionAnalyzeCoCa()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/extracted_words.txt';
        $file_exit = $local_path . '/runtime/tmp/word_index_map.json';
        $question_content = file_get_contents($file_exit);
        $content = json_decode($question_content);
        $content = (array)$content;
        //读取每一行
        $handle = fopen($file, "r");
        $not = [];
        $all = [];
        while (($line = fgets($handle)) !== false) {
            //处理单词
            $line = trim($line);
            $all[] = $line;
        }
        var_dump(count(array_unique($all)));
        var_dump(count($content));
        die;
        foreach ($all as $value) {
            if (!isset($content[$value])) {
                $not[] = $value;
            }
        }
        $outputFile = $local_path . '/runtime/tmp/not_words.txt';
        file_put_contents($outputFile, implode("\n", $not));
        echo "处理完成！\n";
    }

    public function actionInitWordInfo()
    {
        ini_set('memory_limit', '1G');
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/词汇/generated_word_cards.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        $err = [];
        foreach ($content as $key => $value) {
            try {
                $base['wordcardheader'] = $value->wordcardheader;
                $base['coremeanings'] = $value->coremeanings;
                //查询表中数据
                $model = Vocabulary::find()->where(['name' => $key])->one();
                if ($model) {
                    // if (!empty($model->card_info)){
                    //     echo "单词已存在：" . $key . "\n";
                    //     continue;
                    // }
                    $model->card_info = $this->uploadData(json_encode($value, JSON_UNESCAPED_UNICODE));
                    $model->base_info = $base;
                    $model->save();
                    echo "更新单词：" . $key . "\n";
                } else {
                    $model = new Vocabulary();
                    $model->name = $key;
                    $model->card_info = $this->uploadData(json_encode($value, JSON_UNESCAPED_UNICODE));
                    $model->base_info = $base;
                    $model->save();
                    echo "添加单词：" . $key . "\n";
                }
            } catch (\Exception $e) {
                echo $e->getMessage();
                $err[] = $key;
            }
        }

        var_dump($err);
        echo "处理完成！\n";
    }

    public function actionFixWordInfo($id = 0)
    {
        //查询词汇表中所有词，每次查询200个，card_info是oss地址，下载card_info字段值的json数据，并解析成数组，将每个字段值保存到数据库中
        $words = ["alcohol", "alcoholic", "alike", "allowance", "appetite", "appreciation", "arm", "ashamed", "assertion", "assist", "basketball", "beating", "because", "beer", "benefit", "buddy", "catch", "category", "chance", "chorus", "civilian", "composition", "conceal", "correction", "correctly", "creek", "curtain", "developer", "disability", "disposal", "distract", "donor", "electrical", "engagement", "enroll", "etc", "evoke", "excitement", "exhibit", "expensive", "fact", "four", "fund", "gender", "genuine", "greatly", "gross", "guard", "habit", "hardware", "hearing", "highly", "hopeful", "instruction", "intend", "intention", "interior", "intervention", "kidney", "Latin", "laugh", "layer", "loom", "mass", "medieval", "menu", "mere", "moral", "nationwide", "nature", "neighboring", "nickname", "overcome", "Palestinian", "pasta", "patch", "pen", "pork", "poverty", "preference", "quality", "queen", "rather", "realm", "regression", "regulator", "reverse", "roof", "scandal", "serving", "several", "similarity", "squad", "square", "strongly", "substance", "suitable", "surely", "tape", "teaspoon", "temporarily", "thorough", "treatment", "underscore", "unite", "unity", "unknown", "victory", "wet", "wild", "world", "zero", "amongst", "anecdotal", "anthropologist", "approved", "baggage", "barge", "bastard", "betrayal", "biomass", "briefcase", "calling", "capitalist", "caucus", "cock", "cocoa", "comedian", "complexion", "constrain", "contraction", "cosmetic", "curry", "deepen", "demeanor", "den", "dissident", "epic", "erotic", "flank", "folder", "follow-up", "genetically", "greedy", "grit", "gritty", "groan", "haircut", "howl", "incidentally", "indict", "informative", "inning", "inviting", "irresponsible", "lavender", "Lebanese", "lily", "mantra", "masterpiece", "networking", "nicotine", "nigger", "nucleus", "orchard", "painfully", "patiently", "patriarch", "platter", "politely", "powdered", "radiate", "rag", "raisin", "rake", "reappear", "renounce", "residue", "rogue", "ruthless", "seasoned", "secondly", "self-efficacy", "shrub", "souvenir", "televised", "theorist", "thickness", "three-year", "trajectory", "understandable", "volleyball", "walnut"];
        $new_words = [];

        foreach (array_chunk($words, 200) as $v) {
            $list = Vocabulary::find()->andWhere(['not in', 'name', $new_words])->andWhere(['in', 'name', $v])->all();
            foreach ($list as $item) {
                //查询考题数量
                $count = VocabularyQuiz::find()->andWhere(['vocabulary_id' => $item->id])->count();
                if ($count == 21) {
                    echo "$item->name 考题数量已满" . PHP_EOL;
                    continue;
                }
                $id = $item->id;
                //下载card_info字段值的json数据
                $content = $this->downloadContent($item->card_info);
                $content = json_decode($content, true);

                try {
                    //更新数据
                    $item->uk_ipa = $content['wordcardheader']['ukipa'];
                    $item->us_ipa = $content['wordcardheader']['usipa'];
                    $item->basic_sentence = $content['aimemo']['onesentencememo'];
                    $item->pronunciation = $content['wordcardheader']['pronunciationpal'];
                    $item->register = $content['wordcardheader']['register'];
                    $item->ielts_scene = $content['wordcardheader']['ieltsscene'];
                    $item->meanings = is_array($content['coremeanings']) ? json_encode($content['coremeanings'], JSON_UNESCAPED_UNICODE) : json_encode([$content['coremeanings']], JSON_UNESCAPED_UNICODE);
                    $item->fun_etymology = json_encode($content['funetymology']['story'], JSON_UNESCAPED_UNICODE);
                    $item->breakdown     = $content['spellingaid']['breakdown'];
                    $item->mnemonic  = $content['spellingaid']['mnemonic'] ?? '';
                    $item->word_forms = json_encode($content['deeplinks']['wordforms'], JSON_UNESCAPED_UNICODE);
                    $item->collocations = json_encode($content['deeplinks']['collocations'], JSON_UNESCAPED_UNICODE);
                    $item->synonym_analysis = json_encode($content['deeplinks']['synonymanalysis'], JSON_UNESCAPED_UNICODE);
                    $item->confusable_words = json_encode($content['deeplinks']['confusablewordsanalysis'], JSON_UNESCAPED_UNICODE);
                    $item->pitfalls = json_encode($content['commonpitfalls'], JSON_UNESCAPED_UNICODE);
                    $item->tips = isset($content['cicitips']) ? json_encode($content['cicitips'], JSON_UNESCAPED_UNICODE) : json_encode($content['commonpitfalls']['cicitips'], JSON_UNESCAPED_UNICODE);
                    $item->word_root = !isset($content['deeplinks']['wordrootfamily']) ? '' : json_encode($content['deeplinks']['wordrootfamily'], JSON_UNESCAPED_UNICODE);
                    try {
                        $item->save(false);
                    } catch (\Exception $e) {
                        echo $e->getMessage() . PHP_EOL;
                        $new_words[] = $item->name;
                        continue;
                    }
                    echo "$item->name 基本信息更新成功" . PHP_EOL;

                    //更新quiz
                    VocabularyQuiz::deleteAll(['vocabulary_id' => $item->id]);
                    if (!empty($content['contextualapplication']['basicsentence'])) {
                        $quiz = new VocabularyQuiz();
                        $quiz->vocabulary_id = $item->id;
                        $quiz->quiz_type = 1;
                        $quiz->quiz_question = $content['contextualapplication']['basicsentence']['sentence'] ?? '';
                        $quiz->quiz_answer = $content['contextualapplication']['basicsentence']['correctanswer'];
                        $quiz->quiz_translation = $content['contextualapplication']['basicsentence']['translation'];
                        $options = $content['contextualapplication']['basicsentence']['options'] ?? [];
                        $quiz->quiz_options = is_array($options) ? $options : [];
                        $quiz->save();
                        echo "$item->name 基础题目添加成功" . PHP_EOL;
                    }

                    if (!empty($content['quickquiz'])) {
                        if (!empty($content['quickquiz']['multiplechoice'])) {
                            foreach ($content['quickquiz']['multiplechoice'] as $key => $value) {
                                $quiz = new VocabularyQuiz();
                                $quiz->quiz_type = 2;
                                $quiz->quiz_question = $value['questionsentence'];
                                if (is_array($value['sentencetranslation'])) {
                                    $quiz->quiz_answer = $value['sentencetranslation']['correctanswer'];
                                    $options = $value['sentencetranslation']['options'] ?? [];
                                    $quiz->quiz_options = is_array($options) ? $options : [];
                                } else {
                                    $quiz->quiz_translation = $value['sentencetranslation'];
                                    $quiz->quiz_answer = $value['correctanswer'];
                                    $options = $value['options'] ?? [];
                                    $quiz->quiz_options = is_array($options) ? $options : [];
                                }
                                $quiz->vocabulary_id = $item->id;
                                $quiz->save();
                                echo "$item->name 选择题保存成功！" . PHP_EOL;
                            }
                        }
                        if (!empty($content['quickquiz']['fillintheblank'])) {
                            foreach ($content['quickquiz']['fillintheblank'] as $key => $value) {
                                $quiz = new VocabularyQuiz();
                                $quiz->quiz_type = 3;
                                $quiz->quiz_question = $value['questionsentence'];
                                if (is_array($value['sentencetranslation'])) {
                                    $quiz->quiz_answer = $value['sentencetranslation']['correctanswer'];
                                    $options = $value['sentencetranslation']['options'] ?? [];
                                    $quiz->quiz_options = is_array($options) ? $options : [];
                                } else {
                                    $quiz->quiz_translation = $value['sentencetranslation'];
                                    $quiz->quiz_answer = $value['correctanswer'];
                                    $options = $value['options'] ?? [];
                                    $quiz->quiz_options = is_array($options) ? $options : [];
                                }

                                $quiz->vocabulary_id = $item->id;
                                $quiz->save();
                                echo "$item->name 填空题保存成功！" . PHP_EOL;
                            }
                        }
                        if (!empty($content['quickquiz']['listenandchoose'])) {
                            foreach ($content['quickquiz']['listenandchoose'] as $key => $value) {
                                $quiz = new VocabularyQuiz();
                                $quiz->quiz_type = 4;
                                $quiz->quiz_question = $value['audioprompt'];
                                if (is_array($value['sentencetranslation'])) {
                                    $quiz->quiz_answer = $value['sentencetranslation']['correctanswer'];
                                    $options = $value['sentencetranslation']['options'] ?? [];
                                    $quiz->quiz_options = is_array($options) ? $options : [];
                                } else {
                                    $quiz->quiz_translation = $value['sentencetranslation'];
                                    $quiz->quiz_answer = $value['correctanswer'];
                                    $options = $value['options'] ?? [];
                                    $quiz->quiz_options = is_array($options) ? $options : [];
                                }
                                $quiz->vocabulary_id = $item->id;
                                $quiz->save();
                                echo "$item->name 听力选择题保存成功！" . PHP_EOL;
                            }
                        }
                        if (!empty($content['quickquiz']['dictation'])) {
                            foreach ($content['quickquiz']['dictation'] as $key => $value) {
                                $quiz = new VocabularyQuiz();
                                $quiz->quiz_type = 5;
                                $quiz->quiz_question = $value['audioprompt'];
                                if (is_array($value['prompttranslation'])) {
                                    $quiz->quiz_answer = $value['prompttranslation']['correctanswer'];
                                } else {
                                    $quiz->quiz_answer = $value['correctanswer'];
                                    $quiz->quiz_translation = $value['prompttranslation'];
                                }

                                $quiz->vocabulary_id = $item->id;
                                $quiz->quiz_options     = [];
                                $quiz->save();
                                echo "$item->name 听写题保存成功！" . PHP_EOL;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    echo $e->getMessage();
                    $new_words[] = $item->name;
                }
            }
            echo '以下单词数据有问题：' . PHP_EOL;
            var_dump($new_words);
            //写入文件
            $local_path = dirname(__FILE__, 2);
            $file = $local_path . '/runtime/tmp/new_words.txt';
            file_put_contents($file, json_encode($new_words));
        }
    }


    public function uploadData($data)
    {
        $oss = $this->getOssClient();
        //uuid+毫秒时间戳生成文件名
        $filename = sprintf('%04x%04x%04x%04x%04x%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)) . '_' . round(microtime(true) * 1000);
        $local_path = dirname(__FILE__, 2) . '/runtime/tmp';
        $local_file = $local_path . '/' . $filename;

        //生成本地文件
        if (!file_put_contents($local_file, $data)) {
            var_dump("写入文件错误");
            die;
        }

        //上传文件
        $remote_file = 'vocabulary/word_card/' . $filename;
        $this->upload($oss, $remote_file, $local_file);
        unlink($local_file);
        return '/' . $remote_file;
    }

    public function getOssClient(): OssClient
    {
        $accessKeyId = Yii::$app->params['oss']['accessKeyId'];         //获取阿里云oss的accessKeyId
        $accessKeySecret = Yii::$app->params['oss']['accessKeySecret'];     //获取阿里云oss的accessKeySecret
        $endpoint = Yii::$app->params['oss']['endPoint'];            //获取阿里云oss的endPoint
        return new OssClient($accessKeyId, $accessKeySecret, $endpoint); //实例化OssClient对象
    }

    public function upload($oss, $object, $filepath)
    {
        $bucket = Yii::$app->params['oss']['bucket']; //获取阿里云oss的bucket

        $maxRetries = 3;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;
            try {
                $getOssInfo = $oss->uploadFile($bucket, $object, $filepath);
                return $getOssInfo['info']['url'];
            } catch (OssException $e) {
                if ($attempt === $maxRetries) {
                    var_dump($e->getMessage());
                    return null;
                }
                sleep(1);
            }
        }

        return null;
    }

    public function downloadContent($file_url)
    {
        $max_attempts = 5;
        $attempt = 0;

        while ($attempt < $max_attempts) {
            $attempt++;

            try {
                //从阿里云下载文件内容
                $content = $this->downloadFromAliyun($file_url);

                if ($content === false) {
                    var_dump("第 {$attempt} 次下载失败: {$file_url}");
                    continue;
                }

                return $content;
            } catch (\Exception $e) {
                var_dump("第 {$attempt} 次处理异常: " . $e->getMessage());
            }
        }

        var_dump("已尝试 {$max_attempts} 次，仍无法获取足够长度的内容: {$file_url}");
        return false;
    }

    public function downloadFromAliyun($file_url)
    {
        $oss = $this->getOssClient();
        $bucket = Yii::$app->params['oss']['bucket'];

        //移除开头的斜杠
        $object = ltrim($file_url, '/');

        try {
            //从阿里云下载文件内容
            $content = $oss->getObject($bucket, $object);
            return $content;
        } catch (OssException $e) {
            var_dump("从阿里云下载文件失败: " . $e->getMessage());
            return false;
        }
    }

    public function actionGenerateBookRelation()
    {
        // 查询前3500条单词数据，按weight升序排序
        $vocabularies = Vocabulary::find()
            ->orderBy(['weight' => SORT_ASC])
            ->where(['>', 'id', 3500])
            ->limit(3000)
            ->all();

        $sql = "INSERT INTO `vocabulary_book_relation` (`vocabulary_id`, `book_id`, `order`, `status`, `create_by`, `create_time`, `update_by`, `update_time`) VALUES\n";

        $currentTime = time();
        $values = [];

        $order_sort = 0;
        foreach ($vocabularies as $vocabulary) {
            $vocabularyId = $vocabulary->id;
            $bookId = 2;
            $order = $order_sort++;
            $status = 1;
            $createBy = 0;
            $createTime = $currentTime;
            $updateBy = 0;
            $updateTime = $currentTime;

            $values[] = "($vocabularyId, $bookId, $order, $status, $createBy, $createTime, $updateBy, $updateTime)";
        }

        $sql .= implode(",\n", $values) . ";";

        // 保存到文件
        $local_path = dirname(__FILE__, 2);
        $outputFile = $local_path . '/runtime/tmp/vocabulary_book_relation1.sql';
        file_put_contents($outputFile, $sql);

        echo "生成的SQL语句已保存到: {$outputFile}\n";
        echo "总共生成了 " . count($vocabularies) . " 条记录\n";
    }

    /**
     * 从test-words.json初始化词汇表和词汇扩展表
     * 运行命令：php yii words/init-from-test-words
     */
    public function actionInitFromTestWords()
    {
        ini_set('memory_limit', '1G');
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/test-words(2).json';

        if (!file_exists($file)) {
            echo "文件不存在: {$file}\n";
            return;
        }

        $content = file_get_contents($file);
        $words = json_decode($content, true);

        if (!$words) {
            echo "JSON解析失败\n";
            return;
        }

        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($words as $wordData) {
            try {
                $wordName = $wordData['word'] ?? '';
                if (empty($wordName)) {
                    echo "单词名称为空，跳过\n";
                    continue;
                }

                echo "处理单词: {$wordName}\n";

                // 开启事务
                $transaction = Yii::$app->db->beginTransaction();

                try {
                    // 查询或创建vocabulary记录
                    $vocabulary = Vocabulary::find()->where(['name' => $wordName])->one();
                    if (!$vocabulary) {
                        $vocabulary = new Vocabulary();
                        $vocabulary->name = $wordName;
                        echo "创建新单词: {$wordName}\n";
                    } else {
                        echo "更新已存在单词: {$wordName}\n";
                    }

                    // 上传完整单词数据到阿里云OSS
                    $vocabulary->card_info = $this->uploadData(json_encode($wordData, JSON_UNESCAPED_UNICODE));

                    // 设置vocabulary表字段
                    $vocabulary->uk_ipa = $wordData['pronunciation']['ukIPA'] ?? '';
                    $vocabulary->us_ipa = $wordData['pronunciation']['usIPA'] ?? '';
                    $vocabulary->pronunciation = $wordData['pronunciation']['pronunciationPal'] ?? '';

                    // breakdown字段从spellingAid.recommendedSplit获取，直接存JSON
                    if (isset($wordData['spellingAid']['recommendedSplit'])) {
                        $vocabulary->breakdown = json_encode($wordData['spellingAid']['recommendedSplit'], JSON_UNESCAPED_UNICODE);
                    }

                    $vocabulary->syllable_ipa_uk = $wordData['pronunciation']['syllableIPA']['uk'] ?? '';
                    $vocabulary->syllable_ipa_us = $wordData['pronunciation']['syllableIPA']['us'] ?? '';

                    if (!$vocabulary->save()) {
                        throw new \Exception("保存vocabulary失败: " . json_encode($vocabulary->errors));
                    }

                    // 处理vocabulary_ext表
                    $vocabularyExt = VocabularyExt::find()->where(['vocabulary_id' => $vocabulary->id])->one();
                    if (!$vocabularyExt) {
                        $vocabularyExt = new VocabularyExt();
                        $vocabularyExt->vocabulary_id = $vocabulary->id;
                        $vocabularyExt->create_by = 0;
                        $vocabularyExt->create_time = time();
                    }

                    $vocabularyExt->update_by = 0;
                    $vocabularyExt->update_time = time();

                    // 设置vocabulary_ext表字段
                    $vocabularyExt->core_meanings = $wordData['coreMeanings'] ?? null;

                    $vocabularyExt->word_forms = $wordData['deepLinks']['wordForms'] ?? null;

                    $vocabularyExt->word_root_family = $wordData['deepLinks']['wordRootFamily'] ?? null;

                    $vocabularyExt->collocations = $wordData['deepLinks']['collocations'] ?? null;

                    // nuance_analysis字段 - 从wordAnalysis.nuanceAnalysis获取
                    $vocabularyExt->nuance_analysis = $wordData['deepLinks']['wordAnalysis']['nuanceAnalysis'] ?? null;

                    // tips字段 - 从applicationSynthesis获取
                    $vocabularyExt->tips = $wordData['deepLinks']['wordAnalysis']['applicationSynthesis'] ?? null;

                    // mistakes字段
                    $vocabularyExt->mistakes = $wordData['deepLinks']['wordAnalysis']['commonMistakes']['mistakesList'] ?? null;

                    // example_sentences字段 - 直接从exampleSentences获取
                    $vocabularyExt->example_sentences = $wordData['exampleSentences'] ?? null;

                    if (!$vocabularyExt->save()) {
                        throw new \Exception("保存vocabulary_ext失败: " . json_encode($vocabularyExt->errors));
                    }

                    // 删除该单词的旧考题及选项
                    $oldQuizIds = VocabularyQuiz::find()
                        ->select('id')
                        ->where(['vocabulary_id' => $vocabulary->id])
                        ->column();
                    if (!empty($oldQuizIds)) {
                        VocabularyQuizOption::deleteAll(['in', 'quiz_id', $oldQuizIds]);
                    }
                    VocabularyQuiz::deleteAll(['vocabulary_id' => $vocabulary->id]);

                    // 初始化考题数据
                    $quizCount = 0;

                    // 题型1：看词示意 (multipleChoice)
                    if (isset($wordData['quizzes']['multipleChoice']) && is_array($wordData['quizzes']['multipleChoice'])) {
                        foreach ($wordData['quizzes']['multipleChoice'] as $mcQuiz) {
                            $quiz = new VocabularyQuiz();
                            $quiz->vocabulary_id = $vocabulary->id;
                            $quiz->quiz_type = 1; // 看词示意
                            $quiz->quiz_question = $mcQuiz['exampleSentence'] ?? '';
                            $quiz->quiz_translation = $mcQuiz['exampleTranslation'] ?? '';

                            // audio_sentence: 去掉加粗标识（** **）
                            $audioSentence = $mcQuiz['exampleSentence'] ?? '';
                            $audioSentence = preg_replace('/\*\*(.*?)\*\*/', '$1', $audioSentence);
                            $quiz->audio_sentence = $audioSentence;
                            $quiz->quiz_options   = [];

                            if ($quiz->save()) {
                                // 保存选项到 vocabulary_quiz_option 表
                                if (isset($mcQuiz['options']) && is_array($mcQuiz['options'])) {
                                    foreach ($mcQuiz['options'] as $option) {
                                        $quizOption = new VocabularyQuizOption();
                                        $quizOption->quiz_id = $quiz->id;
                                        $quizOption->source_word = $option['sourceWord'] ?? '';
                                        $quizOption->pos = $option['pos'] ?? '';
                                        $quizOption->definition = $option['definition'] ?? '';
                                        $quizOption->is_correct = (isset($option['isCorrect']) && $option['isCorrect'] === true) ? 1 : 2;
                                        $quizOption->create_by = 0;
                                        $quizOption->create_time = time();
                                        $quizOption->update_by = 0;
                                        $quizOption->update_time = time();

                                        if (!$quizOption->save()) {
                                            echo "  警告: 选项保存失败 - " . json_encode($quizOption->errors) . "\n";
                                        }
                                    }
                                }
                                $quizCount++;
                            } else {
                                echo "  警告: 看词示意题保存失败 - " . json_encode($quiz->errors) . "\n";
                            }
                        }
                    }

                    // 题型2：单词拼接 (spellingAid)
                    if (isset($wordData['spellingAid'])) {
                        $spellingAid = $wordData['spellingAid'];

                        $quiz = new VocabularyQuiz();
                        $quiz->vocabulary_id = $vocabulary->id;
                        $quiz->quiz_type = 2; // 单词拼接
                        $quiz->quiz_translation = '[]';
                        $quiz->quiz_options = [];

                        // quiz_question: spellingAid.recommendedSplit
                        if (isset($spellingAid['recommendedSplit'])) {
                            $quiz->quiz_question = json_encode($spellingAid['recommendedSplit'], JSON_UNESCAPED_UNICODE);
                        }

                        // quiz_options: spellingAid.distractors
                        // 遍历distractors数组，删除长度与单词长度相同的值
                        if (isset($spellingAid['distractors']) && is_array($spellingAid['distractors'])) {
                            // 从recommendedSplit数组中提取字母并计算长度
                            $wordLength = 0;
                            if (isset($spellingAid['recommendedSplit']) && is_array($spellingAid['recommendedSplit'])) {
                                $joinedWord = implode('', $spellingAid['recommendedSplit']);
                                // 只保留字母（去掉特殊字符）
                                $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $joinedWord);
                                $wordLength = mb_strlen($lettersOnly);
                            }

                            $filteredDistractors = [];

                            foreach ($spellingAid['distractors'] as $distractor) {
                                // 如果distractor是对象，获取content字段；如果是字符串，直接使用
                                $distractorContent = is_array($distractor) && isset($distractor['content'])
                                    ? $distractor['content']
                                    : (is_string($distractor) ? $distractor : '');

                                // 只保留长度与单词长度不同的干扰项
                                if (mb_strlen($distractorContent) < $wordLength) {
                                    $filteredDistractors[] = $distractor;
                                }
                            }

                            $quiz->quiz_options = empty($filteredDistractors) ? [] : $filteredDistractors;
                        }

                        if ($quiz->save()) {
                            $quizCount++;
                        } else {
                            echo "  警告: 单词拼接题保存失败 - " . json_encode($quiz->errors) . "\n";
                        }
                    }

                    $transaction->commit();
                    $successCount++;
                    echo "✓ 单词 {$wordName} 处理成功\n";
                } catch (\Exception $e) {
                    $transaction->rollBack();
                    throw $e;
                }
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = $wordName ?? 'unknown';
                echo "✗ 处理失败: {$e->getMessage()}\n";
            }
        }

        echo "\n===========================================\n";
        echo "处理完成！\n";
        echo "成功: {$successCount} 个单词\n";
        echo "失败: {$errorCount} 个单词\n";

        if (!empty($errors)) {
            echo "\n失败的单词列表:\n";
            foreach ($errors as $error) {
                echo "- {$error}\n";
            }
        }
        echo "===========================================\n";
    }

    /**
     * 从“单词切割”目录导入correct_chunks和distractor_chunks
     * 运行命令：php yii words/update-quiz-chunks [directory]
     * @param string $directory
     */
    public function actionUpdateQuizChunks(string $directory = ''): void
    {
        $defaultDirectory = dirname(__FILE__, 2) . '/runtime/tmp/单词切割';
        $baseDir = $directory !== '' ? $directory : $defaultDirectory;

        if (!is_dir($baseDir)) {
            echo "目录不存在：{$baseDir}\n";
            return;
        }

        $processedFiles = 0;
        $updatedQuizzes = 0;
        $createdQuizzes = 0;
        $unchangedQuizzes = 0;
        $missingVocabulary = [];
        $failedUpdates = [];
        $failedReads = [];

        $iterator = new \FilesystemIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (strtolower($fileInfo->getExtension()) !== 'json') {
                continue;
            }

            $processedFiles++;
            $wordName = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            $filePath = $fileInfo->getPathname();

            $content = file_get_contents($filePath);
            if ($content === false) {
                $failedReads[] = "{$wordName} ({$filePath}) - 读取文件失败";
                continue;
            }

            $data = json_decode($content, true);
            if (!is_array($data) || empty($data) || !isset($data[0]) || !is_array($data[0])) {
                $failedReads[] = "{$wordName} ({$filePath}) - JSON结构不符合预期";
                continue;
            }
            if (json_last_error() !== JSON_ERROR_NONE) {
                $failedReads[] = "{$wordName} ({$filePath}) - JSON解析错误: " . json_last_error_msg();
                continue;
            }

            $entry = $data[0];
            $correctChunks = isset($entry['correct_chunks']) && is_array($entry['correct_chunks'])
                ? array_values($entry['correct_chunks'])
                : null;
            $distractorChunks = isset($entry['distractor_chunks']) && is_array($entry['distractor_chunks'])
                ? array_values($entry['distractor_chunks'])
                : null;

            if ($correctChunks === null && $distractorChunks === null) {
                $failedReads[] = "{$wordName} ({$filePath}) - 缺少有效的correct_chunks或distractor_chunks";
                continue;
            }

            $vocabulary = Vocabulary::find()->where(['name' => $wordName])->one();
            if (!$vocabulary) {
                $missingVocabulary[] = $wordName;
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $now = time();
                $wordUpdated = 0;

                $quizzes = VocabularyQuiz::find()
                    ->where(['vocabulary_id' => $vocabulary->id, 'quiz_type' => 2])
                    ->all();

                if (empty($quizzes)) {
                    $quiz = new VocabularyQuiz();
                    $quiz->vocabulary_id = $vocabulary->id;
                    $quiz->quiz_type = 2;
                    $quiz->quiz_translation = '[]';
                    $quiz->quiz_question = $correctChunks !== null
                        ? (json_encode($correctChunks, JSON_UNESCAPED_UNICODE) ?: '')
                        : '';
                    $quiz->quiz_options = $distractorChunks ?? [];
                    $quiz->status = $quiz->status ?? 1;
                    $quiz->create_by = 0;
                    $quiz->create_time = $now;
                    $quiz->update_by = 0;
                    $quiz->update_time = $now;

                    if (!$quiz->save()) {
                        throw new \RuntimeException('创建题目失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
                    }

                    $createdQuizzes++;
                    echo "创建 {$wordName} (#{$vocabulary->id}): 新增题目\n";
                    $transaction->commit();
                    continue;
                }

                foreach ($quizzes as $quiz) {
                    $changed = false;

                    if ($correctChunks !== null) {
                        $encodedChunks = json_encode($correctChunks, JSON_UNESCAPED_UNICODE);
                        if ($encodedChunks === false) {
                            throw new \RuntimeException('correct_chunks JSON编码失败');
                        }
                        if ($quiz->quiz_question !== $encodedChunks) {
                            $quiz->quiz_question = $encodedChunks;
                            $changed = true;
                        }
                    }

                    if ($distractorChunks !== null) {
                        $currentOptions = $quiz->quiz_options;
                        if (is_string($currentOptions)) {
                            $decodedOptions = json_decode($currentOptions, true);
                            if (json_last_error() === JSON_ERROR_NONE) {
                                $currentOptions = $decodedOptions;
                            }
                        }
                        if (!is_array($currentOptions)) {
                            $currentOptions = [];
                        }
                        if ($currentOptions !== $distractorChunks) {
                            $quiz->quiz_options = $distractorChunks;
                            $changed = true;
                        }
                    }

                    if ($changed) {
                        $quiz->update_by = 0;
                        $quiz->update_time = $now;
                        if (!$quiz->save()) {
                            throw new \RuntimeException('保存失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
                        }
                        $wordUpdated++;
                    } else {
                        $unchangedQuizzes++;
                    }
                }
                $transaction->commit();
            } catch (\Throwable $e) {
                $transaction->rollBack();
                $failedUpdates[] = "{$wordName} (#{$vocabulary->id}) - " . $e->getMessage();
                continue;
            }

            if ($wordUpdated > 0) {
                $updatedQuizzes += $wordUpdated;
                echo "更新 {$wordName} (#{$vocabulary->id}): {$wordUpdated} 条题目\n";
            }
        }

        echo "\n====== 更新结果 ======\n";
        echo "处理文件数: {$processedFiles}\n";
        echo "更新题目数: {$updatedQuizzes}\n";
        echo "新建题目数: {$createdQuizzes}\n";
        echo "无需更新题目数: {$unchangedQuizzes}\n";

        if (!empty($missingVocabulary)) {
            $this->outputList('未找到词汇的单词', $missingVocabulary);
        }

        if (!empty($failedReads)) {
            $this->outputList('读取失败的文件', $failedReads);
        }

        if (!empty($failedUpdates)) {
            $this->outputList('保存失败的记录', $failedUpdates);
        }

        echo "======================\n";
    }

    private function outputList(string $title, array $items): void
    {
        $count = count($items);
        echo "\n{$title} ({$count}):\n";
        $limit = 20;
        foreach (array_slice($items, 0, $limit) as $item) {
            echo "- {$item}\n";
        }
        if ($count > $limit) {
            echo "- ...\n";
        }
    }

    /**
     * 读取 words_new_split 目录下的拼写题 JSON，并更新/创建 quiz_type=2 的题目
     * php yii words/import-quiz-chunks-from-split "@runtime/tmp/words_new_split"
     */
    public function actionImportQuizChunksFromSplit(string $directory = '@runtime/tmp/words_new_split'): int
    {
        $baseDir = Yii::getAlias($directory);
        if (!is_dir($baseDir)) {
            echo "目录不存在：{$baseDir}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $iterator = new \FilesystemIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
        $processedFiles = 0;
        $createdQuizzes = 0;
        $updatedQuizzes = 0;
        $unchangedQuizzes = 0;
        $skippedNoChunks = 0;
        $missingVocabulary = [];
        $failedReads = [];
        $failedUpdates = [];

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'json') {
                continue;
            }

            $processedFiles++;
            $wordName = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            $filePath = $fileInfo->getPathname();

            $raw = file_get_contents($filePath);
            if ($raw === false) {
                $failedReads[] = "{$wordName} ({$filePath}) - 读取文件失败";
                continue;
            }

            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data) || !isset($data[0]) || !is_array($data[0])) {
                $failedReads[] = "{$wordName} ({$filePath}) - JSON解析失败";
                continue;
            }

            $entry = $data[0];
            $correctChunks = isset($entry['correct_chunks']) && is_array($entry['correct_chunks'])
                ? array_values($entry['correct_chunks'])
                : null;
            $distractorChunks = isset($entry['distractor_chunks']) && is_array($entry['distractor_chunks'])
                ? array_values($entry['distractor_chunks'])
                : [];

            if ($correctChunks === null || $correctChunks === []) {
                $skippedNoChunks++;
                echo "跳过 {$wordName} - 缺少 correct_chunks\n";
                continue;
            }

            $encodedCorrectChunks = json_encode($correctChunks, JSON_UNESCAPED_UNICODE);
            if ($encodedCorrectChunks === false) {
                $failedReads[] = "{$wordName} ({$filePath}) - correct_chunks 编码失败";
                continue;
            }

            $vocabulary = $this->findVocabularyByNameVariants($wordName);
            if ($vocabulary === null) {
                $missingVocabulary[] = $wordName;
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $quizzes = VocabularyQuiz::find()
                    ->where(['vocabulary_id' => $vocabulary->id, 'quiz_type' => 2])
                    ->all();
                $now = time();

                if (empty($quizzes)) {
                    $quiz = new VocabularyQuiz();
                    $quiz->vocabulary_id = $vocabulary->id;
                    $quiz->quiz_type = 2;
                    $quiz->quiz_question = $encodedCorrectChunks;
                    $quiz->quiz_answer = $encodedCorrectChunks;
                    $quiz->quiz_translation = '[]';
                    $quiz->quiz_options = $distractorChunks;
                    $quiz->status = 1;
                    $quiz->create_by = 0;
                    $quiz->create_time = $now;
                    $quiz->update_by = 0;
                    $quiz->update_time = $now;

                    if (!$quiz->save()) {
                        throw new \RuntimeException('题目保存失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
                    }

                    $createdQuizzes++;
                    echo "创建 {$wordName} (#{$vocabulary->id}) 的拼写题\n";
                    $transaction->commit();
                    continue;
                }

                $wordUpdated = 0;
                foreach ($quizzes as $quiz) {
                    $changed = false;

                    if ($quiz->quiz_question !== $encodedCorrectChunks) {
                        $quiz->quiz_question = $encodedCorrectChunks;
                        $changed = true;
                    }

                    if ($quiz->quiz_answer !== $encodedCorrectChunks) {
                        $quiz->quiz_answer = $encodedCorrectChunks;
                        $changed = true;
                    }

                    $currentOptions = $quiz->quiz_options;
                    if (is_string($currentOptions)) {
                        $decodedOptions = json_decode($currentOptions, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $currentOptions = $decodedOptions;
                        }
                    }
                    if (!is_array($currentOptions)) {
                        $currentOptions = [];
                    }
                    if ($currentOptions !== $distractorChunks) {
                        $quiz->quiz_options = $distractorChunks;
                        $changed = true;
                    }

                    if ($changed) {
                        $quiz->update_by = 0;
                        $quiz->update_time = $now;
                        if (!$quiz->save()) {
                            throw new \RuntimeException('题目保存失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
                        }
                        $wordUpdated++;
                    } else {
                        $unchangedQuizzes++;
                    }
                }

                $transaction->commit();

                if ($wordUpdated > 0) {
                    $updatedQuizzes += $wordUpdated;
                    echo "更新 {$wordName} (#{$vocabulary->id}) 的拼写题 {$wordUpdated} 条\n";
                }
            } catch (\Throwable $e) {
                $transaction->rollBack();
                $failedUpdates[] = "{$wordName} (#{$vocabulary->id}) - " . $e->getMessage();
                continue;
            }
        }

        echo "\n====== words_new_split 导入结果 ======\n";
        echo "处理文件数: {$processedFiles}\n";
        echo "新增题目数: {$createdQuizzes}\n";
        echo "更新题目数: {$updatedQuizzes}\n";
        echo "无需更新题目数: {$unchangedQuizzes}\n";
        echo "缺少 correct_chunks: {$skippedNoChunks}\n";

        if (!empty($missingVocabulary)) {
            $this->outputList('未找到词汇', $missingVocabulary);
        }

        if (!empty($failedReads)) {
            $this->outputList('读取/解析失败', $failedReads);
        }

        if (!empty($failedUpdates)) {
            $this->outputList('保存失败', $failedUpdates);
        }

        echo "=====================================\n";

        return ExitCode::OK;
    }

    /**
     * 导入 question_type=1 的题目（看词示意）
     * 运行命令：php yii words/import-sentence-quizzes [directory]
     * @param string $directory
     */
    public function actionImportSentenceQuizzes(string $directory = ''): void
    {
        $defaultDirectory = dirname(__FILE__, 2) . '/runtime/tmp/words_new_question1';
        $baseDir = $directory !== '' ? $directory : $defaultDirectory;

        if (!is_dir($baseDir)) {
            echo "目录不存在：{$baseDir}\n";
            return;
        }

        $processedFiles = 0;
        $createdQuizzes = 0;
        $updatedQuizzes = 0;
        $failedReads = [];
        $failedUpdates = [];
        $missingVocabulary = [];
        $skipped = [];
        $errorWords = [];

        $skipUntilWord = trim(self::SENTENCE_IMPORT_SKIP_UNTIL);
        $skipReached = $skipUntilWord === '';

        $iterator = new \FilesystemIterator($baseDir, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile() || strtolower($fileInfo->getExtension()) !== 'json') {
                continue;
            }

            $processedFiles++;

            $filePath = $fileInfo->getPathname();
            $raw = file_get_contents($filePath);
            if ($raw === false) {
                $failedReads[] = "{$fileInfo->getBasename()} - 读取文件失败";
                continue;
            }

            $data = json_decode($raw, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $failedReads[] = "{$fileInfo->getBasename()} - JSON解析错误: " . json_last_error_msg();
                continue;
            }

            if (!is_array($data) || empty($data) || !isset($data[0]) || !is_array($data[0])) {
                $failedReads[] = "{$fileInfo->getBasename()} - JSON结构不符合预期";
                var_dump("{$fileInfo->getBasename()} - JSON结构不符合预期");
                continue;
            }

            $quizItem = $data[0]['quiz_item'] ?? null;
            if (!is_array($quizItem)) {
                $failedReads[] = "{$fileInfo->getBasename()} - 未找到 quiz_item 数据";
                continue;
            }

            $wordName = trim((string)($quizItem['target_word'] ?? ''));
            if ($wordName === '') {
                $wordName = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            }

            $candidateNames = [$wordName];
            $fileWord = $fileInfo->getBasename('.' . $fileInfo->getExtension());
            if ($fileWord !== '' && !in_array($fileWord, $candidateNames, true)) {
                $candidateNames[] = $fileWord;
            }

            if (!$skipReached) {
                foreach ($candidateNames as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '') {
                        continue;
                    }

                    var_dump($candidate);
                    var_dump($skipUntilWord);
                    if (strcasecmp($candidate, $skipUntilWord) === 0) {
                        $wordName = $candidate;
                        $skipReached = true;
                        break;
                    }
                }

                if (!$skipReached) {
                    var_dump("skipReached:" . $skipReached);
                    continue;
                }
            }

            var_dump("wordName:" . $wordName);

            $sentence = '';
            if (isset($quizItem['sentence'])) {
                $sentence = is_array($quizItem['sentence']) ? '' : trim((string)($quizItem['sentence'] ?? ''));
            }

            if ($sentence === '') {
                $skipped[] = "{$wordName} - 缺少 sentence 字段";
                continue;
            }

            $optionList = $quizItem['options']['option'] ?? [];
            if (!is_array($optionList) || empty($optionList)) {
                $skipped[] = "{$wordName} - 缺少选项数据";
                continue;
            }

            $vocabulary = null;
            try {
                foreach ($candidateNames as $candidate) {
                    $candidate = trim($candidate);
                    if ($candidate === '') {
                        continue;
                    }
                    $vocabulary = Vocabulary::find()->where(['name' => $candidate])->one();
                    if ($vocabulary) {
                        $wordName = $candidate;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $failedUpdates[] = "{$wordName} - 查询词汇失败: " . $e->getMessage();
                $errorWords[] = $wordName;
                continue;
            }

            if (!$vocabulary) {
                $missingVocabulary[] = $wordName;
                continue;
            }

            $existingQuiz = VocabularyQuiz::find()
                ->where(['vocabulary_id' => $vocabulary->id, 'quiz_type' => 1])
                ->one();
            if ($existingQuiz) {
                $skipped[] = "{$wordName} (#{$vocabulary->id}) - 已存在题目";
                echo "跳过 {$wordName} (#{$vocabulary->id}) - 已存在题目\n";
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $now = time();
                $quiz = new VocabularyQuiz();
                $quiz->vocabulary_id = $vocabulary->id;
                $quiz->quiz_type = 1;
                $quiz->create_by = 0;
                $quiz->create_time = $now;
                $quiz->status = 1;

                $quiz->quiz_question = $sentence;
                $quiz->quiz_options = '[]';

                $optionsPayload = [];
                $correctAnswer = '';
                foreach ($optionList as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $definition = trim((string)($option['definition'] ?? ''));
                    if ($definition === '') {
                        continue;
                    }
                    $isCorrect = isset($option['iscorrect']) && $option['iscorrect'] ? 1 : 2;
                    $optionsPayload[] = [
                        'definition' => $definition,
                        'is_correct' => $isCorrect,
                    ];
                    if ($isCorrect === 1 && $correctAnswer === '') {
                        $correctAnswer = $definition;
                    }
                }

                if (empty($optionsPayload)) {
                    $transaction->rollBack();
                    $skipped[] = "{$wordName} - 无有效选项";
                    continue;
                }

                $quiz->quiz_answer = $correctAnswer;
                $quiz->quiz_translation = trim((string)($quizItem['error_analysis'] ?? ''));
                if ($quiz->quiz_translation === '') {
                    $quiz->quiz_translation = '[]';
                }
                $quiz->quiz_options = '[]';
                $quiz->update_by = 0;
                $quiz->update_time = $now;

                if (!$quiz->save()) {
                    throw new \RuntimeException('题目保存失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
                }

                VocabularyQuizOption::deleteAll(['quiz_id' => $quiz->id]);
                foreach ($optionsPayload as $option) {
                    $quizOption = new VocabularyQuizOption();
                    $quizOption->quiz_id = $quiz->id;
                    $quizOption->definition = $option['definition'];
                    $quizOption->is_correct = $option['is_correct'];
                    $quizOption->source_word = '';
                    $quizOption->pos = '';
                    $quizOption->create_by = 0;
                    $quizOption->create_time = $now;
                    $quizOption->update_by = 0;
                    $quizOption->update_time = $now;
                    if (!$quizOption->save()) {
                        throw new \RuntimeException('选项保存失败: ' . json_encode($quizOption->errors, JSON_UNESCAPED_UNICODE));
                    }
                }

                $transaction->commit();
                $createdQuizzes++;
                echo "创建 {$wordName} (#{$vocabulary->id}) 的题目\n";
            } catch (\Throwable $e) {
                $transaction->rollBack();
                $failedUpdates[] = "{$wordName} - " . $e->getMessage();
                $errorWords[] = $wordName;
            }
        }

        echo "\n====== 导入结果 ======\n";
        echo "处理文件数: {$processedFiles}\n";
        echo "新建题目数: {$createdQuizzes}\n";
        echo "更新题目数: {$updatedQuizzes}\n";

        if (!empty($missingVocabulary)) {
            $this->outputList('未找到词汇的单词', $missingVocabulary);
        }

        if (!empty($skipped)) {
            $this->outputList('被跳过的项目', $skipped);
        }

        if (!empty($failedReads)) {
            $this->outputList('读取失败的文件', $failedReads);
        }

        if (!empty($failedUpdates)) {
            $this->outputList('保存失败的记录', $failedUpdates);
        }

        if (!empty($errorWords)) {
            $this->outputList('导入失败的单词', $errorWords);
        }

        echo "======================\n";
    }

    /**
     * 读取 assembled.json 并更新单词数据
     * 运行命令：php yii words/import-assembled [filePath]
     */
    public function actionImportAssembled(string $filePath = ''): void
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $basePath = dirname(__FILE__, 2);
        $path = $filePath !== '' ? $filePath : $basePath . '/runtime/tmp/assembled.json';

        if (!is_file($path)) {
            echo "文件不存在: {$path}\n";
            return;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            echo "读取文件失败: {$path}\n";
            return;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload) || !isset($payload['vocabularies']) || !is_array($payload['vocabularies'])) {
            echo "JSON 解析失败，缺少 vocabularies 字段\n";
            return;
        }

        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($payload['vocabularies'] as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $wordName = trim((string)($entry['word'] ?? ''));
            if ($wordName === '') {
                continue;
            }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                $now = time();
                $vocabulary = Vocabulary::find()->where(['name' => $wordName])->one();
                $isNewVocabulary = false;
                if (!$vocabulary) {
                    $vocabulary = new Vocabulary();
                    $vocabulary->name = $wordName;
                    $vocabulary->create_by = 0;
                    $vocabulary->create_time = $now;
                    $isNewVocabulary = true;
                }

                if ($isNewVocabulary) {
                    if ($vocabulary->status === null) {
                        $vocabulary->status = 1;
                    }
                    if ($vocabulary->weight === null) {
                        $vocabulary->weight = 0;
                    }
                    if ($vocabulary->generate_card_status === null) {
                        $vocabulary->generate_card_status = 1;
                    }
                    if ($vocabulary->generate_quiz_status === null) {
                        $vocabulary->generate_quiz_status = 1;
                    }
                }

                if (array_key_exists('translation', $entry)) {
                    $vocabulary->translation = $this->truncateString($entry['translation'] ?? null, 100);
                }
                if (array_key_exists('definition', $entry)) {
                    $vocabulary->definition = $this->truncateString($entry['definition'] ?? null, 300);
                }

                $pronunciationPayload = $this->buildPronunciationPayload($entry['pronunciation'] ?? null);
                if ($pronunciationPayload !== null) {
                    $encodedPronunciation = json_encode($pronunciationPayload, JSON_UNESCAPED_UNICODE);
                    if ($encodedPronunciation === false) {
                        throw new \RuntimeException('发音数据编码失败');
                    }
                    $vocabulary->pronunciation = $encodedPronunciation;
                    if (isset($pronunciationPayload['uk']['ipa'])) {
                        $vocabulary->uk_ipa = $this->truncateString($pronunciationPayload['uk']['ipa'], 100);
                    }
                    if (isset($pronunciationPayload['us']['ipa'])) {
                        $vocabulary->us_ipa = $this->truncateString($pronunciationPayload['us']['ipa'], 100);
                    }
                }

                $cardInfoPayload = json_encode($entry, JSON_UNESCAPED_UNICODE);
                if ($cardInfoPayload === false) {
                    throw new \RuntimeException('无法序列化单词完整数据');
                }

                $cardInfoPath = $this->uploadData($cardInfoPayload);
                if (empty($cardInfoPath)) {
                    throw new \RuntimeException('上传单词完整数据到 OSS 失败');
                }
                $vocabulary->card_info = $cardInfoPath;

                $vocabulary->update_by = 0;
                $vocabulary->update_time = $now;

                if (!$vocabulary->save()) {
                    throw new \RuntimeException('保存 vocabulary 失败: ' . json_encode($vocabulary->errors, JSON_UNESCAPED_UNICODE));
                }
                if ($isNewVocabulary) {
                    echo "新增单词: {$wordName}\n";
                } else {
                    echo "更新单词: {$wordName}\n";
                }

                $vocabularyExt = VocabularyExt::find()->where(['vocabulary_id' => $vocabulary->id])->one();
                if (!$vocabularyExt) {
                    $vocabularyExt = new VocabularyExt();
                    $vocabularyExt->vocabulary_id = $vocabulary->id;
                    $vocabularyExt->create_by = 0;
                    $vocabularyExt->create_time = $now;
                }
                $vocabularyExt->update_by = 0;
                $vocabularyExt->update_time = $now;

                if (array_key_exists('parts_of_speech', $entry)) {
                    $vocabularyExt->core_meanings = $entry['parts_of_speech'];
                }
                if (array_key_exists('examples', $entry)) {
                    $vocabularyExt->example_sentences = $entry['examples'];
                }
                if (array_key_exists('phrases', $entry)) {
                    $vocabularyExt->collocations = $entry['phrases'];
                }
                if (array_key_exists('synonyms', $entry)) {
                    $vocabularyExt->synonyms = $entry['synonyms'];
                }
                if (array_key_exists('rhyming_words', $entry)) {
                    $vocabularyExt->rhyming_words = $entry['rhyming_words'];
                }
                if (array_key_exists('nearby_words', $entry)) {
                    $vocabularyExt->nearby_words = $entry['nearby_words'];
                }
                if (array_key_exists('etymology', $entry)) {
                    $vocabularyExt->etymology = $this->truncateString($entry['etymology'], 500);
                }

                if (!$vocabularyExt->save()) {
                    throw new \RuntimeException('保存 vocabulary_ext 失败: ' . json_encode($vocabularyExt->errors, JSON_UNESCAPED_UNICODE));
                }

                $transaction->commit();
                $success++;
            } catch (\Throwable $e) {
                $transaction->rollBack();
                $failed++;
                $errors[] = "{$wordName}: {$e->getMessage()}";
                echo "处理单词失败 {$wordName}: {$e->getMessage()}\n";
            }
        }

        echo "\n===========================================\n";
        echo "成功处理: {$success} 个单词\n";
        echo "失败: {$failed} 个单词\n";
        if (!empty($errors)) {
            echo "失败详情:\n";
            foreach ($errors as $error) {
                echo "- {$error}\n";
            }
        }
        echo "===========================================\n";
    }

    /**
     * 从 JSON 文件中的 parts_of_speech 字段批量更新 vocabulary_ext.core_meanings
     * 非词组会额外尝试匹配 "to + 单词" 的词条
     * 命令示例：php yii words/update-core-meanings-from-pos "@console/runtime/tmp/selected_word_details.json" [dryRun=1]
     * 若不传文件路径，默认使用 @console/runtime/tmp/selected_word_details.json
     */
    public function actionUpdateCoreMeaningsFromPos(string $filePath = '', int $dryRun = 0): int
    {
        $targetFile = $filePath === '' ? self::DEFAULT_SELECTED_WORD_DETAILS_FILE : $filePath;
        $resolvedPath = $this->resolveFilePath($targetFile);
        if (!is_file($resolvedPath)) {
            echo "文件不存在: {$resolvedPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($resolvedPath);
        if ($raw === false) {
            echo "读取文件失败: {$resolvedPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            echo "文件内容不是有效的 JSON 数组。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $entries = [];
        $skippedNoWord = 0;
        $skippedNoParts = 0;
        $duplicateWords = 0;
        $generatedVariants = 0;
        $normalizedToVariants = 0;

        foreach ($decoded as $index => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $word = trim((string)($entry['word'] ?? ''));
            if ($word === '') {
                $skippedNoWord++;
                continue;
            }

            $partsOfSpeech = $this->normalizePartsOfSpeechPayload($entry['parts_of_speech'] ?? null);
            if ($partsOfSpeech === []) {
                $skippedNoParts++;
                continue;
            }

            $variants = $this->buildWordVariants($word);
            if (empty($variants)) {
                $skippedNoWord++;
                continue;
            }

            if ($this->stripLeadingToVariant($word) !== null) {
                $normalizedToVariants++;
            }

            foreach ($variants as $variant) {
                $lookupKey = $this->buildVocabularyLookupKey($variant);
                if ($lookupKey === null) {
                    continue;
                }

                $isAlias = $variant !== $word;
                if (isset($entries[$lookupKey])) {
                    $existingIsAlias = $entries[$lookupKey]['is_alias'] ?? false;
                    if ($existingIsAlias && !$isAlias) {
                        $entries[$lookupKey] = [
                            'word' => $variant,
                            'source_word' => $word,
                            'parts' => $partsOfSpeech,
                            'is_alias' => false,
                        ];
                        continue;
                    }

                    if (!$existingIsAlias && $isAlias) {
                        continue;
                    }

                    $duplicateWords++;
                    continue;
                }

                $entries[$lookupKey] = [
                    'word' => $variant,
                    'source_word' => $word,
                    'parts' => $partsOfSpeech,
                    'is_alias' => $isAlias,
                ];

                if ($isAlias) {
                    $generatedVariants++;
                }
            }
        }

        if (empty($entries)) {
            echo "没有可用于更新的数据。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $wordNames = array_map(function ($item) {
            return $item['word'];
        }, $entries);
        $vocabularyRows = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['name' => $wordNames])
            ->asArray()
            ->all();

        if (empty($vocabularyRows)) {
            echo "数据库中未找到任何匹配的单词。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyIdMap = [];
        foreach ($vocabularyRows as $row) {
            $key = $this->buildVocabularyLookupKey($row['name'] ?? null);
            if ($key === null) {
                continue;
            }
            $vocabularyIdMap[$key] = (int)$row['id'];
        }

        if (empty($vocabularyIdMap)) {
            echo "无法根据文件内容匹配到 vocabulary 记录。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyExtRows = VocabularyExt::find()
            ->where(['vocabulary_id' => array_values($vocabularyIdMap)])
            ->indexBy('vocabulary_id')
            ->all();

        $dryRunMode = $dryRun > 0;
        $timestamp = time();

        $processed = 0;
        $updated = 0;
        $created = 0;
        $unchanged = 0;
        $missingWords = [];
        $saveFailures = [];

        foreach ($entries as $lookupKey => $entry) {
            $processed++;
            $word = $entry['word'];
            $sourceWord = $entry['source_word'] ?? $word;
            $wordLabel = $this->formatWordVariantLabel($word, $sourceWord);
            $partsOfSpeech = $entry['parts'];

            $vocabularyId = $vocabularyIdMap[$lookupKey] ?? null;
            if ($vocabularyId === null) {
                $missingWords[] = $wordLabel;
                continue;
            }

            /** @var VocabularyExt|null $vocabularyExt */
            $vocabularyExt = $vocabularyExtRows[$vocabularyId] ?? null;
            if ($vocabularyExt === null) {
                $vocabularyExt = new VocabularyExt();
                $vocabularyExt->vocabulary_id = $vocabularyId;
                $vocabularyExt->create_by = 0;
                $vocabularyExt->create_time = $timestamp;
            }

            $desiredEncoded = $this->encodeCoreMeaningPayloadForComparison($partsOfSpeech);
            if ($desiredEncoded === null) {
                $saveFailures[] = "{$wordLabel} 无法编码为 JSON，已跳过";
                continue;
            }

            $currentPayload = $this->decodeCoreMeaningsPayload($vocabularyExt->core_meanings);
            $currentEncoded = $currentPayload === null ? null : $this->encodeCoreMeaningPayloadForComparison($currentPayload);

            if ($currentEncoded !== null && $currentEncoded === $desiredEncoded) {
                $unchanged++;
                continue;
            }

            if ($dryRunMode) {
                echo "[dry-run] {$wordLabel} (vocabulary_id={$vocabularyId}) 将更新 core_meanings\n";
                $updated++;
                if ($vocabularyExt->isNewRecord) {
                    $created++;
                }
                continue;
            }

            $isNewRecord = $vocabularyExt->isNewRecord;
            $vocabularyExt->core_meanings = $partsOfSpeech;
            $vocabularyExt->update_by = 0;
            $vocabularyExt->update_time = $timestamp;

            if ($isNewRecord) {
                $vocabularyExt->create_by = $vocabularyExt->create_by ?? 0;
                $vocabularyExt->create_time = $vocabularyExt->create_time ?? $timestamp;
            }

            if (!$vocabularyExt->save(false)) {
                $saveFailures[] = "{$wordLabel} (vocabulary_id={$vocabularyId}) 保存失败";
                continue;
            }

            $vocabularyExtRows[$vocabularyId] = $vocabularyExt;
            $updated++;
            if ($isNewRecord) {
                $created++;
            }
        }

        echo "总计解析 JSON 记录: " . count($decoded) . "\n";
        echo "可处理的单词 / 词组（含自动生成的变体）: " . count($entries) . "\n";
        if ($generatedVariants > 0) {
            echo "自动生成的 to + 单词 变体: {$generatedVariants}\n";
        }
        if ($normalizedToVariants > 0) {
            echo "去掉前缀 \"to \" 后参与匹配的单词或词组: {$normalizedToVariants}\n";
        }
        echo "匹配到 vocabulary 记录数: " . count($vocabularyIdMap) . "\n";
        echo "实际扫描单词数: {$processed}\n";
        echo "成功更新（含 dry-run 提示）: {$updated}\n";
        echo "其中新增 vocabulary_ext 记录: {$created}\n";
        echo "保持不变: {$unchanged}\n";
        if ($skippedNoWord > 0) {
            echo "跳过 {$skippedNoWord} 条缺少 word 的记录。\n";
        }
        if ($skippedNoParts > 0) {
            echo "跳过 {$skippedNoParts} 条缺少 parts_of_speech 的记录。\n";
        }
        if ($duplicateWords > 0) {
            echo "检测到 {$duplicateWords} 个重复单词条目（仅保留最后一个）。\n";
        }
        if (!empty($missingWords)) {
            echo "下列单词在 vocabulary 表中未找到：\n";
            foreach ($missingWords as $missingWord) {
                echo "- {$missingWord}\n";
            }
        }
        if (!empty($saveFailures)) {
            echo "保存失败的记录：\n";
            foreach ($saveFailures as $failure) {
                echo "- {$failure}\n";
            }
        }
        if ($dryRunMode) {
            echo "dry-run 模式未对数据库进行写入。\n";
        }

        return ExitCode::OK;
    }

    /**
     * 导出指定词书的单词扩展信息
     * php yii words/export-vocabulary-ext "195,196,197,198,199" "@runtime/tmp/vocabulary_ext_export.json"
     */
    public function actionExportVocabularyExt(string $bookIds = '195,196,197,198,199', string $output = ''): int
    {
        $parsedIds = preg_split('/[,\s]+/', $bookIds, -1, PREG_SPLIT_NO_EMPTY);
        $bookIdList = [];
        if ($parsedIds !== false) {
            foreach ($parsedIds as $id) {
                $intId = (int)trim($id);
                if ($intId > 0) {
                    $bookIdList[$intId] = $intId;
                }
            }
        }

        if (empty($bookIdList)) {
            echo "未提供有效的词书ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $relations = VocabularyUnitRelation::find()
            ->select(['book_id', 'vocabulary_id', 'order'])
            ->where(['book_id' => $bookIdList])
            ->orderBy(['book_id' => SORT_ASC, 'order' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        if (empty($relations)) {
            echo "在词书中未找到单词关联记录\n";
            return ExitCode::OK;
        }

        $bookVocabularyMap = [];
        $vocabularyIds = [];
        foreach ($relations as $relation) {
            $bookId = (int)$relation['book_id'];
            $vocabularyId = (int)$relation['vocabulary_id'];
            $order = isset($relation['order']) ? (int)$relation['order'] : PHP_INT_MAX;

            if (!isset($bookVocabularyMap[$bookId][$vocabularyId]) || $order < $bookVocabularyMap[$bookId][$vocabularyId]) {
                $bookVocabularyMap[$bookId][$vocabularyId] = $order;
            }
            $vocabularyIds[$vocabularyId] = $vocabularyId;
        }

        if (empty($vocabularyIds)) {
            echo "关联记录中未找到有效的单词ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyIdList = array_values($vocabularyIds);

        $vocabularyRows = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['id' => $vocabularyIdList])
            ->asArray()
            ->all();

        $vocabularyNames = [];
        foreach ($vocabularyRows as $row) {
            $vocabularyNames[(int)$row['id']] = $row['name'];
        }

        $extRows = VocabularyExt::find()
            ->select(['vocabulary_id', 'collocations', 'synonyms', 'nearby_words', 'rhyming_words'])
            ->where(['vocabulary_id' => $vocabularyIdList])
            ->asArray()
            ->all();

        $extMap = [];
        foreach ($extRows as $row) {
            $extMap[(int)$row['vocabulary_id']] = [
                'collocations' => $this->decodeJsonValue($row['collocations'] ?? null),
                'synonyms' => $this->decodeJsonValue($row['synonyms'] ?? null),
                'nearby_words' => $this->decodeJsonValue($row['nearby_words'] ?? null),
                'rhyming_words' => $this->decodeJsonValue($row['rhyming_words'] ?? null),
            ];
        }

        $bookNames = VocabularyBook::find()
            ->select(['id', 'name'])
            ->where(['id' => array_values($bookIdList)])
            ->asArray()
            ->all();

        $bookNameMap = [];
        foreach ($bookNames as $bookRow) {
            $bookNameMap[(int)$bookRow['id']] = $bookRow['name'];
        }

        $sortedBookIds = array_keys($bookVocabularyMap);
        sort($sortedBookIds);

        $result = [];
        $bookMissingExt = [];
        $totalWords = 0;

        foreach ($sortedBookIds as $bookId) {
            $wordOrders = $bookVocabularyMap[$bookId] ?? [];
            if (empty($wordOrders)) {
                continue;
            }

            asort($wordOrders, SORT_NUMERIC);

            $words = [];
            $missingCount = 0;

            foreach ($wordOrders as $vocabularyId => $order) {
                $wordName = $vocabularyNames[$vocabularyId] ?? null;
                if ($wordName === null) {
                    continue;
                }

                $ext = $extMap[$vocabularyId] ?? null;
                if ($ext === null) {
                    $missingCount++;
                    $ext = [
                        'collocations' => null,
                        'synonyms' => null,
                        'nearby_words' => null,
                        'rhyming_words' => null,
                    ];
                }

                /** @var array<string,mixed> $ext */
                $words[] = array_merge(
                    [
                        'vocabulary_id' => $vocabularyId,
                        'word' => $wordName,
                    ],
                    $ext
                );
            }

            $bookMissingExt[$bookId] = $missingCount;
            $totalWords += count($words);

            $result[] = [
                'book_id' => $bookId,
                'book_name' => $bookNameMap[$bookId] ?? '',
                'words' => $words,
            ];
        }

        if ($output === '') {
            $outputPath = Yii::getAlias('@runtime') . '/tmp/vocabulary_ext_export.json';
        } else {
            $outputPath = Yii::getAlias($output, false);
            if ($outputPath === false) {
                $outputPath = $output;
            }
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
            echo "无法创建目录: {$outputDir}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $encoded = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            echo "JSON 编码失败\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        if (file_put_contents($outputPath, $encoded) === false) {
            echo "写入文件失败: {$outputPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "已导出 {$totalWords} 个单词扩展信息至 {$outputPath}\n";
        foreach ($bookMissingExt as $bookId => $count) {
            $bookName = $bookNameMap[$bookId] ?? '';
            echo " - 词书 {$bookId} {$bookName} 缺少扩展信息 {$count} 条\n";
        }

        return ExitCode::OK;
    }

    /**
     * 读取导出的扩展字段并写入翻译
     * php yii words/import-vocabulary-ext-translations "@runtime/tmp/vocabulary_ext_export.json" "@runtime/tmp/单词翻译"
     */
    public function actionImportVocabularyExtTranslations(
        string $exportFile = '@runtime/tmp/vocabulary_ext_export.json',
        string $translationDir = '@runtime/tmp/单词翻译'
    ): int {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $exportPath = Yii::getAlias($exportFile);
        if (!is_file($exportPath)) {
            echo "扩展数据文件不存在: {$exportPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $translationBaseDir = Yii::getAlias($translationDir);
        if (!is_dir($translationBaseDir)) {
            echo "翻译目录不存在: {$translationBaseDir}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $rawExport = file_get_contents($exportPath);
        if ($rawExport === false) {
            echo "无法读取扩展数据文件\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $exportData = json_decode($rawExport, true);
        if (!is_array($exportData)) {
            echo "扩展数据 JSON 解析失败\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $wordExtMap = [];
        foreach ($exportData as $bookBlock) {
            if (!is_array($bookBlock) || empty($bookBlock['words']) || !is_array($bookBlock['words'])) {
                continue;
            }
            foreach ($bookBlock['words'] as $wordEntry) {
                if (!is_array($wordEntry)) {
                    continue;
                }
                $wordName = $wordEntry['word'] ?? null;
                if (!is_string($wordName) || $wordName === '') {
                    continue;
                }
                $wordExtMap[$wordName] = $wordEntry;
            }
        }

        if (empty($wordExtMap)) {
            echo "扩展数据中没有单词条目\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $wordNames = array_keys($wordExtMap);
        $vocabularies = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['name' => $wordNames])
            ->indexBy('name')
            ->all();

        if (empty($vocabularies)) {
            echo "数据库中未找到匹配的单词\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyIds = array_map(static function (Vocabulary $vocabulary): int {
            return (int)$vocabulary->id;
        }, $vocabularies);

        $extRecords = VocabularyExt::find()
            ->where(['vocabulary_id' => $vocabularyIds])
            ->indexBy('vocabulary_id')
            ->all();

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        $fields = ['collocations', 'synonyms', 'nearby_words', 'rhyming_words'];

        $updated = 0;
        $skippedNoTranslation = 0;

        try {
            foreach ($wordExtMap as $wordName => $wordData) {
                /** @var Vocabulary|null $vocabulary */
                $vocabulary = $vocabularies[$wordName] ?? null;
                if (!$vocabulary) {
                    continue;
                }

                $translationFile = $this->locateTranslationFile($translationBaseDir, $wordName);
                if ($translationFile === null) {
                    $skippedNoTranslation++;
                    continue;
                }

                $translationPayload = $this->loadJsonFile($translationFile);
                if ($translationPayload === null) {
                    $skippedNoTranslation++;
                    continue;
                }

                $translationLookup = $this->buildTranslationLookup($translationPayload);

                $fieldValues = [];
                foreach ($fields as $field) {
                    $original = $wordData[$field] ?? null;
                    $translation = $this->buildTranslationDataForField($original, $translationLookup);
                    if ($translation === null) {
                        continue;
                    }
                    $fieldValues[$field] = $translation;
                }

                if (empty($fieldValues)) {
                    continue;
                }

                $vocabularyExt = $extRecords[$vocabulary->id] ?? null;
                $isNewRecord = false;
                if (!$vocabularyExt) {
                    $vocabularyExt = new VocabularyExt();
                    $vocabularyExt->vocabulary_id = $vocabulary->id;
                    $vocabularyExt->update_by = 0;
                    $vocabularyExt->create_by = 0;
                    $extRecords[$vocabulary->id] = $vocabularyExt;
                    $isNewRecord = true;
                }

                $hasChange = false;
                foreach ($fieldValues as $field => $value) {
                    if ($vocabularyExt->$field !== $value) {
                        $vocabularyExt->$field = $value;
                        $hasChange = true;
                    }
                }

                if (!$hasChange && !$isNewRecord) {
                    continue;
                }
                $now = time();
                if ($isNewRecord) {
                    $vocabularyExt->create_time = $now;
                    $vocabularyExt->create_by = 0;
                }
                $vocabularyExt->update_time = $now;
                $vocabularyExt->update_by = 0;

                if (!$vocabularyExt->save()) {
                    throw new \RuntimeException('保存扩展数据失败: ' . json_encode($vocabularyExt->errors, JSON_UNESCAPED_UNICODE));
                }
                $updated++;
                echo "更新单词: {$vocabulary->name}\n";
                var_dump("updated number: " . $updated . "\n");
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            echo "更新失败: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "更新完成，共更新 {$updated} 个单词\n";
        echo " - 缺少翻译文件: {$skippedNoTranslation}\n";

        return ExitCode::OK;
    }

    /**
     * 读取 batch JSON 和翻译目录，更新 vocabulary_ext 的扩展字段
     * php yii words/update-vocabulary-ext-from-batch "@runtime/tmp/batch_0000_to_0005.json" "@runtime/tmp/new_words"
     */
    public function actionUpdateVocabularyExtFromBatch(
        string $batchFile = '@runtime/tmp/batch_0000_to_0005.json',
        string $translationDir = '@runtime/tmp/new_words'
    ): int {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $batchPath = Yii::getAlias($batchFile);
        if (!is_file($batchPath)) {
            echo "单词数据文件不存在: {$batchPath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $translationBaseDir = Yii::getAlias($translationDir);
        if (!is_dir($translationBaseDir)) {
            echo "翻译目录不存在: {$translationBaseDir}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($batchPath);
        if ($raw === false) {
            echo "无法读取单词数据文件\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            echo "单词数据不是有效的 JSON 数组\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $wordEntries = [];
        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $wordName = isset($entry['word']) ? trim((string)$entry['word']) : '';
            if ($wordName === '') {
                continue;
            }
            $wordEntries[$wordName] = $entry;
        }

        if ($wordEntries === []) {
            echo "单词数据中没有有效条目\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularies = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['name' => array_keys($wordEntries)])
            ->indexBy('name')
            ->all();

        if ($vocabularies === []) {
            echo "数据库中未找到这些单词\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyIds = array_map(static function (Vocabulary $vocabulary): int {
            return (int)$vocabulary->id;
        }, $vocabularies);

        $extRecords = VocabularyExt::find()
            ->where(['vocabulary_id' => $vocabularyIds])
            ->indexBy('vocabulary_id')
            ->all();

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        $updated = 0;
        $missingTranslationFile = 0;
        $invalidTranslationFile = 0;

        $fieldSourceMap = [
            'collocations' => 'phrases',
            'synonyms' => 'synonyms',
            'nearby_words' => 'nearby_words',
            'rhyming_words' => 'rhyming_words',
        ];

        try {
            foreach ($wordEntries as $wordName => $entry) {
                /** @var Vocabulary|null $vocabulary */
                $vocabulary = $vocabularies[$wordName] ?? null;
                if ($vocabulary === null) {
                    continue;
                }

                $translationLookup = [];
                $translationFile = $this->locateTranslationFile($translationBaseDir, $wordName);
                if ($translationFile === null) {
                    $missingTranslationFile++;
                } else {
                    $translationPayload = $this->loadJsonFile($translationFile);
                    if ($translationPayload === null) {
                        $invalidTranslationFile++;
                    } else {
                        $translationLookup = $this->buildTranslationLookup($translationPayload);
                    }
                }

                $fieldValues = [];
                foreach ($fieldSourceMap as $targetField => $sourceField) {
                    $translated = $this->buildTranslationDataForField($entry[$sourceField] ?? null, $translationLookup);
                    if ($translated !== null) {
                        $fieldValues[$targetField] = $translated;
                    }
                }

                if (array_key_exists('etymology', $entry)) {
                    $etymology = $this->truncateString($entry['etymology'], 500);
                    if ($etymology !== null) {
                        $fieldValues['etymology'] = $etymology;
                    }
                }

                if ($fieldValues === []) {
                    continue;
                }

                $vocabularyExt = $extRecords[$vocabulary->id] ?? null;
                $isNewRecord = false;
                if ($vocabularyExt === null) {
                    $vocabularyExt = new VocabularyExt();
                    $vocabularyExt->vocabulary_id = $vocabulary->id;
                    $extRecords[$vocabulary->id] = $vocabularyExt;
                    $isNewRecord = true;
                }

                $hasChange = false;
                foreach ($fieldValues as $field => $value) {
                    if ($vocabularyExt->$field !== $value) {
                        $vocabularyExt->$field = $value;
                        $hasChange = true;
                    }
                }

                if (!$hasChange && !$isNewRecord) {
                    continue;
                }

                $now = time();
                if ($isNewRecord) {
                    $vocabularyExt->create_time = $now;
                    $vocabularyExt->create_by = 0;
                }
                $vocabularyExt->update_time = $now;
                $vocabularyExt->update_by = 0;

                if (!$vocabularyExt->save()) {
                    throw new \RuntimeException('保存扩展信息失败: ' . json_encode($vocabularyExt->errors, JSON_UNESCAPED_UNICODE));
                }

                $updated++;
                echo "更新单词: {$wordName}\n";
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            echo "更新失败: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        echo "更新完成，共更新 {$updated} 个单词\n";
        echo " - 缺少翻译文件: {$missingTranslationFile}\n";
        echo " - 解析失败的翻译文件: {$invalidTranslationFile}\n";

        return ExitCode::OK;
    }

    /**
     * 修复拼接题多词空格，还原 quiz_answer / quiz_translation
     * php yii words/fix-spelling-quiz-spaces "195,196,197,198,199" 0.8
     */
    public function actionFixSpellingQuizSpaces(string $bookIds = '195,196,197,198,199', float $threshold = 0.8): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $parsedIds = preg_split('/[,\s]+/', $bookIds, -1, PREG_SPLIT_NO_EMPTY);
        $bookIdList = [];
        if ($parsedIds !== false) {
            foreach ($parsedIds as $id) {
                $intId = (int)trim($id);
                if ($intId > 0) {
                    $bookIdList[$intId] = $intId;
                }
            }
        }

        if (empty($bookIdList)) {
            echo "未提供有效的词书ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $thresholdScore = (int)round($threshold * 100);
        $thresholdScore = max(0, min(100, $thresholdScore));

        $vocabularyIds = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['book_id' => array_values($bookIdList)])
            ->column();

        if (empty($vocabularyIds)) {
            echo "指定词书下没有关联的单词\n";
            return ExitCode::OK;
        }

        $vocabularies = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['id' => $vocabularyIds])
            ->andWhere(['like', 'name', ' '])
            ->indexBy('id')
            ->all();

        if (empty($vocabularies)) {
            echo "没有找到名称包含空格的单词\n";
            return ExitCode::OK;
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($vocabularies as $vocabulary) {
            // if (in_array($vocabulary->id, [57888, 57210, 57209, 57833, 57830, 56780, 56383, 40428, 42821, 39019, 42941])) {
            //     continue;
            // }
            $wordParts = $this->splitWordParts($vocabulary->name);
            if (count($wordParts) < 2) {
                continue;
            }

            $quizzes = VocabularyQuiz::find()
                ->where([
                    'quiz_type' => 2,
                    'vocabulary_id' => $vocabulary->id,
                ])
                ->all();

            if (empty($quizzes)) {
                continue;
            }

            foreach ($quizzes as $quiz) {
                $processed++;

                $questionChunks = $this->normalizeChunkStructure($quiz->quiz_question);
                $questionChunkStrings = $this->extractChunkStrings($questionChunks);
                if (empty($questionChunkStrings)) {
                    $skipped++;
                    continue;
                }

                $questionChunksWithSpaces = $this->buildChunksWithSpaces($questionChunks, $wordParts, $thresholdScore);
                $encodedQuestionChunks = json_encode($questionChunksWithSpaces, JSON_UNESCAPED_UNICODE);
                if ($encodedQuestionChunks === false) {
                    $failed++;
                    echo "  无法编码题干 JSON，quiz {$quiz->id}\n";
                    continue;
                }
                $questionAnswer = $this->extractChunkStrings($questionChunksWithSpaces, false, true);
                $questionAnswer = json_encode($questionAnswer, JSON_UNESCAPED_UNICODE);

                $optionChunks = $this->normalizeChunkStructure($quiz->quiz_options);
                $optionChunksWithSpaces = $optionChunks === []
                    ? []
                    : $this->buildChunksWithSpaces($optionChunks, $wordParts, $thresholdScore, true);

                $encodedTranslation = json_encode($optionChunksWithSpaces, JSON_UNESCAPED_UNICODE);

                if ($encodedTranslation === false) {
                    $failed++;
                    echo "  无法编码干扰项 JSON，quiz {$quiz->id}\n";
                    continue;
                }

                $shouldSave = false;
                if ($quiz->quiz_question !== $encodedQuestionChunks) {
                    $quiz->quiz_question = json_encode($questionChunks, JSON_UNESCAPED_UNICODE);
                    $shouldSave = true;
                }
                if ($questionAnswer !== '' && $quiz->quiz_answer !== $questionAnswer) {
                    $quiz->quiz_answer = $questionAnswer;
                    $shouldSave = true;
                }

                if ($quiz->quiz_translation !== $encodedTranslation) {
                    $quiz->quiz_translation = $encodedTranslation;
                    $shouldSave = true;
                }

                if (!$shouldSave) {
                    continue;
                }

                $quiz->update_by = 0;
                $quiz->update_time = time();

                if (!$quiz->save(false, ['quiz_question', 'quiz_answer', 'quiz_translation', 'update_by', 'update_time'])) {
                    $failed++;
                    echo "  保存考题 {$quiz->id} 失败: " . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE) . "\n";
                    continue;
                }

                $updated++;
                echo "更新考题 {$quiz->id} ({$vocabulary->name})\n";
            }
        }

        echo "处理完成：扫描考题 {$processed} 条，更新 {$updated} 条，跳过 {$skipped} 条，失败 {$failed} 条\n";

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 去除拼接题 chunk 值内的空格（仅处理包含空格的单词）
     * php yii words/strip-quiz-chunk-spaces "195,196,197,198,199"
     */
    public function actionStripQuizChunkSpaces(string $bookIds = '195,196,197,198,199'): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $bookIdList = $this->parseBookIdList($bookIds);
        if ($bookIdList === []) {
            echo "未提供有效的词书ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyIds = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['book_id' => $bookIdList])
            ->column();

        if (empty($vocabularyIds)) {
            echo "指定词书下没有关联的单词\n";
            return ExitCode::OK;
        }

        $vocabularies = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['id' => $vocabularyIds])
            ->andWhere(['like', 'name', ' '])
            ->indexBy('id')
            ->all();

        if (empty($vocabularies)) {
            echo "这些词书中没有包含空格的单词\n";
            return ExitCode::OK;
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($vocabularies as $vocabulary) {
            $quizzes = VocabularyQuiz::find()
                ->where([
                    'vocabulary_id' => $vocabulary->id,
                    'quiz_type' => 2,
                ])
                ->all();

            if (!$quizzes) {
                continue;
            }

            foreach ($quizzes as $quiz) {
                $processed++;

                $questionChunks = $this->decodeChunkArray($quiz->quiz_question);
                $optionChunks = $this->decodeChunkArray($quiz->quiz_options);

                if ($questionChunks === null && $optionChunks === null) {
                    $skipped++;
                    continue;
                }

                $shouldSave = false;

                if ($questionChunks !== null) {
                    $cleanQuestionChunks = $this->stripSpacesFromChunkArray($questionChunks);
                    if ($cleanQuestionChunks !== $questionChunks) {
                        $encodedQuestion = json_encode($cleanQuestionChunks, JSON_UNESCAPED_UNICODE);
                        if ($encodedQuestion === false) {
                            $failed++;
                            echo "  无法编码题干 JSON，quiz {$quiz->id}\n";
                            continue;
                        }
                        $quiz->quiz_question = $encodedQuestion;
                        $shouldSave = true;
                    }
                }

                if ($optionChunks !== null) {
                    $cleanOptionChunks = $this->stripSpacesFromChunkArray($optionChunks);
                    if ($cleanOptionChunks !== $optionChunks) {
                        $encodedOptions = json_encode($cleanOptionChunks, JSON_UNESCAPED_UNICODE);
                        if ($encodedOptions === false) {
                            $failed++;
                            echo "  无法编码干扰项 JSON，quiz {$quiz->id}\n";
                            continue;
                        }
                        $quiz->quiz_options = $cleanOptionChunks;
                        $shouldSave = true;
                    }
                }

                if (!$shouldSave) {
                    $skipped++;
                    continue;
                }

                $quiz->update_by = 0;
                $quiz->update_time = time();

                if (!$quiz->save(false, ['quiz_question', 'quiz_options', 'update_by', 'update_time'])) {
                    $failed++;
                    echo "  保存考题 {$quiz->id} 失败: " . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE) . "\n";
                    continue;
                }

                $updated++;
                echo "更新考题 {$quiz->id} ({$vocabulary->name})\n";
            }
        }

        echo "处理完成：扫描考题 {$processed} 条，更新 {$updated} 条，跳过 {$skipped} 条，失败 {$failed} 条\n";

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 移除拼接题中与题干/答案重复的选项/翻译
     * php yii words/clean-quiz-chunk-duplicates "195,196,197,198,199"
     */
    public function actionCleanQuizChunkDuplicates(string $bookIds = '195,196,197,198,199'): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $bookIdList = $this->parseBookIdList($bookIds);
        if ($bookIdList === []) {
            echo "未提供有效的词书ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $vocabularyIds = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['book_id' => $bookIdList])
            ->column();

        if (empty($vocabularyIds)) {
            echo "指定词书下没有关联的单词\n";
            return ExitCode::OK;
        }

        $vocabularies = Vocabulary::find()
            ->select(['id', 'name'])
            ->where(['id' => $vocabularyIds])
            ->andWhere(['like', 'name', ' '])
            ->indexBy('id')
            ->all();

        if (empty($vocabularies)) {
            echo "这些词书中没有包含空格的单词\n";
            return ExitCode::OK;
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($vocabularies as $vocabulary) {
            $quizzes = VocabularyQuiz::find()
                ->where([
                    'vocabulary_id' => $vocabulary->id,
                    'quiz_type' => 2,
                ])
                ->all();

            if (!$quizzes) {
                continue;
            }

            foreach ($quizzes as $quiz) {
                $processed++;

                $questionValues = $this->buildChunkValueSetFromJson($quiz->quiz_question);
                $answerValues = $this->buildChunkValueSetFromJson($quiz->quiz_answer);
                $optionChunks = $this->decodeChunkArray($quiz->quiz_options);
                $translationChunks = $this->decodeChunkArray($quiz->quiz_translation);

                if ($questionValues === [] && $answerValues === []) {
                    $skipped++;
                    continue;
                }

                $optionsChanged = false;
                $translationChanged = false;

                if ($optionChunks !== null && $questionValues !== []) {
                    $cleanOptions = $this->filterChunkArrayValues($optionChunks, $questionValues, true);
                    if ($cleanOptions !== $optionChunks) {
                        $encoded = json_encode($cleanOptions, JSON_UNESCAPED_UNICODE);
                        if ($encoded === false) {
                            $failed++;
                            echo "  无法编码干扰项 JSON，quiz {$quiz->id}\n";
                            continue;
                        }
                        $quiz->quiz_options = $cleanOptions;
                        $optionsChanged = true;
                    }
                }

                if ($translationChunks !== null && $answerValues !== []) {
                    $cleanTranslation = $this->filterChunkArrayValues($translationChunks, $answerValues, true);
                    if ($cleanTranslation !== $translationChunks) {
                        $encoded = json_encode($cleanTranslation, JSON_UNESCAPED_UNICODE);
                        if ($encoded === false) {
                            $failed++;
                            echo "  无法编码翻译 JSON，quiz {$quiz->id}\n";
                            continue;
                        }
                        $quiz->quiz_translation = $encoded;
                        $translationChanged = true;
                    }
                }

                if (!$optionsChanged && !$translationChanged) {
                    $skipped++;
                    continue;
                }

                $fields = ['update_by', 'update_time'];
                if ($optionsChanged) {
                    $fields[] = 'quiz_options';
                }
                if ($translationChanged) {
                    $fields[] = 'quiz_translation';
                }

                $quiz->update_by = 0;
                $quiz->update_time = time();

                // var_dump($quiz);
                // die;

                if (!$quiz->save(false, $fields)) {
                    $failed++;
                    echo "  保存考题 {$quiz->id} 失败: " . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE) . "\n";
                    continue;
                }

                $updated++;
                echo "更新考题 {$quiz->id} ({$vocabulary->name})\n";
            }
        }

        echo "处理完成：扫描考题 {$processed} 条，更新 {$updated} 条，跳过 {$skipped} 条，失败 {$failed} 条\n";

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 将 quiz_type=1 的 quiz_answer 修复为正确选项 ID（限定 book_id >= $minBookId）
     * php yii words/fix-quiz-answer-from-options 195
     */
    public function actionFixQuizAnswerFromOptions(int $minBookId = 195): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        if ($minBookId <= 0) {
            echo "请输入有效的最小词书ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $bookVocabularyQuery = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['>=', 'book_id', $minBookId]);

        if (!(clone $bookVocabularyQuery)->exists()) {
            echo "没有找到 book_id >= {$minBookId} 的关联单词\n";
            return ExitCode::OK;
        }

        $quizQuery = VocabularyQuiz::find()
            ->where(['quiz_type' => 1])
            ->andWhere(['vocabulary_id' => $bookVocabularyQuery])
            ->orderBy('id');

        return $this->fixQuizType1AnswersFromOptionsQuery($quizQuery, "book_id >= {$minBookId}");
    }

    /**
     * 修复指定词书的 quiz_type=1 答案
     * php yii words/fix-quiz-type1-answers-for-book 200
     */
    public function actionFixQuizType1AnswersForBook(int $bookId = 200): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        if ($bookId <= 0) {
            echo "请输入有效的词书ID\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $bookVocabularyQuery = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['book_id' => $bookId]);

        if (!(clone $bookVocabularyQuery)->exists()) {
            echo "词书 {$bookId} 没有关联单词\n";
            return ExitCode::OK;
        }

        $quizQuery = VocabularyQuiz::find()
            ->where(['quiz_type' => 1])
            ->andWhere(['vocabulary_id' => $bookVocabularyQuery])
            ->orderBy('id');

        return $this->fixQuizType1AnswersFromOptionsQuery($quizQuery, "book_id = {$bookId}");
    }

    /**
     * @param \yii\db\ActiveQuery|\app\models\VocabularyQuizQuery $quizQuery
     */
    private function fixQuizType1AnswersFromOptionsQuery($quizQuery, string $contextLabel): int
    {
        $totalQuizzes = (int)(clone $quizQuery)->count();
        if ($totalQuizzes === 0) {
            echo "{$contextLabel} 符合条件的考题为空\n";
            return ExitCode::OK;
        }
        echo "{$contextLabel} 待处理考题：{$totalQuizzes} 条\n";

        $processed = 0;
        $updated = 0;
        $missingCorrect = 0;
        $multipleCorrect = 0;
        $failed = 0;

        foreach ($quizQuery->batch(200) as $quizzes) {
            foreach ($quizzes as $quiz) {
                $processed++;

                $correctOptionIds = VocabularyQuizOption::find()
                    ->select('id')
                    ->where([
                        'quiz_id' => $quiz->id,
                        'is_correct' => 1,
                    ])
                    ->orderBy('id')
                    ->column();

                if (empty($correctOptionIds)) {
                    $missingCorrect++;
                    echo "  quiz {$quiz->id} 缺少正确选项\n";
                    continue;
                }

                if (count($correctOptionIds) > 1) {
                    $multipleCorrect++;
                    echo "  quiz {$quiz->id} 存在多个正确选项: " . implode(',', $correctOptionIds) . "\n";
                    continue;
                }

                $correctAnswer = (string)$correctOptionIds[0];
                if ((string)$quiz->quiz_answer === $correctAnswer) {
                    continue;
                }

                $quiz->quiz_answer = $correctAnswer;
                $quiz->update_by = 0;
                $quiz->update_time = time();

                if (!$quiz->save(false, ['quiz_answer', 'update_by', 'update_time'])) {
                    $failed++;
                    echo "  保存考题 {$quiz->id} 失败: " . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE) . "\n";
                    continue;
                }

                $updated++;
                echo "更新考题 {$quiz->id}\n";
            }
        }

        echo "{$contextLabel} 处理完成：扫描考题 {$processed} 条，更新 {$updated} 条，缺少正确选项 {$missingCorrect} 条，多个正确选项 {$multipleCorrect} 条，失败 {$failed} 条\n";

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 根据 core_meanings 对比结果批量修复 quiz_type=1 的正确选项
     * 仅处理 suggested_option.score 为 1.0 的记录
     * php yii words/fix-quiz-type1-option-correctness @console/runtime/tmp/new_quiz_type1_option_mismatches.json false
     */
    public function actionFixQuizType1OptionCorrectness(string $inputFile = '@console/runtime/tmp/new_quiz_type1_option_mismatches.json', bool $dryRun = true): int
    {
        $filePath = Yii::getAlias($inputFile);
        if (!is_file($filePath)) {
            echo "未找到文件：{$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            echo "无法读取文件：{$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            echo "文件内容不是有效的 JSON 数组：{$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($payload as $entry) {
            $processed++;

            $suggested = $entry['suggested_option'] ?? null;
            $flagged = $entry['flagged_correct_option'] ?? null;

            if (!is_array($suggested) || !is_array($flagged)) {
                $skipped++;
                continue;
            }

            $score = isset($suggested['score']) ? (float)$suggested['score'] : null;
            if ($score === null || $score < 0.999) {
                $skipped++;
                continue;
            }

            $quizId = (int)($entry['quiz_id'] ?? 0);
            $correctOptionId = (int)($suggested['id'] ?? 0);
            $incorrectOptionId = (int)($flagged['id'] ?? 0);

            if ($quizId <= 0 || $correctOptionId <= 0 || $incorrectOptionId <= 0) {
                $failed++;
                echo "  记录缺少必要字段，quiz_id={$quizId}\n";
                continue;
            }

            /** @var VocabularyQuizOption|null $correctOption */
            $correctOption = VocabularyQuizOption::findOne($correctOptionId);
            /** @var VocabularyQuizOption|null $incorrectOption */
            $incorrectOption = VocabularyQuizOption::findOne($incorrectOptionId);
            /** @var VocabularyQuiz|null $quiz */
            $quiz = VocabularyQuiz::findOne($quizId);

            if ($correctOption === null || $incorrectOption === null || $quiz === null) {
                $failed++;
                echo "  quiz {$quizId} 缺少必要数据（选项或考题不存在）\n";
                continue;
            }

            if ((int)$correctOption->quiz_id !== $quizId || (int)$incorrectOption->quiz_id !== $quizId) {
                $failed++;
                echo "  quiz {$quizId} 的选项与记录不匹配\n";
                continue;
            }

            $needsChange = false;
            if ((int)$correctOption->is_correct !== 1) {
                $needsChange = true;
            }
            if ((int)$incorrectOption->is_correct !== 2) {
                $needsChange = true;
            }
            if ((string)$quiz->quiz_answer !== (string)$correctOptionId) {
                $needsChange = true;
            }

            if (!$needsChange) {
                $skipped++;
                continue;
            }

            $now = time();
            // if ($dryRun) {
            //     $updated++;
            //     echo "[dry-run] quiz {$quizId} 将调整正确选项为 {$correctOptionId}（原 {$incorrectOptionId}）\n";
            //     continue;
            // }

            $transaction = Yii::$app->db->beginTransaction();
            try {
                if ((int)$incorrectOption->is_correct !== 2) {
                    $incorrectOption->is_correct = 2;
                    $incorrectOption->update_by = 0;
                    $incorrectOption->update_time = $now;
                    if (!$incorrectOption->save(false, ['is_correct', 'update_by', 'update_time'])) {
                        throw new \RuntimeException('保存错误选项失败：' . json_encode($incorrectOption->errors, JSON_UNESCAPED_UNICODE));
                    }
                }

                if ((int)$correctOption->is_correct !== 1) {
                    $correctOption->is_correct = 1;
                    $correctOption->update_by = 0;
                    $correctOption->update_time = $now;
                    if (!$correctOption->save(false, ['is_correct', 'update_by', 'update_time'])) {
                        throw new \RuntimeException('保存正确选项失败：' . json_encode($correctOption->errors, JSON_UNESCAPED_UNICODE));
                    }
                }

                if ((string)$quiz->quiz_answer !== (string)$correctOptionId) {
                    $quiz->quiz_answer = (string)$correctOptionId;
                    $quiz->update_by = 0;
                    $quiz->update_time = $now;
                    if (!$quiz->save(false, ['quiz_answer', 'update_by', 'update_time'])) {
                        throw new \RuntimeException('更新考题失败：' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
                    }
                }

                $transaction->commit();
                $updated++;
                echo "修正考题 {$quizId}：正确选项 => {$correctOptionId}\n";
            } catch (Throwable $throwable) {
                $transaction->rollBack();
                $failed++;
                echo "  更新考题 {$quizId} 失败：" . $throwable->getMessage() . "\n";
            }
        }

        echo sprintf("扫描 %d 条记录，修正 %d 条，跳过 %d 条，失败 %d 条。\n", $processed, $updated, $skipped, $failed);
        if ($dryRun) {
            echo "dry-run 模式未对数据库做出修改。\n";
        }

        return $failed > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * 根据 JSON 文件提供的映射批量更新 vocabulary_book_unit 的名称
     * JSON 格式：{ "English Name": "中文名", ... }
     * php yii words/update-unit-names-from-file @console/runtime/tmp/translations.json false
     */
    public function actionUpdateUnitNamesFromFile(string $inputFile = '@console/runtime/tmp/translations.json', bool $dryRun = true): int
    {
        $filePath = Yii::getAlias($inputFile);
        if (!is_file($filePath)) {
            echo "未找到文件：{$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            echo "无法读取文件：{$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            echo "文件内容不是有效的 JSON：{$filePath}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $map = [];
        foreach ($decoded as $english => $chinese) {
            $englishName = trim((string)$english);
            $chineseName = trim((string)$chinese);
            if ($englishName === '' || $chineseName === '') {
                continue;
            }
            $map[$englishName] = $chineseName;
        }


        if ($map === []) {
            echo "映射列表为空，未执行任何操作。\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $units = VocabularyBookUnit::find()
            ->where(['name' => array_keys($map), 'book_id' => [195, 196, 197, 198, 199]])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if ($units === []) {
            echo "未在 vocabulary_book_unit 中找到匹配的英文名称。\n";
            return ExitCode::OK;
        }

        $processed = 0;
        $updated = 0;
        foreach ($units as $unit) {
            $processed++;
            $oldName = (string)$unit->name;
            $newName = $map[$oldName] ?? null;
            if ($newName === null || $newName === '' || $oldName === $newName) {
                continue;
            }

            // if ($dryRun) {
            //     echo sprintf("[dry-run] #%d book:%d %s -> %s\n", $unit->id, $unit->book_id, $oldName, $newName);
            //     $updated++;
            //     continue;
            // }

            $unit->name = $newName;
            $unit->update_time = time();
            if ($unit->save(false, ['name', 'update_time'])) {
                echo sprintf("已更新 #%d book:%d %s -> %s\n", $unit->id, $unit->book_id, $oldName, $newName);
                $updated++;
            } else {
                echo sprintf("更新失败 #%d book:%d %s -> %s: %s\n", $unit->id, $unit->book_id, $oldName, $newName, json_encode($unit->errors, JSON_UNESCAPED_UNICODE));
                return ExitCode::UNSPECIFIED_ERROR;
            }
        }

        echo sprintf("匹配 %d 条记录，实际处理 %d 条（dryRun=%s）。\n", count($map), $updated, $dryRun ? 'true' : 'false');
        if ($processed !== count($map)) {
            echo sprintf("注意：仅找到 %d 个 vocabulary_book_unit 记录，部分英文名称可能不存在于数据库。\n", $processed);
        }

        return ExitCode::OK;
    }

    /**
     * 定位单词翻译文件
     */
    private function locateTranslationFile(string $baseDir, string $word): ?string
    {
        $sanitized = str_replace(['/', '\\'], '_', $word);
        $variants = array_unique([
            $word,
            $sanitized,
            str_replace(' ', '_', $word),
            str_replace(' ', '_', $sanitized),
        ]);

        $candidates = [];
        foreach ($variants as $variant) {
            $candidates[] = $baseDir . '/' . $variant . '.json';
            $candidates[] = $baseDir . '/' . $variant . '.JSON';
            $candidates[] = $baseDir . '/' . $variant;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function loadJsonFile(string $filePath): ?array
    {
        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * 根据原始英文列表构建 [{original, translated}] 结构
     *
     * @param array<int|string,mixed>|scalar|null $original
     * @param array<string,string|null> $lookup
     * @return array<int,array{original:string,translated:?string}>|array{original:string,translated:?string}|null
     */
    private function buildTranslationDataForField($original, array $lookup)
    {
        if ($original === null) {
            return null;
        }

        if (is_array($original)) {
            if ($original === []) {
                return null;
            }

            $result = [];
            foreach ($original as $value) {
                $pair = $this->buildTranslationPair($value, $lookup);
                if ($pair !== null) {
                    $result[] = $pair;
                }
            }

            return $result === [] ? null : $result;
        }

        return $this->buildTranslationPair($original, $lookup);
    }

    /**
     * @param mixed $value
     * @param array<string,string|null> $lookup
     * @return array{original:string,translated:?string}|null
     */
    private function buildTranslationPair($value, array $lookup): ?array
    {
        if (is_array($value) || is_object($value)) {
            $encodedOriginal = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($encodedOriginal === false) {
                return null;
            }
            $key = $this->normalizeTranslationKey($encodedOriginal);
            if ($key === '') {
                return null;
            }

            return [
                'original' => $encodedOriginal,
                'translated' => $lookup[$key] ?? null,
            ];
        }

        if (!is_scalar($value)) {
            return null;
        }

        $originalString = trim((string)$value);
        if ($originalString === '') {
            return null;
        }

        $key = $this->normalizeTranslationKey($value);
        if ($key === '') {
            return null;
        }

        return [
            'original' => $originalString,
            'translated' => $lookup[$key] ?? null,
        ];
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $payload
     * @return array<string,string|null>
     */
    private function buildTranslationLookup(array $payload): array
    {
        $lookup = [];
        $this->collectTranslationPairs($payload, $lookup);
        return $lookup;
    }

    /**
     * @param mixed $node
     * @param array<string,string|null> $lookup
     */
    private function collectTranslationPairs($node, array &$lookup): void
    {
        if (!is_array($node)) {
            return;
        }

        $isAssoc = $this->isAssocArray($node);
        if ($isAssoc && array_key_exists('original', $node) && array_key_exists('translated', $node)) {
            $key = $this->normalizeTranslationKey($node['original']);
            if ($key !== '') {
                $lookup[$key] = $this->stringifyTranslationValue($node['translated']);
            }
        }

        foreach ($node as $value) {
            $this->collectTranslationPairs($value, $lookup);
        }
    }

    private function normalizeTranslationKey($value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function stringifyTranslationValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $string = (string)$value;
            return $string === '' ? null : $string;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    private function isAssocArray(array $array): bool
    {
        if ($array === []) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * 尝试将JSON字符串解码为数组
     *
     * @param mixed $value
     * @return mixed
     */
    private function decodeJsonValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        $firstChar = $trimmed[0];
        if ($firstChar !== '{' && $firstChar !== '[') {
            return $value;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    /**
     * @return array<int,int>
     */
    private function parseBookIdList(string $bookIds): array
    {
        $parsedIds = preg_split('/[,\s]+/', $bookIds, -1, PREG_SPLIT_NO_EMPTY);
        if ($parsedIds === false) {
            return [];
        }

        $result = [];
        foreach ($parsedIds as $id) {
            $intId = (int)trim($id);
            if ($intId > 0) {
                $result[$intId] = $intId;
            }
        }

        return array_values($result);
    }

    /**
     * @param mixed $value
     * @return array<int|string,mixed>|null
     */
    private function decodeChunkArray($value): ?array
    {
        $decoded = $this->decodeJsonValue($value);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<int|string,mixed> $payload
     * @return array<int|string,mixed>
     */
    private function stripSpacesFromChunkArray(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->stripSpacesFromChunkArray($value);
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $normalized = preg_replace('/\s+/u', '', (string)$value);
                if ($normalized === null) {
                    $normalized = preg_replace('/\s+/', '', (string)$value);
                }
                $payload[$key] = $normalized === null ? '' : $normalized;
            }
        }

        return $payload;
    }

    /**
     * @param mixed $value
     * @return array<string,bool>
     */
    private function buildChunkValueSetFromJson($value, bool $preferContentField = false): array
    {
        $decoded = $this->decodeJsonValue($value);

        if (is_array($decoded)) {
            return $this->buildChunkValueSetFromArray($decoded, $preferContentField);
        }

        if (is_string($decoded) || is_numeric($decoded)) {
            $trimmed = trim((string)$decoded);
            return $trimmed === '' ? [] : [$trimmed => true];
        }

        return [];
    }

    /**
     * @param array<int|string,mixed> $chunks
     * @return array<string,bool>
     */
    private function buildChunkValueSetFromArray(array $chunks, bool $preferContentField = false): array
    {
        $result = [];

        if ($chunks === []) {
            return $result;
        }

        if ($this->isAssocArray($chunks)) {
            $value = $this->chunkToComparableString($chunks, $preferContentField);
            if ($value !== null) {
                $result[$value] = true;
            }
            return $result;
        }

        foreach ($chunks as $chunk) {
            $value = $this->chunkToComparableString($chunk, $preferContentField);
            if ($value !== null) {
                $result[$value] = true;
            }
        }

        return $result;
    }

    /**
     * @param mixed $chunk
     */
    private function chunkToComparableString($chunk, bool $preferContentField = false): ?string
    {
        if (is_string($chunk) || is_numeric($chunk)) {
            $trimmed = trim((string)$chunk);
            return $trimmed === '' ? null : $trimmed;
        }

        if (!is_array($chunk)) {
            return null;
        }

        if (
            $preferContentField
            && isset($chunk['content'])
            && (is_string($chunk['content']) || is_numeric($chunk['content']))
        ) {
            $trimmed = trim((string)$chunk['content']);
            return $trimmed === '' ? null : $trimmed;
        }

        if ($this->isAssocArray($chunk)) {
            foreach ($chunk as $value) {
                $candidate = $this->chunkToComparableString($value, $preferContentField);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
            return null;
        }

        if (count($chunk) === 1) {
            return $this->chunkToComparableString(reset($chunk), $preferContentField);
        }

        return null;
    }

    /**
     * @param array<int|string,mixed> $chunks
     * @param array<string,bool> $valueSet
     * @return array<int|string,mixed>
     */
    private function filterChunkArrayValues(array $chunks, array $valueSet, bool $preferContentField = false): array
    {
        if ($chunks === []) {
            return [];
        }

        if ($this->isAssocArray($chunks)) {
            $result = [];
            foreach ($chunks as $key => $value) {
                if (is_array($value)) {
                    $result[$key] = $this->filterChunkArrayValues($value, $valueSet, $preferContentField);
                } else {
                    $result[$key] = $value;
                }
            }
            return $result;
        }

        $result = [];
        foreach ($chunks as $chunk) {
            $chunkValue = $this->chunkToComparableString($chunk, $preferContentField);
            if ($chunkValue !== null && isset($valueSet[$chunkValue])) {
                continue;
            }

            if (is_array($chunk)) {
                $chunk = $this->filterChunkArrayValues($chunk, $valueSet, $preferContentField);
            }

            $result[] = $chunk;
        }

        return $result;
    }

    /**
     * @param mixed $value
     * @return array<int|string,mixed>
     */
    private function normalizeChunkStructure($value): array
    {
        $decoded = $this->decodeJsonValue($value);

        if ($decoded === null) {
            return [];
        }

        if (is_array($decoded)) {
            foreach ($decoded as &$item) {
                //把空格过滤掉
                $item = trim((string)$item);
            }
            return $decoded;
        }

        if (is_string($decoded) || is_numeric($decoded)) {
            $trimmed = trim((string)$decoded);
            return $trimmed === '' ? [] : [$trimmed];
        }


        return [];
    }

    /**
     * @param mixed $value
     * @param bool $preferContentField
     * @param bool $preserveSpacing
     * @return array<int,string>
     */
    private function extractChunkStrings($value, bool $preferContentField = false, bool $preserveSpacing = false): array
    {
        $decoded = $this->decodeJsonValue($value);

        if ($decoded === null) {
            return [];
        }

        if (is_string($decoded) || is_numeric($decoded)) {
            $single = $this->prepareChunkString((string)$decoded, $preserveSpacing);
            return $single === null ? [] : [$single];
        }

        if (!is_array($decoded)) {
            return [];
        }

        $result = [];

        if ($this->isAssocArray($decoded)) {
            $single = $this->extractPreferredContent($decoded, $preferContentField, $preserveSpacing);
            if ($single !== null) {
                $result[] = $single;
            }
            return $result;
        }

        foreach ($decoded as $item) {
            if (is_string($item) || is_numeric($item)) {
                $normalized = $this->prepareChunkString((string)$item, $preserveSpacing);
                if ($normalized !== null) {
                    $result[] = $normalized;
                }
                continue;
            }

            if (is_array($item)) {
                $single = $this->extractPreferredContent($item, $preferContentField, $preserveSpacing);
                if ($single !== null) {
                    $result[] = $single;
                }
            }
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractPreferredContent(array $payload, bool $preferContentField, bool $preserveSpacing = false): ?string
    {
        if (
            $preferContentField
            && isset($payload['content'])
            && (is_string($payload['content']) || is_numeric($payload['content']))
        ) {
            return $this->prepareChunkString((string)$payload['content'], $preserveSpacing);
        }

        foreach ($payload as $value) {
            if (is_string($value) || is_numeric($value)) {
                $normalized = $this->prepareChunkString((string)$value, $preserveSpacing);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        return null;
    }

    private function prepareChunkString(string $value, bool $preserveSpacing): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if ($preserveSpacing) {
            return $value;
        }

        return $trimmed;
    }

    /**
     * @return array<int,string>
     */
    private function splitWordParts(string $word): array
    {
        $trimmed = trim($word);
        if ($trimmed === '') {
            return [];
        }

        $parts = preg_split('/\s+/', $trimmed);
        if ($parts === false) {
            return [$trimmed];
        }

        $result = [];
        foreach ($parts as $part) {
            $clean = trim($part);
            if ($clean !== '') {
                $result[] = $clean;
            }
        }

        return $result;
    }

    /**
     * @param array<int|string,mixed> $chunks
     * @param array<int,string> $wordParts
     * @return array<int|string,mixed>
     */
    private function buildChunksWithSpaces(
        array $chunks,
        array $wordParts,
        int $thresholdScore,
        bool $preferContentField = false
    ): array {
        if ($chunks === []) {
            return [];
        }

        $boundaries = $this->buildSpaceBoundaries($wordParts);
        if ($boundaries === []) {
            return $chunks;
        }

        $currentLength = 0;
        return $this->applySpacesByBoundary($chunks, $boundaries, $currentLength, $preferContentField);
    }

    /**
     * @param array<int,string> $wordParts
     * @return array<int,bool>
     */
    private function buildSpaceBoundaries(array $wordParts): array
    {
        $trimmedParts = array_values(array_filter(array_map('trim', $wordParts), static function ($part): bool {
            return $part !== '';
        }));

        if (count($trimmedParts) <= 1) {
            return [];
        }

        $boundaries = [];
        $runningLength = 0;
        $lastIndex = count($trimmedParts) - 1;

        foreach ($trimmedParts as $index => $part) {
            $partLength = $this->calculateStringLength($part);
            if ($partLength === 0) {
                continue;
            }

            $runningLength += $partLength;
            if ($index < $lastIndex) {
                $boundaries[$runningLength] = true;
            }
        }

        return $boundaries;
    }

    /**
     * @param array<int|string,mixed> $chunks
     * @param array<int,bool> $boundaries
     * @return array<int|string,mixed>
     */
    private function applySpacesByBoundary(
        array $chunks,
        array $boundaries,
        int &$currentLength,
        bool $preferContentField
    ): array {
        foreach ($chunks as $index => $chunk) {
            if (is_string($chunk) || is_numeric($chunk)) {
                $chunks[$index] = $this->appendSpaceAtBoundary((string)$chunk, $boundaries, $currentLength);
                continue;
            }

            if (!is_array($chunk)) {
                continue;
            }

            $isAssoc = $this->isAssocArray($chunk);
            if (
                $preferContentField
                && $isAssoc
                && array_key_exists('content', $chunk)
                && (is_string($chunk['content']) || is_numeric($chunk['content']))
            ) {
                $chunk['content'] = $this->appendSpaceAtBoundary((string)$chunk['content'], $boundaries, $currentLength);
                $chunks[$index] = $chunk;
                continue;
            }

            if ($isAssoc) {
                $chunks[$index] = $chunk;
                continue;
            }

            $chunks[$index] = $this->applySpacesByBoundary($chunk, $boundaries, $currentLength, $preferContentField);
        }

        return $chunks;
    }

    /**
     * @param array<int,bool> $boundaries
     */
    private function appendSpaceAtBoundary(string $chunkString, array $boundaries, int &$currentLength): string
    {
        $length = $this->calculateChunkLength($chunkString);
        if ($length === 0) {
            return $chunkString;
        }

        $currentLength += $length;
        if (isset($boundaries[$currentLength])) {
            return rtrim($chunkString) . ' ';
        }

        return $chunkString;
    }

    private function calculateChunkLength(string $chunk): int
    {
        $normalized = preg_replace('/\s+/', '', $chunk);
        if ($normalized === null) {
            $normalized = $chunk;
        }

        $normalized = trim($normalized);

        return $this->calculateStringLength($normalized);
    }

    private function calculateStringLength(string $value): int
    {
        if ($value === '') {
            return 0;
        }

        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }

    private function encodeJsonString($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        if (is_array($value)) {
            if ($value === []) {
                return null;
            }
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
            return $encoded === false ? null : $encoded;
        }

        if (is_scalar($value)) {
            $stringValue = (string)$value;
            return $stringValue === '' ? null : $stringValue;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? null : $encoded;
    }

    /**
     * @param array<int,array<string,mixed>> $books
     * @return array<string,array<string,mixed>>
     */
    private function buildWordDataLookup(array $books): array
    {
        $lookup = [];
        foreach ($books as $book) {
            if (!is_array($book) || empty($book['courses']) || !is_array($book['courses'])) {
                continue;
            }

            foreach ($book['courses'] as $course) {
                if (!is_array($course) || empty($course['words']) || !is_array($course['words'])) {
                    continue;
                }

                foreach ($course['words'] as $wordEntry) {
                    if (!is_array($wordEntry)) {
                        continue;
                    }

                    $wordName = trim((string)($wordEntry['word'] ?? ''));
                    if ($wordName === '' || isset($lookup[$wordName])) {
                        continue;
                    }

                    $lookup[$wordName] = $wordEntry;
                }
            }
        }

        return $lookup;
    }

    /**
     * @param string $directory
     * @return array<string,string>
     */
    private function buildJsonFileLookup(string $directory): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $lookup = [];
        $iterator = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if (strtolower($fileInfo->getExtension()) !== 'json') {
                continue;
            }
            $key = $this->normalizeWordKey($fileInfo->getBasename('.' . $fileInfo->getExtension()));
            if ($key === '') {
                continue;
            }
            if (!isset($lookup[$key])) {
                $lookup[$key] = $fileInfo->getPathname();
            }
        }

        return $lookup;
    }

    private function resolveWordFilePath(array $lookup, string $word): ?string
    {
        $candidates = [
            $word,
            preg_replace('/\s+/', ' ', $word),
            str_replace(' ', '_', $word),
            str_replace(' ', '-', $word),
        ];

        foreach ($candidates as $candidate) {
            $key = $this->normalizeWordKey((string)$candidate);
            if ($key !== '' && isset($lookup[$key])) {
                return $lookup[$key];
            }
        }

        return null;
    }

    private function normalizeWordKey(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $trimmed = mb_strtolower($trimmed, 'UTF-8');
        } else {
            $trimmed = strtolower($trimmed);
        }

        $trimmed = preg_replace('/\s+/', ' ', $trimmed);
        return $trimmed === null ? '' : $trimmed;
    }

    private function findVocabularyByNameVariants(string $wordName): ?Vocabulary
    {
        $trimmed = trim($wordName);
        $variants = [$trimmed];
        if ($trimmed !== '') {
            $variants[] = str_replace('_', ' ', $trimmed);
            $variants[] = str_replace('-', ' ', $trimmed);
            $combined = str_replace(['_', '-'], ' ', $trimmed);
            $normalized = preg_replace('/\s+/', ' ', $combined);
            if ($normalized !== null) {
                $variants[] = $normalized;
            }
        }

        $variants = array_values(array_unique(array_filter($variants, static function ($value) {
            return $value !== '';
        })));

        foreach ($variants as $variant) {
            $vocabulary = Vocabulary::find()->where(['name' => $variant])->one();
            if ($vocabulary) {
                return $vocabulary;
            }
        }

        return null;
    }

    private function createChunkQuizFromFile(Vocabulary $vocabulary, string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('读取文件失败');
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new \RuntimeException('JSON结构不符合预期');
        }

        $entry = $data[0];
        $correctChunks = isset($entry['correct_chunks']) && is_array($entry['correct_chunks'])
            ? array_values($entry['correct_chunks'])
            : null;
        $distractorChunks = isset($entry['distractor_chunks']) && is_array($entry['distractor_chunks'])
            ? array_values($entry['distractor_chunks'])
            : null;

        if ($correctChunks === null && $distractorChunks === null) {
            throw new \RuntimeException('缺少 correct_chunks / distractor_chunks');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $quiz = new VocabularyQuiz();
            $quiz->vocabulary_id = $vocabulary->id;
            $quiz->quiz_type = 2;
            $quiz->quiz_translation = '[]';
            $quiz->quiz_answer = '';
            $quiz->status = 1;
            $quiz->create_by = 0;
            $quiz->create_time = time();
            $quiz->update_by = 0;
            $quiz->update_time = $quiz->create_time;

            if ($correctChunks !== null) {
                $encoded = json_encode($correctChunks, JSON_UNESCAPED_UNICODE);
                if ($encoded === false) {
                    throw new \RuntimeException('correct_chunks JSON编码失败');
                }
                $quiz->quiz_question = $encoded;
            } else {
                $quiz->quiz_question = '';
            }

            $quiz->quiz_options = $distractorChunks ?? [];

            if (!$quiz->save()) {
                throw new \RuntimeException('题目保存失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private function createSentenceQuizFromFile(Vocabulary $vocabulary, string $filePath): void
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException('读取文件失败');
        }

        $data = json_decode($content, true);
        if (!is_array($data) || !isset($data[0]) || !is_array($data[0])) {
            throw new \RuntimeException('JSON结构不符合预期');
        }

        $quizItem = $data[0]['quiz_item'] ?? null;
        if (!is_array($quizItem)) {
            throw new \RuntimeException('未找到 quiz_item 数据');
        }

        $sentence = '';
        if (isset($quizItem['sentence'])) {
            $sentence = is_array($quizItem['sentence']) ? '' : trim((string)($quizItem['sentence'] ?? ''));
        }
        if ($sentence === '') {
            throw new \RuntimeException('缺少 sentence 字段');
        }

        $optionNode = $quizItem['options']['option'] ?? [];
        if (!is_array($optionNode)) {
            $optionNode = [$optionNode];
        }
        if (isset($optionNode['definition'])) {
            $optionNode = [$optionNode];
        }
        $optionList = [];
        foreach ($optionNode as $option) {
            if (is_array($option)) {
                $optionList[] = $option;
            }
        }
        if (empty($optionList)) {
            throw new \RuntimeException('缺少选项数据');
        }

        $translation = trim((string)($quizItem['error_analysis'] ?? ''));
        if ($translation === '') {
            $translation = '[]';
        }

        $optionsPayload = [];
        $correctAnswer = '';
        foreach ($optionList as $option) {
            $definition = trim((string)($option['definition'] ?? ''));
            if ($definition === '') {
                continue;
            }
            $isCorrect = isset($option['iscorrect']) && $option['iscorrect'] ? 1 : 2;
            $optionsPayload[] = [
                'definition' => $definition,
                'is_correct' => $isCorrect,
            ];
            if ($isCorrect === 1 && $correctAnswer === '') {
                $correctAnswer = $definition;
            }
        }

        if (empty($optionsPayload)) {
            throw new \RuntimeException('无有效选项');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            $now = time();
            $quiz = new VocabularyQuiz();
            $quiz->vocabulary_id = $vocabulary->id;
            $quiz->quiz_type = 1;
            $quiz->quiz_question = $sentence;
            $quiz->quiz_answer = $correctAnswer;
            $quiz->quiz_translation = $translation;
            $quiz->quiz_options = '[]';
            $quiz->create_by = 0;
            $quiz->create_time = $now;
            $quiz->update_by = 0;
            $quiz->update_time = $now;
            $quiz->status = 1;

            if (!$quiz->save()) {
                throw new \RuntimeException('题目保存失败: ' . json_encode($quiz->errors, JSON_UNESCAPED_UNICODE));
            }

            foreach ($optionsPayload as $optionPayload) {
                $quizOption = new VocabularyQuizOption();
                $quizOption->quiz_id = $quiz->id;
                $quizOption->definition = $optionPayload['definition'];
                $quizOption->is_correct = $optionPayload['is_correct'];
                $quizOption->source_word = '';
                $quizOption->pos = '';
                $quizOption->create_by = 0;
                $quizOption->create_time = $now;
                $quizOption->update_by = 0;
                $quizOption->update_time = $now;

                if (!$quizOption->save()) {
                    throw new \RuntimeException('选项保存失败: ' . json_encode($quizOption->errors, JSON_UNESCAPED_UNICODE));
                }
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * 根据课程名称与图片映射文件更新 vocabulary_book_unit.cover_image_url
     * php yii words/update-unit-cover-images [jsonFilePath]
     */
    public function actionUpdateUnitCoverImages(string $filePath = ''): int
    {
        $basePath = dirname(__FILE__, 2);
        $defaultPath = $basePath . '/runtime/tmp/courses_name_image_map.json';
        $path = $filePath !== '' ? $filePath : $defaultPath;

        if (!is_file($path)) {
            echo "文件不存在: {$path}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            echo "读取文件失败: {$path}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $mapping = json_decode($raw, true);
        if (!is_array($mapping) || empty($mapping)) {
            echo "JSON解析失败或没有有效的数据: {$path}\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $updated = 0;
        $missing = [];
        $skipped = 0;

        foreach ($mapping as $unitName => $imageName) {
            $unitName = trim((string)$unitName);
            $imageName = is_string($imageName) ? trim($imageName) : '';

            if ($unitName === '' || $imageName === '') {
                $skipped++;
                continue;
            }

            $coverUrl = '/front/vocabulary_unit/' . ltrim($imageName, '/');
            $count = VocabularyBookUnit::updateAll(
                ['cover_image_url' => $coverUrl],
                ['name' => $unitName]
            );

            if ($count > 0) {
                $updated += $count;
                echo "更新单元《{$unitName}》{$count} 条记录 => {$coverUrl}\n";
            } else {
                $missing[] = $unitName;
            }
        }

        echo "更新完成：成功 {$updated} 条，跳过 {$skipped} 条。\n";
        if (!empty($missing)) {
            echo "未匹配到的单元（" . count($missing) . "）: " . implode(', ', $missing) . "\n";
        }

        return ExitCode::OK;
    }

    /**
     * 翻译词汇单元名称并寻找相似单元，补齐封面
     * php yii words/match-unit-cover-images [cacheFile] [minScore] [dryRun]
     */
    public function actionMatchUnitCoverImages(
        string $cacheFile = '@runtime/tmp/unit_name_translation_cache.json',
        string $minScore = '0.4',
        int $dryRun = 0
    ): int {
        $threshold = (float)$minScore;
        if ($threshold <= 0 || $threshold > 1) {
            $threshold = 0.65;
        }
        $dryRunMode = $dryRun > 0;

        $missingUnits = VocabularyBookUnit::find()
            ->where(['>=', 'book_id', 195])
            ->andWhere([
                'or',
                ['cover_image_url' => null],
                ['cover_image_url' => ''],
            ])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if (empty($missingUnits)) {
            echo "没有需要处理的词汇单元\n";
            return ExitCode::OK;
        }

        $candidateUnits = VocabularyBookUnit::find()
            ->where(['not', ['cover_image_url' => null]])
            ->andWhere(['<>', 'cover_image_url', ''])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        if (empty($candidateUnits)) {
            echo "没有可参考的封面数据\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $translationCache = $this->loadNameTranslationCache($cacheFile);
        $httpClient = new Client(['timeout' => 10]);

        $candidates = [];
        foreach ($candidateUnits as $candidate) {
            $candidates[] = [
                'model' => $candidate,
                'normalized' => $this->normalizeUnitName($candidate->name),
                'translationNormalized' => '',
                'needsTranslation' => $this->shouldTranslateName($candidate->name),
                'translationAttempted' => false,
            ];
        }

        $updated = 0;
        $skipped = 0;

        foreach ($missingUnits as $unit) {
            $unitName = (string)$unit->name;
            $normalized = $this->normalizeUnitName($unitName);
            $translation = $this->shouldTranslateName($unitName)
                ? $this->translateNameWithCache($unitName, $translationCache, $httpClient)
                : null;
            $translatedNormalized = $this->normalizeUnitName($translation);

            $match = $this->findBestUnitMatch(
                $normalized,
                $translatedNormalized,
                $candidates,
                $translationCache,
                $httpClient
            );

            if ($match === null || $match['score'] < $threshold) {
                $skipped++;
                $label = $translation ? "{$unitName} ({$translation})" : $unitName;
                echo "未找到匹配单元: {$label}\n";
                continue;
            }

            $matchedUnit = $match['unit'];
            $scoreText = number_format($match['score'], 3);
            $newCover = $matchedUnit->cover_image_url;

            if ($dryRunMode) {
                echo "[DRY-RUN] 《{$unitName}》 => 《{$matchedUnit->name}》 (score {$scoreText}) 使用 {$newCover}\n";
                continue;
            }

            $unit->cover_image_url = $newCover;
            $unit->update_by = 0;
            $unit->update_time = time();

            if (!$unit->save(false, ['cover_image_url', 'update_by', 'update_time'])) {
                echo "保存封面失败: 《{$unitName}》\n";
                $skipped++;
                continue;
            }

            $updated++;
            echo "更新《{$unitName}》 => 《{$matchedUnit->name}》 (score {$scoreText})\n";
        }

        $this->persistNameTranslationCache($cacheFile, $translationCache);

        echo "处理完成：更新 {$updated} 条，跳过 {$skipped} 条";
        if ($dryRunMode) {
            echo "（dry-run模式未写入数据库）";
        }
        echo "\n";

        return ExitCode::OK;
    }

    /**
     * @param array<int,array{model:VocabularyBookUnit,normalized:string,translationNormalized:string,needsTranslation:bool,translationAttempted:bool}> $candidates
     * @param array<string,string|null> $translationCache
     * @return array{unit:VocabularyBookUnit,score:float}|null
     */
    private function findBestUnitMatch(
        string $targetNormalized,
        string $targetTranslatedNormalized,
        array &$candidates,
        array &$translationCache,
        Client $httpClient
    ): ?array {
        $best = null;

        foreach ($candidates as &$candidate) {
            /** @var VocabularyBookUnit $candidateModel */
            $candidateModel = $candidate['model'];
            $score = $this->calculateSimilarity($targetNormalized, $candidate['normalized']);

            if ($targetTranslatedNormalized !== '') {
                $score = max($score, $this->calculateSimilarity($targetTranslatedNormalized, $candidate['normalized']));
            }

            if ($candidate['needsTranslation'] && !$candidate['translationAttempted']) {
                $translated = $this->translateNameWithCache($candidateModel->name ?? '', $translationCache, $httpClient);
                $candidate['translationAttempted'] = true;
                $candidate['translationNormalized'] = $this->normalizeUnitName($translated);
            }

            if ($candidate['translationNormalized'] !== '') {
                $score = max($score, $this->calculateSimilarity($targetNormalized, $candidate['translationNormalized']));
                if ($targetTranslatedNormalized !== '') {
                    $score = max($score, $this->calculateSimilarity($targetTranslatedNormalized, $candidate['translationNormalized']));
                }
            }

            if ($best === null || $score > $best['score']) {
                $best = [
                    'unit' => $candidateModel,
                    'score' => $score,
                ];
            }
        }
        unset($candidate);

        return $best;
    }

    private function normalizeUnitName(?string $name): string
    {
        if ($name === null) {
            return '';
        }
        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($trimmed, 'UTF-8');
        } else {
            $normalized = strtolower($trimmed);
        }

        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    private function calculateSimilarity(string $first, string $second): float
    {
        if ($first === '' || $second === '') {
            return 0.0;
        }
        similar_text($first, $second, $percent);
        return $percent / 100;
    }

    private function shouldTranslateName(?string $name): bool
    {
        if ($name === null) {
            return false;
        }
        return preg_match('/[^\x00-\x7F]/u', $name) === 1;
    }

    /**
     * 清理 vocabulary_unit_relation 表中重复 (unit_id, vocabulary_id) 的数据。
     *
     * @param int $batchSize 每批删除的行数，默认 1000
     */
    public function actionCleanVocabularyUnitRelationDuplicates(int $batchSize = 1000): int
    {
        $batchSize = max(1, $batchSize);
        $db = Yii::$app->db;
        $table = VocabularyUnitRelation::tableName();

        $subQuery = (new Query())
            ->select(new Expression('MIN([[id]])'))
            ->from($table)
            ->groupBy(['unit_id', 'vocabulary_id']);

        $idsQuery = (new Query())
            ->select(['id'])
            ->from($table)
            ->where(['not in', 'id', $subQuery])
            ->orderBy(['id' => SORT_ASC]);

        $countQuery = clone $idsQuery;
        $countQuery->orderBy([]);
        $totalDuplicates = (int)$countQuery->count('*', $db);

        if ($totalDuplicates === 0) {
            echo "未检测到重复数据，退出。\n";
            return ExitCode::OK;
        }

        echo sprintf("检测到 %d 条重复数据，将分批（每批 %d 条）删除...\n", $totalDuplicates, $batchSize);

        $deleted = 0;
        $batchQuery = clone $idsQuery;

        $db->transaction(function () use ($batchQuery, $db, $table, $batchSize, &$deleted): void {
            $batchNumber = 0;
            foreach ($batchQuery->batch($batchSize, $db) as $rows) {
                $batchNumber++;
                $ids = array_map(static function (array $row) {
                    return (int)$row['id'];
                }, $rows);
                if (empty($ids)) {
                    continue;
                }

                $deleted += $db->createCommand()
                    ->delete($table, ['id' => $ids])
                    ->execute();

                echo sprintf("第 %d 批删除 %d 条，累计 %d 条\n", $batchNumber, count($ids), $deleted);
            }
        });

        echo sprintf("清理完成，共删除 %d 条重复数据。\n", $deleted);
        return ExitCode::OK;
    }

    /**
     * @param array<string,string|null> $cache
     */
    private function translateNameWithCache(string $name, array &$cache, Client $client): ?string
    {
        $key = $this->buildUnitTranslationCacheKey($name);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $cache)) {
            return $cache[$key] ?? null;
        }

        $translation = $this->requestUnitNameTranslation($name, $client);
        $cache[$key] = $translation;
        $this->unitTranslationCacheDirty = true;

        return $translation;
    }

    private function requestUnitNameTranslation(string $text, Client $client): ?string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return null;
        }

        try {
            $response = $client->post(self::UNIT_TRANSLATION_ENDPOINT, [
                'json' => [
                    'input' => [
                        'text' => $trimmed,
                    ],
                ],
                'timeout' => 10,
            ]);
        } catch (GuzzleException $e) {
            echo "翻译接口请求失败: {$e->getMessage()}\n";
            return null;
        }

        $body = (string)$response->getBody();
        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            echo "翻译接口返回无法解析的数据\n";
            return null;
        }

        $translation = $payload['output']['translation'] ?? null;
        if (!is_string($translation)) {
            echo "翻译接口响应缺少 translation 字段\n";
            return null;
        }

        $result = trim($translation);
        return $result === '' ? null : $result;
    }

    /**
     * @return array<string,string|null>
     */
    private function loadNameTranslationCache(string $filePath): array
    {
        $resolved = Yii::getAlias($filePath);
        if (!is_file($resolved)) {
            return [];
        }

        $raw = file_get_contents($resolved);
        if ($raw === false) {
            return [];
        }
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return [];
        }

        $cache = [];
        foreach ($decoded as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if ($value === null) {
                $cache[$key] = null;
            } elseif (is_string($value)) {
                $cache[$key] = trim($value) === '' ? null : trim($value);
            }
        }

        return $cache;
    }

    /**
     * @param array<string,string|null> $cache
     */
    private function persistNameTranslationCache(string $filePath, array $cache): void
    {
        if (!$this->unitTranslationCacheDirty) {
            return;
        }

        $resolved = Yii::getAlias($filePath);
        $directory = dirname($resolved);
        if (!is_dir($directory)) {
            FileHelper::createDirectory($directory);
        }

        $encoded = json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            echo "翻译缓存 JSON 编码失败\n";
            return;
        }

        if (file_put_contents($resolved, $encoded) === false) {
            echo "写入翻译缓存失败: {$resolved}\n";
            return;
        }

        $this->unitTranslationCacheDirty = false;
    }

    private function buildUnitTranslationCacheKey(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($trimmed, 'UTF-8');
        }

        return strtolower($trimmed);
    }

    /**
     * @param string $path
     */
    private function resolveFilePath(string $path): string
    {
        $resolved = Yii::getAlias($path, false);
        return $resolved === false ? $path : $resolved;
    }

    /**
     * @param mixed $value
     * @return array<int,mixed>
     */
    private function normalizePartsOfSpeechPayload($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param mixed $value
     * @return array<int,array<string,mixed>>
     */
    private function normalizeExampleSentencesPayload($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $normalized[] = $item;
            }
        }

        return array_values($normalized);
    }

    private function buildVocabularyLookupKey(?string $word): ?string
    {
        if ($word === null) {
            return null;
        }

        $trimmed = trim($word);
        if ($trimmed === '') {
            return null;
        }

        return function_exists('mb_strtolower')
            ? mb_strtolower($trimmed, 'UTF-8')
            : strtolower($trimmed);
    }

    /**
     * @return array<int,string>
     */
    private function buildWordVariants(string $word): array
    {
        $trimmed = trim($word);
        if ($trimmed === '') {
            return [];
        }

        $variants = [$trimmed];

        $stripped = $this->stripLeadingToVariant($trimmed);
        if ($stripped !== null) {
            $variants[] = $stripped;
        }

        if ($this->shouldGenerateToPrefixedVariant($trimmed)) {
            $variants[] = 'to ' . $trimmed;
        }

        return array_values(array_unique($variants));
    }

    private function shouldGenerateToPrefixedVariant(string $word): bool
    {
        if ($word === '') {
            return false;
        }

        if (preg_match('/\s/u', $word)) {
            return false;
        }

        return true;
    }

    private function stripLeadingToVariant(string $word): ?string
    {
        if ($word === '') {
            return null;
        }

        if (preg_match('/^to\s+/iu', $word) !== 1) {
            return null;
        }

        $stripped = preg_replace('/^to\s+/iu', '', $word, 1);
        if (!is_string($stripped)) {
            return null;
        }

        $stripped = trim($stripped);

        return $stripped === '' ? null : $stripped;
    }

    private function formatWordVariantLabel(string $word, ?string $sourceWord): string
    {
        if ($sourceWord === null || $sourceWord === '' || $word === $sourceWord) {
            return $word;
        }

        return sprintf('%s (来源 %s)', $word, $sourceWord);
    }

    /**
     * @param mixed $payload
     */
    private function encodeCoreMeaningPayloadForComparison($payload): ?string
    {
        if (!is_array($payload)) {
            return null;
        }

        $encoded = json_encode(array_values($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            return null;
        }

        return $encoded;
    }

    /**
     * 批量将 vocabulary_ext.core_meanings 中的 pos 字段由全名转换为缩写
     *
     * @param int $batchSize 每批处理记录数
     * @param int $limit     最多处理多少条记录（0 表示全部）
     * @param int $dryRun    传入 1 执行演练，不真正写库
     */
    public function actionNormalizeCoreMeaningsPos(int $batchSize = 500, int $limit = 0, int $dryRun = 0): int
    {
        $query = VocabularyExt::find()
            ->where(['not', ['core_meanings' => null]])
            ->andWhere(['<>', 'core_meanings', '']);

        $totalRecords = (clone $query)->count();
        $targetTotal = $totalRecords;
        if ($limit > 0) {
            $query->limit($limit);
            $targetTotal = min($totalRecords, $limit);
        }

        $batchSize = $batchSize > 0 ? $batchSize : 100;
        $dryRunMode = $dryRun > 0;

        $processed = 0;
        $updated = 0;
        $posChanges = 0;
        $decodeFailures = 0;
        $missingPosStats = [];

        foreach ($query->batch($batchSize) as $batch) {
            /** @var VocabularyExt $vocabularyExt */
            foreach ($batch as $vocabularyExt) {
                $processed++;

                $coreMeanings = $this->decodeCoreMeaningsPayload($vocabularyExt->core_meanings);
                if ($coreMeanings === null) {
                    $decodeFailures++;
                    continue;
                }

                $hasChange = false;
                foreach ($coreMeanings as &$meaning) {
                    if (!is_array($meaning) || !array_key_exists('pos', $meaning)) {
                        continue;
                    }

                    $originalPos = (string)$meaning['pos'];
                    $convertedPos = $this->convertCoreMeaningPos($originalPos);
                    if ($convertedPos === null) {
                        $missingKey = $this->normalizePosValueForLookup($originalPos);
                        if ($missingKey !== null && !isset(self::CORE_MEANING_POS_ABBREVIATION_MAP[$missingKey])) {
                            $missingPosStats[$missingKey] = ($missingPosStats[$missingKey] ?? 0) + 1;
                        }
                        continue;
                    }

                    if ($convertedPos === $originalPos) {
                        continue;
                    }

                    $meaning['pos'] = $convertedPos;
                    $hasChange = true;
                    $posChanges++;
                }
                unset($meaning);

                if (!$hasChange) {
                    continue;
                }

                $encoded = json_encode($coreMeanings, JSON_UNESCAPED_UNICODE);
                if ($encoded === false) {
                    echo "JSON 编码失败，跳过 vocabulary_id={$vocabularyExt->vocabulary_id}\n";
                    continue;
                }

                if ($dryRunMode) {
                    echo "[dry-run] vocabulary_id={$vocabularyExt->vocabulary_id} 将更新 core_meanings 词性缩写\n";
                    continue;
                }

                $vocabularyExt->core_meanings = $coreMeanings;
                $vocabularyExt->update_by = 0;
                $vocabularyExt->update_time = time();
                if (!$vocabularyExt->save(false)) {
                    echo "保存失败，跳过 vocabulary_id={$vocabularyExt->vocabulary_id}\n";
                    continue;
                }

                $updated++;
            }
        }

        echo sprintf(
            "共有 %d 条记录满足条件，计划处理 %d 条，实际扫描 %d 条。\n",
            $totalRecords,
            $targetTotal,
            $processed
        );
        echo sprintf("成功更新 %d 条记录，共修改 %d 个词性。\n", $updated, $posChanges);
        if ($decodeFailures > 0) {
            echo sprintf("其中 %d 条记录的 core_meanings 无法解析或为空。\n", $decodeFailures);
        }
        if ($dryRunMode) {
            echo "dry-run 模式未对数据库做出修改。\n";
        }
        if (!empty($missingPosStats)) {
            echo "以下词性未能映射为缩写（出现次数）：\n";
            foreach ($missingPosStats as $posValue => $count) {
                echo "- {$posValue}: {$count}\n";
            }
        }

        return ExitCode::OK;
    }

    /**
     * @param string|array|null $coreMeanings
     * @return array<int,mixed>|null
     */
    private function decodeCoreMeaningsPayload($coreMeanings): ?array
    {
        if ($coreMeanings === null) {
            return null;
        }

        if (is_array($coreMeanings)) {
            return $coreMeanings;
        }

        if (!is_string($coreMeanings)) {
            return null;
        }

        $trimmed = trim($coreMeanings);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    private function convertCoreMeaningPos(?string $pos): ?string
    {
        if ($pos === null) {
            return null;
        }

        $trimmed = trim((string)$pos);
        if ($trimmed === '') {
            return null;
        }

        if ($this->isCoreMeaningPosAlreadyShort($trimmed)) {
            return $trimmed;
        }

        $normalized = $this->normalizePosValueForLookup($trimmed);
        if ($normalized === null) {
            return null;
        }

        return self::CORE_MEANING_POS_ABBREVIATION_MAP[$normalized] ?? null;
    }

    private function isCoreMeaningPosAlreadyShort(string $pos): bool
    {
        $normalized = trim($pos);
        if ($normalized === '') {
            return false;
        }

        if (function_exists('mb_strtolower')) {
            $normalized = mb_strtolower($normalized, 'UTF-8');
        } else {
            $normalized = strtolower($normalized);
        }

        return isset(self::CORE_MEANING_POS_ALREADY_SHORT[$normalized]);
    }

    private function normalizePosValueForLookup(string $value): ?string
    {
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);

        $normalized = str_replace(['–', '—', '-', '_', '/'], ' ', $normalized);
        $normalized = preg_replace('/\([^)]*\)/u', '', $normalized);
        $normalized = preg_replace('/\[.*?]/u', '', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', $normalized);
        $normalized = trim($normalized, " .");

        return $normalized === '' ? null : $normalized;
    }

    /**
     * 将 collocations 字段中序列化的数据转换成标准 JSON
     */
    public function actionNormalizeCollocations(int $batchSize = 500, int $limit = 0, int $dryRun = 0): int
    {
        $query = VocabularyExt::find()
            ->where(['not', ['collocations' => null]])
            ->andWhere(['<>', 'collocations', ''])
            ->andWhere(["id" => 1001]);

        $totalRecords = (clone $query)->count();
        $targetTotal = $totalRecords;
        if ($limit > 0) {
            $query->limit($limit);
            $targetTotal = min($totalRecords, $limit);
        }

        $batchSize = $batchSize > 0 ? $batchSize : 100;
        $dryRunMode = $dryRun > 0;

        $processed = 0;
        $serializedDetected = 0;
        $successful = 0;
        $decodeFailures = 0;

        foreach ($query->batch($batchSize) as $batch) {
            /** @var VocabularyExt $vocabularyExt */
            foreach ($batch as $vocabularyExt) {
                $processed++;

                $wasSerialized = false;
                $decoded = $this->decodeCollocationsValue($vocabularyExt->collocations, $wasSerialized);
                if (!$wasSerialized) {
                    continue;
                }

                if (!is_array($decoded)) {
                    $decodeFailures++;
                    continue;
                }

                // $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                // if ($encoded === false) {
                //     $decodeFailures++;
                //     continue;
                // }

                $serializedDetected++;

                if ($dryRunMode) {
                    echo "[dry-run] vocabulary_id={$vocabularyExt->vocabulary_id} 将从序列化转换为 JSON。\n";
                    continue;
                }

                $vocabularyExt->collocations = $decoded;
                $vocabularyExt->update_by = 0;
                $vocabularyExt->update_time = time();
                if (!$vocabularyExt->save(false)) {
                    echo "保存 collocations 失败，跳过 vocabulary_id={$vocabularyExt->vocabulary_id}\n";
                    continue;
                }
                echo "成功修复 vocabulary_id={$vocabularyExt->vocabulary_id}\n";
                $successful++;
            }
        }

        echo sprintf(
            "共有 %d 条记录满足条件，计划处理 %d 条，实际扫描 %d 条。\n",
            $totalRecords,
            $targetTotal,
            $processed
        );
        echo sprintf("发现 %d 条序列化记录。\n", $serializedDetected);
        if ($dryRunMode) {
            echo "dry-run 模式未对数据库做出修改。\n";
        } else {
            echo sprintf("成功修复 %d 条记录。\n", $successful);
        }
        if ($decodeFailures > 0) {
            echo sprintf("其中 %d 条记录无法正确反序列化，已跳过。\n", $decodeFailures);
        }

        return ExitCode::OK;
    }

    /**
     * 将 collocations 中旧的 usage 结构转换为 [{original, translated}] 形式
     * 运行命令：php yii words/fix-collocation-usage-translations [batchSize=200] [limit=0] [dryRun=1]
     */
    public function actionFixCollocationUsageTranslations(int $batchSize = 200, int $limit = 0, int $dryRun = 0): int
    {
        $query = VocabularyExt::find()
            ->where(['not', ['collocations' => null]])
            ->andWhere(['<>', 'collocations', ''])
            ->andWhere("collocations LIKE :pattern ESCAPE '#'", [':pattern' => "%#\\%"])
            ->orderBy(['id' => SORT_ASC]);

        $countQuery = clone $query;
        $totalRecords = (int)$countQuery->count();
        $targetTotal = $limit > 0 ? min($totalRecords, $limit) : $totalRecords;

        if ($limit > 0) {
            $query->limit($limit);
        }

        $batchSize = $batchSize > 0 ? $batchSize : 200;
        $dryRunMode = $dryRun > 0;

        $httpClient = new Client(['timeout' => 10]);
        $translationCache = [];

        $processed = 0;
        $converted = 0;
        $skipped = 0;
        $failed = 0;
        $translatedEntries = 0;

        foreach ($query->batch($batchSize) as $batch) {
            /** @var VocabularyExt $vocabularyExt */
            foreach ($batch as $vocabularyExt) {
                $processed++;

                $pairs = $this->convertCollocationsUsageToTranslationPairs($vocabularyExt->collocations);
                if ($pairs === null || $pairs === []) {
                    $skipped++;
                    continue;
                }

                $pairs = $this->translateUsagePairs($pairs, $httpClient, $translationCache, $translatedEntries);

                if ($dryRunMode) {
                    echo "[dry-run] vocabulary_id={$vocabularyExt->vocabulary_id} 将更新 " . count($pairs) . " 条 usage 翻译项。\n";
                    $converted++;
                    continue;
                }

                $vocabularyExt->collocations = $pairs;
                $vocabularyExt->update_by = 0;
                $vocabularyExt->update_time = time();

                if (!$vocabularyExt->save(false)) {
                    echo "保存失败，跳过 vocabulary_id={$vocabularyExt->vocabulary_id}\n";
                    $failed++;
                    continue;
                }

                echo "修复 vocabulary_id={$vocabularyExt->vocabulary_id}，共生成 " . count($pairs) . " 条 usage 记录。\n";
                $converted++;
            }
        }

        echo sprintf(
            "共有 %d 条记录满足条件，计划处理 %d 条，实际扫描 %d 条。\n",
            $totalRecords,
            $targetTotal,
            $processed
        );

        if ($dryRunMode) {
            echo sprintf("dry-run 模式下共有 %d 条记录符合转换条件。\n", $converted);
        } else {
            echo sprintf("成功修复 %d 条记录。\n", $converted);
        }

        echo sprintf("跳过 %d 条无法解析 usage 的记录。\n", $skipped);
        if ($failed > 0) {
            echo sprintf("保存失败 %d 条。\n", $failed);
        }
        echo sprintf("翻译成功 %d 条 usage。\n", $translatedEntries);

        return ExitCode::OK;
    }

    /**
     * @param mixed $collocations
     * @return array<int,mixed>|null
     */
    private function decodeCollocationsValue($collocations, bool &$wasSerialized): ?array
    {
        $wasSerialized = false;

        if ($collocations === null) {
            return null;
        }

        if (is_array($collocations)) {
            return $collocations;
        }

        if (!is_string($collocations)) {
            return null;
        }

        $trimmed = trim($collocations);
        if ($trimmed === '') {
            return null;
        }

        $jsonDecoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return is_array($jsonDecoded) ? $jsonDecoded : null;
        }

        $unserialized = $this->tryUnserializeValue($trimmed, $wasSerialized);
        if ($wasSerialized && is_array($unserialized)) {
            return $unserialized;
        }

        return null;
    }

    /**
     * @param mixed $collocations
     * @return array<int,array{original:string,translated:string}>|null
     */
    private function convertCollocationsUsageToTranslationPairs($collocations): ?array
    {
        $decoded = $this->decodeJsonValue($collocations);
        if ($decoded === null) {
            return null;
        }

        if (!is_array($decoded)) {
            $original = $this->stringifyTranslationValue($decoded);
            if ($original === null) {
                return null;
            }

            return [
                [
                    'original' => $original,
                    'translated' => '',
                ],
            ];
        }

        if ($decoded === []) {
            return null;
        }

        if ($this->looksLikeTranslationPairStructure($decoded)) {
            $normalized = $this->normalizeTranslationPairStructure($decoded);
            return $normalized === [] ? null : $normalized;
        }

        $usages = $this->collectUsageFromCollocationEntries($decoded);
        if ($usages === []) {
            return null;
        }

        $result = [];
        foreach ($usages as $usage) {
            $result[] = [
                'original' => $usage,
                'translated' => '',
            ];
        }

        return $result;
    }

    /**
     * @param array<int|string,mixed> $payload
     */
    private function looksLikeTranslationPairStructure(array $payload): bool
    {
        if ($payload === []) {
            return false;
        }

        if ($this->isAssocArray($payload)) {
            return array_key_exists('original', $payload) && array_key_exists('translated', $payload);
        }

        foreach ($payload as $value) {
            if (is_array($value) && array_key_exists('original', $value) && array_key_exists('translated', $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int|string,mixed> $payload
     * @return array<int,array{original:string,translated:string}>
     */
    private function normalizeTranslationPairStructure(array $payload): array
    {
        $pairs = $this->isAssocArray($payload) ? [$payload] : $payload;

        $result = [];
        foreach ($pairs as $entry) {
            if (!is_array($entry) || !array_key_exists('original', $entry)) {
                continue;
            }

            $usage = $this->extractUsageFromOriginalField($entry['original']);
            if ($usage === null) {
                continue;
            }

            $result[] = [
                'original' => $usage,
                'translated' => $this->normalizeTranslationStringValue($entry['translated'] ?? null),
            ];
        }

        return $result;
    }

    /**
     * @param mixed $value
     */
    private function normalizeTranslationStringValue($value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        return $encoded === false ? '' : $encoded;
    }

    /**
     * @param mixed $value
     */
    private function extractUsageFromOriginalField($value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $stringValue = trim((string)$value);
            if ($stringValue === '') {
                return null;
            }

            $decoded = json_decode($stringValue, true);
            if (is_array($decoded)) {
                $usage = $this->extractUsageTextFromNode($decoded);
                if ($usage !== null) {
                    return $usage;
                }
            }

            return $stringValue;
        }

        if (is_array($value)) {
            return $this->extractUsageTextFromNode($value);
        }

        return null;
    }

    /**
     * @param array<int|string,mixed> $payload
     * @return array<int,string>
     */
    private function collectUsageFromCollocationEntries(array $payload): array
    {
        $result = [];

        if ($payload === []) {
            return $result;
        }

        if ($this->isAssocArray($payload)) {
            $usage = $this->extractUsageTextFromNode($payload);
            if ($usage !== null) {
                $result[] = $usage;
                return $result;
            }

            foreach ($payload as $value) {
                if (!is_array($value)) {
                    continue;
                }
                $nested = $this->collectUsageFromCollocationEntries($value);
                if ($nested === []) {
                    continue;
                }
                foreach ($nested as $usage) {
                    $result[] = $usage;
                }
            }

            return $result;
        }

        foreach ($payload as $value) {
            if (!is_array($value)) {
                continue;
            }

            $usage = $this->extractUsageTextFromNode($value);
            if ($usage !== null) {
                $result[] = $usage;
                continue;
            }

            $nested = $this->collectUsageFromCollocationEntries($value);
            if ($nested === []) {
                continue;
            }
            foreach ($nested as $usage) {
                $result[] = $usage;
            }
        }

        return $result;
    }

    /**
     * @param array<int|string,mixed> $node
     */
    private function extractUsageTextFromNode(array $node): ?string
    {
        foreach ($node as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            if (strcasecmp($key, 'usage') !== 0) {
                continue;
            }

            if (is_string($value) || is_numeric($value)) {
                $trimmed = trim((string)$value);
                return $trimmed === '' ? null : $trimmed;
            }
        }

        return null;
    }

    /**
     * @return mixed|null
     */
    private function tryUnserializeValue(string $value, bool &$wasSerialized)
    {
        $wasSerialized = false;

        if (!$this->looksLikeSerializedValue($value)) {
            return null;
        }

        $result = @unserialize($value, ['allowed_classes' => false]);
        if ($result === false && $value !== 'b:0;') {
            return null;
        }

        $wasSerialized = true;
        return $result;
    }

    private function looksLikeSerializedValue(string $value): bool
    {
        $start = strtolower(ltrim($value));
        if ($start === '') {
            return false;
        }

        if ($start === 'n;') {
            return true;
        }

        return (bool)preg_match('/^(?:a|o|s|i|d|b|c):/i', $start);
    }

    private function truncateString($value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            if ($value === false) {
                return null;
            }
        }

        $stringValue = trim((string)$value);
        if ($stringValue === '') {
            return null;
        }

        if (function_exists('mb_strlen')) {
            if (mb_strlen($stringValue) > $maxLength) {
                $stringValue = mb_substr($stringValue, 0, $maxLength);
            }
        } elseif (strlen($stringValue) > $maxLength) {
            $stringValue = substr($stringValue, 0, $maxLength);
        }

        return $stringValue;
    }

    /**
     * @param mixed $pronunciation
     * @return array<string,array<string,string>>|null
     */
    private function buildPronunciationPayload($pronunciation): ?array
    {
        if (!is_array($pronunciation)) {
            return null;
        }

        $result = [];

        $ukIpa = $this->truncateString($pronunciation['uk_ipa'] ?? null, 100);
        if ($ukIpa !== null) {
            $result['uk'] = ['ipa' => $ukIpa];
        }

        $usIpa = $this->truncateString($pronunciation['us_ipa'] ?? null, 100);
        if ($usIpa !== null) {
            $result['us'] = ['ipa' => $usIpa];
        }

        return empty($result) ? null : $result;
    }

    /**
     * 判断字符串是否只包含英文（ASCII）字符
     */
    private function isEnglishText(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return false;
        }

        if (!preg_match('/[A-Za-z]/', $trimmed)) {
            return false;
        }

        return !preg_match('/[^\x00-\x7F]/', $trimmed);
    }

    /**
     * @return array<int,string>
     */
    private function getChineseUnitNameList(): array
    {
        $raw = <<<NAMES
外观、交流、家庭、旅游、数量、食物、测量、数学、音乐、教育、时尚、交通、时间、计算机、浪漫、农业、艺术、文学、速度、意义、复杂性、价值、质量、年龄、健康、智力、温度、概率、科学、教育、研究、物理学、生物学、化学、地质学、心理学、数学、几何学、环境学、技术学、互联网、历史、宗教、艺术、音乐、文学、建筑学、市场营销、金融、管理、医学、法律、犯罪、惩罚、政治、战争、测量、天气、污染、迁移、强度、速度、意义、无意义、复杂性、价值、健康、温度、概率、科学、教育、研究、天文学、物理学、生物学、化学、地质学、哲学、心理学、几何学、环境学、工程学、技术学、互联网、计算机、历史、宗教、语言、艺术、音乐、文学、建筑学、市场营销、金融、管理、医学、法律、犯罪、惩罚、政府、政治、战争、迁移、污染、天气、强度、速度、意义、价值、复杂性、成功、失败、健康、智力、温度、科学、教育、天文学、物理学、生物学、化学、地质学、哲学、心理学、几何学、环境学、工程学、技术学、互联网、计算机、历史、宗教、语言、艺术、音乐、文学、建筑学、市场营销、金融、管理、医学、法律、犯罪、惩罚、政府、政治、战争、测量、天气、污染
NAMES;

        $parts = explode('、', $raw);
        $result = [];
        foreach ($parts as $part) {
            $value = trim($part);
            if ($value === '') {
                continue;
            }
            $result[] = $value;
        }

        return $result;
    }
}

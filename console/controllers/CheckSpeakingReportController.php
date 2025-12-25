<?php

namespace console\controllers;

use app\models\SimulateExamSpeakingReport;
use OSS\Core\OssException;
use OSS\OssClient;
use Yii;
use yii\console\Controller;

class CheckSpeakingReportController extends BaseController
{
    /**
     * 检查 simulate_exam_speaking_report 表中的数据是否正确
     * 根据预定义的固定数据结构模板检查所有记录
     */
    public function actionIndex()
    {
        // 1. 使用固定的数据结构模板
        $templateData = $this->getFixedTemplateStructure();

        var_dump("使用固定的数据结构模板进行检查\n");
        var_dump("模板结构：");
        foreach ($templateData as $field => $structure) {
            var_dump("  {$field}: " . count($structure) . " 个必需字段");
        }

        var_dump("\n开始检查所有记录...\n");

        // 2. 查询所有数据
        $allRecords = SimulateExamSpeakingReport::find()->where(['record_id' => [7, 11, 14, 21, 31, 43, 81, 129, 139, 184, 187, 192, 195, 199, 200, 217, 224, 225, 226, 232, 245, 273, 308, 324, 328, 329, 355, 343, 453, 463, 469, 472, 476, 483, 508, 515, 533, 536, 537, 550, 580, 581, 587, 598, 599, 642, 675, 677, 714, 738, 742, 754, 757, 781, 785, 789, 813, 855, 866, 884, 888, 903, 915, 916, 919, 933, 935, 936, 966, 984, 991, 999, 1004, 1017, 1035, 1040, 1049, 1081, 1089, 1098, 1114, 1132, 1138, 1142, 1151, 1161, 1176, 1194, 1206, 1232, 1240, 1248, 1256, 1274, 1276, 1278, 1286, 1292, 1296, 1313, 1318, 1363, 1365, 1366, 1391, 1400, 1403, 1414, 1421, 1445, 1466, 1496, 1503, 1515, 1521, 1528, 1543, 1576, 1595, 1631, 1651, 1655, 1665, 1678, 1686, 1693, 1696, 1704, 1706, 1708, 1709, 1723, 1735, 1758, 1763, 1769, 1776, 1777, 1779, 1785, 1802, 1805, 1809, 1815, 1817, 1818, 1819, 1853, 1865, 1874, 1897, 1898, 1908, 1909, 1913, 1922, 1945, 1966, 1971, 1977, 2001, 2002, 2014, 2038, 2046, 2066, 2078, 2079, 2091, 2101, 2107, 2130, 2140, 2149, 2152, 2163, 2170, 2184, 2185, 2192, 2194, 2200, 2225, 2255, 2268, 2269, 2271, 2281, 2283, 2298, 2307, 2312, 2315, 2320, 2326, 2332, 2338, 2357, 2370, 2373, 2387, 2398, 2413, 2420, 2440, 2439, 2446, 2456, 2471, 2490, 2495, 2517, 2521, 2531, 2532, 2576, 2586, 2623, 2648, 2669, 2670, 2677, 2682, 2696, 2697, 2705, 2726, 2744, 2753, 2758, 2808, 2811, 2834, 2840, 2804, 2745, 2850, 1535, 2865, 2864, 2881, 2893, 2556, 2918, 2931, 2934, 2925, 2959, 2964, 2969, 2994, 3001, 3025, 3052, 3055, 3059, 3067, 3082, 3120, 3123, 3124, 3129, 3145, 3155, 3163, 3191, 3192, 3209, 3211, 3217, 3226, 3232, 3247, 3252, 3254, 3259, 3270, 3284, 3328, 3338, 3351, 3359, 3360, 3399, 3436, 3439, 3448, 3453, 3456, 3468, 3490, 3492, 3500, 3504, 3510, 3511, 3538, 3550, 3571, 3584, 3608, 3637, 3643, 3658]])->all();
        // $allRecords = SimulateExamSpeakingReport::find()->all();

        var_dump("共找到 " . count($allRecords) . " 条需要检查的记录\n");

        $errorRecords   = [];
        $correctRecords = [];
        $checkCount     = 0;

        // 4. 逐条检查数据
        foreach ($allRecords as $record) {
            $checkCount++;
            var_dump("\n[{$checkCount}/" . count($allRecords) . "] 检查记录 ID: {$record->id}, record_id: {$record->record_id}");

            $hasError = false;
            $errors   = [];

            // 检查每个字段
            $fields = ['fc', 'gra', 'pron_accuracy', 'vocabulary', 'summary'];
            foreach ($fields as $field) {
                if (empty($record->$field)) {
                    $errors[] = "{$field}: 字段为空";
                    $hasError = true;
                    continue;
                }

                // 下载当前记录的 OSS 文件并解析结构
                $currentContent = $this->downloadOssFile($record->$field, $field);

                if ($currentContent === false) {
                    $errors[] = "{$field}: OSS文件下载失败 ({$record->$field})";
                    $hasError = true;
                    continue;
                }

                $currentStructure = $this->getJsonStructure($currentContent);

                if ($currentStructure === false) {
                    $errors[] = "{$field}: JSON解析失败";
                    $hasError = true;
                    var_dump("  ✗ {$field}: JSON解析失败");
                    continue;
                }

                // 对比结构
                $structureDiff = $this->compareStructure($templateData[$field], $currentStructure);
                if ($structureDiff !== true) {
                    $errors[] = "{$field}: 结构不一致 - {$structureDiff}";
                    $hasError = true;
                    var_dump("  ✗ {$field}: 结构不一致 - {$structureDiff}");
                } else {
                    var_dump("  ✓ {$field}: 结构正确");
                }
            }

            if ($hasError) {
                $errorRecords[] = [
                    'id'        => $record->id,
                    'record_id' => $record->record_id,
                    'errors'    => $errors,
                ];
                var_dump("  结果: ✗ 数据有误");
            } else {
                $correctRecords[] = [
                    'id'        => $record->id,
                    'record_id' => $record->record_id,
                ];
                var_dump("  结果: ✓ 数据正确");
            }
        }

        // 5. 输出检查结果
        var_dump("\n" . str_repeat("=", 60));
        var_dump("检查完成！");
        var_dump(str_repeat("=", 60));
        var_dump("总记录数: " . count($allRecords));
        var_dump("正确记录数: " . count($correctRecords));
        var_dump("错误记录数: " . count($errorRecords));

        if (! empty($errorRecords)) {
            var_dump("\n错误记录详情：");
            foreach ($errorRecords as $error) {
                var_dump("\n  ID: {$error['id']}, record_id: {$error['record_id']}");
                foreach ($error['errors'] as $errorMsg) {
                    var_dump("    - {$errorMsg}");
                }
            }
        }

        // 6. 保存检查结果到文件
        $this->saveResultToFile($errorRecords, $correctRecords, count($allRecords));

        var_dump("\n检查结果已保存到文件");

        // 7. 如果有错误记录，执行自动修复
        if (! empty($errorRecords)) {
            var_dump("\n" . str_repeat("=", 60));
            var_dump("开始自动修复错误记录...");
            var_dump(str_repeat("=", 60));
            $this->fixErrorRecords($errorRecords);
        }
    }

    /**
     * 获取固定的数据结构模板（带类型信息）
     * @return array 返回每个字段的必需键结构和类型
     */
    private function getFixedTemplateStructure()
    {
        return [
            'fc'            => [
                'advice:string',
                'communication_willingness:string',
                'speech_rate:string',
                'pauses_and_hesitations:string',
                'answer_association:string',
            ],
            'vocabulary'    => [
                'overall_evaluation:string',
                'lexical_inaccuracy_details:array',
                'lexical_inaccuracy_details[]',
                'lexical_inaccuracy_details[0].error_type:string',
                'lexical_inaccuracy_details[0].error_instances:array',
                'lexical_inaccuracy_details[0].error_instances[]',
                'lexical_inaccuracy_details[0].error_instances[0].original_sentence:string',
                'lexical_inaccuracy_details[0].error_instances[0].error_word:string',
                'lexical_inaccuracy_details[0].error_instances[0].corrected_sentence:string',
                'lexical_inaccuracy_details[0].error_instances[0].corrected_word:string',
                'redundant_vocabulary_pairs:array',
                'redundant_vocabulary_pairs[]',
                'redundant_vocabulary_pairs[0].redundant_term:string',
                'redundant_vocabulary_pairs[0].recommended_replacement:string',
                'topic_relevant_vocab_suggestions:array',
                'topic_relevant_vocab_suggestions[]',
                'topic_relevant_vocab_suggestions[0].off_topic_term:string',
                'topic_relevant_vocab_suggestions[0].topic_aligned_replacement:string',
            ],
            'pron_accuracy' => [
                'overall_evaluation:string',
                'worst_top:array',
                'worst_top[]',
                'worst_top[0].text:string',
                'worst_top[0].part:integer',
                'worst_top[0].student_conversation_idx:integer',
                'worst_top[0].start_time:integer',
                'worst_top[0].end_time:integer',
                'worst_top[0].prev_word_end_time:integer',
                'worst_top[0].next_word_start_time:integer',
            ],
            'gra'           => [
                'advice:string',
                'issue_category:array',
                'issue_category[]',
                'issue_category[0].title:string',
                'issue_category[0].issue_detail:array',
                'issue_category[0].issue_detail[]',
                'issue_category[0].issue_detail[0].origin_sentence:string',
                'issue_category[0].issue_detail[0].origin_sentence_diff:array',
                'issue_category[0].issue_detail[0].origin_sentence_diff[]',
                'issue_category[0].issue_detail[0].origin_sentence_diff[0].start:integer',
                'issue_category[0].issue_detail[0].origin_sentence_diff[0].end:integer',
                'issue_category[0].issue_detail[0].correction:string',
                'issue_category[0].issue_detail[0].correction_diff:array',
                'issue_category[0].issue_detail[0].correction_diff[]',
                'issue_category[0].issue_detail[0].correction_diff[0].start:integer',
                'issue_category[0].issue_detail[0].correction_diff[0].end:integer',
            ],
            'summary'       => [
                'overall_evaluation:string',
            ],
        ];
    }

    /**
     * 从阿里云 OSS 下载文件内容（支持重试）
     * @param string $ossPath OSS 文件路径
     * @param string $fieldName 字段名（用于日志）
     * @return string|false 文件内容或 false（失败）
     */
    private function downloadOssFile($ossPath, $fieldName)
    {
        if (empty($ossPath)) {
            var_dump("  警告: {$fieldName} 字段为空");
            return false;
        }

        $maxRetries = 3;
        $retryCount = 0;

        while ($retryCount < $maxRetries) {
            try {
                $oss    = $this->getOssClient();
                $bucket = Yii::$app->params['oss']['bucket'];

                // 移除开头的斜杠
                $object = ltrim($ossPath, '/');

                // 从阿里云下载文件内容
                $content = $oss->getObject($bucket, $object);

                return $content;
            } catch (OssException $e) {
                $retryCount++;
                $errorMsg = $e->getMessage();

                if ($retryCount < $maxRetries) {
                    var_dump("  警告: 下载 {$fieldName} 失败 (尝试 {$retryCount}/{$maxRetries}): {$errorMsg}，正在重试...");
                    sleep(1); // 等待1秒后重试
                } else {
                    var_dump("  错误: 下载 {$fieldName} 失败 (已重试 {$maxRetries} 次): {$errorMsg}");
                    return false;
                }
            }
        }

        return false;
    }

    /**
     * 获取 OSS 客户端
     * @return OssClient
     */
    private function getOssClient()
    {
        $accessKeyId     = Yii::$app->params['oss']['accessKeyId'];
        $accessKeySecret = Yii::$app->params['oss']['accessKeySecret'];
        $endpoint        = Yii::$app->params['oss']['endPoint'];
        return new OssClient($accessKeyId, $accessKeySecret, $endpoint);
    }

    /**
     * 解析 JSON 内容并获取其结构（所有的 key 路径）
     * @param string $content JSON 字符串
     * @return array|false 结构数组或 false（失败）
     */
    private function getJsonStructure($content)
    {
        if ($content === false || empty($content)) {
            return false;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        // 提取结构时同时保留类型信息
        return $this->extractKeysWithTypes($data);
    }

    /**
     * 递归提取所有的 key 路径
     * @param mixed $data 数据
     * @param string $prefix 前缀路径
     * @return array key 路径数组
     */
    private function extractKeys($data, $prefix = '')
    {
        $keys = [];

        if (is_array($data)) {
            // 检查是否为关联数组（map）还是索引数组（list）
            if (empty($data)) {
                $keys[] = $prefix . '[]';
            } elseif (array_keys($data) === range(0, count($data) - 1)) {
                // 索引数组
                $keys[] = $prefix . '[]';
                // 只检查第一个元素的结构（假设数组元素结构一致）
                if (isset($data[0])) {
                    $subKeys = $this->extractKeys($data[0], $prefix . '[0]');
                    $keys    = array_merge($keys, $subKeys);
                }
            } else {
                // 关联数组
                foreach ($data as $key => $value) {
                    $currentPath = $prefix === '' ? $key : $prefix . '.' . $key;
                    $keys[]      = $currentPath;
                    if (is_array($value)) {
                        $subKeys = $this->extractKeys($value, $currentPath);
                        $keys    = array_merge($keys, $subKeys);
                    }
                }
            }
        }

        return array_unique($keys);
    }

    /**
     * 递归提取所有的 key 路径及对应的数据类型
     * @param mixed $data 数据
     * @param string $prefix 前缀路径
     * @return array key 路径数组，包含类型信息
     */
    private function extractKeysWithTypes($data, $prefix = '')
    {
        $keys = [];

        if (is_array($data)) {
            // 检查是否为关联数组（map）还是索引数组（list）
            if (empty($data)) {
                $keys[] = $prefix . '[]';
            } elseif (array_keys($data) === range(0, count($data) - 1)) {
                // 索引数组
                $keys[] = $prefix . '[]';
                // 只检查第一个元素的结构（假设数组元素结构一致）
                if (isset($data[0])) {
                    $subKeys = $this->extractKeysWithTypes($data[0], $prefix . '[0]');
                    $keys    = array_merge($keys, $subKeys);
                }
            } else {
                // 关联数组
                foreach ($data as $key => $value) {
                    $currentPath = $prefix === '' ? $key : $prefix . '.' . $key;

                    // 添加类型信息
                    if (is_array($value)) {
                        // 对于数组值，我们仍然需要递归提取子结构
                        $subKeys = $this->extractKeysWithTypes($value, $currentPath);
                        $keys    = array_merge($keys, $subKeys);
                    } else {
                        // 对于标量值，添加类型信息
                        $type   = gettype($value);
                        $keys[] = $currentPath . ':' . $type;
                    }
                }
            }
        } else {
            // 处理非数组类型的根值
            $type   = gettype($data);
            $keys[] = $prefix . ':' . $type;
        }

        return array_unique($keys);
    }

    /**
     * 对比两个结构是否一致（检查存在的key类型是否正确）
     * @param array $template 模板结构（必需的key列表）
     * @param array $current 当前结构
     * @return true|string true 表示一致，字符串表示差异描述
     */
    private function compareStructure($template, $current)
    {
        // 创建一个映射来存储当前结构中的键和类型
        $currentMap = [];
        foreach ($current as $item) {
            // 分离键名和类型
            if (strpos($item, ':') !== false) {
                list($key, $type) = explode(':', $item, 2);
                $currentMap[$key] = $type;
            } else {
                // 兼容旧格式
                $currentMap[$item] = 'unknown';
            }
        }

        // 检查模板中的每个必需键
        $typeMismatchKeys = [];

        foreach ($template as $templateItem) {
            // 分离键名和预期类型（如果有的话）
            $expectedType = null;
            if (strpos($templateItem, ':') !== false) {
                list($requiredKey, $expectedType) = explode(':', $templateItem, 2);
            } else {
                $requiredKey = $templateItem;
            }

            // 只有当键存在时才检查类型
            if (isset($currentMap[$requiredKey])) {
                // 如果指定了预期类型，则检查类型是否匹配
                if ($expectedType && $currentMap[$requiredKey] !== $expectedType && $currentMap[$requiredKey] !== 'NULL') {
                    // 特殊处理：整数和双精度浮点数都视为数值类型
                    if (! (($expectedType === 'integer' && $currentMap[$requiredKey] === 'double') ||
                        ($expectedType === 'double' && $currentMap[$requiredKey] === 'integer'))) {
                        $typeMismatchKeys[] = "{$requiredKey} (期望: {$expectedType}, 实际: {$currentMap[$requiredKey]})";
                    }
                }
            }
            // 如果键不存在，不报错（符合要求：字段可以不存在）
        }

        // 只报告类型不匹配的错误
        if (! empty($typeMismatchKeys)) {
            return "类型不匹配: " . implode(', ', $typeMismatchKeys);
        }

        return true;
    }

    /**
     * 自动修复错误记录
     * @param array $errorRecords 错误记录数组
     */
    private function fixErrorRecords($errorRecords)
    {
        // 提取所有错误记录的 record_id
        $recordIds = array_column($errorRecords, 'record_id');

        if (empty($recordIds)) {
            var_dump("没有需要修复的记录");
            return;
        }

        var_dump("\n错误的 record_id 列表: " . implode(', ', $recordIds));
        var_dump("共 " . count($recordIds) . " 条记录需要修复\n");

        // 1. 更新 simulate_exam_speaking 表，设置 status=5
        // var_dump("正在更新 simulate_exam_speaking 表...");
        // try {
        //     $affectedRows1 = SimulateExamSpeaking::updateAll(
        //         ['status' => 5],
        //         ['id' => $recordIds]
        //     );
        //     var_dump("✓ simulate_exam_speaking 表更新成功，影响 {$affectedRows1} 行");
        // } catch (\Exception $e) {
        //     var_dump("✗ simulate_exam_speaking 表更新失败: " . $e->getMessage());
        // }

        // // 2. 更新 sys_ai_task 表，设置 status=1，条件是 type=8 且 record_id in (record_ids)
        // var_dump("\n正在更新 sys_ai_task 表...");
        // try {
        //     $affectedRows2 = SysAiTask::updateAll(
        //         ['status' => 1],
        //         [
        //             'type' => 8,
        //             'record_id' => $recordIds
        //         ]
        //     );
        //     var_dump("✓ sys_ai_task 表更新成功，影响 {$affectedRows2} 行");
        // } catch (\Exception $e) {
        //     var_dump("✗ sys_ai_task 表更新失败: " . $e->getMessage());
        // }

        var_dump("\n" . str_repeat("=", 60));
        var_dump("修复完成！");
        // var_dump("simulate_exam_speaking 更新: {$affectedRows1} 条");
        // var_dump("sys_ai_task 更新: {$affectedRows2} 条");
        var_dump(str_repeat("=", 60));
    }

    /**
     * 保存检查结果到文件
     * @param array $errorRecords 错误记录
     * @param array $correctRecords 正确记录
     * @param int $totalCount 总记录数
     */
    private function saveResultToFile($errorRecords, $correctRecords, $totalCount)
    {
        $local_path = dirname(__FILE__, 2);
        $timestamp  = date('Y-m-d_H-i-s');
        $filename   = $local_path . '/runtime/tmp/speaking_report_check_' . $timestamp . '.txt';

        $content = "检查报告 - " . date('Y-m-d H:i:s') . "\n";
        $content .= str_repeat("=", 80) . "\n\n";
        $content .= "总记录数: {$totalCount}\n";
        $content .= "正确记录数: " . count($correctRecords) . "\n";
        $content .= "错误记录数: " . count($errorRecords) . "\n\n";

        if (! empty($errorRecords)) {
            $content .= str_repeat("-", 80) . "\n";
            $content .= "错误记录详情：\n";
            $content .= str_repeat("-", 80) . "\n\n";
            foreach ($errorRecords as $error) {
                $content .= "ID: {$error['id']}, record_id: {$error['record_id']}\n";
                foreach ($error['errors'] as $errorMsg) {
                    $content .= "  - {$errorMsg}\n";
                }
                $content .= "\n";
            }
        }

        if (! empty($correctRecords)) {
            $content .= str_repeat("-", 80) . "\n";
            $content .= "正确记录列表：\n";
            $content .= str_repeat("-", 80) . "\n\n";
            foreach ($correctRecords as $record) {
                $content .= "ID: {$record['id']}, record_id: {$record['record_id']}\n";
            }
        }

        // 确保目录存在
        $dir = dirname($filename);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($filename, $content);
        var_dump("结果文件: {$filename}");
    }
}

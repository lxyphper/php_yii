<?php

namespace console\controllers;

use app\models\LmsCourse;
use app\models\LmsLesson;
use app\models\LmsResource;
use app\models\LmsSection;
use app\models\LmsSectionLesson;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\FileHelper;
use Yii;

/**
 * 控制台脚本：导出 LMS 课程下的章节/小节（仅视频类型）及资源信息。
 */
class LmsController extends BaseController
{
    private const VIDEO_SECTION_TYPE = 1;

    /**
     * 示例：php yii lms/export-video-structure "73,74,75,76"
     *
     * @param string $courseIds 逗号或空格分隔的课程 ID 列表
     */
    public function actionExportVideoStructure(string $courseIds = '73,74,75,76'): int
    {
        $courseIdList = $this->normalizeCourseIds($courseIds);
        if (empty($courseIdList)) {
            $this->stderr("请至少提供一个有效的课程ID。\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $courses = LmsCourse::find()
            ->select(['id', 'name', 'category', 'level', 'status', 'weight', 'is_delete'])
            ->where(['id' => $courseIdList])
            ->indexBy('id')
            ->asArray()
            ->all();

        if (empty($courses)) {
            $this->stderr("提供的课程ID均不存在。\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $lessonRows = LmsLesson::find()
            ->select(['id', 'course_id', 'name', 'status', 'weight', 'is_delete'])
            ->where(['course_id' => $courseIdList, 'is_delete' => 2])
            ->orderBy(['weight' => SORT_ASC, 'id' => SORT_ASC])
            ->asArray()
            ->all();

        $lessonsByCourse = [];
        foreach ($lessonRows as $lesson) {
            $lessonsByCourse[$lesson['course_id']][] = $lesson;
        }

        $sectionRows = LmsSectionLesson::find()
            ->alias('lsl')
            ->select([
                'lesson_id' => 'lsl.lesson_id',
                'course_id' => 'lsl.course_id',
                'relation_weight' => 'lsl.weight',
                'section_id' => 's.id',
                'section_name' => 's.name',
                'section_sub_type' => 's.sub_type',
                'section_auth' => 's.auth',
                'section_desc' => 's.desc',
                'section_cover' => 's.cover_path',
                'section_status' => 's.status',
                'section_content' => 's.content',
                'resource_id' => 's.resource_id',
                'resource_name' => 'r.name',
                'resource_ali_id' => 'r.ali_resource_id',
                'resource_path' => 'r.resource_path',
                'resource_duration' => 'r.duration',
                'resource_status' => 'r.status',
            ])
            ->innerJoin(['s' => LmsSection::tableName()], 's.id = lsl.section_id')
            ->innerJoin(['ll' => LmsLesson::tableName()], 'll.id = lsl.lesson_id')
            ->leftJoin(['r' => LmsResource::tableName()], 'r.id = s.resource_id')
            ->where([
                'lsl.course_id' => $courseIdList,
                'lsl.is_delete' => 2,
                'll.is_delete' => 2,
                's.type' => self::VIDEO_SECTION_TYPE,
            ])
            ->orderBy([
                'lsl.course_id' => SORT_ASC,
                'll.weight' => SORT_ASC,
                'lsl.weight' => SORT_ASC,
                's.id' => SORT_ASC,
            ])
            ->asArray()
            ->all();

        $sectionsByLesson = [];
        foreach ($sectionRows as $row) {
            $sectionsByLesson[$row['lesson_id']][] = [
                'id' => (int)$row['section_id'],
                'name' => $row['section_name'],
                'sub_type' => isset($row['section_sub_type']) ? (int)$row['section_sub_type'] : null,
                'auth' => isset($row['section_auth']) ? (int)$row['section_auth'] : null,
                'status' => isset($row['section_status']) ? (int)$row['section_status'] : null,
                'cover_path' => $row['section_cover'],
                'desc' => $row['section_desc'],
                'content' => $row['section_content'],
                'weight' => isset($row['relation_weight']) ? (int)$row['relation_weight'] : null,
                'resource' => [
                    'id' => $row['resource_id'] !== null ? (int)$row['resource_id'] : null,
                    'name' => $row['resource_name'],
                    'ali_resource_id' => $row['resource_ali_id'],
                    'path' => $row['resource_path'],
                    'duration' => $row['resource_duration'] !== null ? (int)$row['resource_duration'] : null,
                    'status' => $row['resource_status'] !== null ? (int)$row['resource_status'] : null,
                ],
            ];
        }

        $result = [];
        $courseResourceSummary = [];
        foreach ($courseIdList as $courseId) {
            $courseData = $courses[$courseId] ?? null;
            if ($courseData === null) {
                $result[] = [
                    'course' => [
                        'id' => $courseId,
                        'missing' => true,
                    ],
                    'lessons' => [],
                ];
                continue;
            }

            $lessonPayloads = [];
            $courseAliResourceIds = [];
            foreach ($lessonsByCourse[$courseId] ?? [] as $lesson) {
                $sections = $sectionsByLesson[$lesson['id']] ?? [];
                foreach ($sections as $section) {
                    $aliId = $section['resource']['ali_resource_id'] ?? null;
                    if (!empty($aliId)) {
                        $courseAliResourceIds[] = $aliId;
                    }
                }

                $lessonPayloads[] = [
                    'id' => (int)$lesson['id'],
                    'name' => $lesson['name'],
                    'status' => (int)$lesson['status'],
                    'weight' => (int)$lesson['weight'],
                    'sections' => $sections,
                ];
            }

            $result[] = [
                'course' => [
                    'id' => (int)$courseData['id'],
                    'name' => $courseData['name'],
                    'category' => (int)$courseData['category'],
                    'level' => (int)$courseData['level'],
                    'status' => (int)$courseData['status'],
                    'weight' => (int)$courseData['weight'],
                    'is_delete' => (int)$courseData['is_delete'],
                ],
                'lessons' => $lessonPayloads,
            ];

            $courseResourceSummary[] = [
                'course_id' => (int)$courseId,
                'ali_resource_ids' => $courseAliResourceIds,
            ];
        }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            $this->stderr('JSON 序列化失败：' . json_last_error_msg() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $dir = Yii::getAlias('@app/runtime/tmp');
        if (!is_dir($dir) && !FileHelper::createDirectory($dir)) {
            $this->stderr("无法创建目录：{$dir}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $timestamp = date('Ymd_His');
        $fileName = sprintf(
            'lms_video_structure_%s_%s.json',
            implode('_', $courseIdList),
            $timestamp
        );
        $filePath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileName;

        if (file_put_contents($filePath, $json) === false) {
            $this->stderr("写入文件失败：{$filePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $resourceJson = json_encode($courseResourceSummary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($resourceJson === false) {
            $this->stderr('资源JSON 序列化失败：' . json_last_error_msg() . "\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $resourceFileName = sprintf(
            'lms_video_resource_ids_%s_%s.json',
            implode('_', $courseIdList),
            $timestamp
        );
        $resourceFilePath = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $resourceFileName;
        if (file_put_contents($resourceFilePath, $resourceJson) === false) {
            $this->stderr("写入资源文件失败：{$resourceFilePath}\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("导出完成：\n - 结构文件：{$filePath}\n - 资源ID文件：{$resourceFilePath}\n");
        return ExitCode::OK;
    }

    /**
     * 根据 unit_lesson_resource_map.json（value 为视频 ali_resource_id）
     * 在指定课程范围内（默认 73,74,75,76）反查 lms_section.name，并导出「视频ID => 小节名称」到 JSON 文件。
     * 仅统计未删除的课时/关联关系（is_delete=2）；若表存在 is_delete 字段，也会同步过滤小节/资源/课程的未删除数据。
     * 当小节名称为「考点讲解」时，为避免重名，会输出「课时名称 - 小节名称」。
     *
     * 示例：
     * - php yii lms/export-video-section-name-map
     * - php yii lms/export-video-section-name-map "@console/runtime/tmp/unit_lesson_resource_map.json" "@console/runtime/tmp/video_section_name_map.json" "73,74,75,76"
     */
    public function actionExportVideoSectionNameMap(
        string $inputFile = '@console/runtime/tmp/unit_lesson_resource_map.json',
        string $outputFile = '',
        string $courseIds = '73,74,75,76'
    ): int {
        $originalErrorReporting = error_reporting();
        error_reporting($originalErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        try {
            $courseIdList = $this->normalizeCourseIds($courseIds);
            if (empty($courseIdList)) {
                $this->stderr("请至少提供一个有效的课程ID。\n");
                return ExitCode::DATAERR;
            }

            $inputPath = Yii::getAlias($inputFile);
            if (!is_file($inputPath)) {
                $this->stderr("输入文件不存在：{$inputPath}\n");
                return ExitCode::NOINPUT;
            }

            $raw = file_get_contents($inputPath);
            if ($raw === false) {
                $this->stderr("读取输入文件失败：{$inputPath}\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $map = json_decode($raw, true);
            if (!is_array($map)) {
                $this->stderr("输入文件不是合法 JSON Object：{$inputPath}\n");
                return ExitCode::DATAERR;
            }

            $orderedVideoIds = [];
            $seenVideoIds = [];
            foreach ($map as $videoId) {
                if (!is_string($videoId)) {
                    continue;
                }
                $videoId = trim($videoId);
                if ($videoId === '') {
                    continue;
                }
                if (isset($seenVideoIds[$videoId])) {
                    continue;
                }
                $seenVideoIds[$videoId] = true;
                $orderedVideoIds[] = $videoId;
            }

            if (empty($orderedVideoIds)) {
                $this->stderr("未从输入文件中解析到任何视频ID：{$inputPath}\n");
                return ExitCode::DATAERR;
            }

            $dbSchema = Yii::$app->db->schema;
            $sectionTable = $dbSchema->getTableSchema(LmsSection::tableName());
            $resourceTable = $dbSchema->getTableSchema(LmsResource::tableName());
            $courseTable = $dbSchema->getTableSchema(LmsCourse::tableName());
            $sectionHasIsDelete = $sectionTable !== null && isset($sectionTable->columns['is_delete']);
            $resourceHasIsDelete = $resourceTable !== null && isset($resourceTable->columns['is_delete']);
            $courseHasIsDelete = $courseTable !== null && isset($courseTable->columns['is_delete']);

            /** @var array<string, array<string, bool>> $sectionNamesByVideoId */
            $sectionNamesByVideoId = [];
            foreach (array_chunk($orderedVideoIds, 500) as $chunk) {
                $where = [
                    'lsl.course_id' => $courseIdList,
                    'lsl.is_delete' => 2,
                    'll.is_delete' => 2,
                    's.type' => self::VIDEO_SECTION_TYPE,
                    'r.ali_resource_id' => $chunk,
                ];
                if ($sectionHasIsDelete) {
                    $where['s.is_delete'] = 2;
                }
                if ($resourceHasIsDelete) {
                    $where['r.is_delete'] = 2;
                }
                if ($courseHasIsDelete) {
                    $where['c.is_delete'] = 2;
                }

                $rows = LmsSectionLesson::find()
                    ->alias('lsl')
                    ->select([
                        'video_id' => 'r.ali_resource_id',
                        'section_id' => 's.id',
                        'section_name' => 's.name',
                        'lesson_name' => 'll.name',
                    ])
                    ->innerJoin(['s' => LmsSection::tableName()], 's.id = lsl.section_id')
                    ->innerJoin(['ll' => LmsLesson::tableName()], 'll.id = lsl.lesson_id')
                    ->innerJoin(['r' => LmsResource::tableName()], 'r.id = s.resource_id')
                    ->innerJoin(['c' => LmsCourse::tableName()], 'c.id = lsl.course_id')
                    ->where($where)
                    ->asArray()
                    ->all();

                foreach ($rows as $row) {
                    $videoId = $row['video_id'] ?? null;
                    if (!is_string($videoId) || $videoId === '') {
                        continue;
                    }
                    $sectionId = isset($row['section_id']) ? (int)$row['section_id'] : 0;
                    if ($sectionId <= 0) {
                        continue;
                    }

                    $sectionName = $row['section_name'] ?? null;
                    if (!is_string($sectionName) || $sectionName === '') {
                        continue;
                    }

                    $displayName = $sectionName;
                    if ($sectionName === '考点讲解') {
                        $lessonName = $row['lesson_name'] ?? null;
                        if (is_string($lessonName)) {
                            $lessonName = trim($lessonName);
                        }
                        if (is_string($lessonName) && $lessonName !== '') {
                            $displayName = $lessonName . ' - ' . $sectionName;
                        }
                    }

                    $sectionNamesByVideoId[$videoId][$displayName] = true;
                }
            }

            $output = [];
            $missingCount = 0;
            foreach ($orderedVideoIds as $videoId) {
                $names = array_keys($sectionNamesByVideoId[$videoId] ?? []);
                if (empty($names)) {
                    $missingCount++;
                    $output[$videoId] = null;
                    continue;
                }
                $output[$videoId] = count($names) === 1 ? $names[0] : $names;
            }

            $json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            if ($json === false) {
                $this->stderr('JSON 序列化失败：' . json_last_error_msg() . "\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $outputPath = $outputFile !== '' ? Yii::getAlias($outputFile) : '';
            if ($outputPath === '') {
                $timestamp = date('Ymd_His');
                $outputPath = Yii::getAlias("@console/runtime/tmp/video_section_name_map_{$timestamp}.json");
            }

            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir) && !FileHelper::createDirectory($outputDir)) {
                $this->stderr("无法创建输出目录：{$outputDir}\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            if (file_put_contents($outputPath, $json) === false) {
                $this->stderr("写入输出文件失败：{$outputPath}\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $this->stdout(sprintf(
                "导出完成：%s\n视频ID总数：%d，未匹配到小节：%d\n",
                $outputPath,
                count($orderedVideoIds),
                $missingCount
            ));

            return ExitCode::OK;
        } finally {
            error_reporting($originalErrorReporting);
        }
    }

    /**
     * @return int[]
     */
    private function normalizeCourseIds(string $courseIds): array
    {
        $parts = preg_split('/[\s,]+/', $courseIds, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        $ids = [];
        foreach ($parts as $part) {
            $id = (int)$part;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }
}

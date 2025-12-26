<?php

/**
 * =====================================================================================
 * StudentController 控制器脚本文档
 * =====================================================================================
 * 
 * 本控制器包含以下可执行脚本命令:
 * 
 * -----------------------------------------------------------------------------------------
 * 1. actionDealBeiWaiAccount - 处理北外账号
 *    命令: php yii student/deal-bei-wai-account
 *    参数: 无
 *    用途: 批量创建北外学生账号，账号格式为 "bfsu-{学号}"，密码为学号后6位
 * 
 * -----------------------------------------------------------------------------------------
 * 2. actionDealWithCourse - 处理课程数据
 *    命令: php yii student/deal-with-course
 *    参数: 无
 *    用途: 从 JSON 文件导入课程数据，处理课程分类、单元和课程信息
 * 
 * -----------------------------------------------------------------------------------------
 * 3. actionAddUser - 添加用户到班级
 *    命令: php yii student/add-user
 *    参数: 无
 *    用途: 将预定义的学生列表添加到指定班级
 * 
 * -----------------------------------------------------------------------------------------
 * 4. actionExportRecord - 导出单个班级学生练习记录
 *    命令: php yii student/export-record {class_id} {start_date} {end_date} {format}
 *    参数:
 *      - class_id (int): 班级ID
 *      - start_date (string): 开始日期，格式 Y-m-d，例如: 2025-05-12
 *      - end_date (string): 结束日期，格式 Y-m-d，例如: 2025-08-04
 *      - format (string): 导出格式，可选 csv 或 xlsx，默认 csv
 *    用途: 导出指定班级在指定时间段内的学生练习记录统计
 *    示例: php yii student/export-record 502 2025-10-12 2025-11-04 xlsx
 * 
 * -----------------------------------------------------------------------------------------
 * 5. actionExportRecordMulti - 导出多个班级学生练习记录并汇总
 *    命令: php yii student/export-record-multi "{class_ids}" {start_date} {end_date} {file_name} {format}
 *    参数:
 *      - class_ids (string): 逗号或空格分隔的班级ID列表
 *      - start_date (string): 开始日期，格式 Y-m-d
 *      - end_date (string): 结束日期，格式 Y-m-d
 *      - file_name (string): 导出文件名（不包含扩展名）
 *      - format (string): 导出格式，可选 csv 或 xlsx，默认 csv
 *    用途: 导出多个班级的学生练习记录并生成汇总统计
 *    示例: php yii student/export-record-multi "517" 2025-11-24 2025-12-26 汇总文件 xlsx
 * 
 * -----------------------------------------------------------------------------------------
 * 6. actionCreateSpecifyAccount - 生成试用账号
 *    命令: php yii student/create-specify-account
 *    参数: 无
 *    用途: 批量生成100个随机试用账号，有效期7天
 * 
 * -----------------------------------------------------------------------------------------
 * 7. actionCreateStatisticsData - 生成统计数据
 *    命令: php yii student/create-statistics-data
 *    参数: 无
 *    用途: 为测试学生ID生成模拟统计数据，包含总统计、听力、阅读、写作、口语和专项提升数据
 * 
 * =====================================================================================
 * 辅助方法（非命令行脚本）:
 * =====================================================================================
 * - randomAccount(): 生成随机6位账号
 * - formatExportDuration(): 将秒数转换为小时/分钟格式
 * - buildExportRecordRowTemplate(): 初始化导出行数据模板
 * - createTotal(): 生成总统计数据
 * - createListening(): 生成听力统计数据
 * - createReading(): 生成阅读统计数据
 * - createWriting(): 生成写作统计数据
 * - createSpeaking(): 生成口语统计数据
 * - createSpecial(): 生成专项提升统计数据
 * - getWritingTypes(): 获取写作题目类型
 * - getListeningTypes(): 获取听力题目类型
 * - getReadingTypes(): 获取阅读题目类型
 * - getSpeakingTypes(): 获取口语题目类型
 * =====================================================================================
 */

namespace console\controllers;

use app\models\BasicTrainingListeningGrammar;
use app\models\BasicTrainingReadingGrammar;
use app\models\BasicTrainingWritingGrammar;
use app\models\Course;
use app\models\CourseType;
use app\models\CourseUnit;
use app\models\EduClass;
use app\models\EduClassStudent;
use app\models\EduClassTeacher;
use app\models\EduTeacher;
use app\models\ExamCollectionRecord;
use app\models\ExamQuestionCollection;
use app\models\ListeningExamQuestionType;
use app\models\ListeningExamRecord;
use app\models\ReadingExamQuestionType;
use app\models\ReadingExamRecord;
use app\models\SimulateExamListening;
use app\models\SimulateExamReading;
use app\models\SimulateExamRecord;
use app\models\SimulateExamWriting;
use app\models\SpeakingAdvanceRecord;
use app\models\SpeakingExamDialogueLog;
use app\models\SpeakingSpecialItemTopic;
use app\models\StatisticsDayListening;
use app\models\StatisticsDayReading;
use app\models\StatisticsDaySpeaking;
use app\models\StatisticsDaySpecial;
use app\models\StatisticsDayTotal;
use app\models\StatisticsDayWriting;
use app\models\Student;
use app\models\StudentSpecifyAccount;
use app\models\WritingBigEssayRecord;
use app\models\WritingBigEssaySampleText;
use app\models\WritingEssayRecord;
use app\models\WritingPracticeRecord;
use yii\console\Controller;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

class StudentController extends BaseController
{
    public function actionDealBeiWaiAccount()
    {
        $list = ["202420902470"];
        foreach ($list as $value) {
            $name = "bfsu-" . $value;
            $password = substr($value, -6, strlen($value));
            $data = Student::find()->where(['account' => $name])->one();
            if (empty($data)) {
                var_dump("$name 不存在，创建");
                $model = new Student();
                $model->account = $name;
                $model->password = $password;
                $model->vip_level = 1;
                $model->reading_vip_level = 1;
                $model->listening_vip_level = 1;
                $model->speaking_vip_level = 1;
                $model->exam_type = 1;
                $model->coach_num = -1;
                $model->review_num = -1;
                $model->essay_num = -1;
                $model->is_approval = 1;
                $model->country_id = 1;
                $model->source = 3;
                try {
                    $model->insert();
                } catch (\Throwable $e) {
                    var_dump("$name 写入失败" . $e->getMessage());
                }
                var_dump("$name 写入完成");
            } else {
                var_dump("$name 已存在");
            }
        }
    }

    public function actionDealWithCourse()
    {
        $local_path = dirname(__FILE__, 2);
        $question_file = $local_path . '/runtime/tmp/results.json';
        $question_content = file_get_contents($question_file);
        $question_content = json_decode($question_content);
        $data = $question_content->lesson_list;
        //1听力 2阅读 3写作 4口语
        $subjectMap = [
            "听力" => 1,
            "阅读" => 2,
            "写作" => 3,
            "口语" => 4,
        ];
        foreach ($data as $value) {
            //处理一级分类
            $course_type_info = CourseType::find()->where(['source_id' => $value->course_id])->one();
            if (empty($course_type_info)) {
                var_dump("course_type $value->course_text 不存在，插入新数据");
                $course_type_info = new CourseType();
                $course_type_info->name = $value->course_text;
                $course_type_info->source_id = $value->course_id;
                try {
                    $course_type_info->insert(false);
                } catch (\Throwable $e) {
                    var_dump("$value->course_text 插入失败，err：" . $e->getMessage());
                    die;
                }
            }
            $course_type = $course_type_info->id;
            //处理二级分类
            $unit_info = CourseUnit::find()->where(['source_id' => $value->unit_id])->one();
            if (empty($unit_info)) {
                var_dump("$value->unit_text 不存在，插入新数据");
                $unit_info = new CourseUnit();
                $unit_info->name = $value->unit_text;
                $unit_info->source_id = $value->unit_id;
                $unit_info->type = $course_type;
                try {
                    $unit_info->insert(false);
                } catch (\Throwable $e) {
                    var_dump("unit $value->unit_text 插入失败，err：" . $e->getMessage());
                    die;
                }
            }
            $unit = $unit_info->id;
            $course_info = Course::find()->where(['source_id' => $value->id])->one();
            if (empty($course_info)) {
                $course_info = new Course();
                $course_info->name = $value->lesson;
                $course_info->duration = $value->duration * 1000;
                $course_info->config = "https://duy-ielts.oss-cn-hangzhou.aliyuncs.com/LMS" .  $value->video;
                $course_info->subject = $subjectMap[$value->subject];
                $course_info->unit = $unit;
                $course_info->category = 1;
                try {
                    $course_info->insert(false);
                } catch (\Throwable $e) {
                    var_dump("Course $value->unit_text 插入失败，err：" . $e->getMessage());
                    die;
                }
            } else {
                $course_info->config = "https://duy-ielts.oss-cn-hangzhou.aliyuncs.com/LMS" . $value->video;
            }
        }
    }

    public function actionAddUser()
    {
        $list_164 = [
            "刘隽熙" => "18813199066",
            "张冉" => "18518758599",
            "郑羽珊" => "16636088688",
            "李凌萱" => "18031257598",
            "于冰洁" => "15154110733",
            "李燕姝" => "15535510633",
            "主梓妤" => "18299803301",
            "姚耀" => "13041221565",
            "车昕芳" => "17333601688",
            "杨鹏程" => "18830832232",
            "张喆毓" => "15238647876",
            "姚金岐" => "18010419329",
        ];

        foreach ($list_164 as $name => $phone) {
            $data = Student::find()->where(['mobile' => $phone])->one();
            if (!empty($data)) {
                $relation = new EduClassStudent();
                $relation->class_id = 166;
                $relation->student_id = $data->id;
                $relation->student_name = $name;
                $relation->insert();
                var_dump("$name 添加成功");
            } else {
                var_dump("$phone 不存在");
            }
        }
    }


    /**
     * 导出学生记录
     * exemple: php yii student/export-record 502 2025-10-12 2025-11-04 xlsx
     * @param int $class_id 班级ID
     * @param string $start_date 开始日期 格式: Y-m-d 例如: 2025-05-12
     * @param string $end_date 结束日期 格式: Y-m-d 例如: 2025-08-04
     * @param string $format 导出格式: csv 或 xlsx，默认 csv
     * @return string
     * @throws \Exception
     */
    public function actionExportRecord(int $class_id, string $start_date, string $end_date, string $format = 'csv'): string
    {
        // 验证格式参数
        $format = strtolower($format);
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            var_dump("格式错误，只支持 csv 或 xlsx");
            return '';
        }

        if ($class_id === 0) {
            var_dump("class_id 不能为0");
            return '';
        }

        $class_info = EduClass::find()->where(['id' => $class_id])->one();
        if ($class_info === null) {
            var_dump("class_id 不存在");
            return '';
        }

        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);

        // 验证日期格式
        if ($start_time === false || $end_time === false) {
            var_dump("日期格式错误，请使用 Y-m-d 格式，例如: 2025-05-12");
            return '';
        }

        if ($start_time > $end_time) {
            var_dump("开始日期不能大于结束日期");
            return '';
        }

        // 格式化日期用于文件名
        $start_date_formatted = date('md', $start_time);
        $end_date_formatted = date('md', $end_time);
        $class_name = $class_info->name . "_{$start_date_formatted}-{$end_date_formatted}";
        $title = [
            "姓名",
            "学号",
            "手机号",
            "是否是老师",
            "总次数",
            "总时长",
            "听力专项提升练习次数",
            "听力专项提升练习时长",
            "听力专项提升练习平均正确率",
            "阅读专项提升练习次数",
            "阅读专项提升练习时长",
            "阅读专项提升练习平均正确率",
            "写作专项提升练习次数",
            "写作专项提升练习时长",
            "写作专项提升练习平均正确率",
            "听力真题练习次数",
            "听力真题练习时长",
            "听力真题练习平均正确率",
            "阅读真题练习次数",
            "阅读真题练习时长",
            "阅读真题练习平均正确率",
            "大作文写作次数",
            "小作文写作次数",
            "大作文范文次数",
            "大作文练习次数",
            "口语机经练习次数",
            "口语机经练习时长",
            "口语进阶练习次数",
            "口语进阶练习时长",
            "模考次数",
            "模考时长"
        ];
        $data = [];

        //获取学生列表
        $student_list = EduClassStudent::find()->where(['class_id' => $class_id])->all();
        if (empty($student_list)) {
            var_dump("学生列表为空");
        }
        $student_ids = [];
        foreach ($student_list as $student) {
            $student_ids[] = $student->student_id;
            $data[$student->student_id] = [
                "name" => $student->student_name,
                "account" => "",
                "mobile" => "",
                "is_teacher" => "否",
                "total_num" => 0,
                "total_time" => 0,
                "listening_special_improve_num" => 0,
                "listening_special_improve_time" => 0,
                "listening_special_improve_rate" => 0,
                "reading_special_improve_num" => 0,
                "reading_special_improve_time" => 0,
                "reading_special_improve_rate" => 0,
                "writing_special_improve_num" => 0,
                "writing_special_improve_time" => 0,
                "writing_special_improve_rate" => 0,
                "listening_real_num" => 0,
                "listening_real_time" => 0,
                "listening_real_rate" => 0,
                "reading_real_num" => 0,
                "reading_real_time" => 0,
                "reading_real_rate" => 0,
                "big_essay_num" => 0,
                "small_essay_num" => 0,
                "big_essay_model_num" => 0,
                "big_essay_exam_num" => 0,
                "oral_num" => 0,
                "oral_time" => 0,
                "oral_advanced_num" => 0,
                "oral_advanced_time" => 0,
                "mock_num" => 0,
                "mock_time" => 0
            ];
        }

        //获取老师列表
        $teacher_list = EduClassTeacher::find()->where(['class_id' => $class_id])->asArray()->all();
        if (empty($teacher_list)) {
            var_dump("老师列表为空");
        }
        $teacher_ids = array_column($teacher_list, 'teacher_id');
        //获取老师信息
        $teacher_info_list = EduTeacher::find()->where(['id' => $teacher_ids])->all();
        if (empty($teacher_info_list)) {
            var_dump("老师信息列表为空");
        }
        foreach ($teacher_info_list as $teacher) {
            $student_ids[] = $teacher->user_id;
            $data[$teacher->user_id] = [
                "name" => $teacher->name,
                "account" => "",
                "mobile" => "",
                "is_teacher" => "是",
                "total_num" => 0,
                "total_time" => 0,
                "listening_special_improve_num" => 0,
                "listening_special_improve_time" => 0,
                "listening_special_improve_rate" => 0,
                "reading_special_improve_num" => 0,
                "reading_special_improve_time" => 0,
                "reading_special_improve_rate" => 0,
                "writing_special_improve_num" => 0,
                "writing_special_improve_time" => 0,
                "writing_special_improve_rate" => 0,
                "listening_real_num" => 0,
                "listening_real_time" => 0,
                "listening_real_rate" => 0,
                "reading_real_num" => 0,
                "reading_real_time" => 0,
                "reading_real_rate" => 0,
                "big_essay_num" => 0,
                "small_essay_num" => 0,
                "big_essay_model_num" => 0,
                "big_essay_exam_num" => 0,
                "oral_num" => 0,
                "oral_time" => 0,
                "oral_advanced_num" => 0,
                "oral_advanced_time" => 0,
                "mock_num" => 0,
                "mock_time" => 0
            ];
        }



        //获取用户信息
        $user_list = Student::find()->where(['id' => $student_ids])->all();
        if (empty($user_list)) {
            var_dump("用户列表为空");
        }

        foreach ($user_list as $user) {
            foreach ($student_list as $student) {
                if ($user->id == $student->student_id) {
                    $data[$student->student_id]['mobile'] = $user->mobile;
                    $data[$student->student_id]['account'] = $user->account;
                }
            }
            foreach ($teacher_info_list as $teacher) {
                if ($user->id == $teacher->user_id) {
                    $data[$teacher->user_id]['mobile'] = $user->mobile;
                    $data[$teacher->user_id]['account'] = $user->account;
                }
            }
        }

        //查询题集练习记录
        $collection_record = ExamCollectionRecord::find()->where(['student_id' => $student_ids, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($collection_record)) {
            $collection_ids = [];
            foreach ($collection_record as $record) {
                $collection_ids[] = $record->collection_id;
            }
            //获取题集信息
            $collection_list = ExamQuestionCollection::find()->where(['id' => $collection_ids])->all();
            if (empty($collection_list)) {
                var_dump("题集列表为空");
            }
            $writing_special = [];
            $reading_special = [];
            $listening_special = [];
            foreach ($collection_list as $collection) {
                foreach ($collection_record as $record) {
                    if ($collection->id == $record->collection_id) {
                        $data[$record->student_id]['total_num']++;
                        $data[$record->student_id]['total_time'] += $record->duration;
                        //1写作 2阅读 3听力
                        if ($collection->type == 1) {
                            $data[$record->student_id]['writing_special_improve_num']++;
                            $data[$record->student_id]['writing_special_improve_time'] += $record->duration;
                            $writing_special[$record->student_id][] = $record->rate;
                        } else if ($collection->type == 2) {
                            $data[$record->student_id]['reading_special_improve_num']++;
                            $data[$record->student_id]['reading_special_improve_time'] += $record->duration;
                            $reading_special[$record->student_id][] = $record->rate;
                        } else if ($collection->type == 3) {
                            $data[$record->student_id]['listening_special_improve_num']++;
                            $data[$record->student_id]['listening_special_improve_time'] += $record->duration;
                            $listening_special[$record->student_id][] = $record->rate;
                        }
                    }
                }
            }
        }
        if (!empty($writing_special)) {
            foreach ($writing_special as $key => $val) {
                $data[$key]['writing_special_improve_rate'] = round(array_sum($val) / count($val), 4);
            }
        }
        if (!empty($reading_special)) {
            foreach ($reading_special as $key => $val) {
                $data[$key]['reading_special_improve_rate'] = round(array_sum($val) / count($val), 4);
            }
        }
        if (!empty($listening_special)) {
            foreach ($listening_special as $key => $val) {
                $data[$key]['listening_special_improve_rate'] = round(array_sum($val) / count($val), 4);
            }
        }

        //查询听力真题练习记录
        $listening_real = [];
        $listening_record = ListeningExamRecord::find()->where(['student_id' => $student_ids, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($listening_record)) {
            foreach ($listening_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['total_num']++;
                        $data[$record->student_id]['total_time'] += $record->duration;
                        $data[$record->student_id]['listening_real_num']++;
                        $data[$record->student_id]['listening_real_time'] += $record->duration;
                        $listening_real[$record->student_id][] = $record->correct / $record->total;
                    }
                }
            }
        }
        if (!empty($listening_real)) {
            foreach ($listening_real as $key => $val) {
                $data[$key]['listening_real_rate'] = round(array_sum($val) / count($val), 4);
            }
        }

        //查询阅读真题练习记录
        $reading_real = [];
        $reading_record = ReadingExamRecord::find()->where(['student_id' => $student_ids, 'status' => 2])->andWhere(['>=', 'finished_time', $start_time])->andWhere(['<=', 'finished_time', $end_time])->all();
        if (!empty($reading_record)) {
            foreach ($reading_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['total_num']++;
                        $data[$record->student_id]['total_time'] += $record->duration;
                        $data[$record->student_id]['reading_real_num']++;
                        $data[$record->student_id]['reading_real_time'] += $record->duration;
                        $reading_real[$record->student_id][] = $record->correct / $record->total;
                    }
                }
            }
        }
        if (!empty($reading_real)) {
            foreach ($reading_real as $key => $val) {
                $data[$key]['reading_real_rate'] = round(array_sum($val) / count($val), 4);
            }
        }

        //查询大作文写作记录
        $big_essay_record = WritingBigEssayRecord::find()->where(['student_id' => $student_ids, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($big_essay_record)) {
            foreach ($big_essay_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['big_essay_num']++;
                        $data[$record->student_id]['total_num']++;
                    }
                }
            }
        }

        //查询小作文写作记录
        $small_essay_record = WritingEssayRecord::find()->where(['student_id' => $student_ids, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($small_essay_record)) {
            foreach ($small_essay_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['small_essay_num']++;
                        $data[$record->student_id]['total_num']++;
                    }
                }
            }
        }

        //查询大作文范文练习记录
        $big_essay_model_record = WritingBigEssaySampleText::find()->where(['student_id' => $student_ids, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($big_essay_model_record)) {
            foreach ($big_essay_model_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['big_essay_model_num']++;
                        $data[$record->student_id]['total_num']++;
                    }
                }
            }
        }

        //查询大作文练习记录
        $big_essay_exam_record = WritingPracticeRecord::find()->where(['student_id' => $student_ids])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($big_essay_exam_record)) {
            foreach ($big_essay_exam_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['big_essay_exam_num']++;
                        $data[$record->student_id]['total_num']++;
                    }
                }
            }
        }

        //查询口语机经练习记录
        $oral_record = SpeakingExamDialogueLog::find()->where(['student_id' => $student_ids, 'role' => 1, 'type' => [1, 2]])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($oral_record)) {
            foreach ($oral_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['oral_num']++;
                        $data[$record->student_id]['oral_time'] += $record->duration / 1000;
                        $data[$record->student_id]['total_num']++;
                        $data[$record->student_id]['total_time'] += $record->duration / 1000;
                    }
                }
            }
        }

        //查询口语进阶练习记录
        $oral_advanced_record = SpeakingAdvanceRecord::find()->where(['student_id' => $student_ids])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($oral_advanced_record)) {
            foreach ($oral_advanced_record as $record) {
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['oral_advanced_num']++;
                        $data[$record->student_id]['oral_advanced_time'] += $record->duration / 1000;
                        $data[$record->student_id]['total_num']++;
                        $data[$record->student_id]['total_time'] += $record->duration / 1000;
                    }
                }
            }
        }

        //查询模考记录
        $mock_record = SimulateExamRecord::find()->where(['student_id' => $student_ids])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($mock_record)) {
            $mockRecordIds = [];
            foreach ($mock_record as $record) {
                $mockRecordIds[] = (int)$record->id;
            }
            $listeningByRecordId = SimulateExamListening::find()->where(['record_id' => $mockRecordIds])->indexBy('record_id')->all();
            $readingByRecordId = SimulateExamReading::find()->where(['record_id' => $mockRecordIds])->indexBy('record_id')->all();
            $writingByRecordId = SimulateExamWriting::find()->where(['record_id' => $mockRecordIds])->indexBy('record_id')->all();
            foreach ($mock_record as $record) {
                $duration = $this->calculateMockDurationFromParts(
                    $listeningByRecordId[$record->id] ?? null,
                    $readingByRecordId[$record->id] ?? null,
                    $writingByRecordId[$record->id] ?? null
                );
                foreach ($student_list as $student) {
                    if ($record->student_id == $student->student_id) {
                        $data[$record->student_id]['mock_num']++;
                        $data[$record->student_id]['mock_time'] += $duration;
                        $data[$record->student_id]['total_num']++;
                        $data[$record->student_id]['total_time'] += $duration;
                    }
                }
            }
        }

        // 导出文件
        $file_name = $class_name . "." . $format;
        $local_path = dirname(__FILE__, 2);
        $file_path = $local_path . '/runtime/tmp/';
        if (!file_exists($file_path)) {
            mkdir($file_path, 0777, true);
        }

        $file_path = $file_path . $file_name;

        // 在写入文件前将所有时长字段从秒转换为小时/分钟可读格式
        $duration_fields = [
            // 'total_time',
            // 'listening_special_improve_time',
            // 'reading_special_improve_time',
            // 'writing_special_improve_time',
            // 'listening_real_time',
            // 'reading_real_time',
            // 'oral_time',
            // 'oral_advanced_time',
            // 'mock_time',
        ];

        foreach ($student_ids as $id) {
            foreach ($duration_fields as $field) {
                $data[$id][$field] = $this->formatExportDuration($data[$id][$field]);
            }
        }

        if ($format === 'xlsx') {
            // 使用 PhpSpreadsheet 导出 XLSX
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // 写入标题行
            $sheet->fromArray($title, null, 'A1');

            // 写入数据行
            $row = 2;
            foreach ($student_ids as $id) {
                $sheet->fromArray(array_values($data[$id]), null, 'A' . $row);
                $row++;
            }

            // 设置列宽自适应
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // 保存文件
            $writer = new Xlsx($spreadsheet);
            $writer->save($file_path);
        } else {
            // 导出 CSV 格式
            $fp = fopen($file_path, 'w');
            // 添加 BOM 以支持中文
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $title);
            foreach ($student_ids as $id) {
                fputcsv($fp, $data[$id]);
            }
            fclose($fp);
        }

        var_dump("导出成功，文件路径：$file_path");
        return $file_path;
    }

    /**
     * 导出多个班级的学生练习记录并汇总
     * exemple: php yii student/export-record-multi "611" 2025-11-24 2025-12-18 汇总文件 xlsx
     * @param string $class_ids 逗号或空格分隔的班级ID
     * @param string $start_date 开始日期 格式: Y-m-d
     * @param string $end_date 结束日期 格式: Y-m-d
     * @param string $file_name 导出文件名（不包含扩展名）
     * @param string $format 导出格式: csv 或 xlsx，默认 csv
     * @return string
     */
    public function actionExportRecordMulti(string $class_ids, string $start_date, string $end_date, string $file_name, string $format = 'csv'): string
    {
        $format = strtolower($format);
        if (!in_array($format, ['csv', 'xlsx'])) {
            var_dump("格式错误，只支持 csv 或 xlsx");
            return '';
        }

        $classIds = array_filter(array_map('intval', preg_split('/[,\s]+/', $class_ids)));
        $classIds = array_values(array_unique(array_filter($classIds, function ($id) {
            return $id > 0;
        })));
        if (empty($classIds)) {
            var_dump("class_ids 不能为空");
            return '';
        }

        $file_name = trim($file_name);
        $file_name = str_replace(['/', '\\'], '_', $file_name);
        if ($file_name === '') {
            var_dump("导出文件名称不能为空");
            return '';
        }
        $file_base_name = pathinfo($file_name, PATHINFO_FILENAME);
        if ($file_base_name === '' && $file_name !== '') {
            $file_base_name = $file_name;
        }
        if ($file_base_name === '') {
            $file_base_name = 'export_' . date('Ymd_His');
        }

        $start_time = strtotime($start_date);
        $end_time = strtotime($end_date);

        if ($start_time === false || $end_time === false) {
            var_dump("日期格式错误，请使用 Y-m-d 格式，例如: 2025-05-12");
            return '';
        }

        if ($start_time > $end_time) {
            var_dump("开始日期不能大于结束日期");
            return '';
        }

        $title = [
            "姓名",
            "学号",
            "手机号",
            "是否是老师",
            "总次数",
            "总时长",
            "听力专项提升练习次数",
            "听力专项提升练习时长",
            "听力专项提升练习平均正确率",
            "阅读专项提升练习次数",
            "阅读专项提升练习时长",
            "阅读专项提升练习平均正确率",
            "写作专项提升练习次数",
            "写作专项提升练习时长",
            "写作专项提升练习平均正确率",
            "听力真题练习次数",
            "听力真题练习时长",
            "听力真题练习平均正确率",
            "阅读真题练习次数",
            "阅读真题练习时长",
            "阅读真题练习平均正确率",
            "大作文写作次数",
            "小作文写作次数",
            "大作文范文次数",
            "大作文练习次数",
            "口语机经练习次数",
            "口语机经练习时长",
            "口语进阶练习次数",
            "口语进阶练习时长",
            "模考次数",
            "模考时长"
        ];

        $data = [];

        $student_list = EduClassStudent::find()->where(['class_id' => $classIds])->all();
        if (empty($student_list)) {
            var_dump("学生列表为空");
        }
        foreach ($student_list as $student) {
            if (!isset($data[$student->student_id])) {
                $data[$student->student_id] = $this->buildExportRecordRowTemplate($student->student_name);
            }
        }

        $teacher_list = EduClassTeacher::find()->where(['class_id' => $classIds])->asArray()->all();
        if (empty($teacher_list)) {
            var_dump("老师列表为空");
        }
        $teacher_ids = array_column($teacher_list, 'teacher_id');
        $teacher_info_list = [];
        $teacherUserIds = [];
        if (!empty($teacher_ids)) {
            $teacher_info_list = EduTeacher::find()->where(['id' => $teacher_ids])->all();
        }
        if (empty($teacher_info_list)) {
            var_dump("老师信息列表为空");
        }
        foreach ($teacher_info_list as $teacher) {
            $teacherUserIds[] = $teacher->user_id;
            if (!isset($data[$teacher->user_id])) {
                $data[$teacher->user_id] = $this->buildExportRecordRowTemplate($teacher->name);
            }
            $data[$teacher->user_id]['is_teacher'] = '是';
        }

        $userIds = array_keys($data);
        if (empty($userIds)) {
            var_dump("没有需要导出的学生或老师");
            return '';
        }

        $user_list = Student::find()->where(['id' => $userIds])->all();
        if (empty($user_list)) {
            var_dump("用户列表为空");
        }
        foreach ($user_list as $user) {
            if (!isset($data[$user->id])) {
                continue;
            }
            $data[$user->id]['mobile'] = $user->mobile;
            $data[$user->id]['account'] = $user->account;
        }

        $writing_special = [];
        $reading_special = [];
        $listening_special = [];
        $listening_real = [];
        $reading_real = [];

        $collection_record = ExamCollectionRecord::find()->where(['student_id' => $userIds, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($collection_record)) {
            $collection_ids = array_unique(array_column($collection_record, 'collection_id'));
            $collection_list = [];
            if (!empty($collection_ids)) {
                $collection_list = ExamQuestionCollection::find()->where(['id' => $collection_ids])->all();
            }
            if (empty($collection_list)) {
                var_dump("题集列表为空");
            }
            $collection_map = [];
            foreach ($collection_list as $collection) {
                $collection_map[$collection->id] = $collection->type;
            }

            foreach ($collection_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $type = $collection_map[$record->collection_id] ?? null;
                if ($type === null) {
                    continue;
                }
                $data[$record->student_id]['total_num']++;
                $data[$record->student_id]['total_time'] += $record->duration;
                if ($type == 1) {
                    $data[$record->student_id]['writing_special_improve_num']++;
                    $data[$record->student_id]['writing_special_improve_time'] += $record->duration;
                    $writing_special[$record->student_id][] = $record->rate;
                } elseif ($type == 2) {
                    $data[$record->student_id]['reading_special_improve_num']++;
                    $data[$record->student_id]['reading_special_improve_time'] += $record->duration;
                    $reading_special[$record->student_id][] = $record->rate;
                } elseif ($type == 3) {
                    $data[$record->student_id]['listening_special_improve_num']++;
                    $data[$record->student_id]['listening_special_improve_time'] += $record->duration;
                    $listening_special[$record->student_id][] = $record->rate;
                }
            }
        }

        foreach ($writing_special as $key => $val) {
            if (!isset($data[$key]) || empty($val)) {
                continue;
            }
            $data[$key]['writing_special_improve_rate'] = round(array_sum($val) / count($val), 4);
        }
        foreach ($reading_special as $key => $val) {
            if (!isset($data[$key]) || empty($val)) {
                continue;
            }
            $data[$key]['reading_special_improve_rate'] = round(array_sum($val) / count($val), 4);
        }
        foreach ($listening_special as $key => $val) {
            if (!isset($data[$key]) || empty($val)) {
                continue;
            }
            $data[$key]['listening_special_improve_rate'] = round(array_sum($val) / count($val), 4);
        }

        $listening_record = ListeningExamRecord::find()->where(['student_id' => $userIds, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($listening_record)) {
            foreach ($listening_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $data[$record->student_id]['total_num']++;
                $data[$record->student_id]['total_time'] += $record->duration;
                $data[$record->student_id]['listening_real_num']++;
                $data[$record->student_id]['listening_real_time'] += $record->duration;
                if ($record->total > 0) {
                    $listening_real[$record->student_id][] = $record->correct / $record->total;
                }
            }
        }
        foreach ($listening_real as $key => $val) {
            if (!isset($data[$key]) || empty($val)) {
                continue;
            }
            $data[$key]['listening_real_rate'] = round(array_sum($val) / count($val), 4);
        }

        $reading_record = ReadingExamRecord::find()->where(['student_id' => $userIds, 'status' => 2])->andWhere(['>=', 'finished_time', $start_time])->andWhere(['<=', 'finished_time', $end_time])->all();
        if (!empty($reading_record)) {
            foreach ($reading_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $data[$record->student_id]['total_num']++;
                $data[$record->student_id]['total_time'] += $record->duration;
                $data[$record->student_id]['reading_real_num']++;
                $data[$record->student_id]['reading_real_time'] += $record->duration;
                if ($record->total > 0) {
                    $reading_real[$record->student_id][] = $record->correct / $record->total;
                }
            }
        }
        foreach ($reading_real as $key => $val) {
            if (!isset($data[$key]) || empty($val)) {
                continue;
            }
            $data[$key]['reading_real_rate'] = round(array_sum($val) / count($val), 4);
        }

        $big_essay_record = WritingBigEssayRecord::find()->where(['student_id' => $userIds, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($big_essay_record)) {
            foreach ($big_essay_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $data[$record->student_id]['big_essay_num']++;
                $data[$record->student_id]['total_num']++;
            }
        }

        $small_essay_record = WritingEssayRecord::find()->where(['student_id' => $userIds, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($small_essay_record)) {
            foreach ($small_essay_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $data[$record->student_id]['small_essay_num']++;
                $data[$record->student_id]['total_num']++;
            }
        }

        $big_essay_model_record = WritingBigEssaySampleText::find()->where(['student_id' => $userIds, 'status' => 2])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($big_essay_model_record)) {
            foreach ($big_essay_model_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $data[$record->student_id]['big_essay_model_num']++;
                $data[$record->student_id]['total_num']++;
            }
        }

        $big_essay_exam_record = WritingPracticeRecord::find()->where(['student_id' => $userIds])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($big_essay_exam_record)) {
            foreach ($big_essay_exam_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $data[$record->student_id]['big_essay_exam_num']++;
                $data[$record->student_id]['total_num']++;
            }
        }

        $oral_record = SpeakingExamDialogueLog::find()->where(['student_id' => $userIds, 'role' => 1, 'type' => [1, 2]])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($oral_record)) {
            foreach ($oral_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $duration = $record->duration / 1000;
                $data[$record->student_id]['oral_num']++;
                $data[$record->student_id]['oral_time'] += $duration;
                $data[$record->student_id]['total_num']++;
                $data[$record->student_id]['total_time'] += $duration;
            }
        }

        $oral_advanced_record = SpeakingAdvanceRecord::find()->where(['student_id' => $userIds])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($oral_advanced_record)) {
            foreach ($oral_advanced_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $duration = $record->duration / 1000;
                $data[$record->student_id]['oral_advanced_num']++;
                $data[$record->student_id]['oral_advanced_time'] += $duration;
                $data[$record->student_id]['total_num']++;
                $data[$record->student_id]['total_time'] += $duration;
            }
        }

        $mock_record = SimulateExamRecord::find()->where(['student_id' => $userIds])->andWhere(['>=', 'update_time', $start_time])->andWhere(['<=', 'update_time', $end_time])->all();
        if (!empty($mock_record)) {
            $mockRecordIds = [];
            foreach ($mock_record as $record) {
                $mockRecordIds[] = (int)$record->id;
            }
            $listeningByRecordId = SimulateExamListening::find()->where(['record_id' => $mockRecordIds])->indexBy('record_id')->all();
            $readingByRecordId = SimulateExamReading::find()->where(['record_id' => $mockRecordIds])->indexBy('record_id')->all();
            $writingByRecordId = SimulateExamWriting::find()->where(['record_id' => $mockRecordIds])->indexBy('record_id')->all();
            foreach ($mock_record as $record) {
                if (!isset($data[$record->student_id])) {
                    continue;
                }
                $duration = $this->calculateMockDurationFromParts(
                    $listeningByRecordId[$record->id] ?? null,
                    $readingByRecordId[$record->id] ?? null,
                    $writingByRecordId[$record->id] ?? null
                );
                $data[$record->student_id]['mock_num']++;
                $data[$record->student_id]['mock_time'] += $duration;
                $data[$record->student_id]['total_num']++;
                $data[$record->student_id]['total_time'] += $duration;
            }
        }

        $totalPracticeNum = 0;
        $totalPracticeTime = 0;
        $practicedStudentCount = 0;
        $overallRatePool = [];

        $countFields = [
            'listening_special_improve_num',
            'reading_special_improve_num',
            'writing_special_improve_num',
            'listening_real_num',
            'reading_real_num',
            'big_essay_num',
            'small_essay_num',
            'big_essay_model_num',
            'big_essay_exam_num',
            'oral_num',
            'oral_advanced_num',
            'mock_num'
        ];
        $timeFields = [
            'listening_special_improve_time',
            'reading_special_improve_time',
            'writing_special_improve_time',
            'listening_real_time',
            'reading_real_time',
            'oral_time',
            'oral_advanced_time',
            'mock_time'
        ];
        $rateFields = [
            'listening_special_improve_rate' => 'listening_special_improve_num',
            'reading_special_improve_rate' => 'reading_special_improve_num',
            'writing_special_improve_rate' => 'writing_special_improve_num',
            'listening_real_rate' => 'listening_real_num',
            'reading_real_rate' => 'reading_real_num',
        ];

        $countSums = array_fill_keys($countFields, 0);
        $timeSums = array_fill_keys($timeFields, 0);
        $rateSums = [];
        $rateCounts = [];

        foreach ($data as $userId => $row) {
            if (in_array($userId, $teacherUserIds, true)) {
                continue;
            }
            $totalPracticeNum += $row['total_num'];
            $totalPracticeTime += $row['total_time'];
            if ($row['total_num'] > 0) {
                $practicedStudentCount++;
            }
            foreach ($countFields as $field) {
                $countSums[$field] += $row[$field];
            }
            foreach ($timeFields as $field) {
                $timeSums[$field] += $row[$field];
            }
            foreach ($rateFields as $rateField => $countField) {
                if ($row[$countField] > 0 && $row[$rateField] > 0) {
                    if (!isset($rateSums[$rateField])) {
                        $rateSums[$rateField] = 0;
                        $rateCounts[$rateField] = 0;
                    }
                    $rateSums[$rateField] += $row[$rateField];
                    $rateCounts[$rateField] += 1;
                    $overallRatePool[] = $row[$rateField];
                }
            }
        }

        $avgPracticeDuration = $practicedStudentCount > 0 ? round($totalPracticeTime / $practicedStudentCount, 2) : 0;
        $overallAvgAccuracy = !empty($overallRatePool) ? round(array_sum($overallRatePool) / count($overallRatePool), 4) : 0;
        $rateAverages = [];
        foreach ($rateFields as $rateField => $countField) {
            $rateAverages[$rateField] = (isset($rateCounts[$rateField]) && $rateCounts[$rateField] > 0)
                ? round($rateSums[$rateField] / $rateCounts[$rateField], 4)
                : 0;
        }

        $summaryTitleRow = $this->buildExportRecordRowTemplate('标题');
        $summaryTitleRow['account'] = '练习人数';
        $summaryTitleRow['mobile'] = '练过的人的平均练习时长(秒)';
        $summaryTitleRow['name'] = '练过的人的平均正确率';
        $summaryTitleRow['total_num'] = '练习总次数';
        $summaryTitleRow['total_time'] = '练习总时长(秒)';
        $summaryTitleRow['listening_special_improve_num'] = '听力专项次数总数';
        $summaryTitleRow['listening_special_improve_time'] = '听力专项时长总计(秒)';
        $summaryTitleRow['listening_special_improve_rate'] = '听力专项平均正确率';
        $summaryTitleRow['reading_special_improve_num'] = '阅读专项次数总数';
        $summaryTitleRow['reading_special_improve_time'] = '阅读专项时长总计(秒)';
        $summaryTitleRow['reading_special_improve_rate'] = '阅读专项平均正确率';
        $summaryTitleRow['writing_special_improve_num'] = '写作专项次数总数';
        $summaryTitleRow['writing_special_improve_time'] = '写作专项时长总计(秒)';
        $summaryTitleRow['writing_special_improve_rate'] = '写作专项平均正确率';
        $summaryTitleRow['listening_real_num'] = '听力真题次数总数';
        $summaryTitleRow['listening_real_time'] = '听力真题时长总计(秒)';
        $summaryTitleRow['listening_real_rate'] = '听力真题平均正确率';
        $summaryTitleRow['reading_real_num'] = '阅读真题次数总数';
        $summaryTitleRow['reading_real_time'] = '阅读真题时长总计(秒)';
        $summaryTitleRow['reading_real_rate'] = '阅读真题平均正确率';
        $summaryTitleRow['big_essay_num'] = '大作文写作次数总数';
        $summaryTitleRow['small_essay_num'] = '小作文写作次数总数';
        $summaryTitleRow['big_essay_model_num'] = '大作文范文次数总数';
        $summaryTitleRow['big_essay_exam_num'] = '大作文练习次数总数';
        $summaryTitleRow['oral_num'] = '口语机经练习次数总数';
        $summaryTitleRow['oral_time'] = '口语机经练习时长总计(秒)';
        $summaryTitleRow['oral_advanced_num'] = '口语进阶练习次数总数';
        $summaryTitleRow['oral_advanced_time'] = '口语进阶练习时长总计(秒)';
        $summaryTitleRow['mock_num'] = '模考次数总数';
        $summaryTitleRow['mock_time'] = '模考时长总计(秒)';

        $summaryValueRow = $this->buildExportRecordRowTemplate('统计');
        $summaryValueRow['name'] = $overallAvgAccuracy;
        $summaryValueRow['account'] = $practicedStudentCount;
        $summaryValueRow['mobile'] = $avgPracticeDuration;
        $summaryValueRow['total_num'] = $totalPracticeNum;
        $summaryValueRow['total_time'] = $totalPracticeTime;
        foreach ($countFields as $field) {
            $summaryValueRow[$field] = $countSums[$field];
        }
        foreach ($timeFields as $field) {
            $summaryValueRow[$field] = $timeSums[$field];
        }
        foreach ($rateFields as $rateField => $countField) {
            $summaryValueRow[$rateField] = $rateAverages[$rateField] ?? 0;
        }

        $rows = [];
        foreach ($data as $row) {
            $rows[] = array_values($row);
        }
        $rows[] = array_values($summaryTitleRow);
        $rows[] = array_values($summaryValueRow);

        $local_path = dirname(__FILE__, 2);
        $file_path = $local_path . '/runtime/tmp/';
        if (!file_exists($file_path)) {
            mkdir($file_path, 0777, true);
        }
        $file_path = $file_path . $file_base_name . '.' . $format;

        if ($format === 'xlsx') {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->fromArray($title, null, 'A1');
            $rowIndex = 2;
            foreach ($rows as $row) {
                $sheet->fromArray($row, null, 'A' . $rowIndex);
                $rowIndex++;
            }
            foreach (range('A', $sheet->getHighestColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save($file_path);
        } else {
            $fp = fopen($file_path, 'w');
            fwrite($fp, "\xEF\xBB\xBF");
            fputcsv($fp, $title);
            foreach ($rows as $row) {
                fputcsv($fp, $row);
            }
            fclose($fp);
        }

        var_dump("导出成功，文件路径：$file_path");
        return $file_path;
    }

    /**
     * 生成试用账号
     */
    public function actionCreateSpecifyAccount()
    {
        for ($i = 0; $i < 100; $i++) {
            $account = $this->randomAccount();
            $data = new StudentSpecifyAccount();
            $data->name = $account;
            $data->use_duration = 7;
            $data->create_time = time();
            $data->update_time = time();
            $data->save();
            var_dump("$account 创建成功");
        }
        var_dump("生成完成");
    }

    /**
     * 随机生成账号
     */
    public function randomAccount()
    {
        $length = 6;
        $characters = '0123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNOPQRSTUVWXYZ';
        $account = '';
        for ($i = 0; $i < $length; $i++) {
            $account .= $characters[rand(0, strlen($characters) - 1)];
        }

        //查询该账号是否存在
        $data = StudentSpecifyAccount::find()->where(['name' => $account])->one();
        if (!empty($data)) {
            $this->randomAccount();
        }

        echo "生成的随机账号: " . $account . "\n";
        return $account;
    }

    /**
     * 将秒转换为导出需要的小时/分钟展示格式
     */
    private function formatExportDuration($seconds): string
    {
        $seconds = (int)round($seconds);
        if ($seconds <= 0) {
            return '0分钟';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainSeconds = $seconds % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = $hours . '小时';
        }

        if ($minutes > 0 || $hours > 0) {
            $parts[] = $minutes . '分钟';
        }

        if ($hours === 0 && $minutes === 0 && $remainSeconds > 0) {
            $parts[] = $remainSeconds . '秒';
        }

        return implode('', $parts);
    }

    private function calculateMockDurationFromParts(?SimulateExamListening $listening, ?SimulateExamReading $reading, ?SimulateExamWriting $writing): int
    {
        $duration = 0;
        $duration += $this->calculateUsedSecondsFromSurplusTime($listening ? $listening->surplus_time : null, 1800);
        $duration += $this->calculateUsedSecondsFromSurplusTime($reading ? $reading->surplus_time : null, 3600);
        $duration += $this->calculateUsedSecondsFromSurplusTime($writing ? $writing->surplus_time : null, 3600);

        return $duration;
    }

    private function calculateUsedSecondsFromSurplusTime($surplusTime, int $totalSeconds): int
    {
        if ($totalSeconds <= 0 || $surplusTime === null) {
            return 0;
        }

        $surplusTime = (int)$surplusTime;
        if ($surplusTime <= 0) {
            return $totalSeconds;
        }

        if ($surplusTime >= $totalSeconds) {
            return 0;
        }

        return $totalSeconds - $surplusTime;
    }

    /**
     * 初始化导出行模版
     */
    private function buildExportRecordRowTemplate(string $name = '', string $account = '', string $mobile = ''): array
    {
        return [
            "name" => $name,
            "account" => $account,
            "mobile" => $mobile,
            "is_teacher" => "否",
            "total_num" => 0,
            "total_time" => 0,
            "listening_special_improve_num" => 0,
            "listening_special_improve_time" => 0,
            "listening_special_improve_rate" => 0,
            "reading_special_improve_num" => 0,
            "reading_special_improve_time" => 0,
            "reading_special_improve_rate" => 0,
            "writing_special_improve_num" => 0,
            "writing_special_improve_time" => 0,
            "writing_special_improve_rate" => 0,
            "listening_real_num" => 0,
            "listening_real_time" => 0,
            "listening_real_rate" => 0,
            "reading_real_num" => 0,
            "reading_real_time" => 0,
            "reading_real_rate" => 0,
            "big_essay_num" => 0,
            "small_essay_num" => 0,
            "big_essay_model_num" => 0,
            "big_essay_exam_num" => 0,
            "oral_num" => 0,
            "oral_time" => 0,
            "oral_advanced_num" => 0,
            "oral_advanced_time" => 0,
            "mock_num" => 0,
            "mock_time" => 0,
        ];
    }

    /**
     * 生成统计数据
     */
    public function actionCreateStatisticsData()
    {
        $student_ids = [
            10000001,
            10000002,
            10000003,
            10000004,
            10000005,
            10000006,
            10000007,
            10000008,
            10000009,
            10000010,
        ];

        foreach ($student_ids as $student_id) {
            for ($i = 0; $i < 30; $i++) {
                //生成总统计数据
                $this->createTotal($student_id);
                //生成听力统计数据
                $this->createListening($student_id);
                //生成阅读统计数据
                $this->createReading($student_id);
                //生成写作统计数据
                $this->createWriting($student_id);
                //生成口语统计数据
                $this->createSpeaking($student_id);
                //生成专项提升统计数据
                $this->createSpecial($student_id);
            }
        }
    }

    public function createTotal($id)
    {
        $total = new StatisticsDayTotal();
        $total->student_id = $id;
        $total->listening_special_duration = rand(600, 3600);
        $total->listening_special_num = rand(10, 50);
        $total->listening_special_rate = rand(30, 100) / 100;
        $total->reading_special_duration = rand(600, 3600);
        $total->reading_special_num = rand(10, 50);
        $total->reading_special_rate = rand(30, 100) / 100;
        $total->writing_special_duration = rand(600, 3600);
        $total->writing_special_num = rand(10, 50);
        $total->writing_special_rate = rand(30, 100) / 100;
        $total->speaking_special_duration = rand(600, 3600);
        $total->speaking_special_num = rand(10, 50);
        $total->speaking_special_rate = rand(30, 100) / 100;
        $total->listening_paper_duration = rand(600, 3600);
        $total->listening_paper_num = rand(10, 50);
        $total->listening_paper_correct = rand(100, 500);
        $total->listening_paper_rate = rand(30, 100) / 100;
        $total->reading_paper_duration = rand(600, 3600);
        $total->reading_paper_num = rand(10, 50);
        $total->reading_paper_correct = rand(100, 500);
        $total->reading_paper_rate = rand(30, 100) / 100;
        $total->writing_paper_duration = rand(600, 3600);
        $total->writing_paper_num = rand(10, 50);
        $total->writing_paper_score = rand(100, 500);
        $total->speaking_paper_duration = rand(600, 3600);
        $total->speaking_paper_num = rand(10, 50);
        $total->speaking_paper_rate = rand(30, 100) / 100;
        $total->all_duration = $total->listening_special_duration + $total->reading_special_duration + $total->writing_special_duration + $total->speaking_special_duration + $total->listening_paper_duration + $total->reading_paper_duration + $total->writing_paper_duration + $total->speaking_paper_duration;
        $total->all_num = $total->listening_special_num + $total->reading_special_num + $total->writing_special_num + $total->speaking_special_num + $total->listening_paper_num + $total->reading_paper_num + $total->writing_paper_num + $total->speaking_paper_num;
        $total->save();
        var_dump("$id 生成总统计数据成功");
    }

    public function createListening($id)
    {
        $type_data = ListeningExamQuestionType::find()->asArray()->all();
        $type_list = array_column($type_data, 'id');

        foreach ($type_list as $type) {
            $listening = new StatisticsDayListening();
            $listening->student_id = $id;
            $listening->question_type = $type;
            $listening->duration = rand(180, 400);
            $listening->total_num = rand(10, 30);
            $listening->total_correct = rand(10, $listening->total_num);
            $listening->total_rate = $listening->total_correct / $listening->total_num;
            $listening->save();
            var_dump("$id 题型 $type 生成听力统计数据成功");
        }
        var_dump("$id 生成听力统计数据成功");
    }

    public function createReading($id)
    {
        $type_data = ReadingExamQuestionType::find()->asArray()->all();
        $type_list = array_column($type_data, 'id');

        foreach ($type_list as $type) {
            $reading = new StatisticsDayReading();
            $reading->student_id = $id;
            $reading->question_type = $type;
            $reading->duration = rand(180, 400);
            $reading->total_num = rand(10, 30);
            $reading->total_correct = rand(10, $reading->total_num);
            $reading->total_rate = $reading->total_correct / $reading->total_num;
            $reading->save();
            var_dump("$id 题型 $type 生成阅读统计数据成功");
        }
        var_dump("$id 生成阅读统计数据成功");
    }

    public function createWriting($id)
    {
        $total = new StatisticsDayWriting();
        $total->student_id = $id;
        $total->task1_duration = rand(600, 1200);
        $total->task2_duration = rand(1200, 1800);
        $total->task1_num = rand(1, 3);
        $total->task2_num = rand(1, 2);
        $total->task1_ta = rand(2, 7);
        $total->task1_cc = rand(2, 7);
        $total->task1_lr = rand(2, 7);
        $total->task1_gra = rand(2, 7);
        $total->task2_ta = rand(2, 7);
        $total->task2_cc = rand(2, 7);
        $total->task2_lr = rand(2, 7);
        $total->task2_gra = rand(2, 7);
        $total->save();
        var_dump("$id 生成写作统计数据成功");
    }

    public function createSpeaking($id)
    {
        $total = new StatisticsDaySpeaking();
        $total->student_id = $id;
        $total->duration = rand(600, 2400);
        $total->num = rand(5, 15);
        $total->grammar = rand(30, 95);
        $total->vocabulary = rand(30, 95);
        $total->proficient = rand(30, 95);
        $total->pron_fluency = rand(30, 95);
        $total->pron_accuracy = rand(30, 95);
        $total->save();
        var_dump("$id 生成写作统计数据成功");
    }

    public function createSpecial($id)
    {
        //获取写作题目类型、题目语法
        $grammar_list = $this->getWritingTypes();
        foreach ($grammar_list as $key => $grammar) {
            foreach ($grammar as $grammar_key => $grammar_value) {
                $total = new StatisticsDaySpecial();
                $total->course = 1;
                $total->question_type = $key;
                $total->grammar = $grammar_value;
                $total->student_id = $id;
                $total->duration = rand(180, 600);
                $total->num = rand(1, 3);
                $total->rate = rand(30, 95) / 100;
                $total->save();
                var_dump("$id 生成专项练习写作 question_type = $key, grammar = $grammar_value 统计数据成功");
            }
        }

        //获取听力题目类型、题目语法
        $grammar_list = $this->getListeningTypes();
        foreach ($grammar_list as $key => $grammar) {
            foreach ($grammar as $grammar_key => $grammar_value) {
                $total = new StatisticsDaySpecial();
                $total->course = 3;
                $total->question_type = $key;
                $total->grammar = $grammar_value;
                $total->student_id = $id;
                $total->duration = rand(180, 600);
                $total->num = rand(1, 3);
                $total->rate = rand(30, 95) / 100;
                $total->save();
                var_dump("$id 生成专项练习听力 question_type = $key, grammar = $grammar_value 统计数据成功");
            }
        }

        //获取阅读题目类型、题目语法
        $grammar_list = $this->getReadingTypes();
        foreach ($grammar_list as $key => $grammar) {
            foreach ($grammar as $grammar_key => $grammar_value) {
                $total = new StatisticsDaySpecial();
                $total->course = 2;
                $total->question_type = $key;
                $total->grammar = $grammar_value;
                $total->student_id = $id;
                $total->duration = rand(180, 600);
                $total->num = rand(1, 3);
                $total->rate = rand(30, 95) / 100;
                $total->save();
                var_dump("$id 生成专项练习阅读 question_type = $key, grammar = $grammar_value 统计数据成功");
            }
        }

        //获取口语题目类型、题目语法
        $grammar_list = $this->getSpeakingTypes();
        foreach ($grammar_list as $key => $grammar) {
            foreach ($grammar as $grammar_key => $grammar_value) {
                $total = new StatisticsDaySpecial();
                $total->course = 4;
                $total->question_type = $key;
                $total->grammar = $grammar_value;
                $total->student_id = $id;
                $total->duration = rand(180, 600);
                $total->num = rand(1, 3);
                $total->rate = rand(30, 95);
                $total->save();
                var_dump("$id 生成专项练习口语 question_type = $key, grammar = $grammar_value 统计数据成功");
            }
        }
    }

    public function getWritingTypes()
    {
        $type_list = [1, 61, 62, 2, 3, 4, 5];
        $grammar_list = [];
        //获取语法
        $list = BasicTrainingWritingGrammar::find()->where(['type' => $type_list])->asArray()->all();
        foreach ($list as $grammar) {
            if (in_array($grammar['type'], [61, 62])) {
                $grammar_list[6][] = $grammar['id'];
            } else {
                $grammar_list[$grammar['type']][] = $grammar['id'];
            }
        }
        return $grammar_list;
    }

    public function getListeningTypes()
    {
        $type_list = [1, 2, 3, 4, 5];
        $grammar_list = [];
        //获取语法
        $list = BasicTrainingListeningGrammar::find()->where(['type' => $type_list])->asArray()->all();
        foreach ($list as $grammar) {
            $grammar_list[$grammar['type']][] = $grammar['id'];
        }
        return $grammar_list;
    }

    public function getReadingTypes()
    {
        $type_list = [2, 3, 6];
        $difficulty_list = [1, 5];
        $grammar_list = [];
        //获取语法
        $list = BasicTrainingReadingGrammar::find()->where(['type' => $type_list])->asArray()->all();
        foreach ($list as $grammar) {
            $grammar_list[$grammar['type']][] = $grammar['id'];
        }
        foreach ($difficulty_list as $difficulty) {
            $grammar_list[$difficulty] = [1, 2, 3];
        }
        return $grammar_list;
    }

    public function getSpeakingTypes()
    {
        $type_list = [1, 2, 3, 4, 5];
        $grammar_list = [];
        //获取语法
        $list = SpeakingSpecialItemTopic::find()->where(['type' => $type_list])->asArray()->all();
        foreach ($list as $grammar) {
            $grammar_list[$grammar['type']][] = $grammar['id'];
        }
        return $grammar_list;
    }
}

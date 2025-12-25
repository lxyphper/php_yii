<?php

namespace console\controllers;

use app\models\Exercises;
use app\models\ExercisesExtend;
use app\models\ExercisesGuide;
use app\models\ListeningExamPaper;
use app\models\ListeningExamQuestion;
use app\models\ListeningExamQuestionType;
use app\models\ReadingExamPaper;
use app\models\ReadingExamQuestion;
use app\models\SimulatePaperGroup;
use app\models\SimulatePaperGroupQuery;
use app\models\SimulatePaperType;
use app\models\WritingExaminingQuestion;
use yii\base\InvalidConfigException;
use yii\console\Controller;
use yii\httpclient\Client;
use yii\httpclient\Exception;

class PaperDealWithController extends BaseController
{
    public function actionListeningGroup()
    {
        $group_list = $this->getGroupList();
        //        var_dump($group_list);die;

        $paper_list = ListeningExamPaper::find()->andWhere(['<=', 'id', 224])->all();
        foreach ($paper_list as $value) {
            foreach ($group_list as $k => $v) {
                if (str_contains($value->complete_title, $k)) {
                    $value->paper_group = $v;
                    $value->save();
                    var_dump("paper_id:$value->id ，更新完成");
                    break;
                }
            }
        }
    }

    public function actionReadingGroup()
    {
        $group_list = $this->getGroupList();
        //        var_dump($group_list);die;

        $paper_list = ReadingExamPaper::find()->andWhere(['<=', 'id', 168])->all();
        foreach ($paper_list as $value) {
            foreach ($group_list as $k => $v) {
                if (str_contains($value->complete_title, $k)) {
                    $value->paper_group = $v;
                    $value->save();
                    var_dump("paper_id:$value->id ，更新完成");
                    break;
                }
            }
        }
    }

    public function actionWritingGroup()
    {
        $group_list = $this->getWritingGroupList();
        //        var_dump($group_list);die;

        $paper_list = Exercises::find()->andWhere(['<=', 'id', 58])->all();
        foreach ($paper_list as $value) {
            foreach ($group_list as $k => $v) {
                if (str_contains($value->title, $k)) {
                    $value->paper_group = $v;
                    $value->save();
                    var_dump("paper_id:$value->id ，更新完成");
                    break;
                }
            }
        }
    }

    public function getGroupList(): array
    {
        $listMap = [];
        $typeMap = $this->getPaperTypeList();
        $query = SimulatePaperGroup::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $value->name = str_replace('Test', 'Test ', $value->name);
            $listMap[$typeMap[$value->paper_type] . ' ' . $value->name] = $value->id;
        }

        return $listMap;
    }

    public function getPaperTypeList(): array
    {
        $listMap = [];
        $query = SimulatePaperType::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $value->name = str_replace('IELTS ', '剑雅', $value->name);
            $listMap[$value->id] = $value->name;
        }

        return $listMap;
    }

    public function getWritingGroupList(): array
    {
        $listMap = [];
        $typeMap = $this->getWritingPaperTypeList();
        $query = SimulatePaperGroup::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $value->name = str_replace('Test', '', $value->name);
            $listMap[$typeMap[$value->paper_type] . '_' . $value->name] = $value->id;
        }

        return $listMap;
    }

    public function getWritingPaperTypeList(): array
    {
        $listMap = [];
        $query = SimulatePaperType::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $value->name = str_replace('IELTS ', '剑雅', $value->name);
            $listMap[$value->id] = $value->name;
        }

        return $listMap;
    }

    /**
     * 对中文进行翻译
     * 写作大作文表：exercises中category_content，trap_content
     * 写作大作文扩展表：exercises_extend中requirement
     * 写作大作文思路表：exercises_guide中title，is_delete=2，lang=zh-CN
     * 写作大作文审题练习表：title，option
     * 阅读题目表：reading_exam_question中analyze
     * 阅读段落中的总结：content字段中的summary
     * 听力题目表：listening_exam_question中analyze
     */
    public function actionWritingTranslate($id = 0)
    {
        $list = Exercises::find()->where(['>', 'id', $id])->all();
        foreach ($list as $value) {
            $is_save = false;
            if (empty($value->category_content_en) && !empty($value->category_content)) {
                $is_save = true;
                $value->category_content_en = $this->postAiTranslate($value->category_content);
            }
            if (empty($value->trap_content_en) && !empty($value->trap_content)) {
                $is_save = true;
                $value->trap_content_en = $this->postAiTranslate($value->trap_content);
            }
            if ($is_save) {
                $value->save(false);
                var_dump("题目id：" . $value->id . "更新成功");
            }
        }
    }

    //写作大作文扩展表：exercises_extend中requirement
    public function actionWritingExtendTranslate()
    {
        $list = ExercisesExtend::find()->where(['requirement_en' => ''])->all();
        foreach ($list as $value) {
            if (empty($value->requirement_en) && !empty($value->requirement)) {
                // if (!empty($value->requirement)) {
                $value->requirement_en = $this->postAiTranslate($value->requirement);
                $value->save(true);
                var_dump("题目id：" . $value->exercises_id . "更新成功");
            }
        }
    }

    //阅读题目表：reading_exam_question中analyze
    public function actionReadingTranslate()
    {
        $id = 40134;
        $num = 500;
        while ($list = ReadingExamQuestion::find()->where(['>', 'id', $id])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (empty($value->analyze_en) && !empty($value->analyze)) {
                    $value->analyze_en = $this->postAiTranslate($value->analyze);
                    $value->save(true);
                    var_dump("题目id：" . $value->id . "更新成功");
                }
            }
        }
    }

    //阅读段落中的总结：content字段中的summary
    public function actionReadingSummaryTranslate($id = 0)
    {
        $num = 10;
        while ($list = ReadingExamPaper::find()->where(['>', 'id', $id])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (!empty($value->content) && !empty($value->content['summary']) && !empty($value->content['summary_en'])) {
                    $content = $value->content;
                    $tmp = [];
                    $save = false;
                    foreach ($value->content['summary'] as $key => $val) {
                        if (empty($content['summary_en'][$key])) {
                            $save = true;
                            $tmp[$key] = $this->postAiTranslate($val);
                        }
                    }
                    $content['summary_en'] = $tmp;
                    $value->content = $content;
                    if ($save) {
                        $value->save(true);
                        var_dump("题目id：" . $value->id . "更新成功");
                    }
                }
            }
        }
    }

    //听力题目表：listening_exam_question中analyze
    public function actionListeningTranslate()
    {
        $id = 22114;
        $num = 500;
        while ($list = ListeningExamQuestion::find()->where(['>', 'id', $id])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (empty($value->analyze_en) && !empty($value->analyze)) {
                    $value->analyze_en = $this->postAiTranslate($value->analyze);
                    $value->save(true);
                    var_dump("题目id：" . $value->id . "更新成功");
                }
            }
        }
    }

    //写作大作文审题练习表：title，option
    public function actionWritingQuestionTranslate($id = 0)
    {
        //        $id = 0;
        $num = 10;
        while ($list = WritingExaminingQuestion::find()->where(['>', 'id', $id])->where(['title_en' => '', 'task_type' => 1])->orderBy("id asc")->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                $isSave = false;
                if (empty($value->title_en) && !empty($value->title)) {
                    $isSave = true;
                    $value->title_en = $this->postAiTranslate($value->title);
                }
                $item = [];
                //                if (!empty($value->option_en)) {
                //                    foreach ($value->option_en as $key => $op) {
                //                        if (empty($op['item']) && !empty($value->option[$key]['item'])) {
                //                            $isSave = true;
                //                            $op['item'] = $this->postAiTranslate($value->option[$key]['item']);
                //                        }
                //                        if (empty($op['explanation']) && !empty($value->option[$key]['explanation'])) {
                //                            $isSave = true;
                //                            $op['explanation'] = $this->postAiTranslate($value->option[$key]['explanation']);
                //                        }
                //                        if (empty($op['student_error']) && !empty($value->option[$key]['student_error'])) {
                //                            $isSave = true;
                //                            $op['student_error'] = $this->postAiTranslate($value->option[$key]['student_error']);
                //                        }
                //                        if (empty($op['student_thought']) && !empty($value->option[$key]['student_thought'])) {
                //                            $isSave = true;
                //                            $op['student_thought'] = $this->postAiTranslate($value->option[$key]['student_thought']);
                //                        }
                //                        $item[] = $op;
                //                    }
                //                }

                if ($value->task_type == 1) {
                    foreach ($value->option as $key => $op) {
                        if (!empty($op['item'])) {
                            $isSave = true;
                            $op['item'] = $this->postAiTranslate($op['item']);
                        }
                        if (!empty($op['explanation'])) {
                            $isSave = true;
                            $op['explanation'] = $this->postAiTranslate($op['explanation']);
                        }
                        if (!empty($op['student_error'])) {
                            $isSave = true;
                            $op['student_error'] = $this->postAiTranslate($op['student_error']);
                        }
                        if (!empty($op['student_thought'])) {
                            $isSave = true;
                            $op['student_thought'] = $this->postAiTranslate($op['student_thought']);
                        }
                        $item[] = $op;
                    }
                } else {
                    foreach ($value->option as $key => $op) {
                        if (!empty($op['content'])) {
                            $isSave = true;
                            $op['content'] = $this->postAiTranslate($op['content']);
                        }
                        if (!empty($op['analysis'])) {
                            $isSave = true;
                            $op['analysis'] = $this->postAiTranslate($op['analysis']);
                        }
                        $item[] = $op;
                    }
                }


                $value->option_en = $item;
                if ($isSave) {
                    $value->save(false);
                    var_dump("题目id：" . $value->id . "更新成功");
                }
            }
        }
    }

    //写作大作文思路表：exercises_guide中title，is_delete=2，lang=zh-CN
    public function actionWritingGuideTranslate($id = 0)
    {
        // $id = 161304;
        $num = 3;
        while ($list = ExercisesGuide::find()->where(['>', 'id', $id])->andWhere(['is_delete' => 2, 'lang' => 'zh-CN', 'title_en' => ''])->andWhere(['!=', 'title', ''])->orderBy("id asc")->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (empty($value->title_en) && !empty($value->title) && $value->title != '...') {
                    $value->title_en = $this->postAiTranslate($value->title);
                    $value->save(true);
                    var_dump("思路id：" . $value->id . "更新成功");
                }
            }
        }
    }

    //修改角度为首字母大写
    public function actionWritingGuideAngleTranslate($id = 0)
    {
        //        $id = 161304;
        $num = 3;
        while ($list = ExercisesGuide::find()->where(['>', 'id', $id])->andWhere(['level' => 3, 'is_delete' => 2])->orderBy("id asc")->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (!empty($value->title_en)) {
                    $value->title_en = ucfirst(strtolower($value->title_en));
                    $value->save(true);
                    var_dump("角度，思路id：" . $value->id . "更新成功");
                }
            }
        }
    }

    public function actionFixWritingQuestion()
    {
        $local_path = dirname(__FILE__, 2);
        $filename = $local_path . '/runtime/tmp/writing_question.csv';
        $data = $this->getDataByCsv($filename);
        if (empty($data)) {
            var_dump("数据为空");
            return false;
        }
        var_dump($data);
        die;
        foreach ($paper_content->data as $value) {
            var_dump($value);
            die;
            if (empty($value->title_en)) {
                $value->title_en = $this->postAiTranslate($value->title);
                $value->save(true);
                var_dump("题目id：" . $value->id . "更新成功");
            }
        }
    }

    public function postAiTranslate($str): string
    {
        if (empty($str)) {
            return "";
        }
        $url = "http://100.64.0.2:8085/translate-to-english/invoke";
        //        $url = "http://100.64.0.6:8000/translate-to-english/invoke";
        //        $url = "http://172.16.80.182:8000/translate-to-english/invoke";
        $client = new Client();
        $data = [
            "input" => [
                "text" => $str
            ]
        ];
        try {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($url)
                ->setHeaders(['Content-Type' => 'application/json'])
                ->setFormat(Client::FORMAT_JSON)
                ->setData($data)
                ->send();
            if ($response->getIsOk()) {
                $result = $response->data;
                var_dump($result);
                var_dump("原文：$str;翻译结果：" . $result['output']['translation']);
                return $result['output']['translation'];
            } else {
                var_dump("请求接口失败，statusCode：" . $response->getStatusCode() . ",errContent：" . $response->getContent());
            }
        } catch (InvalidConfigException | Exception $e) {
            var_dump("请求接口失败，err：" . $e->getMessage());
        }
        return "";
    }

    public function getDataByCsv($filename)
    {
        // 打开 CSV 文件
        if (!file_exists($filename) || !is_readable($filename)) {
            return false;
        }

        $header = null;
        $data = [];

        if (($handle = fopen($filename, 'r')) !== false) {
            // 读取 CSV 文件的每一行
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                // 处理 CSV 的表头
                if (!$header) {
                    $header = $row;
                } else {
                    // 将每行数据与表头结合，并创建关联数组
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }

        // 将数据转换为 JSON 格式
        return $data;
    }
}

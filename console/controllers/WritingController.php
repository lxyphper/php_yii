<?php

namespace console\controllers;

use app\models\Exercises;
use app\models\ListeningExamPaper;
use app\models\ListeningExamQuestionType;
use app\models\ReadingExamPaper;
use app\models\SimulatePaperGroup;
use app\models\SimulatePaperGroupQuery;
use app\models\SimulatePaperType;
use app\models\WritingEssay;
use app\models\WritingEssayType;
use app\models\WritingExaminingQuestion;
use yii\console\Controller;
use yii\log\Logger;

class WritingController extends BaseController
{
    public function actionInitEssay($url)
    {
        $type_list = $this->getWritingEssayTypeList();
        //        var_dump($group_list);die;
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        //        $type_list = [];
        //        foreach ($arr as $key=>$value) {
        //            $type_list[] = $value->writing_type;
        //        }
        //
        //        var_dump(array_unique($type_list));die;

        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        $file_oss_path = "/exercises/imgs/essay/";

        $paper_list = Exercises::find()->andWhere(['<=', 'id', 58])->all();
        foreach ($paper_list as $value) {
            $title = str_replace("剑雅", '', $value->title);
            $title_arr = explode('_', $title);
            $new_title = 'C' . $title_arr[0] . '-T' . $title_arr[1] . '-T1';
            $paper_info = $arr->$new_title;

            $paperObj = WritingEssay::findOne(['title' => $value->title]);
            if (!empty($paperObj)) {
                \Yii::getLogger()->log("该试卷已存在，title:$new_title", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$new_title");
                $paperId = $paperObj->id;
            } else {
                //创建试卷
                $paper = new WritingEssay();
                $paper->title = $value->title;
                $paper->type = $type_list[$paper_info->writing_type];
                $paper->content = $paper_info->prompt_text;
                $paper->img_desc = $paper_info->img_desc;
                $paper->img_url = $file_oss_path . $paper_info->title . '.png';
                $paper->paper_group = $value->paper_group;
                try {
                    $paper->insert(true);
                } catch (\Throwable $e) {
                    \Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
                var_dump("$value->title 创建完成，id: $paperId");
            }
        }

        var_dump("初始化完成");
    }

    public function actionInitQuestion($url)
    {
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        try {
            $paper_list = Exercises::find()->andWhere(['>', 'id', 0])->andWhere(['enter_by' => 0])->all();
            foreach ($paper_list as $value) {
                $title = $value->title;
                if (!isset($arr->$title)) {
                    var_dump("$title 数据不存在");
                    continue;
                }
                $question_list = $arr->$title->questions;
                WritingExaminingQuestion::deleteAll(['paper_id' => $value->id]);
                $num = 1;
                foreach ($question_list as $val) {
                    $qObj = new WritingExaminingQuestion();
                    $qObj->title = $val->title ?? "";
                    $qObj->summary = $val->summary ?? "";
                    $qObj->option = $val->options;
                    $qObj->number = $num++;
                    $qObj->paper_id = $value->id;
                    foreach ((array)$qObj->option as $k => $v) {
                        if (isset($v->is_correct) && $v->is_correct == 'true') {
                            $qObj->answer = $k;
                        }
                    }
                    try {
                        $qObj->insert();
                    } catch (\Throwable $e) {
                        var_dump("插入题目数据失败，err=" . $e->getMessage());
                    }
                }
                var_dump("$title 题目数据初始化完成");
            }
        } catch (\Throwable $e) {
            var_dump("初始化$value->title ， $val->title , $v 失败，err=", $e->getMessage());
        }
    }

    public function actionFixFields($url)
    {
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        $content_map = [];
        foreach ($arr as $val) {
            $content_map[$val->header] = $val->requirement;
        }

        $paper_list = Exercises::find()->andWhere(['>', 'id', 0])->andWhere(['enter_by' => 0])->all();

        foreach ($paper_list as $key => $value) {
            $title = $value->title;
            if (isset($content_map[$title])) {
                $value->requirement = $content_map[$title];
                $value->save(false);
                var_dump($title . "更新完成");
            } else {
                var_dump("$title 在数据中不存在");
            }
        }

        var_dump("全部更新完成");
    }

    public function actionFixStatement($url)
    {
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }


        $paper_list = Exercises::find()->andWhere(['>', 'id', 0])->andWhere(['enter_by' => 0])->all();

        foreach ($paper_list as $key => $value) {
            $title = $value->title;
            if (isset($arr->$title)) {
                $value->statement_question = $arr->$title;
                $value->save(false);
                var_dump($title . "更新完成");
            } else {
                var_dump("$title 在数据中不存在");
            }
        }

        var_dump("全部更新完成");
    }

    public function getOptionList($options): array
    {
        shuffle($options);
        return $options;
    }

    public function getWritingEssayTypeList(): array
    {
        $listMap = [];
        $query = WritingEssayType::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->name] = $value->id;
        }

        return $listMap;
    }

    public function actionDealWithContent()
    {
        $paper_list = Exercises::find()->andWhere(['>', 'id', 0])->all();
        foreach ($paper_list as $value) {
            if (strlen($value->content) > 0) {
                $content = $this->filterStr($value->content);
                $value->search_content = $content;
                $value->save(false);
                var_dump("标题：" . $value->title . "，处理完成");
            }
        }
    }

    public function actionDealWithEssayContent()
    {
        $paper_list = WritingEssay::find()->andWhere(['>', 'id', 0])->all();
        foreach ($paper_list as $value) {
            if (strlen($value->content) > 0) {
                $content = $this->filterStr($value->content);
                $value->search_content = $content;
                $value->save();
                var_dump("标题：" . $value->title . "，处理完成");
            }
        }
    }

    public function filterStr($str): string
    {
        // 去除所有的符号和换行符
        $newStr = preg_replace('/[^a-zA-Z0-9\s]/', '', $str);

        // 将多个空格替换为一个空格
        return preg_replace('/\s+/', ' ', $newStr);
    }

    public function actionCheckQuestion()
    {
        $list = WritingExaminingQuestion::find()->where(['>', 'id', 3336])->all();
        if (!empty($list)) {
            foreach ($list as $value) {
                $is_change = false;
                $new_value = [];
                if (!empty($value->option)) {
                    foreach ((array)$value->option as $val) {
                        //                        $new_value[] = $val['explanation'];
                        //                        $paper_ids[] = $value->paper_id;
                        //                        if (mb_strlen($val['explanation']) < 30) {
                        //                            $new_value[] = $value->id;
                        //                            $paper_ids[] = $value->paper_id;
                        //                        }
                        if (!isset($val['explanation']) && isset($val['analysis_to_student'])) {
                            $val['explanation'] = $val['analysis_to_student'];
                            $is_change = true;
                        }
                        $new_value[] = $val;
                    }
                }
                if ($is_change) {
                    $value->option = $new_value;
                    var_dump($value->save());
                    var_dump("更换字段数据：$value->id 完成");
                }
            }
        }
    }

    public function actionFixEssayImgType()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/小作文识别题目图片类型.json';
        $content = file_get_contents($file);
        $arr = json_decode($content);
        $data_arr = [];
        foreach ($arr as $key => $value) {
            $data_arr[$value->id] = $this->getImgTypeMap()[$value->question_img_type] ?? $value->question_img_type;
        }
        // var_dump($data_arr);die;
        $list = WritingEssay::find()->where([">", "id", 0])->all();
        if (!empty($list)) {
            foreach ($list as $value) {
                $value->img_type = $data_arr[$value->id];
                $value->save();
                var_dump("更换字段数据：$value->id 完成");
            }
        }
    }

    public function actionInitEssayQuestion(): void
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/writing_task1_examining_single_choice_v2_剑雅20.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            //获取试卷
            $paper = WritingEssay::find()->where(['title' => $value->group_id])->one();
            if (empty($paper)) {
                var_dump("试卷不存在，group_id: $value->group_id");
                die;
            }
            $num = substr($value->header, strlen($value->group_id) + 1) + 1;
            var_dump("试卷：$value->group_id  序号：$num");
            //获取题目
            $question = WritingExaminingQuestion::find()->where(['paper_id' => $paper->id, 'number' => $num, 'task_type' => 2])->one();
            if (empty($question)) {
                var_dump("题目不存在，group_id: $value->group_id");
                $question = new WritingExaminingQuestion();
                $question->title = $value->context->question_stem;
                $question->option = $value->context->options;
                $question->number = $num;
                $question->task_type = 2;
                $question->paper_id = $paper->id;
                foreach ($value->context->options as $k => $v) {
                    if ($k == $value->context->answer) {
                        $question->answer = $k;
                    }
                }
                $question->insert(false);
                var_dump("题目创建成功，group_id: $value->group_id num: $num");
            } else {
                $question->title = $value->context->question_stem;
                $question->option = $value->context->options;
                $question->title_en = '';
                $question->option_en = '';
                foreach ($value->context->options as $k => $v) {
                    if ($v->name == $value->context->answer) {
                        $question->answer = $k;
                    }
                }
                $question->save(false);
                var_dump("题目已存在，group_id: $value->group_id num: $num");
            }
        }
        var_dump("初始化完成");
    }

    /**
     * Summary of getImgTypeMap
     * 1动态图 2静态图 3地图 4流程图
     * @return []
     */
    public function getImgTypeMap(): array
    {
        return [
            "动态图" => 1,
            "静态图" => 2,
            "地图" => 3,
            "流程图" => 4,
        ];
    }
}

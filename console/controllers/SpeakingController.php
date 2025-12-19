<?php

namespace console\controllers;

use app\models\Exercises;
use app\models\ListeningExamPaper;
use app\models\ListeningExamQuestion;
use app\models\ListeningExamQuestionGroup;
use app\models\ReadingExamPaper;
use app\models\ReadingExamQuestion;
use app\models\ReadingExamQuestionGroup;
use app\models\SimulateExamRecord;
use app\models\SimulateExamSpeaking;
use app\models\SimulatePaperGroup;
use app\models\SimulatePaperType;
use app\models\SpeakingExamPaper;
use app\models\SpeakingExamPaperCategory;
use app\models\SpeakingExamPaperQuery;
use app\models\SpeakingExamQuestion;
use app\models\SpeakingKetQuestion;
use app\models\SpeakingKetTopic;
use app\models\SpeakingPetQuestion;
use app\models\SpeakingPetTopic;
use app\models\SpeakingSimulatePaper;
use app\models\SpeakingSpecialItemGroup;
use app\models\SpeakingSpecialItemQuestion;
use app\models\SpeakingSpecialItemTopic;
use app\models\WritingEssay;
use app\models\SimulateExamSpeakingReport;
use app\models\SysAiTask;
use Elastic\Elasticsearch;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Exception\GuzzleException;
use yii\console\Controller;
use GuzzleHttp\Client;
use OSS\OssClient;
use OSS\Core\OssException;
use Yii;

class SpeakingController extends Controller
{
    public function actionInitDataPart()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/speak_list.json';
        $paper_content = file_get_contents($file);
        $paper_content = json_decode($paper_content);
        $paper_list = [];
        $parts = [];
        $c_list = $this->getCategoryList();
        $all_topic = [];
        foreach ($paper_content as $value) {
            foreach ($value->list as $val) {
                $all_topic[] = $val->oralTopicId;
                $parts[$value->unit][] = [
                    "id" => $val->oralTopicId,
                    "category" => $c_list[$value->name],
                    "name" => $val->oralTopicName,
                    "flag" => $val->ifNew == 0 ? 1 : 2,
                    "time_tag" => $val->timeTag,
                    "base_id" => $val->oralTopicId,
                ];
            }
        }

        SpeakingExamPaper::updateAll(['status' => 2], ['base_id' => $all_topic]);

        foreach ($parts as $k => $v) {
            foreach ($v as $vv) {
                if ($k == 'Part 1') {
                    $paper = SpeakingExamPaper::find()->where(['part' => 1, 'base_id' => $vv['base_id']])->one();
                    if (empty($paper)) {
                        $paper = new SpeakingExamPaper();
                        $paper->name = $vv['name'];
                        $paper->part = 1;
                        $paper->base_id = $vv['base_id'];
                        $paper->time_tag = $vv['time_tag'];
                        $paper->category = $vv['category'];
                        $paper->flag = $vv['flag'];
                        try {
                            $paper->insert();
                        } catch (\Throwable $e) {
                            var_dump("插入试卷：$paper->base_id 失败, err:" . $e->getMessage());
                        }
                    }
                } elseif ($k == 'Part 2&3') {
                    $paper = SpeakingExamPaper::find()->where(['part' => 2, 'base_id' => $vv['base_id']])->one();
                    if (empty($paper)) {
                        $paper = new SpeakingExamPaper();
                        $paper->name = $vv['name'];
                        $paper->part = 2;
                        $paper->base_id = $vv['base_id'];
                        $paper->time_tag = $vv['time_tag'];
                        $paper->category = $vv['category'];
                        $paper->flag = $vv['flag'];
                        try {
                            $paper->insert();
                        } catch (\Throwable $e) {
                            var_dump("插入试卷：$paper->base_id 失败, err:" . $e->getMessage());
                        }
                        $paper3 = new SpeakingExamPaper();
                        $paper3->name = $vv['name'];
                        $paper3->part = 3;
                        $paper3->group_id = $paper->id;
                        $paper3->base_id = $vv['base_id'];
                        $paper3->time_tag = $vv['time_tag'];
                        $paper3->category = $vv['category'];
                        $paper3->flag = $vv['flag'];
                        try {
                            $paper3->insert();
                        } catch (\Throwable $e) {
                            var_dump("插入试卷：$paper3->base_id 失败, err:" . $e->getMessage());
                        }
                    }
                }
                var_dump("试卷：" . $vv['base_id'] . "插入成功");
            }
        }
        var_dump("处理完成");
    }

    public function actionInitDataQuestion()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/speak_list1.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        $category_map = [
            "event" => 3,
            "location" => 4,
            "thing" => 2,
            "person" => 1,
        ];
        foreach ($content as $value) {
            if ($value->part == 0) {
                foreach ($value->detail->content->oralQuestionDetailVOList as $val) {
                    $paper_info = SpeakingExamPaper::find()->where(['part' => 1, 'base_id' => $val->oralTopicId])->one();
                    if (empty($paper_info)) {
                        $paper_info = new SpeakingExamPaper();
                        $paper_info->name = $val->oralTopicName;
                        $paper_info->flag = $value->ifNew + 1;
                        $paper_info->time_tag = $value->timeTag;
                        $paper_info->part = 1;
                        $paper_info->category = $category_map[$val->oralTopCategoryName];
                        $paper_info->base_id = $val->oralTopicId;
                        try {
                            $paper_info->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("$val->oralTopicName 插入失败，err = " . $e->getMessage());
                            die;
                        }
                        var_dump("话题 $val->oralTopicId 插入成功");
                    }
                    $paper_id = $paper_info->id;
                    $question_info = SpeakingExamQuestion::find()->where(['paper_id' => $paper_id, 'source_id' => $val->oralQuestionId])->one();
                    if (empty($question_info)) {
                        $question_info = new SpeakingExamQuestion();
                        $question_info->source_id = $val->oralQuestionId;
                        $question_info->title = $val->oralQuestion;
                        $question_info->paper_id = $paper_id;
                        try {
                            $question_info->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("$val->oralQuestion 插入失败，err = " . $e->getMessage());
                            die;
                        }
                        var_dump("题目 $val->oralQuestionId 插入成功");
                    }
                }
            } else {
                foreach ($value->detail->content->oralQuestionDetailVOList as $val) {
                    if ($val->oralPart == 2) {
                        $paper_info = SpeakingExamPaper::find()->where(['part' => 2, 'base_id' => $val->oralTopicId])->one();
                        if (empty($paper_info)) {
                            $paper_info = new SpeakingExamPaper();
                            $paper_info->name = $val->oralTopicName;
                            $paper_info->flag = $value->ifNew + 1;
                            $paper_info->time_tag = $value->timeTag;
                            $paper_info->part = 2;
                            $paper_info->category = $category_map[$val->oralTopCategoryName];
                            $paper_info->base_id = $val->oralTopicId;
                            try {
                                $paper_info->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("$val->oralTopicId 插入失败，err = " . $e->getMessage());
                                die;
                            }
                            var_dump("话题 $val->oralTopicId 插入成功");
                        }
                    } else {
                        $paper_info = SpeakingExamPaper::find()->where(['part' => 3, 'base_id' => $val->oralTopicId])->one();
                        $paper_info2 = SpeakingExamPaper::find()->where(['part' => 2, 'base_id' => $val->oralTopicId])->one();
                        if (empty($paper_info)) {
                            $paper_info = new SpeakingExamPaper();
                            $paper_info->name = $val->oralTopicName;
                            $paper_info->flag = $value->ifNew + 1;
                            $paper_info->time_tag = $value->timeTag;
                            $paper_info->part = 3;
                            $paper_info->category = $category_map[$val->oralTopCategoryName];
                            $paper_info->base_id = $val->oralTopicId;
                            $paper_info->group_id = $paper_info2->id;
                            try {
                                $paper_info->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("$val->oralTopicId 插入失败，err = " . $e->getMessage());
                                die;
                            }
                            var_dump("话题 $val->oralTopicId 插入成功");
                        } else {
                            $paper_info2 = SpeakingExamPaper::find()->where(['part' => 2, 'base_id' => $val->oralTopicId])->one();
                            var_dump($val->oralTopicId);
                            $paper_info->group_id = $paper_info2->id;
                            $paper_info->save(false);
                            var_dump("话题 $val->oralTopicId 更新成功");
                        }
                    }
                    $paper_id = $paper_info->id;

                    $question_info = SpeakingExamQuestion::find()->where(['paper_id' => $paper_id, 'source_id' => $val->oralQuestionId])->one();
                    if (empty($question_info)) {
                        $question_info = new SpeakingExamQuestion();
                        $question_info->source_id = $val->oralQuestionId;
                        $question_info->title = $val->oralQuestion;
                        $question_info->paper_id = $paper_id;
                        try {
                            $question_info->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("$val->oralQuestionId 插入失败，err = " . $e->getMessage());
                            die;
                        }
                        var_dump("题目 $val->oralQuestionId 插入成功");
                    }
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionFixQuestion()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/口语进阶气泡内容_2025年9月16日.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            $id = $value->id;
            $question_list = SpeakingExamQuestion::find()->where(['source_id' => $id])->all();
            if (!empty($question_list)) {
                foreach ($question_list as $question) {
                    if (empty($question->tips)) {
                        $question->tips = $this->uploadTips(json_encode(['expression' => $value->expression_simple], JSON_UNESCAPED_UNICODE));
                        $question->more_tips = $this->uploadTips(json_encode(['expression' => $value->expression_advanced], JSON_UNESCAPED_UNICODE));
                        $question->save(false);
                    }
                }
            }
            var_dump("$id 保存完成");
        }
        var_dump("全部处理完成");
    }

    public function actionFixQuestionTips()
    {
        $question_list = SpeakingExamQuestion::find()->all();
        if (!empty($question_list)) {
            foreach ($question_list as $question) {
                if (!empty($question->tips)) {
                    //从阿里云下载文件并检查内容长度
                    $new_tips = $this->downloadAndCheckContent($question->tips);
                    if (!empty($new_tips)) {
                        $question->tips = $new_tips;
                    }

                    if (!empty($question->more_tips)) {
                        $new_more_tips = $this->downloadAndCheckContent($question->more_tips);
                        if (!empty($new_more_tips)) {
                            $question->more_tips = $new_more_tips;
                        }
                    }

                    $question->save(false);
                    var_dump("题目 ID: {$question->id} 处理完成");
                }
            }
        }
        var_dump("全部处理完成");
    }

    public function downloadAndCheckContent($file_url)
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

                //检查内容长度
                if (strlen($content) >= 500) {
                    var_dump("第 {$attempt} 次下载成功，内容长度: " . strlen($content));
                    //内容长度满足要求，重新上传到阿里云
                    return $file_url;
                } else {
                    $file_url = $content;
                    var_dump("第 {$attempt} 次下载内容长度不足: " . strlen($content) . "，继续重试");
                }
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

    public function actionFixSubQuestion()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/part2_splited_question_2025年9月16日.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            $id = $value->id;
            $question_list = SpeakingExamQuestion::find()->where(['source_id' => $id])->all();
            foreach ($question_list as $question) {
                $question->sub_questions = $value->sub_question;
                $question->save(false);
            }
            var_dump("$id 保存完成");
        }
        var_dump("全部处理完成");
    }

    public function actionAddNewData()
    {
        $local_path = dirname(__FILE__, 2);
        $filename = $local_path . '/runtime/tmp/雅思口语新题_2025年9月16日.csv';
        $data = $this->getDataByCsv($filename);
        if (empty($data)) {
            var_dump("数据为空");
            return false;
        }

        $now = time();
        $show_topic_ids = array_column($data, 'topic_id');
        // var_dump("show_topic_ids:".json_encode($show_topic_ids));die;

        foreach ($data as $item) {
            $category_name = isset($item['category']) ? $item['category'] : $item['category_name'];
            $paper = SpeakingExamPaper::find()->where(['part' => $item['part'], 'base_id' => $item['topic_id']])->one();
            if (empty($paper)) {
                $paper = new SpeakingExamPaper();
                $paper->name = $item['topic_name'];
                $paper->part = $item['part'];
                $paper->base_id = $item['topic_id'];
                $paper->time_tag = $item['time_tag'];
                $paper->category = $this->getCategoryMap()[$category_name] ?? 0;
                $paper->flag = $item['is_new'] == 0 ? 1 : 2;
                $paper->create_time = $now;
                $paper->update_time = $now;
                $paper->passed_num = $item['recent_exam_count'];
                if ($item['part'] == 3) {
                    $part2_paper = SpeakingExamPaper::find()->where(['part' => 2, 'base_id' => $item['topic_id']])->one();
                    if (empty($part2_paper)) {
                        var_dump("part2_paper: " . $item['topic_id'] . " 不存在");
                        die;
                    }
                    $paper->group_id = $part2_paper->id;
                }
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    var_dump("插入试卷：$paper->base_id 失败, err:" . $e->getMessage());
                }
            } else {
                $paper->category = $this->getCategoryMap()[$category_name] ?? 0;
                $paper->name = $item['topic_name'];
                $paper->time_tag = $item['time_tag'];
                $paper->flag = $item['is_new'] == 0 ? 1 : 2;
                $paper->update_time = $now;
                $paper->passed_num = $item['recent_exam_count'];
                $paper->save(false);
                var_dump("话题：" . $item['topic_id'] . "已存在");
                // continue;
            }

            $question = SpeakingExamQuestion::find()->where(['paper_id' => $paper->id, 'source_id' => $item['question_id']])->one();
            if (empty($question)) {
                $question = new SpeakingExamQuestion();
                $question->title = $item['question'];
                $question->paper_id = $paper->id;
                $question->source_id = $item['question_id'];
                $question->create_time = $now;
                $question->update_time = $now;
                try {
                    $question->insert();
                } catch (\Throwable $e) {
                    var_dump("插入问题：$paper->base_id 失败, err:" . $e->getMessage());
                }
            } else {
                $question->title = $item['question'];
                $question->save(false);
                var_dump("问题：" . $item['question_id'] . "已存在");
                continue;
            }

            var_dump("题目插入完成，question_id=" . $item['question_id']);
        }

        //更新要隐藏的数据
        SpeakingExamPaper::updateAll(['status' => 2], ['is_lms' => 2]);
        SpeakingExamPaper::updateAll(['status' => 1], ['base_id' => $show_topic_ids]);

        var_dump("处理完成");
        var_dump(json_encode($show_topic_ids));
        return true;
    }

    public function actionInitKetData()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/KET_questions(1).json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        $part_map = [
            "A1" => 1,
            "A2" => 2,
        ];
        foreach ($content as $value) {
            $topic = SpeakingKetTopic::find()->where(['name' => $value->topic_name, 'part' => $part_map[$value->part]])->one();
            if (empty($topic)) {
                $topic = new SpeakingKetTopic();
                $topic->name = $value->topic_name;
                $topic->part = $part_map[$value->part];
                $topic->create_time = time();
                $topic->update_time = time();
                try {
                    $topic->insert();
                } catch (\Throwable $e) {
                    var_dump("插入话题失败，err = " . $e->getMessage());
                    die;
                }
                var_dump("话题 $value->topic_name 插入成功");
            } else {
                $topic->name = $value->cn_name;
                $topic->en_name = $value->en_name;
                $topic->save(false);
                var_dump("话题 $value->topic_name 已存在");
            }
            // $topic_id = $topic->id;
            // foreach ($value->questions as $val) {
            //     $question = SpeakingKetQuestion::find()->where(['topic'=>$topic_id, 'name'=>$val->question])->one();
            //     if (empty($question)) {
            //         $question = new SpeakingKetQuestion();
            //         $question->topic = $topic_id;
            //         $question->name = $val->question;
            //         $question->create_time = time();
            //         $question->update_time = time();
            //         try {
            //             $question->insert();
            //         } catch (\Throwable $e) {
            //             var_dump("插入问题失败，err = " . $e->getMessage());
            //             die;
            //         }
            //         var_dump("问题 $val->question 插入成功");
            //     }
            // }
        }
        var_dump("处理完成");
    }

    public function actionInitPetData()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/PET_questions(1).json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        $part_map = [
            "B1" => 1,
        ];
        foreach ($content as $value) {
            $topic = SpeakingPetTopic::find()->where(['name' => $value->topic_name, 'part' => $part_map[$value->part]])->one();
            if (empty($topic)) {
                $topic = new SpeakingPetTopic();
                $topic->name = $value->topic_name;
                $topic->part = $part_map[$value->part];
                $topic->create_time = time();
                $topic->update_time = time();
                try {
                    $topic->insert();
                } catch (\Throwable $e) {
                    var_dump("插入话题失败，err = " . $e->getMessage());
                    die;
                }
                var_dump("话题 $value->topic_name 插入成功");
            } else {
                $topic->name = $value->cn_name;
                $topic->en_name = $value->en_name;
                $topic->save(false);
                var_dump("话题 $value->topic_name 已存在");
            }
            // $topic_id = $topic->id;
            // foreach ($value->questions as $val) {
            //     $question = SpeakingPetQuestion::find()->where(['topic'=>$topic_id, 'name'=>$val->question])->one();
            //     if (empty($question)) {
            //         $question = new SpeakingPetQuestion();
            //         $question->topic = $topic_id;
            //         $question->name = $val->question;
            //         $question->create_time = time();
            //         $question->update_time = time();
            //         try {
            //             $question->insert();
            //         } catch (\Throwable $e) {
            //             var_dump("插入问题失败，err = " . $e->getMessage());
            //             die;
            //         }
            //         var_dump("问题 $val->question 插入成功");
            //     }
            // }
        }
        var_dump("处理完成");
    }

    public function actionFixKetData()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/KET-口语进阶气泡内容.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            $data = SpeakingKetQuestion::find()->where(["id" => $value->id])->one();
            if (!empty($data)) {
                $data->emoji = "thinking-face";
                $data->more_emoji = "face-with-monocle";
                $data->tips = json_encode(['expression' => $value->expression_simple], JSON_UNESCAPED_UNICODE);
                $data->more_tips = json_encode(['expression' => $value->expression_advanced], JSON_UNESCAPED_UNICODE);
                $data->save(false);
                var_dump("题目：$key 保存完成");
            } else {
                var_dump("题目：$key 不存在");
            }
        }
    }

    public function actionFixPetData()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/PET-口语进阶气泡内容.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            $data = SpeakingPetQuestion::find()->where(["id" => $value->id])->one();
            if (!empty($data)) {
                $data->emoji = "thinking-face";
                $data->more_emoji = "face-with-monocle";
                $data->tips = json_encode(['expression' => $value->expression_simple], JSON_UNESCAPED_UNICODE);
                $data->more_tips = json_encode(['expression' => $value->expression_advanced], JSON_UNESCAPED_UNICODE);
                $data->save(false);
                var_dump("题目：$key 保存完成");
            } else {
                var_dump("题目：$key 不存在");
            }
        }
    }

    public function actionInitSentenceFollowAlong()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/句子跟读.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $value) {
            $topic = SpeakingSpecialItemTopic::find()->where(['name' => $value->title])->one();
            if (empty($topic)) {
                $topic = new SpeakingSpecialItemTopic();
                $topic->name = $value->title;
                $topic->type = 1;
                $topic->insert(false);
                var_dump("topic：$value->title 保存完成");
            } else {
                var_dump("topic：$value->title 已存在");
            }
            foreach ($value->sub_group as $ke => $val) {
                $num = $ke + 1;
                $group_name = 'Group ' . $num;
                $group = SpeakingSpecialItemGroup::find()->where(['desc' => $val->sub_title, 'title' => $group_name, 'topic' => $topic->id])->one();
                if (empty($group)) {
                    $group = new SpeakingSpecialItemGroup();
                    $group->title = $group_name;
                    $group->desc = $val->sub_title;
                    $group->topic = $topic->id;
                    $group->tips = $val->intro;
                    $group->insert(false);
                    var_dump("group：$val->sub_title 保存完成");
                } else {
                    var_dump("group：$val->sub_title 已存在");
                }
                foreach ($val->sentences as $k => $v) {
                    $question = SpeakingSpecialItemQuestion::find()->where(['group_id' => $group->id, 'title' => $v->english])->one();
                    if (empty($question)) {
                        $question = new SpeakingSpecialItemQuestion();
                        $question->title = $v->english;
                        $question->group_id = $group->id;
                        $question->tip = $v->tip;
                        $question->insert(false);
                        var_dump("question：$v->english 保存完成");
                    } else {
                        var_dump("question：$v->english 已存在");
                    }
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionInitParagraphFollowAlong()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/段落跟读.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $value) {
            $topic = SpeakingSpecialItemTopic::find()->where(['name' => $value->title, 'type' => 2])->one();
            if (empty($topic)) {
                $topic = new SpeakingSpecialItemTopic();
                $topic->name = $value->title;
                $topic->type = 2;
                $topic->insert(false);
                var_dump("topic：$value->title 保存完成");
            } else {
                $topic->category = $this->getCategoryList()[$value->category] ?? 0;
                $topic->save(false);
                var_dump("topic：$value->title 已存在");
                continue;
            }
            foreach ($value->sub_group as $ke => $val) {
                $num = $ke + 1;
                $group_name = 'Group ' . $num;
                $group = SpeakingSpecialItemGroup::find()->where(['desc' => $val->sub_title, 'title' => $group_name, 'topic' => $topic->id])->one();
                if (empty($group)) {
                    $group = new SpeakingSpecialItemGroup();
                    $group->title = $group_name;
                    $group->desc = $val->sub_title;
                    $group->topic = $topic->id;
                    $group->tips = $val->intro;
                    $group->insert(false);
                    var_dump("group：$val->sub_title 保存完成");
                } else {
                    var_dump("group：$val->sub_title 已存在");
                }
                foreach ($val->sentences as $k => $v) {
                    $question = SpeakingSpecialItemQuestion::find()->where(['group_id' => $group->id, 'title' => $v->english])->one();
                    if (empty($question)) {
                        $question = new SpeakingSpecialItemQuestion();
                        $question->title = $v->english;
                        $question->group_id = $group->id;
                        $question->tip = $v->tip;
                        $question->insert(false);
                        var_dump("question：$v->english 保存完成");
                    } else {
                        var_dump("question：$v->english 已存在");
                    }
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionInitSentenceTranslation()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/句子翻译.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $value) {
            $topic = SpeakingSpecialItemTopic::find()->where(['name' => $value->title, 'type' => 3])->one();
            if (empty($topic)) {
                $topic = new SpeakingSpecialItemTopic();
                $topic->name = $value->title;
                $topic->desc = $value->description;
                $topic->type = 3;
                $topic->insert(false);
                var_dump("topic：$value->title 保存完成");
            } else {
                var_dump("topic：$value->title 已存在");
            }
            foreach ($value->sub_group as $ke => $val) {
                $group = SpeakingSpecialItemGroup::find()->where(['title' => $val->sub_title, 'topic' => $topic->id])->one();
                if (empty($group)) {
                    $group = new SpeakingSpecialItemGroup();
                    $group->title = $val->sub_title;
                    $group->topic = $topic->id;
                    $group->tips = $val->intro;
                    $group->insert(false);
                    var_dump("group：$val->sub_title 保存完成");
                } else {
                    var_dump("group：$val->sub_title 已存在");
                }
                foreach ($val->sentences as $k => $v) {
                    $question = SpeakingSpecialItemQuestion::find()->where(['group_id' => $group->id, 'title' => $v->question])->one();
                    if (empty($question)) {
                        $question = new SpeakingSpecialItemQuestion();
                        $question->title = $v->question;
                        $question->answer = $v->answer;
                        $question->group_id = $group->id;
                        $question->tip = $v->tip;
                        $question->insert(false);
                        var_dump("question：$v->question 保存完成");
                    } else {
                        var_dump("question：$v->question 已存在");
                    }
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionInitParagraphTranslation()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/段落翻译(1).json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $value) {
            $topic = SpeakingSpecialItemTopic::find()->where(['name' => $value->title, 'type' => 4])->one();
            if (empty($topic)) {
                $topic = new SpeakingSpecialItemTopic();
                $topic->name = $value->title;
                $topic->type = 4;
                $topic->insert(false);
                var_dump("topic：$value->title 保存完成");
            } else {
                var_dump("topic：$value->title 已存在");
            }
            foreach ($value->sub_group as $ke => $val) {
                $group = SpeakingSpecialItemGroup::find()->where(['title' => $val->sub_title, 'topic' => $topic->id])->one();
                if (empty($group)) {
                    $group = new SpeakingSpecialItemGroup();
                    $group->title = $val->sub_title;
                    $group->topic = $topic->id;
                    $group->tips = $val->intro;
                    $group->insert(false);
                    var_dump("group：$val->sub_title 保存完成");
                } else {
                    $group->tips = $val->intro;
                    $group->save(false);
                    var_dump("group：$val->sub_title 已存在");
                }
                // foreach ($val->sentences as $k=>$v) {
                //     $question = SpeakingSpecialItemQuestion::find()->where(['group_id'=>$group->id, 'title'=>$v->question])->one();
                //     if (empty($question)) {
                //         $question = new SpeakingSpecialItemQuestion();
                //         $question->title = $v->question;
                //         $question->answer = $v->answer;
                //         $question->group_id = $group->id;
                //         $question->tip = $v->tip;
                //         $question->insert(false);
                //         var_dump("question：$v->question 保存完成");
                //     } else {
                //         var_dump("question：$v->question 已存在");
                //     }
                // }
            }
        }
        var_dump("处理完成");
    }

    public function actionInitMergeSimpleSentences()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/合并简单句.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $value) {
            $topic = SpeakingSpecialItemTopic::find()->where(['name' => $value->title, 'type' => 5])->one();
            if (empty($topic)) {
                $topic = new SpeakingSpecialItemTopic();
                $topic->name = $value->title;
                $topic->type = 5;
                $topic->insert(false);
                var_dump("topic：$value->title 保存完成");
            } else {
                var_dump("topic：$value->title 已存在");
            }
            foreach ($value->sub_group as $ke => $val) {
                $group = SpeakingSpecialItemGroup::find()->where(['title' => $val->sub_title, 'topic' => $topic->id])->one();
                if (empty($group)) {
                    $group = new SpeakingSpecialItemGroup();
                    $group->title = $val->sub_title;
                    $group->topic = $topic->id;
                    $group->tips = $val->intro;
                    $group->insert(false);
                    var_dump("group：$val->sub_title 保存完成");
                } else {
                    var_dump("group：$val->sub_title 已存在");
                }
                foreach ($val->sentences as $k => $v) {
                    $question = SpeakingSpecialItemQuestion::find()->where(['group_id' => $group->id, 'title' => $v->question])->one();
                    if (empty($question)) {
                        $question = new SpeakingSpecialItemQuestion();
                        $question->title = $v->question;
                        $question->answer = $v->answer;
                        $question->group_id = $group->id;
                        $question->tip = $v->tip;
                        $question->insert(false);
                        var_dump("question：$v->question 保存完成");
                    } else {
                        var_dump("question：$v->question 已存在");
                    }
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionFixIeltsData()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/口语进阶气泡内容_2025年05月13日.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $key => $value) {
            $data = SpeakingExamQuestion::find()->where(["source_id" => $value->id])->one();
            if (!empty($data)) {
                // $data->sub_questions = $value->sub_question;
                $data->emoji = "thinking-face";
                $data->more_emoji = "face-with-monocle";
                $data->tips = json_encode(['expression' => $value->expression_simple], JSON_UNESCAPED_UNICODE);
                $data->more_tips = json_encode(['expression' => $value->expression_advanced], JSON_UNESCAPED_UNICODE);
                $data->save(false);
                var_dump("题目：$value->id 保存完成");
            } else {
                var_dump("题目：$value->id 不存在");
            }
        }
        var_dump("处理完成");
    }

    /**
     * 处理Lms题组
     * Summary of actionFixSpecialItemGroup
     * @return void
     */
    public function actionFixSpecialItemGroup()
    {
        $list = SpeakingSpecialItemTopic::find()->where(["status" => 1])->asArray()->all();
        foreach ($list as $topic) {
            $topic_id = $topic['id'];
            $group_list = SpeakingSpecialItemGroup::find()->where(["topic" => $topic_id])->asArray()->all();
            foreach ($group_list as $group) {
                $group_id = $group['id'];
                $new_group = new SpeakingSpecialItemGroup();
                //替换title中的Group 为Section
                $new_group->title = str_replace("Group", "Section", $group['title']);
                $new_group->desc = $group['desc'];
                $new_group->tips = $group['tips'];
                $new_group->topic = $topic_id;
                $new_group->type = 2;
                $new_group->status = 1;
                $new_group->desc = $group['desc'];
                $new_group->insert(false);
                var_dump("new_group_id = " . $new_group->id);
                $new_group_id = $new_group->id;
                $question_list = SpeakingSpecialItemQuestion::find()->where(["group_id" => $group_id])->asArray()->all();
                foreach ($question_list as $key => $question) {
                    if ($key > 1) {
                        continue;
                    }
                    $new_question = new SpeakingSpecialItemQuestion();
                    $new_question->title = $question['title'];
                    $new_question->answer = $question['answer'];
                    $new_question->group_id = $new_group_id;
                    $new_question->tip = $question['tip'];
                    $new_question->status = 1;
                    $new_question->title_audio = $question['title_audio'];
                    $new_question->answer_audio = $question['answer_audio'];
                    $new_question->insert(false);
                    var_dump('new_question_id = ' . $new_question->id);
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionCheckPart2()
    {
        $paper_list = SpeakingExamPaper::find()->where(['part' => 2])->all();
        $paper_id_list = array_column($paper_list, 'id');
        $question_list = SpeakingExamQuestion::find()->where(['paper_id' => $paper_id_list])->all();
        foreach ($question_list as $question) {
            $content = $question->title;
            //用换行符分割字符串
            $lines = explode("\n", $content);
            //判断$lines[1]是否存在字符串"You should say"
            if (strpos($lines[1], "You should say") === false) {
                var_dump($lines[1]);
                var_dump("question_id = " . $question->id);
            }
        }
        var_dump("处理完成");
    }

    public function actionAddTopic()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/speaking-add2.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        foreach ($content as $value) {
            $topic = SpeakingExamPaper::find()->where(['name' => $value->topic, "part" => $value->part, 'status' => 1])->one();
            if (empty($topic)) {
                $topic = new SpeakingExamPaper();
                $topic->name = $value->topic;
                $topic->part = $value->part;
                $topic->status = 1;
                $topic->category = $this->getCategoryMap()[$value->type];
                $topic->flag = 2;
                $topic->insert(false);
                var_dump("topic：$value->topic 保存完成");
            } else {
                var_dump("topic：$value->topic 已存在");
            }
            if ($value->part == 1) {
                foreach ($value->question_list as $val) {
                    $question = SpeakingExamQuestion::find()->where(["title" => $val, 'paper_id' => $topic->id])->one();
                    if (empty($question)) {
                        $question = new SpeakingExamQuestion();
                        $question->title = $val;
                        $question->paper_id = $topic->id;
                        $question->insert(false);
                        var_dump("$val 保存完成");
                    } else {
                        var_dump("$val 已存在");
                    }
                }
            } else if ($value->part == 2) {
                $question = SpeakingExamQuestion::find()->where(["title" => $value->question, 'paper_id' => $topic->id])->one();
                if (empty($question)) {
                    $question = new SpeakingExamQuestion();
                    $question->title = $value->question;
                    $question->paper_id = $topic->id;
                    $question->insert(false);
                    var_dump("$value->question 保存完成");
                } else {
                    var_dump("$value->question 已存在");
                }
                $part3_topic = SpeakingExamPaper::find()->where(['name' => $value->topic, "part" => 3, 'status' => 1])->one();
                if (empty($part3_topic)) {
                    $part3_topic = new SpeakingExamPaper();
                    $part3_topic->name = $value->topic;
                    $part3_topic->part = 3;
                    $part3_topic->group_id = $topic->id;
                    $part3_topic->status = 1;
                    $part3_topic->flag = 2;
                    $part3_topic->category = $this->getCategoryMap()[$value->type];
                    $part3_topic->insert(false);
                    var_dump("part3_topic：$value->topic 保存完成");
                } else {
                    var_dump("part3_topic：$value->topic 已存在");
                }
                foreach ($value->part3->question_list as $val) {
                    $question = SpeakingExamQuestion::find()->where(["title" => $val, 'paper_id' => $part3_topic->id])->one();
                    if (empty($question)) {
                        $question = new SpeakingExamQuestion();
                        $question->title = $val;
                        $question->paper_id = $part3_topic->id;
                        $question->insert(false);
                        var_dump("$val 保存完成");
                    } else {
                        var_dump("$val 已存在");
                    }
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionFixSimulateRecord()
    {
        $record_list = SimulateExamSpeaking::find()->where(['record_id' => 0])->all();
        foreach ($record_list as $item) {
            $simulate_record = new SimulateExamRecord();
            $simulate_record->student_id = $item->student_id;
            $simulate_record->create_by = $item->create_by;
            $simulate_record->update_by = $item->create_by;
            $simulate_record->create_time = $item->create_time;
            $simulate_record->update_time = $item->create_time;
            $simulate_record->type = 5;
            if ($item->status == 6) {
                $simulate_record->status = 2;
            } else {
                $simulate_record->status = 1;
            }
            $simulate_record->insert(false);
            //更新记录中的关联record_id
            $item->record_id = $simulate_record->id;
            $item->save(false);
            var_dump('id：' . $simulate_record->id . ' student_id：' . $item['student_id'] . ' status：' . $simulate_record->status . ' 保存完成');
        }
    }

    public function actionInitSimulate()
    {
        // $paper_list = SpeakingSimulatePaper::find()->asArray()->all();
        // $topic_ids = [];
        // foreach ($paper_list as $paper) {
        //     $part1 = explode(',',$paper['part1_topic']);
        //     $topic_ids = array_merge($topic_ids, $part1);
        //     $topic_ids[] = $paper['part2_topic'];
        // }
        // var_dump(array_unique($topic_ids));
        // $topic_list = SpeakingExamPaper::find()->where(['id'=>$topic_ids, 'status'=>1])->asArray()->all();
        // var_dump($topic_list);
        // die;
        //获取模考分类
        $type_list = SimulatePaperType::find()->asArray()->all();
        $type_map = [];
        foreach ($type_list as $type) {
            $type_map[$type['id']] = $type['name'];
        }
        $group_list = SimulatePaperGroup::find()->asArray()->all();
        //获取口语话题
        $topic_list = SpeakingExamPaper::find()->where(['status' => 1, 'is_lms' => 2])->asArray()->all();
        $part1_topic = [];
        $part2_topic = [];
        foreach ($topic_list as $topic) {
            if ($topic['part'] == 1) {
                $part1_topic[] = $topic['id'];
            } else if ($topic['part'] == 2) {
                $part2_topic[] = $topic['id'];
            }
        }

        $group_arr = [];
        foreach ($group_list as $group) {
            $group_arr[] = [
                'id' => $group['id'],
                'name' => $type_map[$group['paper_type']] . ' ' . $group['name'],
            ];
        }

        $part1_exit = [];
        $part2_exit = [];
        foreach ($group_arr as $item) {
            $paper = SpeakingSimulatePaper::find()->where(['paper_group' => $item['id']])->one();
            if (empty($paper)) {
                $paper = new SpeakingSimulatePaper();
                $paper->paper_group = $item['id'];
                $paper->title = $item['name'];
            }

            if (!empty($paper->part1_topic)) {
                //判断part1中的话题是否有不存在的话题id
                $part1_topic_list = explode(',', $paper->part1_topic);
                $part1_topic_list = array_diff($part1_topic_list, $part1_topic);
                if (!empty($part1_topic_list)) {
                    $part1 = $this->getPart1Topic($part1_topic, $part1_exit);
                }
            } else {
                //从part1中随机2到5个话题用逗号拼接成字符串
                $part1 = $this->getPart1Topic($part1_topic, $part1_exit);
            }
            $part1_exit[] = $part1;
            $paper->part1_topic = $part1;

            //判断part2中的话题是否有不存在的话题id
            if (!empty($paper->part2_topic)) {
                if (!in_array($paper->part2_topic, $part2_topic)) {
                    $part2 = $this->getPart2Topic($part2_topic, $part2_exit);
                }
            } else {
                //part2中随机1个话题id
                $part2 = $this->getPart2Topic($part2_topic, $part2_exit);
            }
            $part2_exit[] = $part2;
            $paper->part2_topic = $part2;
            $paper->save(false);
            var_dump('id：' . $paper->id . ' group_id：' . $item['id'] . ' part1：' . $part1 . ' part2：' . $part2 . ' 保存完成');
        }
    }

    //从part1中随机2到5个话题用逗号拼接成字符串，已存在的则重新生成，直到不重复
    public function getPart1Topic($part1_topic, $part1_exit)
    {
        $random_keys = array_rand($part1_topic, rand(2, 5));
        $part1_values = array_intersect_key($part1_topic, array_flip($random_keys));
        $part1 = implode(',', $part1_values);
        while (in_array($part1, $part1_exit)) {
            $random_keys = array_rand($part1_topic, rand(2, 5));
            $part1_values = array_intersect_key($part1_topic, array_flip($random_keys));
            $part1 = implode(',', $part1_values);
        }
        return $part1;
    }

    //从part2中随机1个话题id，已存在的则重新生成，直到不重复
    public function getPart2Topic($part2_topic, $part2_exit)
    {
        $random_key = array_rand($part2_topic, 1);
        $part2 = $part2_topic[$random_key];
        while (in_array($part2, $part2_exit)) {
            $random_key = array_rand($part2_topic, 1);
            $part2 = $part2_topic[$random_key];
        }
        return $part2;
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

    public function getCategoryList(): array
    {
        $data = [];
        $list = SpeakingExamPaperCategory::find()->all();
        foreach ($list as $value) {
            $data[$value->name] = $value->id;
        }

        return $data;
    }

    public function getPartList()
    {
        return [
            "Part 1" => 1,
            "Part 2" => 2,
            "Part 3" => 3,
        ];
    }

    public function getCategoryMap(): array
    {
        return [
            "thing" => 2,
            "event" => 3,
            "person" => 1,
            "location" => 4,
        ];
    }

    public function uploadTips($data)
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
        $remote_file = 'student/speaking/tips/' . $filename;
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
        $retryCount = 0;
        $result = array();

        while ($retryCount < $maxRetries) {
            try {
                $getOssInfo = $oss->uploadFile($bucket, $object, $filepath);
                var_dump($getOssInfo);
                $result['url'] = $getOssInfo['info']['url'];
                return $result['url'];
            } catch (OssException $e) {
                $retryCount++;
                var_dump($e->getMessage());
                if ($retryCount >= $maxRetries) {
                    throw $e;
                }
                sleep(1);
            }
        }
        return $result['url'];
    }

    /**
     * 批量重置口语模考报告
     * 每次执行一组SQL，然后每30秒检查一次该组的sys_ai_task中的status是否都为2
     * 如果都为2，再执行下一组
     *
     * @return void
     */
    public function actionBatchResetSpeakingReport()
    {
        // 数据分组（每4个一组）
        $data = [1909, 1913, 1922, 1945, 1966, 1971, 1977, 2001, 2002, 2014, 2038, 2046, 2066, 2078, 2079, 2091, 2101, 2107, 2130, 2140, 2149, 2152, 2163, 2170, 2184, 2185, 2192, 2194, 2200, 2225, 2255, 2268, 2269, 2271, 2281, 2283, 2298, 2307, 2312, 2315, 2320, 2326, 2332, 2338, 2357, 2370, 2373, 2387, 2398, 2413, 2420, 2440, 2439, 2446, 2456, 2471, 2490, 2495, 2517, 2521, 2531, 2532, 2576, 2586, 2623, 2648, 2669, 2670, 2677, 2682, 2696, 2697, 2705, 2726, 2744, 2753, 2758, 2808, 2811, 2834, 2840, 2804, 2745, 2850, 1535, 2865, 2864, 2881, 2893, 2556, 2918, 2931, 2934, 2925, 2959, 2964, 2969, 2994, 3001, 3025, 3052, 3055, 3059, 3067, 3082, 3120, 3123, 3124, 3129, 3145, 3155, 3163, 3191, 3192, 3209, 3211, 3217, 3226, 3232, 3247, 3252, 3254, 3259, 3270, 3284, 3328, 3338, 3351, 3359, 3360, 3399, 3436, 3439, 3448, 3453, 3456];

        // 分组处理
        $groups = array_chunk($data, 4);
        $totalGroups = count($groups);

        $this->log("开始执行批量SQL任务，总共 {$totalGroups} 组");

        foreach ($groups as $groupIndex => $groupIds) {
            $groupNum = $groupIndex + 1;
            $idsStr = implode(', ', $groupIds);

            $this->log(str_repeat('=', 60));
            $this->log("第 {$groupNum}/{$totalGroups} 组: IDs ({$idsStr})");
            $this->log(str_repeat('=', 60));

            // 开启事务
            $transaction = Yii::$app->db->beginTransaction();
            try {
                // SQL 1: DELETE FROM simulate_exam_speaking_report
                $deletedRows = SimulateExamSpeakingReport::deleteAll(['record_id' => $groupIds]);
                $this->log("✓ DELETE simulate_exam_speaking_report: {$deletedRows} 条记录");

                // SQL 2: UPDATE simulate_exam_speaking
                $updatedRows = SimulateExamSpeaking::updateAll(
                    ['status' => 5, 'report_score' => ''],
                    ['id' => $groupIds]
                );
                $this->log("✓ UPDATE simulate_exam_speaking: {$updatedRows} 条记录");

                // SQL 3: UPDATE sys_ai_task
                $taskUpdatedRows = SysAiTask::updateAll(
                    ['status' => 1],
                    ['type' => 8, 'record_id' => $groupIds]
                );
                $this->log("✓ UPDATE sys_ai_task: {$taskUpdatedRows} 条记录");

                // 提交事务
                $transaction->commit();
                $this->log("✓ SQL执行成功");
            } catch (\Exception $e) {
                $transaction->rollBack();
                $this->log("✗ SQL执行失败: " . $e->getMessage());
                throw $e;
            }

            // 等待任务完成（检查status是否都为2）
            $this->log("等待任务完成（检查status是否都为2）...");
            $checkCount = 0;
            $maxChecks = 100; // 最大检查次数，防止无限循环

            while ($checkCount < $maxChecks) {
                $checkCount++;

                // 查询该组ID在sys_ai_task表中的status
                $tasks = SysAiTask::find()
                    ->where(['type' => 8, 'record_id' => $groupIds])
                    ->all();

                // 如果没有记录，跳过检查
                if (empty($tasks)) {
                    $this->log("✓ 没有找到相关任务记录，跳过检查");
                    break;
                }

                // 检查是否所有status都为2
                $allCompleted = true;
                $pendingTasks = [];
                foreach ($tasks as $task) {
                    if ($task->status != 2) {
                        $allCompleted = false;
                        $pendingTasks[] = "record_id={$task->record_id}, status={$task->status}";
                    }
                }

                if ($allCompleted) {
                    $this->log("✓ 所有任务已完成（检查次数: {$checkCount}）");
                    break;
                }

                // 输出等待信息
                $pendingCount = count($pendingTasks);
                $this->log("  等待中... 还有 {$pendingCount} 个任务未完成: " . implode(', ', array_slice($pendingTasks, 0, 3)) . ($pendingCount > 3 ? '...' : ''));

                // 等待30秒
                sleep(30);
            }

            if ($checkCount >= $maxChecks) {
                $this->log("⚠ 已达到最大检查次数，继续执行下一组");
            }
        }

        $this->log(str_repeat('=', 60));
        $this->log("所有批次执行完成！");
        $this->log(str_repeat('=', 60));
    }

    /**
     * 打印带时间戳的日志
     * @param string $message
     */
    private function log($message)
    {
        $timestamp = date('Y-m-d H:i:s');
        echo "[{$timestamp}] {$message}\n";
    }
}

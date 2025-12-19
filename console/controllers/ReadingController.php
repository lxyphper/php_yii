<?php

/**
 * Created by PhpStorm.
 * User: 168
 * Date: 2017/10/23
 * Time: 14:00
 */

namespace console\controllers;

use app\models\ReadingExamContext;
use app\models\ReadingExamPaper;
use app\models\ReadingExamPaperTopic;
use app\models\ReadingExamPaperType;
use app\models\ReadingExamPaperUnit;
use app\models\ReadingExamQuestion;
use app\models\ReadingExamQuestionContext;
use app\models\ReadingExamQuestionGroup;
use app\models\ReadingExamQuestionOption;
use app\models\ReadingExamQuestionType;
use app\models\ReadingExamRecord;
use app\models\SimulateExamReading;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\console\Controller;
use yii\log\Logger;

/**
 * 阅读题目数据处理
 * Class ReadingController
 * @package console\controllers
 */
class ReadingController extends Controller
{
    //数据初始化
    public function actionInitJyData($url): void
    {
        $type = 1;
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['id'];
            var_dump($titleStr);
            $titleArr = explode('-', $titleStr);
            $subType = '剑雅' . $titleArr[0];
            $unit = $unit_map['Test' . $titleArr[1]];
            $title = 'Passage ' . $titleArr[2];
            var_dump($title);
            //获取类型
            $subTypeId = (new ReadingExamPaperType())->getByName($type, $subType);
            if (empty($subTypeId)) {
                var_dump("获取类型错误，参数[type:$type,subType:$subType]");
                \Yii::getLogger()->log("获取类型错误，参数[type:$type,subType:$subType]", Logger::LEVEL_ERROR);
                continue;
            }
            //获取考试id
            $unitId = (new ReadingExamPaperUnit())->getByName($subTypeId, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]");
                \Yii::getLogger()->log("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }
            $essayObj = (array)$value['essay_obj'];
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                \Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ReadingExamPaper();
                $paper->title = $title;
                $paper->essay_title = $essayObj['title'];
                $paper->content = $essayObj;
                $paper->unit = $unitId;
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    \Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $groupDesc = substr($val['description'], 0, 20);
                $groupQuery = ReadingExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    \Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ReadingExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $questionType[$val['question_type']];
                    $group->desc = $val['description'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        \Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = (array)$val['question_ary'];
                //生成题目
                switch ($group->type) {
                    case 5:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $context = (array)$va['context'][0];
                            $answer = $context['answer'];
                            if (!isset($context['question_num'])) {
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ReadingExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $context['question_num'];
                                $questionObj->title = $context['title'];
                                $questionObj->display_answer = $context['display_answer'] ?? '';
                                $questionObj->central_sentences = $context['central_sentences'] ?? [];
                                $questionObj->ai_data = $context['ai_data'] ?? [];
                                $questionObj->locating_words = $context['locating_words'] ?? [];
                                $questionObj->key_locating_words = $context['locating_words'] ?? [];
                                $questionObj->analyze = $context['analyze'] ?? '';
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $context['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            foreach ($optionList as $o) {
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if ($optionOjb->title == $answer || $optionOjb->content == $answer) {
                                    $questionObj->answer = [$optionOjb->id];
                                }
                            }
                            $questionObj->save();
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 1: //判断题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $context = (array)$va['context'][0];
                            $answer = $va['answer'];
                            if (!isset($context['question_num'])) {
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ReadingExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $context['question_num'];
                                $questionObj->title = $context['title'];
                                $questionObj->display_answer = $context['display_answer'] ?? '';
                                $questionObj->central_sentences = $context['central_sentences'] ?? [];
                                $questionObj->ai_data = $context['ai_data'] ?? [];
                                $questionObj->locating_words = $context['locating_words'] ?? [];
                                $questionObj->key_locating_words = $context['locating_words'] ?? [];
                                $questionObj->analyze = $context['analyze'] ?? '';
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $context['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            foreach ($optionList as $o) {
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if ($optionOjb->title == $answer || $optionOjb->content == $answer) {
                                    $questionObj->answer = [$optionOjb->id];
                                }
                            }
                            $questionObj->save();
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 9: //句子配对题
                    case 2: //从属配对题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            if (isset($va['option_head'])) {
                                $groupTitle = $va['option_head'];
                            }

                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            //保存题目
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->answer = [$opMap[$item['answer']]];
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $item['title'];
                                }
                                $questionObj->save();
                                $questionId = $questionObj->id;
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 3: //句子段落信息配对题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    // $questionObj->base_analyze = $item['analyze'];
                                    $questionObj->answer = explode('或', $item['answer']);
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $item['title'];
                                }
                                $questionObj->save();
                                $questionId = $questionObj->id;
                            }
                        }

                        // $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 6:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title] = $opObj->id;
                            }

                            //保存题目
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->answer = [$opMap[$item['answer']]];
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    $questionObj->sub_essay_code = !empty($item['sub_essay_code']) ? strval($item['sub_essay_code']) : '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                            }
                        }

                        // $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 7:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $group->title = $va['title'] ?? '';
                            // $group->base_analyze = $va['analyze'] ?? '';
                            $group->save();

                            $context = (array)$va['context'][0];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }

                            $answer = $context['answer'];
                            $questionNum = $val['question_num_range'];
                            $optionMap = [];

                            //保存选项
                            $optionList = $va['option'];
                            foreach ($optionList as $o) {
                                //                                ReadingExamQuestionOption::deleteAll(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$o->name]);
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 2;
                                    $optionOjb->biz_id = $groupId;
                                    $optionOjb->save();
                                }
                                $optionMap[$optionOjb->title] = $optionOjb->id;
                            }
                            if (count($questionNum) > 1) {
                                ReadingExamQuestion::deleteAll(['paper_id' => $paperId, 'group_id' => $groupId]);
                            }
                            foreach ($questionNum as $k => $num) {
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $num;
                                    $questionObj->answer = [$optionMap[trim($answer[$k])]];
                                    $questionObj->display_answer = $context['display_answer'] ?? '';
                                    $questionObj->central_sentences = $context['central_sentences'] ?? [];
                                    $questionObj->ai_data = $context['ai_data'] ?? [];
                                    $questionObj->locating_words = $context['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $context['locating_words'] ?? [];
                                    $questionObj->analyze = $context['analyze'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->answer = [$optionMap[trim($answer[$k])]];
                                    $questionObj->save();
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 4:
                    case 8: //填空题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    var_dump("paper_id:$paperId,number:" . $item['question_num'] . "不存在");
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->answer = $this->dealWithAnswer($item['all_answer']);
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    //                                    var_dump("paper_id:$paperId,number:" .$item['question_num']."存在");
                                    //                                    $questionObj->answer = explode('或',$item['answer']);
                                    //                                    var_dump("title:$titleStr,id:$questionObj->id,answer:");
                                    //                                    var_dump($questionObj->answer);
                                    //                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                        }

                        // $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 10:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'];
                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    // $questionObj->id_text = $item['question_id'];
                                    // $questionObj->base_analyze = $item['analyze'];
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    $answer = $item['answer'] ?? '';
                                    if (!empty($answer)) {
                                        $questionObj->answer = [$opMap[$answer]];
                                    } else {
                                        $questionObj->answer = [];
                                    }

                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                        }

                        // $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 11:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $contextList = (array)$va['context'];
                            foreach ($contextList as $context) {
                                $context = (array)$context;
                                if (!isset($context['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $context['question_num'];
                                    // $questionObj->base_analyze = $context['analyze'];
                                    $questionObj->title = $context['title'];
                                    $questionObj->answer = $this->dealWithAnswer($context['all_answer']);
                                    $questionObj->display_answer = $context['display_answer'] ?? '';
                                    $questionObj->central_sentences = $context['central_sentences'] ?? [];
                                    $questionObj->ai_data = $context['ai_data'] ?? [];
                                    $questionObj->locating_words = $context['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $context['locating_words'] ?? [];
                                    $questionObj->analyze = $context['analyze'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $context['title'];
                                    $questionObj->save();
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 13: //表格题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = json_encode($va['table']);
                            $groupTitle = $va['table_title'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->answer = $this->dealWithAnswer($item['all_answer']);
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('\u3010' . $item['question_num'] . '\u3011', '$' . $questionId . '$', $collect);
                                $group->content = json_decode($collect);
                            }
                        }

                        // $group->base_analyze = $groupAnalyze;
                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 12:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'] ?? '';
                            $img_url = $va['image_src'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->answer = $this->dealWithAnswer($item['all_answer']);
                                    $questionObj->display_answer = $item['display_answer'] ?? '';
                                    $questionObj->central_sentences = $item['central_sentences'] ?? [];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->locating_words = $item['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                    $questionObj->analyze = $item['analyze'] ?? '';
                                    $questionObj->title = $item['title'] ?? '';
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                            $group->img_url = $img_url;
                        }

                        // $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
        }
    }

    public function actionInitJjData($url): void
    {
        $type = 2;
        $questionType = $this->getGroupType();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("机经初始化数据为空", Logger::LEVEL_ERROR);
            exit('机经初始化数据为空');
        }
        $del_unit = [];
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            if ($titleStr == '机经 Test 6-passage two') {
                $titleStr = '机经 Test 6-passage 2';
            }
            if ($titleStr == '机经 Test 6-passage three') {
                $titleStr = '机经 Test 6-passage 3';
            }
            $titleArr = explode('-', $titleStr);
            $unit = $titleArr[0];
            $title = $titleArr[1];

            //获取考试id
            $unitId = (new ReadingExamPaperUnit())->getByName($type, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[type:$type,unit:$unit]");
                \Yii::getLogger()->log("获取考试id错误，参数[type:$type,unit:$unit]", Logger::LEVEL_ERROR);
                $unitObj = new ReadingExamPaperUnit();
                $unitObj->type = $type;
                $unitObj->name = $unit;
                try {
                    $unitObj->insert();
                } catch (\Throwable $e) {
                    var_dump("生成考试失败，err:" . $e->getMessage());
                    \Yii::getLogger()->log("生成考试失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }
                $unitId = $unitObj->id;
            }
            $value['processed'] = (array)$value['processed'];
            $essayObj = (array)$value['processed']['essay_obj'];
            unset($essayObj['tmp_paragraph']);
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                //                $paperInfo->content = $essayObj;
                //                $paperInfo->save();
                var_dump("该试卷已存在，title:$titleStr");
                \Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ReadingExamPaper();
                $paper->title = $title;
                $paper->essay_title = $essayObj['title'];
                $paper->content = $essayObj;
                $paper->unit = $unitId;
                $paper->complete_title = '机经 ' . $titleStr;
                try {
                    $paper->insert(false);
                } catch (\Throwable $e) {
                    var_dump("生成试卷失败，err:" . $e->getMessage());
                    \Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }
            //保存题目分组
            $question = (array)$value['processed']['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $groupDesc = substr($val['description'], 0, 20);
                $groupQuery = ReadingExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (empty($group) && $questionType[$val['question_type']] == 7) {
                    $groupQuery = ReadingExamQuestionGroup::find();
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => 14]);
                    $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                    $group = $groupQuery->one();
                }

                if (!empty($group)) {
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    \Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ReadingExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $questionType[$val['question_type']];
                    $group->desc = $val['description'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        var_dump("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage());
                        \Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = (array)$val['question_ary'];
                //生成题目
                switch ($group->type) {
                    //                    case 5:
                    case 1: //判断题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $context = (array)$va['context'][0];
                            $answer = $va['answer'];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ReadingExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $context['question_num'];
                                $questionObj->base_analyze = $va['analyze'] ?? '';
                                $questionObj->title = $context['title'];
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $context['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            ReadingExamQuestionOption::deleteAll(['biz_type' => 1, 'biz_id' => $questionId]);
                            foreach ($optionList as $o) {
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                } else {
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                }
                                $optionOjb->save();
                                if ($answer == 'unknown') {
                                    $questionObj->answer = [];
                                } else {
                                    if ($questionObj->id == 29183) {
                                        var_dump($optionOjb->content);
                                        var_dump($optionOjb->title);
                                        var_dump($answer);
                                        var_dump($group->type);
                                        var_dump($optionOjb->content == $answer || $optionOjb->title == $answer);
                                    }
                                    if ($group->type == 1) {
                                        if ($optionOjb->content == $answer || $optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    } else {
                                        if ($optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    }
                                }
                            }
                            $questionObj->save(false);
                            if ($questionObj->id == 29183) {
                                var_dump($questionObj->answer);
                                var_dump("更新答案：$answer 成功");
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    //                    case 9:
                    //                    case 2:
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    //                            if (isset($va['option_head'])) {
                    //                                $groupTitle = $va['option_head'];
                    //                            }
                    //
                    //                            $opMap = [];
                    //                            //保存选项
                    //                            foreach ((array)$va['option'] as $op) {
                    //                                $opObj = ReadingExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$op->name]);
                    //                                if (empty($opObj)) {
                    //                                    $opObj = new ReadingExamQuestionOption();
                    //                                    $opObj->biz_type = 2;
                    //                                    $opObj->biz_id = $groupId;
                    //                                    $opObj->title = $op->name;
                    //                                    $opObj->content = $op->option;
                    //                                    try {
                    //                                        $opObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                                $opMap[$opObj->title] = $opObj->id;
                    //                            }
                    //                            //保存题目
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->title = $item['title'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = isset($opMap[$item['answer']]) ? [$opMap[$item['answer']]] : [];
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                } else {
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->title = $item['title'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = isset($opMap[$item['answer']]) ? [$opMap[$item['answer']]] : [];
                    //                                    }
                    //                                    $questionObj->save();
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                            }
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->title = $groupTitle;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 3://句子段落信息配对题
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    ////                            ReadingExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->title = $item['title'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = explode('或', $item['answer']);
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                } else {
                    //                                    $questionObj->title = $item['title'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = explode('或',$item['answer']);
                    //                                    }
                    //                                    $questionObj->save();
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                            }
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 6:
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    //                            $opMap = [];
                    //                            $idTextMap = [];
                    //                            //保存选项
                    //                            foreach ((array)$va['option'] as $op) {
                    //                                $opObj = ReadingExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$op->name]);
                    //                                if (empty($opObj)) {
                    //                                    $opObj = new ReadingExamQuestionOption();
                    //                                    $opObj->biz_type = 2;
                    //                                    $opObj->biz_id = $groupId;
                    //                                    $opObj->title = $op->name;
                    //                                    $opObj->content = $op->option;
                    //                                    try {
                    //                                        $opObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                                $opMap[$opObj->content] = $opObj->id;
                    //                            }
                    //                            foreach ($va['answer'] as $v) {
                    //                                $idTextMap[$v->sub_essay_code] = $v->sccode;
                    //                            }
                    //                            //保存题目
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = [$opMap[$item['answer']]];
                    //                                    }
                    //                                    $questionObj->id_text = $idTextMap[$item['sub_essay_code']];
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                            }
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 14:
                    //                    case 7:
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //
                    //                            $context = (array)$va['context'][0];
                    //                            if (!isset($context['question_num'])) {
                    //                                var_dump("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在");
                    //                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                continue;
                    //                            }
                    //                            $answer = explode(',',$context['answer']);
                    //                            $questionNum = explode('-', $context['question_num']);
                    //                            if (count($questionNum) < 2) {
                    //                                $group->type = 14;
                    //                                $group->save();
                    //                                //不定项选择
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$context['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $context['question_num'];
                    //                                    $questionObj->base_analyze = $va['analyze'] ?? '';
                    //                                    $questionObj->title = $context['title'];
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$context['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$context['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                } else {
                    //                                    $questionObj->title = $context['title'];
                    //                                    $questionObj->save();
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                                //保存选项
                    //                                $optionList = $va['option'];
                    //                                ReadingExamQuestionOption::deleteAll(['biz_type'=>1, 'biz_id'=>$questionId]);
                    //                                $sub_answer = [];
                    //                                foreach ($optionList as $o) {
                    //                                    $optionOjb = ReadingExamQuestionOption::findOne(['biz_type'=>1, 'biz_id'=>$questionId, 'title'=>$o->name]);
                    //                                    if (empty($optionOjb)) {
                    //                                        $optionOjb = new ReadingExamQuestionOption();
                    //                                        $optionOjb->title = $o->name;
                    //                                        $optionOjb->content = $o->option;
                    //                                        $optionOjb->biz_type = 1;
                    //                                        $optionOjb->biz_id = $questionId;
                    //                                    } else{
                    //                                        $optionOjb->title = $o->name;
                    //                                        $optionOjb->content = $o->option;
                    //                                    }
                    //                                    $optionOjb->save();
                    //                                    if ($answer == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        if (in_array($optionOjb->title,$answer)) {
                    //                                            $sub_answer[] = $optionOjb->id;
                    //                                        }
                    //                                    }
                    //                                }
                    //                                $questionObj->answer = $sub_answer;
                    //                                $questionObj->save();
                    //                            } else {
                    //                                $group->title = $va['title'];
                    //                                $group->base_analyze = $va['analyze'] ?? '';
                    //                                $group->save();
                    //
                    //                                $optionMap = [];
                    //                                if ($questionNum[1] - $questionNum[0] > 1) {
                    //                                    $tmp_num = [];
                    //                                    for ($i = $questionNum[0]; $i<=$questionNum[1]; $i++) {
                    //                                        $tmp_num[] = $i;
                    //                                    }
                    //                                    $questionNum = $tmp_num;
                    //                                }
                    //
                    //                                //保存选项
                    //                                $optionList = $va['option'];
                    //                                foreach ($optionList as $o) {
                    //                                    $optionOjb = ReadingExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$o->name]);
                    //                                    if (empty($optionOjb)) {
                    //                                        $optionOjb = new ReadingExamQuestionOption();
                    //                                        $optionOjb->title = $o->name;
                    //                                        $optionOjb->content = $o->option;
                    //                                        $optionOjb->biz_type = 2;
                    //                                        $optionOjb->biz_id = $groupId;
                    //                                        $optionOjb->save();
                    //                                    }
                    //                                    $optionMap[$optionOjb->title] = $optionOjb->id;
                    //                                }
                    //                                if ($questionNum[1] - $questionNum[0] > 1) {
                    //                                    ReadingExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                    //                                }
                    //                                foreach ($questionNum as $k => $num) {
                    //                                    //查询题目是否存在
                    //                                    $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$num]);
                    //                                    if (empty($questionObj)) {
                    //                                        $questionObj = new ReadingExamQuestion();
                    //                                        $questionObj->paper_id = $paperId;
                    //                                        $questionObj->group_id = $groupId;
                    //                                        $questionObj->number = $num;
                    //                                        if ($context['answer'] == 'unknown') {
                    //                                            $questionObj->answer = [];
                    //                                        } else {
                    //                                            $questionObj->answer = [$optionMap[trim($answer[$k])]];
                    //                                        }
                    //                                        try {
                    //                                            $questionObj->insert();
                    //                                        } catch (\Throwable $e) {
                    //                                            var_dump("试卷【 $titleStr 】题目【 ".$num ." 】生成失败，err:".$e->getMessage());
                    //                                            \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$num ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                            continue;
                    //                                        }
                    //                                    }
                    //                                }
                    //                            }
                    //
                    //
                    //
                    //                        }
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 4:
                    //                    case 8://填空题
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    //                            $collect = $va['title'];
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->id_text = $item['question_id'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = explode('或', $item['answer']);
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                                $collect = str_replace('【'. $item['question_id'] . '】', '$' . $questionId . '$', $collect);
                    //                                $group->content = ['collect'=>$collect];
                    //                            }
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 10:
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    //                            $collect = $va['title'];
                    //                            $opMap = [];
                    //                            //保存选项
                    //                            foreach ((array)$va['option'] as $op) {
                    //                                $opObj = ReadingExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$op->name]);
                    //                                if (empty($opObj)) {
                    //                                    $opObj = new ReadingExamQuestionOption();
                    //                                    $opObj->biz_type = 2;
                    //                                    $opObj->biz_id = $groupId;
                    //                                    $opObj->title = $op->name;
                    //                                    $opObj->content = $op->option;
                    //                                    try {
                    //                                        $opObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                                $opMap[$opObj->title.':'.trim($opObj->content)] = $opObj->id;
                    //                                $opMap[$opObj->title] = $opObj->id;
                    //                            }
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->id_text = $item['question_id'] ?? '';
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    $answerArr = explode('或', $item['answer']);
                    //                                    $tmpAnswer = [];
                    //                                    foreach ($answerArr as $answer) {
                    //                                        $tmpAnswer[] = $opMap[$answer];
                    //                                    }
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = $tmpAnswer;
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                } else {
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->id_text = $item['question_id'] ?? '';
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $answerArr = explode('或', $item['answer']);
                    //                                        $tmpAnswer = [];
                    //                                        foreach ($answerArr as $answer) {
                    //                                            $tmpAnswer[] = $opMap[$answer];
                    //                                        }
                    //                                        $questionObj->answer = $tmpAnswer;
                    //                                    }
                    //
                    //                                    $questionObj->save();
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                                $collect = str_replace('【'. $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                    //                                $group->content = ['collect'=>$collect];
                    //                            }
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 11:
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $contextList = (array)$va['context'];
                    //                            foreach ($contextList as $context) {
                    //                                $context = (array)$context;
                    //                                if (!isset($context['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$context['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $context['question_num'];
                    //                                    $questionObj->base_analyze = $context['analyze'] ?? '';
                    //                                    $questionObj->title = $context['title'];
                    //                                    if ($context['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = explode('或', $context['answer']);
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$context['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$context['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                } else {
                    //                                    $questionObj->title = $context['title'];
                    //                                    $questionObj->save();
                    //                                }
                    //                            }
                    //
                    //                        }
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 13://表格题
                    //                        //保存题目
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    //                            $collect = json_encode($va['table']);
                    //                            $groupTitle = $va['table_title'];
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->id_text = $item['question_id'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = explode('或', $item['answer']);
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                                $collect = str_replace('\u3010'. $item['question_id'] . '\u3011', '$' . $questionId . '$', $collect);
                    //                                $group->content = json_decode($collect);
                    //                            }
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->title = $groupTitle;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    //                    case 12:
                    //                        foreach ($questionList as $va) {
                    //                            $va = (array)$va;
                    //                            $groupAnalyze = $va['analyze'] ?? '';
                    //                            $collect = $va['title'];
                    //                            $img_url = $va['image_src'];
                    //                            foreach ((array)$va['context'] as $item) {
                    //                                $item = (array)$item;
                    //                                if (!isset($item['question_num'])) {
                    //                                    var_dump("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在");
                    //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                    continue;
                    //                                }
                    //                                //查询题目是否存在
                    //                                $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                                if (empty($questionObj)) {
                    //                                    $questionObj = new ReadingExamQuestion();
                    //                                    $questionObj->paper_id = $paperId;
                    //                                    $questionObj->group_id = $groupId;
                    //                                    $questionObj->number = $item['question_num'];
                    //                                    $questionObj->id_text = $item['question_id'];
                    //                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                    //                                    if ($item['answer'] == 'unknown') {
                    //                                        $questionObj->answer = [];
                    //                                    } else {
                    //                                        $questionObj->answer = $item['answer'];
                    //                                    }
                    //                                    try {
                    //                                        $questionObj->insert();
                    //                                    } catch (\Throwable $e) {
                    //                                        var_dump("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage());
                    //                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                        continue;
                    //                                    }
                    //                                }
                    //                                $questionId = $questionObj->id;
                    //                                $collect = str_replace('【'. $item['question_id'] . '】', '$' . $questionId . '$', $collect);
                    //                                $group->content = ['collect'=>$collect];
                    //                            }
                    //                            $group->img_url = $img_url;
                    //                        }
                    //
                    //                        $group->base_analyze = $groupAnalyze;
                    //                        $group->save();
                    //                        var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
            var_dump("del_unit_one:");
            var_dump($del_unit);
        }
        var_dump("del_unit:");
        var_dump($del_unit);
        var_dump("机经题初始化完成");
    }

    public function actionInitYcData($url): void
    {
        $questionType = $this->getGroupType();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("机经初始化数据为空", Logger::LEVEL_ERROR);
            exit('机经初始化数据为空');
        }
        $del_unit = [];
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            $title = $titleStr;

            //获取考试id
            $unitId = 963;
            $value['processed'] = (array)$value['processed'];
            $essayObj = (array)$value['processed']['essay_obj'];
            unset($essayObj['tmp_paragraph']);
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                //                $paperInfo->content = $essayObj;
                //                $paperInfo->save();
                var_dump("该试卷已存在，title:$titleStr");
                \Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ReadingExamPaper();
                $paper->title = $title;
                $paper->essay_title = $essayObj['title'];
                $paper->content = $essayObj;
                $paper->unit = $unitId;
                $paper->complete_title = $titleStr;
                try {
                    $paper->insert(false);
                } catch (\Throwable $e) {
                    var_dump("生成试卷失败，err:" . $e->getMessage());
                    \Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }
            //保存题目分组
            $question = (array)$value['processed']['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $groupDesc = substr($val['description'], 0, 20);
                $groupQuery = ReadingExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (empty($group) && $questionType[$val['question_type']] == 7) {
                    $groupQuery = ReadingExamQuestionGroup::find();
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => 14]);
                    $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                    $group = $groupQuery->one();
                }

                if (!empty($group)) {
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    \Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ReadingExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $questionType[$val['question_type']];
                    $group->desc = $val['description'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        var_dump("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage());
                        \Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = (array)$val['question_ary'];
                //生成题目
                switch ($group->type) {
                    case 5:
                    case 1: //判断题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $context = (array)$va['context'][0];
                            $answer = $context['answer'];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ReadingExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $context['question_num'];
                                $questionObj->base_analyze = $va['analyze'] ?? '';
                                $questionObj->title = $context['title'];
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $context['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            ReadingExamQuestionOption::deleteAll(['biz_type' => 1, 'biz_id' => $questionId]);
                            foreach ($optionList as $o) {
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                } else {
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                }
                                $optionOjb->save();
                                if ($answer == 'unknown') {
                                    $questionObj->answer = [];
                                } else {
                                    if ($group->type == 1) {
                                        if ($optionOjb->content == $answer || $optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    } else {
                                        if ($optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    }
                                }
                            }
                            $questionObj->save(false);
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 9:
                    case 2:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            if (isset($va['option_head'])) {
                                $groupTitle = $va['option_head'];
                            }

                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            //保存题目
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = isset($opMap[$item['answer']]) ? [$opMap[$item['answer']]] : [];
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = isset($opMap[$item['answer']]) ? [$opMap[$item['answer']]] : [];
                                    }
                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 3: //句子段落信息配对题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            //                            ReadingExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 6:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $opMap = [];
                            $idTextMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->content] = $opObj->id;
                            }
                            //                            foreach ($va['answer'] as $v) {
                            //                                $idTextMap[$v->sub_essay_code] = $v->sccode;
                            //                            }
                            //保存题目
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = [$opMap[$item['answer']]];
                                    }
                                    //                                    $questionObj->id_text = $idTextMap[$item['sub_essay_code']];
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 14:
                    case 7:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;

                            $context = (array)$va['context'][0];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            $answer = explode(',', $context['answer']);
                            $questionNum = explode('-', $context['question_num']);
                            if (count($questionNum) < 2) {
                                $group->type = 14;
                                $group->save();
                                //不定项选择
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $context['question_num'];
                                    $questionObj->base_analyze = $va['analyze'] ?? '';
                                    $questionObj->title = $context['title'];
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $context['title'];
                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                                //保存选项
                                $optionList = $va['option'];
                                ReadingExamQuestionOption::deleteAll(['biz_type' => 1, 'biz_id' => $questionId]);
                                $sub_answer = [];
                                foreach ($optionList as $o) {
                                    $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                    if (empty($optionOjb)) {
                                        $optionOjb = new ReadingExamQuestionOption();
                                        $optionOjb->title = $o->name;
                                        $optionOjb->content = $o->option;
                                        $optionOjb->biz_type = 1;
                                        $optionOjb->biz_id = $questionId;
                                    } else {
                                        $optionOjb->title = $o->name;
                                        $optionOjb->content = $o->option;
                                    }
                                    $optionOjb->save();
                                    if ($answer == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        if (in_array($optionOjb->title, $answer)) {
                                            $sub_answer[] = $optionOjb->id;
                                        }
                                    }
                                }
                                $questionObj->answer = $sub_answer;
                                $questionObj->save();
                            } else {
                                $group->title = $va['title'];
                                $group->base_analyze = $va['analyze'] ?? '';
                                $group->save();

                                $optionMap = [];
                                if ($questionNum[1] - $questionNum[0] > 1) {
                                    $tmp_num = [];
                                    for ($i = $questionNum[0]; $i <= $questionNum[1]; $i++) {
                                        $tmp_num[] = $i;
                                    }
                                    $questionNum = $tmp_num;
                                }

                                //保存选项
                                $optionList = $va['option'];
                                foreach ($optionList as $o) {
                                    $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $o->name]);
                                    if (empty($optionOjb)) {
                                        $optionOjb = new ReadingExamQuestionOption();
                                        $optionOjb->title = $o->name;
                                        $optionOjb->content = $o->option;
                                        $optionOjb->biz_type = 2;
                                        $optionOjb->biz_id = $groupId;
                                        $optionOjb->save();
                                    }
                                    $optionMap[$optionOjb->title] = $optionOjb->id;
                                }
                                if ($questionNum[1] - $questionNum[0] > 1) {
                                    ReadingExamQuestion::deleteAll(['paper_id' => $paperId, 'group_id' => $groupId]);
                                }
                                foreach ($questionNum as $k => $num) {
                                    //查询题目是否存在
                                    $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                    if (empty($questionObj)) {
                                        $questionObj = new ReadingExamQuestion();
                                        $questionObj->paper_id = $paperId;
                                        $questionObj->group_id = $groupId;
                                        $questionObj->number = $num;
                                        if ($context['answer'] == 'unknown') {
                                            $questionObj->answer = [];
                                        } else {
                                            $questionObj->answer = [$optionMap[trim($answer[$k])]];
                                        }
                                        try {
                                            $questionObj->insert();
                                        } catch (\Throwable $e) {
                                            var_dump("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage());
                                            \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                            continue;
                                        }
                                    }
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 4:
                    case 8: //填空题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_id'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 10:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'];
                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title . ':' . trim($opObj->content)] = $opObj->id;
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'] ?? '';
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    $answerArr = explode('或', $item['answer']);
                                    $tmpAnswer = [];
                                    foreach ($answerArr as $answer) {
                                        $tmpAnswer[] = $opMap[$answer];
                                    }
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = $tmpAnswer;
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'] ?? '';
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $answerArr = explode('或', $item['answer']);
                                        $tmpAnswer = [];
                                        foreach ($answerArr as $answer) {
                                            $tmpAnswer[] = $opMap[$answer];
                                        }
                                        $questionObj->answer = $tmpAnswer;
                                    }

                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 11:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $contextList = (array)$va['context'];
                            foreach ($contextList as $context) {
                                $context = (array)$context;
                                if (!isset($context['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $context['question_num'];
                                    $questionObj->base_analyze = $context['analyze'] ?? '';
                                    $questionObj->title = $context['title'];
                                    if ($context['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $context['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $context['title'];
                                    $questionObj->save();
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 13: //表格题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = json_encode($va['table']);
                            $groupTitle = $va['table_title'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('\u3010' . $item['question_id'] . '\u3011', '$' . $questionId . '$', $collect);
                                $group->content = json_decode($collect);
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 12:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'] ?? '';
                            if (empty($collect) && !empty($val['question_num_range'])) {
                                $collect_arr = [];
                                foreach ($val['question_num_range'] as $c) {
                                    $collect_arr[] = '【' . $c . '】';
                                }
                                $collect = implode('\n', $collect_arr);
                            }
                            $img_url = $va['image_src'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'] ?? '';
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = $item['answer'];
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                            $group->img_url = $img_url;
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
            var_dump("del_unit_one:");
            var_dump($del_unit);
        }
        var_dump("del_unit:");
        var_dump($del_unit);
        var_dump("机经题初始化完成");
    }

    public function actionInitNewYcData($url): void
    {
        $questionType = $this->getGroupType();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("机经初始化数据为空", Logger::LEVEL_ERROR);
            exit('机经初始化数据为空');
        }
        $del_unit = [];
        //查询话题
        $topic_list = ReadingExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['display_title'];
            $title = $titleStr;

            //获取考试id
            $unitId = 963;
            $essayObj = (array)$value['essay_obj'];
            if (isset($essayObj['tmp_paragraph'])) {
                unset($essayObj['tmp_paragraph']);
            }

            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                $topic = explode(',', $value['topic']);
                $curr_topic = [];
                foreach ($topic as $v) {
                    if (isset($topic_map[$v])) {
                        $curr_topic[] = $topic_map[$v];
                    }
                }
                $paperInfo->topic = $curr_topic;
                $paperInfo->save(false);
                var_dump("该试卷已存在，title:$titleStr");
                \Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                continue;
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ReadingExamPaper();
                $paper->title = $title;
                $paper->essay_title = $essayObj['title'];
                $paper->content = $essayObj;
                $paper->unit = $unitId;
                $paper->complete_title = $titleStr;
                try {
                    $paper->insert(false);
                } catch (\Throwable $e) {
                    var_dump("生成试卷失败，err:" . $e->getMessage());
                    \Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }
            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $groupDesc = substr($val['description'], 0, 20);
                $groupQuery = ReadingExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (empty($group) && $questionType[$val['question_type']] == 7) {
                    $groupQuery = ReadingExamQuestionGroup::find();
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => 14]);
                    $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                    $group = $groupQuery->one();
                }

                if (!empty($group)) {
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    \Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ReadingExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $questionType[$val['question_type']];
                    $group->desc = $val['description'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        var_dump("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage());
                        \Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = (array)$val['question_ary'];
                //生成题目
                switch ($group->type) {
                    case 5:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $context = (array)$va['context'][0];
                            $answer = $context['answer'];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ReadingExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $context['question_num'];
                                $questionObj->base_analyze = $va['analyze'] ?? '';
                                $questionObj->title = $context['title'];
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $context['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            ReadingExamQuestionOption::deleteAll(['biz_type' => 1, 'biz_id' => $questionId]);
                            foreach ($optionList as $o) {
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                } else {
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                }
                                $optionOjb->save();
                                if ($answer == 'unknown') {
                                    $questionObj->answer = [];
                                } else {
                                    if ($group->type == 1) {
                                        if ($optionOjb->content == $answer || $optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    } else {
                                        if ($optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    }
                                }
                            }
                            $questionObj->save(false);
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 1: //判断题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $context = (array)$va['context'][0];
                            $answer = $va['answer'];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ReadingExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $context['question_num'];
                                $questionObj->base_analyze = $va['analyze'] ?? '';
                                $questionObj->title = $context['title'];
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $context['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            ReadingExamQuestionOption::deleteAll(['biz_type' => 1, 'biz_id' => $questionId]);
                            foreach ($optionList as $o) {
                                $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ReadingExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                } else {
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                }
                                $optionOjb->save();
                                if ($answer == 'unknown') {
                                    $questionObj->answer = [];
                                } else {
                                    if ($group->type == 1) {
                                        if ($optionOjb->content == $answer || $optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    } else {
                                        if ($optionOjb->title == $answer) {
                                            $questionObj->answer = [$optionOjb->id];
                                        }
                                    }
                                }
                            }
                            $questionObj->save(false);
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 9:
                    case 2:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            if (isset($va['option_head'])) {
                                $groupTitle = $va['option_head'];
                            }

                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            //保存题目
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = isset($opMap[$item['answer']]) ? [$opMap[$item['answer']]] : [];
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = isset($opMap[$item['answer']]) ? [$opMap[$item['answer']]] : [];
                                    }
                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 3: //句子段落信息配对题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            //                            ReadingExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $item['title'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 6:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $opMap = [];
                            $idTextMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            //                            foreach ($va['answer'] as $v) {
                            //                                $idTextMap[$v->sub_essay_code] = $v->sccode;
                            //                            }
                            //保存题目
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = [$opMap[$item['answer']]];
                                    }
                                    //                                    $questionObj->id_text = $idTextMap[$item['sub_essay_code']];
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 14:
                    case 7:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;

                            $context = (array)$va['context'][0];
                            if (!isset($context['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            $answer = $context['answer'];
                            $questionNum = explode('-', $context['question_num']);
                            if (count($questionNum) < 2) {
                                $group->type = 14;
                                $group->save();
                                //不定项选择
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $context['question_num'];
                                    $questionObj->base_analyze = $va['analyze'] ?? '';
                                    $questionObj->title = $context['title'];
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $context['title'];
                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                                //保存选项
                                $optionList = $va['option'];
                                ReadingExamQuestionOption::deleteAll(['biz_type' => 1, 'biz_id' => $questionId]);
                                $sub_answer = [];
                                foreach ($optionList as $o) {
                                    $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                    if (empty($optionOjb)) {
                                        $optionOjb = new ReadingExamQuestionOption();
                                        $optionOjb->title = $o->name;
                                        $optionOjb->content = $o->option;
                                        $optionOjb->biz_type = 1;
                                        $optionOjb->biz_id = $questionId;
                                    } else {
                                        $optionOjb->title = $o->name;
                                        $optionOjb->content = $o->option;
                                    }
                                    $optionOjb->save();
                                    if ($answer == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        if (in_array($optionOjb->title, $answer)) {
                                            $sub_answer[] = $optionOjb->id;
                                        }
                                    }
                                }
                                $questionObj->answer = $sub_answer;
                                $questionObj->save();
                            } else {
                                $group->title = $va['title'];
                                $group->base_analyze = $va['analyze'] ?? '';
                                $group->save();

                                $optionMap = [];
                                if ($questionNum[1] - $questionNum[0] > 1) {
                                    $tmp_num = [];
                                    for ($i = $questionNum[0]; $i <= $questionNum[1]; $i++) {
                                        $tmp_num[] = $i;
                                    }
                                    $questionNum = $tmp_num;
                                }

                                //保存选项
                                $optionList = $va['option'];
                                foreach ($optionList as $o) {
                                    $optionOjb = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $o->name]);
                                    if (empty($optionOjb)) {
                                        $optionOjb = new ReadingExamQuestionOption();
                                        $optionOjb->title = $o->name;
                                        $optionOjb->content = $o->option;
                                        $optionOjb->biz_type = 2;
                                        $optionOjb->biz_id = $groupId;
                                        $optionOjb->save();
                                    }
                                    $optionMap[$optionOjb->title] = $optionOjb->id;
                                }
                                if ($questionNum[1] - $questionNum[0] > 1) {
                                    ReadingExamQuestion::deleteAll(['paper_id' => $paperId, 'group_id' => $groupId]);
                                }
                                //                                if (in_array(18,$questionNum)) {
                                //                                    var_dump($optionMap);
                                //                                    var_dump($questionNum);die;
                                //                                }

                                foreach ($questionNum as $k => $num) {
                                    //查询题目是否存在
                                    $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                    if (empty($questionObj)) {
                                        $questionObj = new ReadingExamQuestion();
                                        $questionObj->paper_id = $paperId;
                                        $questionObj->group_id = $groupId;
                                        $questionObj->number = $num;
                                        if ($context['answer'] == 'unknown') {
                                            $questionObj->answer = [];
                                        } else {
                                            $questionObj->answer = [$optionMap[trim($answer[$k])]];
                                        }
                                        try {
                                            $questionObj->insert();
                                        } catch (\Throwable $e) {
                                            var_dump("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage());
                                            \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                            continue;
                                        }
                                    }
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 4:
                    case 8: //填空题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                var_dump("question_num:" . $item['question_num']);
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_id'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 10:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'];
                            $opMap = [];
                            //保存选项
                            foreach ((array)$va['option'] as $op) {
                                $opObj = ReadingExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                                if (empty($opObj)) {
                                    $opObj = new ReadingExamQuestionOption();
                                    $opObj->biz_type = 2;
                                    $opObj->biz_id = $groupId;
                                    $opObj->title = $op->name;
                                    $opObj->content = $op->option;
                                    try {
                                        $opObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $opMap[$opObj->title . ':' . trim($opObj->content)] = $opObj->id;
                                $opMap[$opObj->title] = $opObj->id;
                            }
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'] ?? '';
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    $answerArr = explode('或', $item['answer']);
                                    $tmpAnswer = [];
                                    foreach ($answerArr as $answer) {
                                        $tmpAnswer[] = $opMap[$answer];
                                    }
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = $tmpAnswer;
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'] ?? '';
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $answerArr = explode('或', $item['answer']);
                                        $tmpAnswer = [];
                                        foreach ($answerArr as $answer) {
                                            $tmpAnswer[] = $opMap[$answer];
                                        }
                                        $questionObj->answer = $tmpAnswer;
                                    }

                                    $questionObj->save();
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 11:
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $contextList = (array)$va['context'];
                            foreach ($contextList as $context) {
                                $context = (array)$context;
                                if (!isset($context['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $context['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $context['question_num'];
                                    $questionObj->base_analyze = $context['analyze'] ?? '';
                                    $questionObj->title = $context['title'];
                                    if ($context['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $context['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $context['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->title = $context['title'];
                                    $questionObj->save();
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 13: //表格题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = json_encode($va['table']);
                            $groupTitle = $va['table_title'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'];
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = explode('或', $item['answer']);
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('\u3010' . $item['question_id'] . '\u3011', '$' . $questionId . '$', $collect);
                                $group->content = json_decode($collect);
                            }
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 12:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $groupAnalyze = $va['analyze'] ?? '';
                            $collect = $va['title'] ?? '';
                            if (empty($collect) && !empty($val['question_num_range'])) {
                                $collect_arr = [];
                                foreach ($val['question_num_range'] as $c) {
                                    $collect_arr[] = '【' . $c . '】';
                                }
                                $collect = implode('\n', $collect_arr);
                            }
                            $img_url = $va['image_src'];
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    $questionObj = new ReadingExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $item['question_num'];
                                    $questionObj->id_text = $item['question_id'] ?? '';
                                    $questionObj->base_analyze = $item['analyze'] ?? '';
                                    if ($item['answer'] == 'unknown') {
                                        $questionObj->answer = [];
                                    } else {
                                        $questionObj->answer = $item['answer'];
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                        \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                }
                                $questionId = $questionObj->id;
                                $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                                $group->content = ['collect' => $collect];
                            }
                            $group->img_url = $img_url;
                        }

                        $group->base_analyze = $groupAnalyze;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
            var_dump("del_unit_one:");
            var_dump($del_unit);
        }
        var_dump("del_unit:");
        var_dump($del_unit);
        var_dump("机经题初始化完成");
    }

    //数据修复
    public function actionFixJyData($url)
    {
        $type = 1;
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ReadingExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['id'];
            var_dump($titleStr);
            $titleArr = explode('-', $titleStr);
            $subType = '剑雅' . $titleArr[0];
            $unit = $unit_map['Test' . $titleArr[1]];
            $title = 'Passage ' . $titleArr[2];
            var_dump($title);
            $complete_title = $subType . ' ' . $unit . '-' . $title;
            //获取类型
            $subTypeId = (new ReadingExamPaperType())->getByName($type, $subType);
            if (empty($subTypeId)) {
                \Yii::getLogger()->log("获取类型错误，参数[type:$type,subType:$subType]", Logger::LEVEL_ERROR);
                continue;
            }

            //获取考试id
            $unitId = (new ReadingExamPaperUnit())->getByName($subTypeId, $unit);
            if (empty($unitId)) {
                \Yii::getLogger()->log("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }

            $complete_analyze =  $value['complete_analyze'] ?? '';
            $essay_summary = $value['essay_summary'] ?? '';
            $essay_obj = $value['essay_obj'];
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            $topic = explode(',', $value['topic']);
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            if (empty($paperInfo)) {
                \Yii::getLogger()->log("获取试卷id错误，参数[subTypeId:$subTypeId,unit:$unit,title:$title]", Logger::LEVEL_ERROR);
                continue;
            }

            $paperInfo->topic = $curr_topic;
            //            $paperInfo->analyze = $complete_analyze;
            //            $paperInfo->essay_summary = $essay_summary;
            //            $paperInfo->content = $essayObj;
            $paperInfo->complete_title = $complete_title;
            $paperInfo->save();
            var_dump("试卷：$titleStr 保存成功");

            //保存问题内容信息
            $question_context = ReadingExamContext::findOne(['biz_id' => $paperInfo->id, 'biz_type' => 1]);
            if (empty($question_context)) {
                $contextObj = new ReadingExamContext();
                $contextObj->content = $essay_obj;
                $contextObj->biz_id = $paperInfo->id;
                $contextObj->biz_type = 1;
                try {
                    $contextObj->insert();
                } catch (\Throwable $e) {
                    \Yii::getLogger()->log("保存问题内容信息失败ReadingExamQuestionContext，参数[type:$type,subType:$subType]", Logger::LEVEL_ERROR);
                }
            } else {
                $question_context->content = $essay_obj;
                $question_context->save();
            }

            $question = (array)$value['questions'];
            if (empty($question)) {
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            $paperId = $paperInfo->id;

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }
                //查询题型分组信息
                $groupDesc = substr($val['description'], 0, 20);
                $groupQuery = ReadingExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                $groupId = $group->id;

                if (empty($group)) {
                    \Yii::getLogger()->log("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                    continue;
                }

                $questionList = (array)$val['question_ary'];

                //生成题目
                switch ($group->type) {
                    case 1:
                    case 2:
                    case 3:
                    case 4:
                    case 5:
                    case 6:
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                    case 12:
                    case 13:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $locating_words = $item['key_locating_words'];
                                //                                $sub_essay_code = $item['sub_essay_code'] ?? '';

                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                //                                $questionObj->analyze_print = $analyze_print;
                                $questionObj->key_locating_words = $locating_words;
                                $questionObj->locating_words = $locating_words;
                                //                                $questionObj->key_locating_words = empty($key_locating_words) ? '[]' : $key_locating_words;
                                //                                $questionObj->locating_words = empty($locating_words) ? '[]' : $locating_words;
                                //                                $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                //                                $questionObj->sub_essay_code = empty($sub_essay_code) ? '' : $sub_essay_code;
                                $questionObj->display_answer = $item['display_answer'];
                                //                                $questionObj->display_answer = empty($item['all_answer']) ? '' : implode(" / ", $item['all_answer']);
                                //                                if (!empty($item['analyze'])) {
                                $questionObj->analyze = $item['AI_analyze'];
                                //                                }
                                //                                $questionObj->analyze = empty($item['analyze']) ? '' : $item['analyze'];
                                //                                if (!empty($item['central_sentences'])) {
                                //                                    $questionObj->central_sentences = $item['central_sentences'];
                                //                                }
                                $questionObj->central_sentences = $item['central_sentences'];
                                //    $questionObj->answer = $item['all_answer'];
                                //                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                //                                $questionObj->option_analysis = $item['option_analysis'] ?? [];
                                $questionObj->save();

                                //保存问题内容信息
                                $question_context = ReadingExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                if (empty($question_context)) {
                                    $contextObj = new ReadingExamContext();
                                    $contextObj->content = $item;
                                    $contextObj->biz_id = $questionObj->id;
                                    $contextObj->biz_type = 2;
                                    try {
                                        $contextObj->insert();
                                    } catch (\Throwable $e) {
                                        \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                    }
                                } else {
                                    $question_context->content = $item;
                                    $question_context->save();
                                }

                                var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成", Logger::LEVEL_INFO);
                            }
                        }
                        break;
                    case 7:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $group->title = $va['title'] ?? '';
                            $group->save();
                            $parsed_answer = $va['parsed_answer'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($val['question_num_range'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                $questionNum = $val['question_num_range'];
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $key_locating_words = $item['key_locating_words'] ?? '[]';
                                $locating_words = $item['key_locating_words'];

                                foreach ($questionNum as $k => $num) {
                                    //查询题目是否存在
                                    $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                    //                                    $questionObj->analyze_print = $analyze_print;
                                    if (!empty($locating_words)) {
                                        $questionObj->key_locating_words = $locating_words;
                                        $questionObj->locating_words = $locating_words;
                                    }
                                    //                                    $questionObj->key_locating_words = empty($key_locating_words) ? '[]' : $key_locating_words;
                                    //                                    $questionObj->locating_words = empty($locating_words) ? '[]' : $locating_words;
                                    //                                    $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                    $questionObj->display_answer = $item['display_answer'];
                                    //                                    if (!empty($item['analyze'])) {
                                    //                                        $questionObj->analyze = $item['analyze'];
                                    //                                    }
                                    $questionObj->analyze = $item['AI_analyze'];
                                    //                                    if (!empty($item['central_sentences'])) {
                                    //                                        $questionObj->central_sentences = $item['central_sentences'];
                                    //                                    }
                                    $questionObj->central_sentences = $item['central_sentences'];
                                    //                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->save();

                                    //保存问题内容信息
                                    $question_context = ReadingExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                    if (empty($question_context)) {
                                        $contextObj = new ReadingExamContext();
                                        $contextObj->content = $item;
                                        $contextObj->biz_id = $questionObj->id;
                                        $contextObj->biz_type = 2;
                                        try {
                                            $contextObj->insert();
                                        } catch (\Throwable $e) {
                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                        }
                                    } else {
                                        $question_context->content = $item;
                                        $question_context->save();
                                    }

                                    var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $num . "】更新完成", Logger::LEVEL_INFO);
                                }
                            }
                        }
                        break;
                }
            }
        }
    }

    public function actionFixJjData()
    {
        $type = 2;
        $questionType = $this->getGroupType();
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/阅读机经2_part5.json';
        $question_content = file_get_contents($file);
        $arr = json_decode($question_content);
        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ReadingExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $key => $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            if ($titleStr == '机经 Test 6-passage two') {
                $titleStr = '机经 Test 6-passage 2';
            }
            if ($titleStr == '机经 Test 6-passage three') {
                $titleStr = '机经 Test 6-passage 3';
            }
            $titleArr = explode('-', $titleStr);
            $unit = $titleArr[0];
            $title = $titleArr[1];


            //获取考试id
            $unitId = (new ReadingExamPaperUnit())->getByName($type, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[type:$type,unit:$unit]");
                \Yii::getLogger()->log("获取考试id错误，参数[type:$type,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }

            $value['processed'] = (array)$value['processed'];
            $essayObj = (array)$value['processed']['essay_obj'];
            $complete_analyze =  $value['processed']['complete_analyze'] ?? '';
            $essay_summary = $value['processed']['essay_summary'] ?? '';
            $essay_obj = $value['processed']['essay_obj'] ?? '{}';
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            $topic = explode(',', $value['topic']);
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            if (empty($paperInfo)) {
                var_dump("获取试卷id错误，参数[unit:$unit,title:$title]");
                \Yii::getLogger()->log("获取试卷id错误，参数[unit:$unit,title:$title]", Logger::LEVEL_ERROR);
                continue;
            }

            if ($essay_obj != '{}' && !empty($essay_obj)) {
                if (isset($essay_obj->order_ary)) {
                    foreach ($essay_obj->order_ary as &$order_val) {
                        $order_val = (string) $order_val;
                    }
                }
                if (isset($essay_obj->tmp_paragraph)) {
                    unset($essay_obj->tmp_paragraph);
                }
            }

            //            $paperInfo->topic = $curr_topic;
            //            $paperInfo->analyze = $complete_analyze;
            //            $paperInfo->essay_summary = $essay_summary;
            //            $paperInfo->content = $essay_obj;
            //            $paperInfo->save();


            //保存问题内容信息
            $question_context = ReadingExamContext::findOne(['biz_id' => $paperInfo->id, 'biz_type' => 1]);
            if (empty($question_context)) {
                $contextObj = new ReadingExamContext();
                $contextObj->content = $essay_obj;
                $contextObj->biz_id = $paperInfo->id;
                $contextObj->biz_type = 1;
                try {
                    $contextObj->insert();
                } catch (\Throwable $e) {
                    var_dump("保存问题内容信息失败ReadingExamQuestionContext");
                    \Yii::getLogger()->log("保存问题内容信息失败ReadingExamQuestionContext", Logger::LEVEL_ERROR);
                }
            } else {
                $question_context->content = $essay_obj;
                $question_context->save();
            }

            $question = (array)$value['processed']['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            $paperId = $paperInfo->id;

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }
                //查询题型分组信息
                //                $groupDesc = substr($val['description'], 0, 20);
                $desc = explode('\n', $val['description']);
                $groupDesc = $desc[0];
                $groupQuery = ReadingExamQuestionGroup::find();
                if ($questionType[$val['question_type']] == 7) {
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => [7, 14]]);
                } else {
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                }

                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (empty($group)) {
                    var_dump($questionType[$val['question_type']]);
                    var_dump("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    continue;
                }

                $groupId = $group->id;

                $questionList = (array)$val['question_ary'];

                //生成题目
                switch ($group->type) {
                    case 1:
                    case 2:
                    case 3:
                    case 4:
                    case 5:
                    case 6:
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                    case 12:
                    case 14:
                    case 13:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $parsed_answer = $va['parsed_answer'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $key_locating_words = $item['key_locating_words'] ?? [];
                                $locating_words = $item['locating_words'] ?? [];

                                //                                $sub_essay_code = $item['sub_essay_code'] ?? '';

                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                //                                if (empty($questionObj)) {
                                //                                    var_dump($paperId);
                                //                                    var_dump($groupId);
                                //                                    var_dump($item['question_num']);
                                //                                    die;
                                //                                    continue;
                                //                                }
                                //                                $questionObj->analyze_print = $analyze_print;
                                //                                if (!empty($locating_words)) {
                                $questionObj->key_locating_words = $locating_words;
                                $questionObj->locating_words = $locating_words;
                                //                                }
                                //                                $questionObj->key_locating_words = empty($locating_words) ? [] : $locating_words;
                                //                                $questionObj->locating_words = empty($locating_words) ? [] : $locating_words;
                                //                                $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                //                                $questionObj->sub_essay_code = (string)$sub_essay_code;
                                //                                $questionObj->display_answer = empty($item['display_answer']) ? '' : $item['display_answer'];
                                //                                $questionObj->display_answer = empty($questionObj->answer) ? '' : implode( " / ", $questionObj->answer);
                                //                                if (!empty($item['analyze'])) {
                                $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                //                                }
                                //                                $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                //                                if (!empty($item['central_sentences'])) {
                                //                                    $questionObj->central_sentences = $item['central_sentences'];
                                //                                }
                                $questionObj->central_sentences = empty($item['central_sentences']) ? [] : $item['central_sentences'];
                                //                                $questionObj->answer = isset($item['all_answer']) ? $this->dealWithAnswer($item['all_answer']) : [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                //                                $questionObj->option_analysis = $item['option_analysis'] ?? [];
                                $questionObj->save();

                                //保存问题内容信息
                                if (!empty($questionObj)) {
                                    $question_context = ReadingExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                    if (empty($question_context)) {
                                        $contextObj = new ReadingExamContext();
                                        $contextObj->content = $item;
                                        $contextObj->biz_id = $questionObj->id;
                                        $contextObj->biz_type = 2;
                                        try {
                                            $contextObj->insert();
                                        } catch (\Throwable $e) {
                                            var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                        }
                                    } else {
                                        $question_context->content = $item;
                                        $question_context->save();
                                    }
                                }

                                var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成", Logger::LEVEL_INFO);
                            }
                        }
                        break;
                    case 7:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $parsed_answer = $va['parsed_answer'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $key_locating_words = $item['key_locating_words'] ?? '[]';
                                $locating_words = $item['locating_words'] ?? '[]';
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                $questionNum = explode('-', $item['question_num']);
                                foreach ($questionNum as $k => $num) {
                                    //查询题目是否存在
                                    $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                    //                                    $questionObj->analyze_print = $analyze_print;
                                    if (!empty($locating_words)) {
                                        $questionObj->key_locating_words = $locating_words;
                                        $questionObj->locating_words = $locating_words;
                                    }
                                    //                                    $questionObj->key_locating_words = empty($key_locating_words) ? '[]' : $key_locating_words;
                                    //                                    $questionObj->locating_words = empty($locating_words) ? '[]' : $locating_words;
                                    //                                    $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                    //                                    $questionObj->sub_essay_code = $item['sub_essay_code'] ?? '';
                                    //                                    $questionObj->display_answer = empty($item['display_answer']) ? '' : $item['display_answer'];
                                    //                                    if (!empty($item['analyze'])) {
                                    //                                        $questionObj->analyze = $item['analyze'];
                                    //                                    }
                                    $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                    //                                    if (!empty($item['central_sentences'])) {
                                    //                                        $questionObj->central_sentences = $item['central_sentences'];
                                    //                                    }
                                    $questionObj->central_sentences = empty($item['central_sentences']) ? [] : $item['central_sentences'];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->save();

                                    //保存问题内容信息
                                    $question_context = ReadingExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                    if (empty($question_context)) {
                                        $contextObj = new ReadingExamContext();
                                        $contextObj->content = $item;
                                        $contextObj->biz_id = $questionObj->id;
                                        $contextObj->biz_type = 2;
                                        try {
                                            $contextObj->insert();
                                        } catch (\Throwable $e) {
                                            var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                        }
                                    }

                                    var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $num . "】更新完成", Logger::LEVEL_INFO);
                                }
                            }
                        }
                        break;
                }
            }
        }
    }

    /**
     * Summary of actionFixYcData
     * @param mixed $url
     * @return void
     */
    public function actionFixYcData($url)
    {
        $type = 2;
        $questionType = $this->getGroupType();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ReadingExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $key => $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            $title = $titleStr;


            //获取考试id
            $unitId = 963;

            $value['processed'] = (array)$value['processed'];
            $essayObj = (array)$value['processed']['essay_obj'];
            $complete_analyze =  $value['processed']['complete_analyze'] ?? '';
            $essay_summary = $value['processed']['essay_summary'] ?? '';
            $essay_obj = $value['processed']['essay_obj'] ?? '{}';
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            $topic = explode(',', $value['topic'] ?? '');
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            if (empty($paperInfo)) {
                var_dump("获取试卷id错误，参数[title:$title]");
                \Yii::getLogger()->log("获取试卷id错误，参数[title:$title]", Logger::LEVEL_ERROR);
                continue;
            }

            if ($essay_obj != '{}' && !empty($essay_obj)) {
                if (isset($essay_obj->order_ary)) {
                    foreach ($essay_obj->order_ary as &$order_val) {
                        $order_val = (string) $order_val;
                    }
                }
                if (isset($essay_obj->tmp_paragraph)) {
                    unset($essay_obj->tmp_paragraph);
                }
            }

            //            $paperInfo->topic = $curr_topic;
            //            $paperInfo->weight = $value['weight'];
            //            $paperInfo->analyze = $complete_analyze;
            //            $paperInfo->essay_summary = $essay_summary;
            $paperInfo->content = $essay_obj;
            $paperInfo->save();
            continue;


            //保存问题内容信息
            $question_context = ReadingExamContext::findOne(['biz_id' => $paperInfo->id, 'biz_type' => 1]);
            if (empty($question_context)) {
                $contextObj = new ReadingExamContext();
                $contextObj->content = $essay_obj;
                $contextObj->biz_id = $paperInfo->id;
                $contextObj->biz_type = 1;
                try {
                    $contextObj->insert();
                } catch (\Throwable $e) {
                    var_dump("保存问题内容信息失败ReadingExamQuestionContext");
                    \Yii::getLogger()->log("保存问题内容信息失败ReadingExamQuestionContext", Logger::LEVEL_ERROR);
                }
            } else {
                $question_context->content = $essay_obj;
                $question_context->save();
            }

            $question = (array)$value['processed']['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            $paperId = $paperInfo->id;

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }
                //查询题型分组信息
                //                $groupDesc = substr($val['description'], 0, 20);
                $desc = explode('\n', $val['description']);
                $groupDesc = $desc[0];
                $groupQuery = ReadingExamQuestionGroup::find();
                if ($questionType[$val['question_type']] == 7) {
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => [7, 14]]);
                } else {
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                }

                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (empty($group)) {
                    var_dump($questionType[$val['question_type']]);
                    var_dump("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    continue;
                }

                $groupId = $group->id;

                $questionList = (array)$val['question_ary'];

                //生成题目
                switch ($group->type) {
                    //                    case 1:
                    //                    case 2:
                    //                    case 3:
                    //                    case 4:
                    //                    case 5:
                    case 6:
                        //                    case 8:
                        //                    case 9:
                        //                    case 10:
                        //                    case 11:
                        //                    case 12:
                        //                    case 14:
                        //                    case 13:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $parsed_answer = $va['parsed_answer'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $key_locating_words = $item['key_locating_words'] ?? [];
                                $locating_words = $item['locating_words'] ?? [];

                                $sub_essay_code = $item['sub_essay_code'] ?? '';

                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                //                                if (empty($questionObj)) {
                                //                                    var_dump($paperId);
                                //                                    var_dump($groupId);
                                //                                    var_dump($item['question_num']);
                                //                                    die;
                                //                                    continue;
                                //                                }
                                //                                $questionObj->analyze_print = $analyze_print;
                                //                                if (!empty($locating_words)) {
                                //                                $questionObj->key_locating_words = $locating_words;
                                //                                $questionObj->locating_words = $locating_words;
                                //                                }
                                //                                $questionObj->key_locating_words = empty($locating_words) ? [] : $locating_words;
                                //                                $questionObj->locating_words = empty($locating_words) ? [] : $locating_words;
                                //                                $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                $questionObj->sub_essay_code = (string)$sub_essay_code;
                                //                                $questionObj->display_answer = empty($item['display_answer']) ? '' : $item['display_answer'];
                                //                                $questionObj->display_answer = empty($questionObj->answer) ? '' : implode( " / ", $questionObj->answer);
                                //                                if (!empty($item['analyze'])) {
                                //                                $questionObj->analyze = empty($item['analyze']) ? '' : $item['analyze'];
                                //                                }
                                //                                $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                //                                if (!empty($item['central_sentences'])) {
                                //                                    $questionObj->central_sentences = $item['central_sentences'];
                                //                                }
                                //                                $questionObj->central_sentences = empty($item['central_sentences']) ? [] : $item['central_sentences'];
                                //                                $questionObj->answer = isset($item['all_answer']) ? $this->dealWithAnswer($item['all_answer']) : [];
                                //                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                //                                $questionObj->option_analysis = $item['option_analysis'] ?? [];
                                $questionObj->save();

                                //保存问题内容信息
                                //                                if (!empty($questionObj)) {
                                //                                    $question_context = ReadingExamContext::findOne(['biz_id'=>$questionObj->id, 'biz_type'=>2]);
                                //                                    if (empty($question_context)) {
                                //                                        $contextObj = new ReadingExamContext();
                                //                                        $contextObj->content = $item;
                                //                                        $contextObj->biz_id = $questionObj->id;
                                //                                        $contextObj->biz_type = 2;
                                //                                        try {
                                //                                            $contextObj->insert();
                                //                                        } catch (\Throwable $e) {
                                //                                            var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                //                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]",Logger::LEVEL_ERROR);
                                //                                        }
                                //                                    } else {
                                //                                        $question_context->content = $item;
                                //                                        $question_context->save();
                                //                                    }
                                //                                }

                                var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成", Logger::LEVEL_INFO);
                            }
                        }
                        break;
                        //                    case 7:
                        //                        foreach ($questionList as $va) {
                        //                            $va = (array)$va;
                        //                            $parsed_answer = $va['parsed_answer'] ?? '';
                        //                            foreach ((array)$va['context'] as $item) {
                        //                                $item = (array)$item;
                        ////                                $analyze_print = $item['analyze_print_ary'];
                        //                                $key_locating_words = $item['key_locating_words'] ?? '[]';
                        //                                $locating_words = $item['locating_words'] ?? '[]';
                        //                                if (!isset($item['question_num'])) {
                        //                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                        //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                        //                                    continue;
                        //                                }
                        //                                $questionNum = $val['question_num_range'];
                        //                                foreach ($questionNum as $k => $num) {
                        //                                    //查询题目是否存在
                        //                                    $questionObj = ReadingExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$num]);
                        ////                                    $questionObj->analyze_print = $analyze_print;
                        ////                                    if (!empty($locating_words)) {
                        //                                        $questionObj->key_locating_words = $locating_words;
                        //                                        $questionObj->locating_words = $locating_words;
                        ////                                    }
                        ////                                    $questionObj->key_locating_words = empty($key_locating_words) ? '[]' : $key_locating_words;
                        ////                                    $questionObj->locating_words = empty($locating_words) ? '[]' : $locating_words;
                        ////                                    $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                        ////                                    $questionObj->sub_essay_code = $item['sub_essay_code'] ?? '';
                        //                                    $questionObj->display_answer = empty($item['display_answer']) ? '' : $item['display_answer'];
                        ////                                    if (!empty($item['analyze'])) {
                        //                                        $questionObj->analyze = $item['analyze'] ?? '';
                        ////                                    }
                        ////                                    $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                        ////                                    if (!empty($item['central_sentences'])) {
                        ////                                        $questionObj->central_sentences = $item['central_sentences'];
                        ////                                    }
                        //                                    $questionObj->central_sentences = empty($item['central_sentences']) ? [] : $item['central_sentences'];
                        //                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                        //                                    $questionObj->save();
                        //
                        //                                    //保存问题内容信息
                        //                                    $question_context = ReadingExamContext::findOne(['biz_id'=>$questionObj->id, 'biz_type'=>2]);
                        //                                    if (empty($question_context)) {
                        //                                        $contextObj = new ReadingExamContext();
                        //                                        $contextObj->content = $item;
                        //                                        $contextObj->biz_id = $questionObj->id;
                        //                                        $contextObj->biz_type = 2;
                        //                                        try {
                        //                                            $contextObj->insert();
                        //                                        } catch (\Throwable $e) {
                        //                                            var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                        //                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]",Logger::LEVEL_ERROR);
                        //                                        }
                        //                                    }
                        //
                        //                                    var_dump("试卷【 $titleStr . "."-".$paperId." 】题目【 " . $val['description'] . " 】题号【".$item['question_num']."】更新完成");
                        //                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【".$num."】更新完成", Logger::LEVEL_INFO);
                        //                                }
                        //
                        //                            }
                        //                        }
                        //                        break;
                }
            }
        }
    }

    public function actionFixNewYcData($url)
    {
        $type = 2;
        $questionType = $this->getGroupType();
        $content = file_get_contents($url);
        $arr = json_decode($content);
        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ReadingExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $key => $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['display_title'];
            $title = $titleStr;

            //获取考试id
            $unitId = 963;

            $essayObj = (array)$value['essay_obj'];
            $complete_analyze =  $value['complete_analyze'] ?? '';
            $essay_summary = $value['essay_summary'] ?? '';
            $essay_obj = $value['essay_obj'] ?? '{}';
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            $topic = explode(',', $value['topic'] ?? '');
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            if (empty($paperInfo)) {
                var_dump("获取试卷id错误，参数[title:$title]");
                \Yii::getLogger()->log("获取试卷id错误，参数[title:$title]", Logger::LEVEL_ERROR);
                continue;
            }

            //    if ($essay_obj != '{}' && !empty($essay_obj)) {
            //        if (isset($essay_obj->order_ary)) {
            //            foreach ($essay_obj->order_ary as &$order_val) {
            //                $order_val = (string) $order_val;
            //            }
            //        }
            //        if (isset($essay_obj->tmp_paragraph)) {
            //            unset($essay_obj->tmp_paragraph);
            //        }
            //    }

            //    $paperInfo->topic = $curr_topic;
            //    $paperInfo->weight = $value['weight'];
            //    $paperInfo->analyze = $complete_analyze;
            //    $paperInfo->essay_summary = $essay_summary;
            //    $paperInfo->content = $essay_obj;
            //    $paperInfo->save();
            //    continue;


            //保存问题内容信息
            $question_context = ReadingExamContext::findOne(['biz_id' => $paperInfo->id, 'biz_type' => 1]);
            if (empty($question_context)) {
                $contextObj = new ReadingExamContext();
                $contextObj->content = $essay_obj;
                $contextObj->biz_id = $paperInfo->id;
                $contextObj->biz_type = 1;
                try {
                    $contextObj->insert();
                } catch (\Throwable $e) {
                    var_dump("保存问题内容信息失败ReadingExamQuestionContext");
                    \Yii::getLogger()->log("保存问题内容信息失败ReadingExamQuestionContext", Logger::LEVEL_ERROR);
                }
            } else {
                $question_context->content = $essay_obj;
                $question_context->save();
            }

            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            $paperId = $paperInfo->id;

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }
                //查询题型分组信息
                //                $groupDesc = substr($val['description'], 0, 20);
                $desc = explode('\n', $val['description']);
                $groupDesc = $desc[0];
                $groupQuery = ReadingExamQuestionGroup::find();
                if ($questionType[$val['question_type']] == 7) {
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => [7, 14]]);
                } else {
                    $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                }

                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (empty($group)) {
                    var_dump($questionType[$val['question_type']]);
                    var_dump("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    continue;
                }

                $groupId = $group->id;

                $questionList = (array)$val['question_ary'];

                //生成题目
                switch ($group->type) {
                    case 1:
                    case 2:
                    case 3:
                    case 4:
                    case 5:
                    case 6:
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                    case 12:
                    case 14:
                    case 13:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $parsed_answer = $va['parsed_answer'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $key_locating_words = $item['key_locating_words'] ?? [];
                                $locating_words = $item['key_locating_words'] ?? [];

                                $sub_essay_code = $item['sub_essay_code'] ?? '';

                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    var_dump($paperId);
                                    var_dump($groupId);
                                    var_dump($item['question_num']);
                                    continue;
                                }
                                //                                $questionObj->analyze_print = $analyze_print;
                                if (!empty($locating_words)) {
                                    $questionObj->key_locating_words = $locating_words;
                                    $questionObj->locating_words = $locating_words;
                                }
                                //                                $questionObj->key_locating_words = empty($locating_words) ? [] : $locating_words;
                                //                                $questionObj->locating_words = empty($locating_words) ? [] : $locating_words;
                                //                                $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                $questionObj->sub_essay_code = (string)$sub_essay_code;
                                $questionObj->display_answer = empty($item['display_answer']) ? '' : $item['display_answer'];
                                //                                $questionObj->display_answer = empty($questionObj->answer) ? '' : implode( " / ", $questionObj->answer);
                                //                                if (!empty($item['AI_analyze'])) {
                                //                                $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                //                                }
                                $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                //                                if (!empty($item['central_sentences'])) {
                                //                                    $questionObj->central_sentences = $item['central_sentences'];
                                //                                }
                                $questionObj->central_sentences = empty($item['central_sentences']) ? [] : $item['central_sentences'];
                                //                                $questionObj->answer = isset($item['all_answer']) ? $this->dealWithAnswer($item['all_answer']) : [];
                                //                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                //                                $questionObj->option_analysis = $item['option_analysis'] ?? [];
                                $questionObj->save();

                                //保存问题内容信息
                                if (!empty($questionObj)) {
                                    $question_context = ReadingExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                    if (empty($question_context)) {
                                        $contextObj = new ReadingExamContext();
                                        $contextObj->content = $item;
                                        $contextObj->biz_id = $questionObj->id;
                                        $contextObj->biz_type = 2;
                                        try {
                                            $contextObj->insert();
                                        } catch (\Throwable $e) {
                                            var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                        }
                                    } else {
                                        $question_context->content = $item;
                                        $question_context->save();
                                    }
                                }

                                var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成", Logger::LEVEL_INFO);
                            }
                        }
                        break;
                    case 7:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $parsed_answer = $va['parsed_answer'] ?? '';
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                //                                $analyze_print = $item['analyze_print_ary'];
                                $key_locating_words = $item['key_locating_words'] ?? '[]';
                                $locating_words = $item['key_locating_words'] ?? '[]';
                                if (!isset($item['question_num'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    continue;
                                }
                                $questionNum = $val['question_num_range'];
                                foreach ($questionNum as $k => $num) {
                                    //查询题目是否存在
                                    $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                    //                                    $questionObj->analyze_print = $analyze_print;
                                    //                                    if (!empty($locating_words)) {
                                    $questionObj->key_locating_words = $locating_words;
                                    $questionObj->locating_words = $locating_words;
                                    //                                    }
                                    //                                    $questionObj->key_locating_words = empty($key_locating_words) ? '[]' : $key_locating_words;
                                    //                                    $questionObj->locating_words = empty($locating_words) ? '[]' : $locating_words;
                                    //                                    $questionObj->parsed_answer = empty($parsed_answer) ? '': json_encode($parsed_answer);
                                    //                                    $questionObj->sub_essay_code = $item['sub_essay_code'] ?? '';
                                    $questionObj->display_answer = empty($item['display_answer']) ? '' : $item['display_answer'];
                                    //                                    if (!empty($item['analyze'])) {
                                    //                                        $questionObj->analyze = $item['analyze'] ?? '';
                                    //                                    }
                                    $questionObj->analyze = empty($item['AI_analyze']) ? '' : $item['AI_analyze'];
                                    //                                    if (!empty($item['central_sentences'])) {
                                    //                                        $questionObj->central_sentences = $item['central_sentences'];
                                    //                                    }
                                    $questionObj->central_sentences = empty($item['central_sentences']) ? [] : $item['central_sentences'];
                                    $questionObj->ai_data = $item['ai_data'] ?? [];
                                    $questionObj->save();

                                    //保存问题内容信息
                                    $question_context = ReadingExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                    if (empty($question_context)) {
                                        $contextObj = new ReadingExamContext();
                                        $contextObj->content = $item;
                                        $contextObj->biz_id = $questionObj->id;
                                        $contextObj->biz_type = 2;
                                        try {
                                            $contextObj->insert();
                                        } catch (\Throwable $e) {
                                            var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                            \Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                        }
                                    }

                                    var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $num . "】更新完成", Logger::LEVEL_INFO);
                                }
                            }
                        }
                        break;
                }
            }
        }
    }

    public function actionCopyData(): void
    {
        $done = [2369, 2384, 2390, 2391, 2400, 2459, 2730, 2729, 2711, 2696, 2697, 2563, 2512, 2465];
        $paper_ids = [2369, 2384, 2390, 2391, 2400, 2459, 2730, 2729, 2711, 2696, 2697, 2563, 2512, 2465];
        $paper_map = [
            2563 => 'T42 P1',
            2384 => 'T101 P3',
            2400 => 'T96 P2',
            2711 => 'V19106',
            2391 => 'T99 P2',
            2459 => 'T76 P3',
            2512 => 'T59 P1',
            2697 => 'V17115',
            2465 => 'T74 P3',
            2390 => 'T99 P3',
            2369 => 'T106 P3',
            2730 => 'V16325',
            2729 => 'V27308',
            2696 => 'V17125',
        ];

        $list = ReadingExamPaper::find()->where(["id" => $paper_ids])->all();
        foreach ($list as $value) {
            //            var_dump($value->id);die;
            $paper = new ReadingExamPaper();
            $paper->title = $paper_map[$value->id];
            $paper->title_en = $paper_map[$value->id];
            $paper->complete_title = $paper_map[$value->id];
            $paper->complete_title_en = $paper_map[$value->id];
            $paper->essay_title = $value->essay_title;
            $paper->content = $value->content;
            $paper->weight = $value->weight;
            $paper->topic = $value->topic;
            $paper->unit = 963;
            $paper->paper_group = $value->paper_group;
            $paper->difficulty = $value->difficulty;
            $paper->analyze = $value->analyze;
            $paper->analyze_en = $value->analyze_en;
            $paper->essay_summary = $value->essay_summary;
            $paper->status = $value->status;
            try {
                $paper->insert(false);
            } catch (\Throwable $e) {
                var_dump("插入paper数据失败，paper_id=" . $value->id . ',err=' . $e->getMessage());
                continue;
            }
            var_dump("插入paper数据成功，paper_id=" . $value->id . ',new_paper_id=' . $paper->id);

            $context = ReadingExamContext::find()->where(['biz_type' => 1, 'biz_id' => $value->id])->one();
            if (!empty($context)) {
                $new_context = new ReadingExamContext();
                $new_context->biz_type = $context->biz_type;
                $new_context->biz_id = $paper->id;
                $new_context->content = $context->content;
                try {
                    $new_context->insert(false);
                } catch (\Throwable $e) {
                    var_dump("new_context插入失败，paper_id=" . $paper->id . 'err=' . $e->getMessage());
                }
            }

            //查询所有的题组
            $group_list = ReadingExamQuestionGroup::find()->where(['paper_id' => $value->id])->all();
            foreach ($group_list as $val) {
                var_dump($val->type);
                $group = new ReadingExamQuestionGroup();
                $group->paper_id = $paper->id;
                $group->type = $val->type;
                switch ($val->type) {
                    case 5:
                    case 1:
                        $group->desc = $val->desc;
                        $group->title = $val->title;
                        $group->content = $val->content;
                        $group->analyze = $val->analyze;
                        $group->base_analyze = $val->base_analyze;
                        $group->img_url = $val->img_url;
                        try {
                            $group->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("插入group数据失败，group_id=" . $val->id . ',err=' . $e->getMessage());
                            break;
                        }
                        var_dump("插入group数据成功，group_id=" . $val->id . ',new_group_id=' . $group->id);
                        $question_list = ReadingExamQuestion::find()->where(['group_id' => $val->id])->all();
                        foreach ($question_list as $va) {
                            $question = new ReadingExamQuestion();
                            $question->title = $va->title;
                            $question->paper_id = $paper->id;
                            $question->group_id = $group->id;
                            $question->number = $va->number;
                            $question->display_answer = $va->display_answer;
                            $question->analyze_print = $va->analyze_print;
                            $question->analyze = $va->analyze;
                            $question->analyze_en = $va->analyze_en;
                            $question->key_locating_words = $va->key_locating_words;
                            $question->locating_words = $va->locating_words;
                            $question->ai_data = $va->ai_data;
                            $question->option_analysis = $va->option_analysis;
                            $question->base_analyze = $va->base_analyze;
                            $question->parsed_answer = $va->parsed_answer;
                            $question->id_text = $va->id_text;
                            $question->central_sentences = $va->central_sentences;
                            try {
                                $question->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入question数据失败，question_id=" . $va->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入question数据成功，question_id=" . $va->id . ',new_question_id=' . $question->id);

                            $question_context = ReadingExamContext::find()->where(['biz_type' => 2, 'biz_id' => $va->id])->one();
                            if (!empty($question_context)) {
                                $new_question_context = new ReadingExamContext();
                                $new_question_context->biz_type = $question_context->biz_type;
                                $new_question_context->biz_id = $question->id;
                                $new_question_context->content = $question_context->content;
                                try {
                                    $new_question_context->insert(false);
                                } catch (\Throwable $e) {
                                    var_dump("插入question_context数据失败，question_id=" . $question->id . ',err=' . $e->getMessage());
                                }
                            }

                            $option_list = ReadingExamQuestionOption::find()->where(['biz_type' => 1, 'biz_id' => $va->id])->all();
                            foreach ($option_list as $v) {
                                $option = new ReadingExamQuestionOption();
                                $option->biz_type = $v->biz_type;
                                $option->biz_id = $question->id;
                                $option->title = $v->title;
                                $option->content = $v->content;
                                try {
                                    $option->insert(false);
                                } catch (\Throwable $e) {
                                    var_dump("插入option数据失败，option_id=" . $v->id . ',err=' . $e->getMessage());
                                    break;
                                }
                                var_dump("插入option数据成功，option_id=" . $v->id . ',new_option_id=' . $option->id);
                                if ($v->id == $va->answer[0]) {
                                    $question->answer = [$option->id];
                                    $question->save(false);
                                    var_dump("更新答案完成，question_id=" . $question->id);
                                }
                            }
                        }
                        break;
                    case 4:
                    case 12:
                    case 8:
                        $group->desc = $val->desc;
                        $group->title = $val->title;
                        $group->content = $val->content;
                        $group->analyze = $val->analyze;
                        $group->base_analyze = $val->base_analyze;
                        $group->img_url = $val->img_url;
                        try {
                            $group->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("插入group数据失败，group_id=" . $val->id . ',err=' . $e->getMessage());
                            break;
                        }
                        var_dump("插入group数据成功，group_id=" . $val->id . ',new_group_id=' . $group->id);
                        $question_list = ReadingExamQuestion::find()->where(['group_id' => $val->id])->all();
                        foreach ($question_list as $va) {
                            $question = new ReadingExamQuestion();
                            $question->title = $va->title;
                            $question->paper_id = $paper->id;
                            $question->group_id = $group->id;
                            $question->number = $va->number;
                            $question->answer = $va->answer;
                            $question->display_answer = $va->display_answer;
                            $question->analyze_print = $va->analyze_print;
                            $question->analyze = $va->analyze;
                            $question->analyze_en = $va->analyze_en;
                            $question->key_locating_words = $va->key_locating_words;
                            $question->locating_words = $va->locating_words;
                            $question->ai_data = $va->ai_data;
                            $question->option_analysis = $va->option_analysis;
                            $question->base_analyze = $va->base_analyze;
                            $question->parsed_answer = $va->parsed_answer;
                            $question->id_text = $va->id_text;
                            $question->central_sentences = $va->central_sentences;
                            try {
                                $question->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入question数据失败，question_id=" . $va->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入question数据成功，question_id=" . $va->id . ',new_question_id=' . $question->id);

                            $question_context = ReadingExamContext::find()->where(['biz_type' => 2, 'biz_id' => $va->id])->one();
                            if (!empty($question_context)) {
                                $new_question_context = new ReadingExamContext();
                                $new_question_context->biz_type = $question_context->biz_type;
                                $new_question_context->biz_id = $question->id;
                                $new_question_context->content = $question_context->content;
                                try {
                                    $new_question_context->insert(false);
                                } catch (\Throwable $e) {
                                    var_dump("插入question_context数据失败，question_id=" . $question->id . ',err=' . $e->getMessage());
                                }
                            }

                            //替换题目id
                            $group->content = str_replace("$$va->id$", "$$question->id$", $group->content);
                        }
                        $group->save(false);
                        break;
                    case 2:
                    case 6:
                    case 7:
                    case 9:
                        $group->desc = $val->desc;
                        $group->title = $val->title;
                        $group->content = $val->content;
                        $group->analyze = $val->analyze;
                        $group->base_analyze = $val->base_analyze;
                        $group->img_url = $val->img_url;
                        try {
                            $group->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("插入group数据失败，group_id=" . $val->id . ',err=' . $e->getMessage());
                            break;
                        }
                        var_dump("插入group数据成功，group_id=" . $val->id . ',new_group_id=' . $group->id);

                        $option_list = ReadingExamQuestionOption::find()->where(['biz_type' => 2, 'biz_id' => $val->id])->all();
                        $option_map = [];
                        foreach ($option_list as $v) {
                            $option = new ReadingExamQuestionOption();
                            $option->biz_type = $v->biz_type;
                            $option->biz_id = $group->id;
                            $option->title = $v->title;
                            $option->content = $v->content;
                            try {
                                $option->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入option数据失败，option_id=" . $v->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入option数据成功，option_id=" . $v->id . ',new_option_id=' . $option->id);
                            $option_map[$v->id] = $option->id;
                        }

                        $question_list = ReadingExamQuestion::find()->where(['group_id' => $val->id])->all();
                        foreach ($question_list as $va) {
                            $question = new ReadingExamQuestion();
                            $question->title = $va->title;
                            $question->paper_id = $paper->id;
                            $question->group_id = $group->id;
                            $question->number = $va->number;
                            $question->answer = [$option_map[$va->answer[0]]];
                            $question->display_answer = $va->display_answer;
                            $question->analyze_print = $va->analyze_print;
                            $question->analyze = $va->analyze;
                            $question->analyze_en = $va->analyze_en;
                            $question->key_locating_words = $va->key_locating_words;
                            $question->locating_words = $va->locating_words;
                            $question->ai_data = $va->ai_data;
                            $question->option_analysis = $va->option_analysis;
                            $question->base_analyze = $va->base_analyze;
                            $question->parsed_answer = $va->parsed_answer;
                            $question->id_text = $va->id_text;
                            $question->central_sentences = $va->central_sentences;
                            try {
                                $question->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入question数据失败，question_id=" . $va->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入question数据成功，question_id=" . $va->id . ',new_question_id=' . $question->id);

                            $question_context = ReadingExamContext::find()->where(['biz_type' => 2, 'biz_id' => $va->id])->one();
                            if (!empty($question_context)) {
                                $new_question_context = new ReadingExamContext();
                                $new_question_context->biz_type = $question_context->biz_type;
                                $new_question_context->biz_id = $question->id;
                                $new_question_context->content = $question_context->content;
                                try {
                                    $new_question_context->insert(false);
                                } catch (\Throwable $e) {
                                    var_dump("插入question_context数据失败，question_id=" . $question->id . ',err=' . $e->getMessage());
                                }
                            }
                        }
                        break;
                    case 10:
                        $group->desc = $val->desc;
                        $group->title = $val->title;
                        $group->content = $val->content;
                        $group->analyze = $val->analyze;
                        $group->base_analyze = $val->base_analyze;
                        $group->img_url = $val->img_url;
                        try {
                            $group->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("插入group数据失败，group_id=" . $val->id . ',err=' . $e->getMessage());
                            break;
                        }
                        var_dump("插入group数据成功，group_id=" . $val->id . ',new_group_id=' . $group->id);

                        $option_list = ReadingExamQuestionOption::find()->where(['biz_type' => 2, 'biz_id' => $val->id])->all();
                        $option_map = [];
                        foreach ($option_list as $v) {
                            $option = new ReadingExamQuestionOption();
                            $option->biz_type = $v->biz_type;
                            $option->biz_id = $group->id;
                            $option->title = $v->title;
                            $option->content = $v->content;
                            try {
                                $option->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入option数据失败，option_id=" . $v->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入option数据成功，option_id=" . $v->id . ',new_option_id=' . $option->id);
                            $option_map[$v->id] = $option->id;
                        }

                        $question_list = ReadingExamQuestion::find()->where(['group_id' => $val->id])->all();
                        foreach ($question_list as $va) {
                            $question = new ReadingExamQuestion();
                            $question->title = $va->title;
                            $question->paper_id = $paper->id;
                            $question->group_id = $group->id;
                            $question->number = $va->number;
                            $question->answer = [$option_map[$va->answer[0]]];
                            $question->display_answer = $va->display_answer;
                            $question->analyze_print = $va->analyze_print;
                            $question->analyze = $va->analyze;
                            $question->analyze_en = $va->analyze_en;
                            $question->key_locating_words = $va->key_locating_words;
                            $question->locating_words = $va->locating_words;
                            $question->ai_data = $va->ai_data;
                            $question->option_analysis = $va->option_analysis;
                            $question->base_analyze = $va->base_analyze;
                            $question->parsed_answer = $va->parsed_answer;
                            $question->id_text = $va->id_text;
                            $question->central_sentences = $va->central_sentences;
                            try {
                                $question->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入question数据失败，question_id=" . $va->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入question数据成功，question_id=" . $va->id . ',new_question_id=' . $question->id);

                            $question_context = ReadingExamContext::find()->where(['biz_type' => 2, 'biz_id' => $va->id])->one();
                            if (!empty($question_context)) {
                                $new_question_context = new ReadingExamContext();
                                $new_question_context->biz_type = $question_context->biz_type;
                                $new_question_context->biz_id = $question->id;
                                $new_question_context->content = $question_context->content;
                                try {
                                    $new_question_context->insert(false);
                                } catch (\Throwable $e) {
                                    var_dump("插入question_context数据失败，question_id=" . $question->id . ',err=' . $e->getMessage());
                                }
                            }

                            //替换题目id
                            $group->content = str_replace("$$va->id$", "$$question->id$", $group->content);
                        }
                        $group->save(false);
                        break;
                    case 3:
                    case 11:
                        $group->desc = $val->desc;
                        $group->title = $val->title;
                        $group->content = $val->content;
                        $group->analyze = $val->analyze;
                        $group->base_analyze = $val->base_analyze;
                        $group->img_url = $val->img_url;
                        try {
                            $group->insert(false);
                        } catch (\Throwable $e) {
                            var_dump("插入group数据失败，group_id=" . $val->id . ',err=' . $e->getMessage());
                            break;
                        }
                        var_dump("插入group数据成功，group_id=" . $val->id . ',new_group_id=' . $group->id);
                        $question_list = ReadingExamQuestion::find()->where(['group_id' => $val->id])->all();
                        foreach ($question_list as $va) {
                            $question = new ReadingExamQuestion();
                            $question->title = $va->title;
                            $question->paper_id = $paper->id;
                            $question->group_id = $group->id;
                            $question->number = $va->number;
                            $question->answer = $va->answer;
                            $question->display_answer = $va->display_answer;
                            $question->analyze_print = $va->analyze_print;
                            $question->analyze = $va->analyze;
                            $question->analyze_en = $va->analyze_en;
                            $question->key_locating_words = $va->key_locating_words;
                            $question->locating_words = $va->locating_words;
                            $question->ai_data = $va->ai_data;
                            $question->option_analysis = $va->option_analysis;
                            $question->base_analyze = $va->base_analyze;
                            $question->parsed_answer = $va->parsed_answer;
                            $question->id_text = $va->id_text;
                            $question->central_sentences = $va->central_sentences;
                            try {
                                $question->insert(false);
                            } catch (\Throwable $e) {
                                var_dump("插入question数据失败，question_id=" . $va->id . ',err=' . $e->getMessage());
                                break;
                            }
                            var_dump("插入question数据成功，question_id=" . $va->id . ',new_question_id=' . $question->id);

                            $question_context = ReadingExamContext::find()->where(['biz_type' => 2, 'biz_id' => $va->id])->one();
                            if (!empty($question_context)) {
                                $new_question_context = new ReadingExamContext();
                                $new_question_context->biz_type = $question_context->biz_type;
                                $new_question_context->biz_id = $question->id;
                                $new_question_context->content = $question_context->content;
                                try {
                                    $new_question_context->insert(false);
                                } catch (\Throwable $e) {
                                    var_dump("插入question_context数据失败，question_id=" . $question->id . ',err=' . $e->getMessage());
                                }
                            }
                        }
                        break;
                }
            }
            var_dump("paper_id=" . $value->id . '复制完成');
        }
    }

    public function actionFixSort()
    {
        $list = ReadingExamPaper::find()->where(['>', 'id', 168])->all();
        $group = [];
        foreach ($list as $value) {
            $group[$value->unit][] = $value;
        }

        foreach ($group as $val) {
            $org_sort = [];
            foreach ($val as $v) {
                $org_sort[] = $v->weight;
            }
            sort($org_sort);
            foreach ($val as $ke => $va) {
                $va->weight = $org_sort[$ke];
                $va->save();
                var_dump("{$va->id},更新排序完成");
                var_dump("排序：" . $va->weight);
            }
        }
    }

    public function getGroupType(): array
    {
        $listMap = [];
        $query = ReadingExamQuestionType::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            if ($value->name == '总结有选项题') {
                $value->name = '总结有选项题 (选词摘要题目)';
            }
            if ($value->name == '句子配对题') {
                $value->name = '句子配句子题';
            }
            $listMap[$value->name] = $value->id;
        }

        return $listMap;
    }

    public function getUnitMap(): array
    {
        return [
            "Test1" => "Test 1",
            "Test2" => "Test 2",
            "Test3" => "Test 3",
            "Test4" => "Test 4",
            "Test5" => "Test 5",
            "Test6" => "Test 6",
            "Test7" => "Test 7",
            "Test8" => "Test 8",
        ];
    }

    public function dealWithAnswer($arr): array
    {
        $ret = [];
        if (count($arr) == 0) {
            return $ret;
        }

        foreach ($arr as $value) {
            if (!is_string($value)) {
                $ret[] = strval($value);
            } else {
                $ret[] = $value;
            }
        }

        return $ret;
    }

    /**
     * 读取 question.json 与 answer_error_free_ask_answers.json
     * 批量更新题目 answer/display_answer 及 analyze 字段
     *
     * 使用方式：
     * php yii reading/sync-question-answer-analyze
     */
    public function actionSyncQuestionAnswerAnalyze(): void
    {
        $basePath = dirname(__FILE__, 2);
        $questionFile = $basePath . '/runtime/tmp/question.json';
        $analyzeFile = $basePath . '/runtime/tmp/answer_error_free_ask_answers.json';

        $answerUpdated = $this->updateQuestionAnswersFromFile($questionFile);
        $analyzeUpdated = $this->updateQuestionAnalyzeFromFile($analyzeFile);

        var_dump("answer/display_answer 更新完成：$answerUpdated 条");
        var_dump("analyze 更新完成：$analyzeUpdated 条");
    }

    /**
     * 从 question.json 更新 answer/display_answer
     */
    private function updateQuestionAnswersFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            var_dump("question.json 文件不存在：$filePath");
            return 0;
        }

        $content = file_get_contents($filePath);
        $questionItems = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($questionItems)) {
            var_dump("question.json 解析失败：" . json_last_error_msg());
            return 0;
        }

        $updated = 0;
        foreach ($questionItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $questionId = (int)($item['id'] ?? 0);
            if ($questionId <= 0) {
                continue;
            }

            $hasAnswer = array_key_exists('answer', $item);
            $hasDisplay = array_key_exists('display_answer', $item);
            if (!$hasAnswer && !$hasDisplay) {
                continue;
            }

            $question = ReadingExamQuestion::findOne($questionId);
            if (empty($question)) {
                var_dump("题目不存在，ID：$questionId");
                continue;
            }

            $needSave = false;

            if ($hasAnswer) {
                $answerValue = is_array($item['answer'])
                    ? json_encode($item['answer'], JSON_UNESCAPED_UNICODE)
                    : (string)$item['answer'];
                if ($question->answer !== $answerValue) {
                    $question->answer = $answerValue;
                    $needSave = true;
                }
            }

            if ($hasDisplay) {
                $displayAnswer = (string)$item['display_answer'];
                if ($question->display_answer !== $displayAnswer) {
                    $question->display_answer = $displayAnswer;
                    $needSave = true;
                }
            }

            if ($needSave) {
                if ($question->save(false)) {
                    $updated++;
                } else {
                    var_dump("题目【ID：$questionId 】保存失败：" . json_encode($question->errors, JSON_UNESCAPED_UNICODE));
                }
            }
        }

        return $updated;
    }

    /**
     * 从 answer_error_free_ask_answers.json 更新 analyze 字段
     */
    private function updateQuestionAnalyzeFromFile(string $filePath): int
    {
        if (!file_exists($filePath)) {
            var_dump("answer_error_free_ask_answers.json 文件不存在：$filePath");
            return 0;
        }

        $content = file_get_contents($filePath);
        $analyzeItems = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($analyzeItems)) {
            var_dump("answer_error_free_ask_answers.json 解析失败：" . json_last_error_msg());
            return 0;
        }

        $updated = 0;
        foreach ($analyzeItems as $questionId => $analyzeText) {
            $questionId = (int)$questionId;
            if ($questionId <= 0) {
                continue;
            }

            $analyzeValue = is_string($analyzeText) ? trim($analyzeText) : '';
            if ($analyzeValue === '') {
                continue;
            }

            $question = ReadingExamQuestion::findOne($questionId);
            if (empty($question)) {
                var_dump("题目不存在，ID：$questionId");
                continue;
            }

            if ($question->analyze === $analyzeValue) {
                continue;
            }

            $question->analyze = $analyzeValue;
            if ($question->save(false)) {
                $updated++;
            } else {
                var_dump("题目【ID：$questionId 】analyze 保存失败：" . json_encode($question->errors, JSON_UNESCAPED_UNICODE));
            }
        }

        return $updated;
    }

    /**
     * 读取 question_context.json 更新 reading_exam_context.content
     *
     * 使用方式：
     * php yii reading/sync-question-context
     */
    public function actionSyncQuestionContext(): void
    {
        $basePath = dirname(__FILE__, 2);
        $filePath = $basePath . '/runtime/tmp/question_context.json';

        if (!file_exists($filePath)) {
            var_dump("question_context.json 文件不存在：$filePath");
            return;
        }

        $content = file_get_contents($filePath);
        $contextItems = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($contextItems)) {
            var_dump("question_context.json 解析失败：" . json_last_error_msg());
            return;
        }

        $updated = 0;
        foreach ($contextItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $contextId = (int)($item['id'] ?? 0);
            if ($contextId <= 0 || !array_key_exists('content', $item)) {
                continue;
            }

            $questionContext = ReadingExamContext::findOne($contextId);
            if (empty($questionContext)) {
                var_dump("上下文不存在，context_id：$contextId");
                continue;
            }

            $newContent = $this->normalizeContextContent($item['content']);
            $currentContentJson = $this->encodeContentForCompare($questionContext->content);
            $newContentJson = $this->encodeContentForCompare($newContent);
            if ($currentContentJson === $newContentJson) {
                continue;
            }

            $questionContext->content = $newContent;
            if ($questionContext->save(false)) {
                $updated++;
            } else {
                var_dump("上下文【ID：$contextId 】保存失败：" . json_encode($questionContext->errors, JSON_UNESCAPED_UNICODE));
            }
        }

        var_dump("question_context 更新完成：$updated 条");
    }

    private function normalizeContextContent($raw)
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $raw;
    }

    private function encodeContentForCompare($content): string
    {
        if (is_array($content) || is_object($content)) {
            return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string)$content;
    }

    public function actionFixContext()
    {
        $ids = [30458, 30459, 30460, 30591, 30592, 30704, 30705, 30706, 30823, 30824, 31191, 31192, 31340, 31341, 31433, 31434, 31435, 31436, 31463, 31464, 31465, 31519, 31520, 31521, 31548, 31549, 31550, 31625, 31626, 31627, 31628, 31629, 31749, 31750, 31804, 31805, 31929, 31930, 31931, 31932, 31933, 32038, 32039, 32065, 32066, 32067, 32105, 32106, 32203, 32204, 32205, 32349, 32350, 32351, 32352, 32353, 32438, 32439, 32440, 32442, 32443, 32444, 32445, 32503, 32504, 32505, 32506, 32507, 32508, 32509, 32510, 32511, 32538, 32539, 32613, 32614, 32615, 32616, 32617, 32618, 32619, 32620, 32648, 32649, 32667, 32668, 32706, 32707, 32708, 32709, 32710, 32711, 32784, 32785, 32786, 32787, 32799, 32800, 32801, 32830, 32831, 32832, 32833, 32873, 32874, 32875, 32876, 32915, 32916, 32917, 32963, 32964, 32965, 33091, 33092, 33093, 33094, 33116, 33117, 33118, 33119, 33221, 33222, 33243, 33244, 33245, 33246, 33253, 33254, 33255, 33256, 33419, 33420, 33421, 33422, 33435, 33436, 33556, 33557, 33558, 33559, 33628, 33629, 33630, 33631, 33647, 33648, 33649, 33650, 34281, 34282, 34283, 34284, 34459, 34460, 34461, 34672, 34673, 34674, 34798, 34799, 34800, 34801, 34848, 34849, 34850, 34851, 34852, 34853, 35045, 35046, 35201, 35202, 35241, 35242, 35243, 35244, 35245, 35358, 35359, 35360, 35424, 35425, 35426, 35427, 35595, 35596, 35779, 35780, 35872, 35873, 35874, 35875, 36415, 36416, 36417, 36418, 36461, 36462, 36463, 36464];
        $list = ReadingExamContext::find()->where(['id' => $ids])->all();
        foreach ($list as $value) {
            $content = $value->content;
            if ((isset($content['answer'])) && (is_array($content['answer']))) {
                $content['answer'] = implode(',', $content['answer']);
                $value->content = $content;
                $value->save(false);
                var_dump("id: $value->id ,更新答案：" . $content['answer']);
            }
        }
    }

    //数据修复
    public function actionFixLocation()
    {
        $type = 1;
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/剑雅20阅读解析结果3.json';
        $question_content = file_get_contents($file);
        $arr = json_decode($question_content);
        if (empty($arr)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ReadingExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['id'];
            var_dump($titleStr);
            $titleArr = explode('-', $titleStr);
            $subType = '剑雅' . $titleArr[0];
            $unit = $unit_map['Test' . $titleArr[1]];
            $title = 'Passage ' . $titleArr[2];
            var_dump($title);
            $complete_title = $subType . ' ' . $unit . '-' . $title;
            //获取类型
            $subTypeId = (new ReadingExamPaperType())->getByName($type, $subType);
            if (empty($subTypeId)) {
                \Yii::getLogger()->log("获取类型错误，参数[type:$type,subType:$subType]", Logger::LEVEL_ERROR);
                var_dump("获取类型错误，参数[type:$type,subType:$subType]");
                die;
            }

            //获取考试id
            $unitId = (new ReadingExamPaperUnit())->getByName($subTypeId, $unit);
            if (empty($unitId)) {
                \Yii::getLogger()->log("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]", Logger::LEVEL_ERROR);
                var_dump("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]");
                die;
            }

            $complete_analyze =  $value['complete_analyze'] ?? '';
            $essay_summary = $value['essay_summary'] ?? '';
            $essay_obj = $value['essay_obj'];
            $paperInfo = ReadingExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            $topic = explode(',', $value['topic']);
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            if (empty($paperInfo)) {
                \Yii::getLogger()->log("获取试卷id错误，参数[subTypeId:$subTypeId,unit:$unit,title:$title]", Logger::LEVEL_ERROR);
                var_dump("获取试卷id错误，参数[subTypeId:$subTypeId,unit:$unit,title:$title]");
                die;
            }

            $question = (array)$value['questions'];
            if (empty($question)) {
                \Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                var_dump("题目为空");
                die;
            }

            $paperId = $paperInfo->id;

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    \Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    die;
                }
                //查询题型分组信息
                $groupArr = explode(' ', $val['description']);
                $groupDesc = $groupArr[0] . ' ' . $groupArr[1];
                $groupQuery = ReadingExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $questionType[$val['question_type']]]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();
                if (empty($group)) {
                    \Yii::getLogger()->log("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                    var_dump("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    die;
                }

                $groupId = $group->id;
                $questionList = (array)$val['question_ary'];

                //生成题目
                switch ($group->type) {
                    case 1:
                    case 2:
                    case 3:
                    case 4:
                    case 5:
                    case 6:
                    case 8:
                    case 9:
                    case 10:
                    case 11:
                    case 12:
                    case 13:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($item['question_num'])) {
                                    \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    die;
                                }
                                $locating_words = $item['key_locating_words'];

                                //查询题目是否存在
                                $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                                if (empty($questionObj)) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】不存在");
                                    continue;
                                }
                                $questionObj->key_locating_words = $locating_words;
                                $questionObj->locating_words = $locating_words;
                                $questionObj->save();

                                var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                \Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成", Logger::LEVEL_INFO);
                            }
                        }
                        break;
                    case 7:
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $group->title = $va['title'] ?? '';
                            $group->save();
                            foreach ((array)$va['context'] as $item) {
                                $item = (array)$item;
                                if (!isset($val['question_num_range'])) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号不存在");
                                    die;
                                }
                                $questionNum = $val['question_num_range'];
                                $locating_words = $item['key_locating_words'];

                                foreach ($questionNum as $k => $num) {
                                    //查询题目是否存在
                                    $questionObj = ReadingExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                    if (empty($questionObj)) {
                                        var_dump("试卷【 $titleStr 】题目【 " . $val['description'] . " 】题号【" . $num . "】不存在");
                                        die;
                                    }
                                    $questionObj->key_locating_words = $locating_words;
                                    $questionObj->locating_words = $locating_words;
                                    $questionObj->save();

                                    var_dump("试卷【 $titleStr . " . "-" . $paperId . " 】题目【 " . $val['description'] . " 】题号【" . $item['question_num'] . "】更新完成");
                                }
                            }
                        }
                        break;
                }
            }
        }
    }

    public function actionFixRecordAnswer()
    {
        $question_ids = [1325, 1522, 1524, 1775];
        //获取听力所有题型做题记录
        $list = ReadingExamRecord::find()->where(['paper_id' => [101, 117, 137]])->all();
        if (empty($list)) {
            var_dump("处理完成");
            die;
        }
        foreach ($list as $value) {
            //获取题组信息
            $answer = $value->answer;
            $is_save = false;
            if (!empty($answer)) {
                foreach ($answer as $k => $v) {
                    if (in_array($v['id'], $question_ids) && (!empty($v['answer'])) && is_array($v['answer'])) {
                        if (substr_count($v['answer'][0],  'or') > 0) {
                            $is_save = true;
                            $answer[$k]['answer'] = array_map('trim', explode('or', $v['answer'][0]));
                        } else if (substr_count($v['answer'][0], 'and') > 0) {
                            $is_save = true;
                            $answer[$k]['answer'] = array_map('trim', explode('and', $v['answer'][0]));
                        }
                    }
                }
            }
            if ($is_save) {
                $value->answer = $answer;
                $value->save(false);
                var_dump("更新 $value->id 完成");
            }
        }
    }

    public function actionFixSimulateRecordAnswer()
    {
        $question_ids = [1325, 1522, 1524, 1775];
        //获取听力所有题型做题记录
        $list = SimulateExamReading::find()->where(['paper_group_id' => [34, 39, 46]])->all();
        if (empty($list)) {
            var_dump("处理完成");
            die;
        }
        foreach ($list as $value) {
            //获取题组信息
            $answer = $value->answer;
            $is_save = false;
            if (!empty($answer)) {
                $answer = json_decode($answer, true);
                foreach ($answer as $k => $v) {
                    if (in_array($v['id'], $question_ids) && (!empty($v['answer'])) && is_array($v['answer'])) {
                        if (substr_count($v['answer'][0],  'or') > 0) {
                            $is_save = true;
                            $answer[$k]['answer'] = array_map('trim', explode('or', $v['answer'][0]));
                        } else if (substr_count($v['answer'][0],  'and') > 0) {
                            $is_save = true;
                            $answer[$k]['answer'] = array_map('trim', explode('and', $v['answer'][0]));
                        }
                    }
                }
            }
            if ($is_save) {
                $value->answer = json_encode($answer);
                $value->save(false);
                var_dump("更新 $value->id 完成");
            }
        }
    }



    /**
     * 修复 reading_exam_question.answer 中被二次 JSON 序列化的记录。
     *
     * 现象：answer 是 JSON 字段，但部分数据被保存成了 JSON 字符串，
     * 如 "[\"A\",\"B\"]"，导致外层类型为 STRING。
     * 本脚本会把这类记录解一层 JSON 后写回，使其成为真正的 JSON 数组/对象。
     *
     * 使用方式：
     * php yii reading/fix-answer-json [dryRun=1] [batchSize=200] [limit=0]
     *
     * 参数：
     * - dryRun: 1 仅打印将要修复的记录，不落库；0 真正执行更新。
     * - batchSize: 每批处理条数。
     * - limit: 限制处理条数，0 表示不限制。
     */
    public function actionFixAnswerJson(int $dryRun = 1, int $batchSize = 200, int $limit = 0): void
    {
        $dryRun = $dryRun === 1;

        $query = ReadingExamQuestion::find()
            ->where('answer IS NOT NULL')
            ->andWhere("JSON_TYPE(answer) = 'STRING'")
            ->andWhere("(JSON_UNQUOTE(answer) LIKE '[%' OR JSON_UNQUOTE(answer) LIKE '{%')")
            ->orderBy(['id' => SORT_ASC]);

        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        var_dump("候选记录数：$total");
        if ($total == 0) {
            return;
        }

        $fixed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($query->batch($batchSize) as $questions) {
            /** @var ReadingExamQuestion $question */
            foreach ($questions as $question) {
                $raw = $question->answer;
                if (!is_string($raw) || $raw === '') {
                    $skipped++;
                    continue;
                }

                [$decoded, $decodeTimes] = $this->decodeDoubleJson($raw);
                var_dump($decoded);
                var_dump($decodeTimes);
                if ($decoded === null) {
                    $skipped++;
                    continue;
                }

                // if ($dryRun) {
                //     $fixed++;
                //     $preview = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                //     $this->stdout("DRY-RUN 修复 id={$question->id} 解码次数={$decodeTimes} 原值={$raw} 新值={$preview}\n");
                //     continue;
                // }

                echo "=========================================";
                var_dump($decoded);
                $question->answer = $decoded;
                if ($question->save(false, ['answer'])) {
                    $fixed++;
                    $this->stdout("修复成功 id={$question->id} 解码次数={$decodeTimes}\n");
                } else {
                    $failed++;
                    var_dump("修复失败 id={$question->id} 错误：" . json_encode($question->errors, JSON_UNESCAPED_UNICODE));
                }
            }
        }

        var_dump("完成。修复：$fixed ，跳过：$skipped ，失败：$failed ，dryRun ：" . ($dryRun ? 'true' : 'false'));
    }

    /**
     * 尝试对可能被二次 JSON 序列化的 answer 解码。
     *
     * @param string $raw 数据库原始值
     * @return array{0:mixed|null,1:int} [解码后的值(若无须修复则为 null), 解码次数]
     */
    private function decodeDoubleJson(string $raw): array
    {
        $decodeTimes = 0;
        $current = $raw;
        $decoded = null;

        // 最多解两层，避免异常数据导致死循环。
        while ($decodeTimes < 2) {
            $decoded = json_decode($current, true);
            $decodeTimes++;

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [null, $decodeTimes];
            }

            if (!is_string($decoded)) {
                return [$decoded, $decodeTimes];
            }

            $trimmed = ltrim($decoded);
            if ($trimmed === '' || ($trimmed[0] !== '[' && $trimmed[0] !== '{')) {
                return [null, $decodeTimes];
            }

            $current = $decoded;
        }

        return [$decoded, $decodeTimes];
    }

    /**
     * 根据 answer_analysis_errors.json 导出题目信息
     * exemple: php yii reading/export-answer-analysis-errors
     * @return string
     */
    public function actionExportAnswerAnalysisErrors(): string
    {
        $basePath = dirname(__FILE__, 2);
        $jsonPath = $basePath . '/runtime/tmp/answer_analysis_errors.json';

        if (!file_exists($jsonPath)) {
            var_dump("文件不存在：$jsonPath");
            return '';
        }

        $jsonContent = file_get_contents($jsonPath);
        $errorItems = json_decode($jsonContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            var_dump("JSON 解析失败：" . json_last_error_msg());
            return '';
        }

        if (empty($errorItems) || !is_array($errorItems)) {
            var_dump("answer_analysis_errors.json 内容为空");
            return '';
        }

        $questionIds = array_values(array_unique(array_filter(array_column($errorItems, 'question_id'))));
        if (empty($questionIds)) {
            var_dump("question_id 列表为空");
            return '';
        }

        $questions = ReadingExamQuestion::find()
            ->select(['id', 'paper_id', 'number'])
            ->where(['id' => $questionIds])
            ->indexBy('id')
            ->asArray()
            ->all();

        $paperIds = array_unique(array_map(static function ($item) {
            return $item['paper_id'];
        }, $questions));

        $papers = [];
        if (!empty($paperIds)) {
            $papers = ReadingExamPaper::find()
                ->select(['id', 'complete_title'])
                ->where(['id' => $paperIds])
                ->indexBy('id')
                ->asArray()
                ->all();
        }

        $missingQuestions = array_diff($questionIds, array_keys($questions));
        if (!empty($missingQuestions)) {
            var_dump("未找到的题目ID：" . implode(',', $missingQuestions));
        }

        $missingPaperIds = array_diff($paperIds, array_keys($papers));
        if (!empty($missingPaperIds)) {
            var_dump("未找到的试卷ID：" . implode(',', $missingPaperIds));
        }

        $headers = [
            'complete_title',
            'paper_id',
            'number',
            'question_id',
            'standard_answer',
            'llm_correct_answer',
            'question_type',
        ];

        $rows = [];
        foreach ($errorItems as $item) {
            $questionId = $item['question_id'] ?? null;
            $questionInfo = $questionId && isset($questions[$questionId]) ? $questions[$questionId] : null;
            $paperInfo = $questionInfo && isset($papers[$questionInfo['paper_id']]) ? $papers[$questionInfo['paper_id']] : null;

            $rows[] = [
                $paperInfo['complete_title'] ?? '',
                $paperInfo['id'] ?? '',
                $questionInfo['number'] ?? '',
                $questionId ?? '',
                $item['standard_answer'] ?? '',
                $item['llm_correct_answer'] ?? '',
                $item['question_type'] ?? '',
            ];
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($headers, null, 'A1');

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        foreach (range('A', $sheet->getHighestColumn()) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $exportDir = $basePath . '/runtime/tmp/';
        if (!file_exists($exportDir)) {
            mkdir($exportDir, 0777, true);
        }

        $fileName = 'answer_analysis_errors_' . date('Ymd_His') . '.xlsx';
        $filePath = $exportDir . $fileName;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        var_dump("导出成功，文件路径：$filePath");
        return $filePath;
    }
}

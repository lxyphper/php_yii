<?php

/**
 * Created by PhpStorm.
 * User: 168
 * Date: 2017/10/23
 * Time: 14:00
 */

namespace console\controllers;

use app\models\ListeningExamContext;
use app\models\ListeningExamPaper;
use app\models\ListeningExamPaperQuery;
use app\models\ListeningExamPaperTopic;
use app\models\ListeningExamPaperType;
use app\models\ListeningExamPaperUnit;
use app\models\ListeningExamQuestion;
use app\models\ListeningExamQuestionGroup;
use app\models\ListeningExamQuestionOption;
use app\models\ListeningExamQuestionType;
use app\models\ListeningExamRecord;
use app\models\ReadingExamPaperTopic;
use app\models\ReadingExamQuestion;
use app\models\SimulateExamListening;
use app\models\SimulateExamRecord;
use Yii;
use yii\console\Controller;
use yii\log\Logger;
use yii\helpers\FileHelper;
use OSS\OssClient;
use OSS\Core\OssException;

/**
 * 听力题目数据处理
 * Class ListeningController
 * @package console\controllers
 */
class ListeningController extends Controller
{
    public int $paperId = 0;
    public int $dryRun = 1;
    public int $batchSize = 200;
    public int $startId = 0;
    public int $endId = 0;
    public int $limit = 0;
    public int $operatorId = 0;

    public function options($actionID)
    {
        $options = parent::options($actionID);
        if ($actionID === 'fix-question-time-from-lyc-index') {
            $options = array_merge($options, [
                'paperId',
                'dryRun',
                'batchSize',
                'startId',
                'endId',
                'limit',
                'operatorId',
            ]);
        }

        return $options;
    }

    //数据初始化
    //1 单选题，2 多选题，3 表格填空题，4 简答题，5 句子填空题，6 图片填空题，7 图片匹配题，8 匹配题
    public function actionInitJyData($url): void
    {
        $type = 1;
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }
        $desc_map = [];
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['_id'];
            var_dump($titleStr);
            $titleArr = explode('-', $titleStr);
            $subType = '剑雅' . $titleArr[0];
            $unit = 'Test ' . $titleArr[1];
            $title = 'Section ' . $titleArr[2];
            //获取类型
            $subTypeId = (new ListeningExamPaperType())->getByName($type, $subType);
            if (empty($subTypeId)) {
                var_dump("试卷：$titleStr,获取类型错误，参数[type:$type,subType:$subType]");
                Yii::getLogger()->log("获取类型错误，参数[type:$type,subType:$subType]", Logger::LEVEL_ERROR);
                continue;
            }
            //获取考试id
            $unitId = (new ListeningExamPaperUnit())->getByName($subTypeId, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]");
                Yii::getLogger()->log("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }

            //查询话题
            $topic_list = ListeningExamPaperTopic::find()->all();
            $topic_map = [];
            foreach ($topic_list as $topic_item) {
                $topic_map[$topic_item->name] = $topic_item->id;
            }

            $topic = explode(',', $value['topic']);
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            $paperInfo = ListeningExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ListeningExamPaper();
                $paper->title = $title;
                $paper->complete_title = $subType . ' ' . $unit . '-' . $title;
                $paper->content = $value['lyc'];
                $paper->unit = $unitId;
                $paper->topic = $curr_topic;
                $paper->file_url = '/exercises/listening/' . $titleStr . '.mp3';
                $paper->file_json_url = str_replace('mp3', 'json', $paper->file_url);
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $question_type = $questionType[$val['question_type']];
                // if ($question_type == 2 && substr_count($val['context'][0]->question_num, '-') == 0) {
                //     $question_type = 9;
                // }

                // $desc[$titleStr][] = $val['question_desc'];
                // continue;
                $groupDesc = substr($val['question_desc'], 0, 20);
                $groupDescArr = explode("\n", $val['question_desc']);
                $groupDesc = $groupDescArr[0];
                $groupQuery = ListeningExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $question_type]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ListeningExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $questionType[$val['question_type']];
                    $group->desc = $val['question_desc'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = $val['context'];
                //生成题目
                switch ($group->type) {
                    case 1: //单选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $answer = $va['answer'];
                            if (!isset($va['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $va['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $va['question_num'];
                                $questionObj->title = $va['title'];
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $va['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            foreach ($optionList as $o) {
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if ($optionOjb->title == $answer) {
                                    $questionObj->answer = [$optionOjb->id];
                                }
                            }
                            $questionObj->save(false);
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    //                     case 2://多选题
                    //                         //保存题目
                    //                         foreach ($questionList as $va) {
                    //                             $va = (array)$va;
                    //                             $group->title = $va['title'];
                    //                             $group->save();

                    //                             if (!isset($va['question_num'])) {
                    //                                 var_dump("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在");
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }

                    //                             $answer = array_column($va['answer'], 'name');
                    //                             $questionNum = $val['question_num_range'];
                    //                             $optionMap = [];

                    //                             //保存选项
                    //                             $optionList = $va['option'];
                    //                             foreach ($optionList as $o) {
                    //                             //    ListeningExamQuestionOption::deleteAll(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$o->name]);die;
                    //                                 $optionOjb = ListeningExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$o->name]);
                    //                                 if (empty($optionOjb)) {
                    //                                     $optionOjb = new ListeningExamQuestionOption();
                    //                                     $optionOjb->title = $o->name;
                    //                                     $optionOjb->content = $o->option;
                    //                                     $optionOjb->biz_type = 2;
                    //                                     $optionOjb->biz_id = $groupId;
                    //                                     $optionOjb->save();
                    //                                 }
                    //                                 $optionMap[$optionOjb->title] = $optionOjb->id;
                    //                             }
                    //                             if (count($questionNum)>1) {
                    //                                 ListeningExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                    //                             }
                    //                             foreach ($questionNum as $k => $num) {
                    //                                 //查询题目是否存在
                    //                                 $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$num]);
                    //                                 if (empty($questionObj)) {
                    //                                     $questionObj = new ListeningExamQuestion();
                    //                                     $questionObj->paper_id = $paperId;
                    //                                     $questionObj->group_id = $groupId;
                    //                                     $questionObj->number = $num;
                    //                                     $questionObj->answer = [$optionMap[trim($answer[$k])]];
                    //                                     try {
                    //                                         $questionObj->insert();
                    //                                     } catch (\Throwable $e) {
                    //                                         Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$num ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                         continue;
                    //                                     }
                    //                                 } else{
                    //                                     $questionObj->answer = [$optionMap[trim($answer[$k])]];
                    //                                     $questionObj->save();
                    //                                 }
                    //                             }

                    //                         }
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 3://表格题填空题
                    //                         //保存题目
                    //                         $collect = json_encode($val['table']);
                    //                         $groupTitle = $val['table_header'] ?? '';
                    //                         foreach ($questionList as $item) {
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 $questionObj->save();
                    //                                 var_dump("$questionObj->number 更新答案完成");
                    //                             }
                    //                             $questionId = $questionObj->id;
                    //                             $collect = str_replace('\u3010'. $item['question_num'] . '\u3011', '$' . $questionId . '$', $collect);
                    //                         }
                    //                         // $group->content = json_decode($collect);
                    //                         // $group->title = $groupTitle;
                    //                         // $group->save();
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 4://填空题
                    //                         //保存题目
                    //                         foreach ($questionList as $item) {
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 var_dump("paper_id:$paperId,number:" .$item['question_num']."不存在");
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 $questionObj->title = $item['title'];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 $questionObj->save();
                    //                                 var_dump("$questionObj->number 更新答案完成");
                    //                             }
                    //                         }

                    //                         // $group->save();
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 5://填空题
                    //                         //保存题目
                    //                         $collect = $val['title'];
                    //                         foreach ($questionList as $item) {
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 var_dump("paper_id:$paperId,number:" .$item['question_num']."不存在");
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 $questionObj->save();
                    //                                 var_dump("$questionObj->number 更新答案完成");
                    //                             }
                    //                             $questionId = $questionObj->id;
                    //                             $collect = str_replace('【'. $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                    //                             $group->content = ['collect'=>$collect];
                    //                         }

                    //                         // $group->save();
                    //                         // var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 6:
                    //                         $img_url = $val['img_url'];
                    //                         foreach ($questionList as $item) {
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->answer = $item['answer'];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else{
                    //                                 $questionObj->answer = $item['all_answer'] ?? [];
                    //                                 $questionObj->save();
                    //                                 var_dump("$questionObj->number 更新答案完成");
                    //                             }
                    //                         }

                    //                         // $group->img_url = $img_url;
                    //                         // $group->save();
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 7:
                    //                         $img_url = $val['img_url'];
                    //                         foreach ($questionList as $item) {
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->answer = [$item['answer']];
                    //                                 $questionObj->title = $item['title'] ?? '';
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else{
                    //                                 // $questionObj->number = $item['question_num'];
                    //                                 $questionObj->answer = [$item['answer']];
                    //                                 // $questionObj->title = $item['title'] ?? '';
                    //                                 $questionObj->save();
                    //                             }
                    //                         }

                    //                         // $group->img_url = $img_url;
                    //                         // $group->save();
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 8://匹配题
                    //                         //保存题目
                    //                         $opMap = [];
                    //                         //保存选项
                    //                         foreach ($val['option'] as $op) {
                    //                             $opObj = ListeningExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$op->name]);
                    //                             if (empty($opObj)) {
                    //                                 $opObj = new ListeningExamQuestionOption();
                    //                                 $opObj->biz_type = 2;
                    //                                 $opObj->biz_id = $groupId;
                    //                                 $opObj->title = $op->name;
                    //                                 $opObj->content = $op->option;
                    //                                 try {
                    //                                     $opObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             }
                    //                             $opMap[$opObj->title] = $opObj->id;
                    //                         }
                    //                         foreach ($questionList as $item) {
                    //                             //保存题目
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->title = $item['title'];
                    //                                 $questionObj->answer = [$opMap[$item['answer']]];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->answer = [$opMap[$item['answer']]];
                    //                                 // $questionObj->title = $item['title'] ?? '';
                    //                                 // $questionObj->display_answer = $item['display_answer'] ?? $item['answer'];
                    //                                 $questionObj->save();
                    //                             }
                    //                         }

                    //                         // $group->title = $val['title'] ?? '';
                    //                         // $group->save();
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 9://不定项选择题
                    //                         //保存题目
                    //                         foreach ($questionList as $va) {
                    //                             $va = (array)$va;
                    //                             $answer = array_column($va['answer'], 'answer');
                    //                             if (!isset($va['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$va['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $va['question_num'];
                    //                                 $questionObj->title = $va['title'];
                    //                                 $questionObj->answer_sentences = $answer_sentences[$va['question_num']] ?? '';
                    //                                 if (!empty($va['lyc_time'])) {
                    //                                     $lyc_time = (array)$va['lyc_time'];
                    //                                     $questionObj->start_time = $lyc_time['start_time'];
                    //                                     $questionObj->end_time = $lyc_time['end_time'];
                    //                                 }
                    //                                 if (!empty($va['answer_sentences'])) {
                    //                                     $questionObj->answer_sentences = $va['answer_sentences'];
                    //                                 }
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->title = $va['title'];
                    //                             }
                    //                             $questionId = $questionObj->id;
                    //                             //保存选项
                    //                             $optionList = $va['option'];
                    //                             $temp_answer = [];
                    //                             foreach ($optionList as $o) {
                    //                                 $optionOjb = ListeningExamQuestionOption::findOne(['biz_type'=>1, 'biz_id'=>$questionId, 'title'=>$o->name]);
                    //                                 if (empty($optionOjb)) {
                    //                                     $optionOjb = new ListeningExamQuestionOption();
                    //                                     $optionOjb->title = $o->name;
                    //                                     $optionOjb->content = $o->option;
                    //                                     $optionOjb->biz_type = 1;
                    //                                     $optionOjb->biz_id = $questionId;
                    //                                     $optionOjb->save();
                    //                                 }
                    //                                 if (in_array($optionOjb->title , $answer)) {
                    //                                     $temp_answer[] = $optionOjb->id;
                    //                                 }
                    //                             }
                    //                             $questionObj->answer = $temp_answer;
                    //                             $questionObj->save();

                    //                         }
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    //                     case 10://表格匹配题
                    //                         $collect = json_encode($val['table']);
                    //                         $groupTitle = $val['table_header'] ?? '';
                    //                         //保存题目
                    //                         $opMap = [];
                    //                         //保存选项
                    //                         foreach ($val['option'] as $op) {
                    //                             $opObj = ListeningExamQuestionOption::findOne(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$op->name]);
                    //                             if (empty($opObj)) {
                    //                                 $opObj = new ListeningExamQuestionOption();
                    //                                 $opObj->biz_type = 2;
                    //                                 $opObj->biz_id = $groupId;
                    //                                 $opObj->title = $op->name;
                    //                                 $opObj->content = $op->option;
                    //                                 try {
                    //                                     $opObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 ". $op->name ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             }
                    //                             $opMap[$opObj->title] = $opObj->id;
                    //                         }
                    //                         foreach ($questionList as $item) {
                    //                             //保存题目
                    //                             $item = (array)$item;
                    //                             if (!isset($item['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".json_encode($item) ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$item['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $item['question_num'];
                    //                                 $questionObj->title = $item['title'];
                    //                                 $questionObj->answer = [$opMap[$item['answer']]];
                    //                                 if (!empty($item['lyc_time'])) {
                    //                                     $lyc_time = (array)$item['lyc_time'];
                    //                                     $questionObj->start_time = $lyc_time['start_time'];
                    //                                     $questionObj->end_time = $lyc_time['end_time'];
                    //                                 }
                    //                                 if (!empty($item['answer_sentences'])) {
                    //                                     $questionObj->answer_sentences = $item['answer_sentences'];
                    //                                 }
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$item['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->title = $item['title'];
                    //                                 $questionObj->display_answer = $item['display_answer'] ?? '';
                    //                                 $questionObj->answer = [$opMap[$item['answer']]];
                    //                                 if (!empty($item['lyc_time'])) {
                    //                                     $lyc_time = (array)$item['lyc_time'];
                    //                                     $questionObj->start_time = $lyc_time['start_time'];
                    //                                     $questionObj->end_time = $lyc_time['end_time'];
                    //                                 }
                    //                                 if (!empty($item['answer_sentences'])) {
                    //                                     $questionObj->answer_sentences = $item['answer_sentences'];
                    //                                 }
                    //                                 $questionObj->save();
                    //                             }
                    //                             $collect = str_replace('\u3010'. $item['question_num'] . '\u3011', '$' . $questionObj->id . '$', $collect);
                    // //                            $questionObj->save();
                    //                         }

                    // //                        var_dump($collect);die;
                    //                         //处理表格数据
                    //                         // $group->title = $groupTitle;
                    //                         // $group->content = json_decode($collect);
                    //                         // $group->save(false);
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
        }
        // var_dump($desc);die;
    }

    public function actionInitJjData($url): void
    {
        $type = 2;
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            $title_arr = explode('-', $titleStr);
            $unit = $title_arr[0];
            $title = ucwords($title_arr[1]);

            //获取考试id
            $unitId = (new ListeningExamPaperUnit())->getByName($type, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[type:$type,unit:$unit]");
                Yii::getLogger()->log("获取考试id错误，参数[type:$type,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }

            $paperInfo = ListeningExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                //                $paperInfo->title = $title;
                //                $paperInfo->complete_title = '机经' . ' ' . $unit . '-' . $title;
                //                $paperInfo->unit = $unitId;
                //                $paperInfo->file_url = '/exercises/listening/' . str_replace(' ', '_', $value['audio']);
                //                $paperInfo->file_json_url = str_replace('mp3', 'json', $paperInfo->file_url);
                //                $paperInfo->content = $value['lyc'];
                $paperInfo->save();
                Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ListeningExamPaper();
                $paper->title = $title;
                $paper->complete_title = '机经' . ' ' . $unit . '-' . $title;
                $paper->unit = $unitId;
                $paper->file_url = '/exercises/listening/' . str_replace(' ', '_', $value['audio']);
                $paper->file_json_url = str_replace('mp3', 'json', $paper->file_url);
                $paper->content = $value['lyc'];
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $question_type = $questionType[$val['question_type']];
                if ($question_type == 2 && substr_count($val['context'][0]->question_num, '-') == 0) {
                    $question_type = 9;
                }

                $groupDesc = substr($val['question_desc'], 0, 20);
                $groupQuery = ListeningExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $question_type]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    //                    $group->desc = $val['question_desc'];
                    //                    $group->save();
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    var_dump("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    //生成题目分组
                    $group = new ListeningExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $question_type;
                    $group->desc = $val['question_desc'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = $val['context'];
                if ($group->type != 1) {
                    continue;
                }
                //生成题目
                switch ($group->type) {
                    case 1: //单选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $answer = $va['answer'];
                            if (!isset($va['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $va['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $va['question_num'];
                                $questionObj->title = $va['title'];
                                if (!empty($va['lyc_time'])) {
                                    $lyc_time = (array)$va['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $va['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $va['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            foreach ($optionList as $o) {
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if ($optionOjb->title == $answer) {
                                    $questionObj->answer = [$optionOjb->id];
                                }
                            }
                            $questionObj->save();
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 2: //多选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $group->title = $va['title'];
                            $group->save();

                            if (!isset($va['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }

                            $answer = array_column($va['answer'], 'answer');
                            $questionNum = explode('-', $va['question_num']);
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
                                //                                ListeningExamQuestionOption::deleteAll(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$o->name]);
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 2;
                                    $optionOjb->biz_id = $groupId;
                                    $optionOjb->save();
                                }
                                $optionMap[$optionOjb->title] = $optionOjb->id;
                            }
                            //                            if ($questionNum[1] - $questionNum[0] > 1) {
                            //                                ListeningExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                            //                            }
                            foreach ($questionNum as $k => $num) {
                                //查询题目是否存在
                                $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                if (empty($questionObj)) {
                                    $questionObj = new ListeningExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $num;
                                    $questionObj->answer = [$optionMap[trim($answer[$k])]];
                                    if (!empty($va['lyc_time'])) {
                                        $lyc_time = (array)$va['lyc_time'];
                                        $questionObj->start_time = $lyc_time['start_time'];
                                        $questionObj->end_time = $lyc_time['end_time'];
                                    }
                                    if (!empty($item['answer_sentences'])) {
                                        $questionObj->answer_sentences = $va['answer_sentences'];
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
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
                    case 3: //表格题填空题
                        //保存题目
                        $collect = json_encode($val['table']);
                        $groupTitle = $val['table_header'] ?? '';
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            //                                else {
                            //                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                            //                                $questionObj->save();
                            //                            }
                            $questionId = $questionObj->id;
                            $collect = str_replace('\u3010' . $item['question_num'] . '\u3011', '$' . $questionId . '$', $collect);
                        }
                        $group->content = json_decode($collect);
                        //                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 4: //简答题
                        //保存题目
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                var_dump("paper_id:$paperId,number:" . $item['question_num'] . "不存在");
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                $questionObj->title = $item['title'];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            //                            else {
                            //                                var_dump("paper_id:$paperId,number:" .$item['question_num']."存在");
                            //                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                            //                                var_dump("title:$titleStr,id:$questionObj->id,answer:");
                            //                                var_dump($questionObj->answer);
                            //                                $questionObj->save();
                            //                            }
                        }

                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 5: //句子填空题
                        //保存题目
                        $collect = $val['title'];
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                var_dump("paper_id:$paperId,number:" . $item['question_num'] . "不存在");
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                $questionObj->answer_sentences = $answer_sentences[$item['question_num']] ?? '';
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                    var_dump("paper_id:$paperId,number:" . $item['question_num'] . "插入成功");
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                var_dump("paper_id:$paperId,number:" . $item['question_num'] . "存在");
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                var_dump("title:$titleStr,id:$questionObj->id,answer:");
                                var_dump($questionObj->answer);
                                $questionObj->save();
                            }
                            $questionId = $questionObj->id;
                            $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                        }

                        $group->content = ['collect' => $collect];
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 6:
                        if (!empty($val['image_url'])) {
                            $img_url = '/exercises/imgs/paper/listening/' . $val['image_url'];
                        } else {
                            $img_url = '';
                        }

                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                $questionObj->title = str_replace("【    】", "", $item['title']);
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                $questionObj->title = str_replace("【    】", "", $item['title']);
                                $questionObj->save();
                            }
                        }

                        $group->img_url = $img_url;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 7: //图片匹配题
                        $img_url = '/exercises/imgs/paper/listening/' . $val['img_url'][0];
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = [$item['answer'], strtolower($item['answer'])];
                                $questionObj->title = $item['title'];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->answer = [$item['answer'], strtolower($item['answer'])];
                                $questionObj->save();
                            }
                        }

                        $group->img_url = $img_url;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 8: //匹配题
                        //保存题目
                        $opMap = [];
                        //保存选项
                        foreach ($val['option'] as $op) {
                            $opObj = ListeningExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                            if (empty($opObj)) {
                                $opObj = new ListeningExamQuestionOption();
                                $opObj->biz_type = 2;
                                $opObj->biz_id = $groupId;
                                $opObj->title = $op->name;
                                $opObj->content = $op->option;
                                try {
                                    $opObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            $opMap[$opObj->title] = $opObj->id;
                        }
                        foreach ($questionList as $item) {
                            //保存题目
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->title = $item['title'];
                                $questionObj->answer = [$opMap[$item['answer']]];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $item['title'];
                            }
                            $questionObj->save();
                        }

                        $group->title = $val['title'] ?? '';
                        $group->question_title = $val['context_title'] ?? '';
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 9: //不定项选择题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $answer = array_column($va['answer'], 'answer');
                            if (!isset($va['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $va['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $va['question_num'];
                                $questionObj->title = $va['title'];
                                $questionObj->answer_sentences = $answer_sentences[$va['question_num']] ?? '';
                                if (!empty($va['lyc_time'])) {
                                    $lyc_time = (array)$va['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($va['answer_sentences'])) {
                                    $questionObj->answer_sentences = $va['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $va['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            $temp_answer = [];
                            foreach ($optionList as $o) {
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if (in_array($optionOjb->title, $answer)) {
                                    $temp_answer[] = $optionOjb->id;
                                }
                            }
                            $questionObj->answer = $temp_answer;
                            $questionObj->save();
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 10: //表格匹配题
                        $collect = json_encode($val['table']);
                        $groupTitle = $val['table_header'] ?? '';
                        //保存题目
                        $opMap = [];
                        //保存选项
                        foreach ($val['option'] as $op) {
                            $opObj = ListeningExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                            if (empty($opObj)) {
                                $opObj = new ListeningExamQuestionOption();
                                $opObj->biz_type = 2;
                                $opObj->biz_id = $groupId;
                                $opObj->title = $op->name;
                                $opObj->content = $op->option;
                                try {
                                    $opObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            $opMap[$opObj->title] = $opObj->id;
                        }
                        foreach ($questionList as $item) {
                            //保存题目
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->title = $item['title'];
                                $questionObj->answer = [$opMap[$item['answer']]];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $item['title'];
                            }
                            $collect = str_replace('\u3010' . $item['question_num'] . '\u3011', '$' . $questionObj->id . '$', $collect);
                            //                            $questionObj->save();
                        }

                        //                        var_dump($collect);die;
                        //处理表格数据
                        //                        $group->title = $groupTitle;
                        $group->content = json_decode($collect);
                        $group->save(false);
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
        }
    }

    public function actionInitYcData($url): void
    {
        $type = 2;
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ListeningExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            $title = $value['header'];

            $topic = explode(',', $value['topic']);
            $curr_topic = [];
            foreach ($topic as $v) {
                if (isset($topic_map[$v])) {
                    $curr_topic[] = $topic_map[$v];
                }
            }

            //获取考试id
            $unitId = 276;
            $paperInfo = ListeningExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                //                $paperInfo->title = $title;
                //                $paperInfo->complete_title = '机经' . ' ' . $unit . '-' . $title;
                //                $paperInfo->unit = $unitId;
                //                $paperInfo->file_url = '/exercises/listening/' . str_replace(' ', '_', $value['audio']);
                //                $paperInfo->file_json_url = str_replace('mp3', 'json', $paperInfo->file_url);
                //                $paperInfo->content = $value['lyc'];
                //                $paperInfo->save();
                Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ListeningExamPaper();
                $paper->title = $title;
                $paper->complete_title = $title;
                $paper->unit = $unitId;
                $paper->file_url = '/exercises/listening/' . $value['listening_id'] . '.mp3';
                $paper->file_json_url = '/exercises/listening/' . $value['listening_id'] . '.json';
                $paper->content = $value['lyc'];
                $paper->topic = $curr_topic;
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $question_type = $questionType[$val['question_type']];
                if ($question_type == 2 && substr_count($val['context'][0]->question_num, '-') == 0) {
                    $question_type = 9;
                }

                $groupDesc = substr($val['question_desc'], 0, 20);
                $groupQuery = ListeningExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $question_type]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    //                    $group->desc = $val['question_desc'];
                    //                    $group->save();
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    var_dump("该分组不存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    //生成题目分组
                    $group = new ListeningExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $question_type;
                    $group->desc = $val['question_desc'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = $val['context'];
                //生成题目
                switch ($group->type) {
                    case 1: //单选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $answer = $va['answer'];
                            if (!isset($va['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $va['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $va['question_num'];
                                $questionObj->title = $va['title'];
                                $questionObj->analyze = $va['analyze'] ?? '';
                                $questionObj->display_answer = $va['display_answer'] ?? '';
                                $questionObj->locating_words = $va['locating_words'] ?? [];
                                $questionObj->key_locating_words = $va['locating_words'] ?? [];
                                $questionObj->ai_data = $va['ai_data'] ?? [];
                                if (!empty($va['lyc_time'])) {
                                    $lyc_time = (array)$va['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $va['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $va['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            foreach ($optionList as $o) {
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if ($optionOjb->title == $answer) {
                                    $questionObj->answer = [$optionOjb->id];
                                }
                            }
                            $questionObj->save();
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 2: //多选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $group->title = $va['title'];
                            $group->save();

                            if (!isset($va['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }

                            $answer = array_column($va['answer'], 'name');
                            $questionNum = explode('-', $va['question_num']);
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
                                //                                ListeningExamQuestionOption::deleteAll(['biz_type'=>2, 'biz_id'=>$groupId, 'title'=>$o->name]);
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 2;
                                    $optionOjb->biz_id = $groupId;
                                    $optionOjb->save();
                                }
                                $optionMap[$optionOjb->title] = $optionOjb->id;
                            }
                            //                            if ($questionNum[1] - $questionNum[0] > 1) {
                            //                                ListeningExamQuestion::deleteAll(['paper_id'=>$paperId,'group_id'=>$groupId]);
                            //                            }
                            foreach ($questionNum as $k => $num) {
                                //查询题目是否存在
                                $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                if (empty($questionObj)) {
                                    $questionObj = new ListeningExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $num;
                                    $questionObj->answer = [$optionMap[trim($answer[$k])]];
                                    $questionObj->analyze = $va['analyze'] ?? '';
                                    $questionObj->display_answer = $va['display_answer'] ?? '';
                                    $questionObj->locating_words = $va['locating_words'] ?? [];
                                    $questionObj->key_locating_words = $va['locating_words'] ?? [];
                                    $questionObj->ai_data = $va['ai_data'] ?? [];
                                    if (!empty($va['lyc_time'])) {
                                        $lyc_time = (array)$va['lyc_time'];
                                        $questionObj->start_time = $lyc_time['start_time'];
                                        $questionObj->end_time = $lyc_time['end_time'];
                                    }
                                    if (!empty($item['answer_sentences'])) {
                                        $questionObj->answer_sentences = $va['answer_sentences'];
                                    }
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
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
                    case 3: //表格题填空题
                        //保存题目
                        $collect = json_encode($val['table']);
                        $groupTitle = $val['table_header'] ?? '';
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->dealWithAnswer($item['all_answer']);
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            //                                else {
                            //                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                            //                                $questionObj->save();
                            //                            }
                            $questionId = $questionObj->id;
                            $collect = str_replace('\u3010' . $item['question_num'] . '\u3011', '$' . $questionId . '$', $collect);
                        }
                        $group->content = json_decode($collect);
                        //                        $group->title = $groupTitle;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 4: //简答题
                        //保存题目
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                var_dump("paper_id:$paperId,number:" . $item['question_num'] . "不存在");
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->dealWithAnswer($item['all_answer']);
                                $questionObj->title = $item['title'];
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            //                            else {
                            //                                var_dump("paper_id:$paperId,number:" .$item['question_num']."存在");
                            //                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                            //                                var_dump("title:$titleStr,id:$questionObj->id,answer:");
                            //                                var_dump($questionObj->answer);
                            //                                $questionObj->save();
                            //                            }
                        }

                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 5: //句子填空题
                        //保存题目
                        $collect = $val['title'];
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                var_dump("paper_id:$paperId,number:" . $item['question_num'] . "不存在");
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->dealWithAnswer($item['all_answer']);
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                //                                $questionObj->answer_sentences = $answer_sentences[$item['question_num']] ?? '';
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                    var_dump("paper_id:$paperId,number:" . $item['question_num'] . "插入成功");
                                } catch (\Throwable $e) {
                                    var_dump("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage());
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                var_dump("paper_id:$paperId,number:" . $item['question_num'] . "存在");
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                var_dump("title:$titleStr,id:$questionObj->id,answer:");
                                var_dump($questionObj->answer);
                                $questionObj->save();
                            }
                            $questionId = $questionObj->id;
                            $collect = str_replace('【' . $item['question_num'] . '】', '$' . $questionId . '$', $collect);
                        }

                        $group->content = ['collect' => $collect];
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 6:
                        if (!empty($val['image_url'])) {
                            $img_url = '/exercises/imgs/paper/listening/' . $val['image_url'];
                        } else {
                            $img_url = '';
                        }

                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                $questionObj->title = str_replace("【    】", "", $item['title']);
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->answer = $this->getAnswerArr($item['answer']);
                                $questionObj->title = str_replace("【    】", "", $item['title']);
                                $questionObj->save();
                            }
                        }

                        $group->img_url = $img_url;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 7: //图片匹配题
                        $img_url_arr = explode('/', $val['img_url']);
                        $img_url = '/exercises/imgs/paper/listening/' . $img_url_arr[count($img_url_arr) - 1];
                        foreach ($questionList as $item) {
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->answer = [$item['answer'], strtolower($item['answer'])];
                                $questionObj->title = $item['title'] ?? '';
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->answer = [$item['answer'], strtolower($item['answer'])];
                                $questionObj->save();
                            }
                        }

                        $group->img_url = $img_url;
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 8: //匹配题
                        //保存题目
                        $opMap = [];
                        //保存选项
                        foreach ($val['option'] as $op) {
                            $opObj = ListeningExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                            if (empty($opObj)) {
                                $opObj = new ListeningExamQuestionOption();
                                $opObj->biz_type = 2;
                                $opObj->biz_id = $groupId;
                                $opObj->title = $op->name;
                                $opObj->content = $op->option;
                                try {
                                    $opObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            $opMap[$opObj->title] = $opObj->id;
                        }
                        foreach ($questionList as $item) {
                            //保存题目
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->title = $item['title'];
                                $questionObj->answer = [$opMap[$item['answer']]];
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $item['title'];
                            }
                            $questionObj->save();
                        }

                        $group->title = $val['title'] ?? '';
                        $group->question_title = $val['context_title'] ?? '';
                        $group->save();
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 9: //不定项选择题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            $answer = array_column($va['answer'], 'answer');
                            if (!isset($va['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $va['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $va['question_num'];
                                $questionObj->title = $va['title'];
                                //                                $questionObj->answer_sentences = $answer_sentences[$va['question_num']] ?? '';
                                $questionObj->analyze = $va['analyze'] ?? '';
                                $questionObj->display_answer = $va['display_answer'] ?? '';
                                $questionObj->locating_words = $va['locating_words'] ?? [];
                                $questionObj->key_locating_words = $va['locating_words'] ?? [];
                                $questionObj->ai_data = $va['ai_data'] ?? [];
                                if (!empty($va['lyc_time'])) {
                                    $lyc_time = (array)$va['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($va['answer_sentences'])) {
                                    $questionObj->answer_sentences = $va['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $va['title'];
                            }
                            $questionId = $questionObj->id;
                            //保存选项
                            $optionList = $va['option'];
                            $temp_answer = [];
                            foreach ($optionList as $o) {
                                $optionOjb = ListeningExamQuestionOption::findOne(['biz_type' => 1, 'biz_id' => $questionId, 'title' => $o->name]);
                                if (empty($optionOjb)) {
                                    $optionOjb = new ListeningExamQuestionOption();
                                    $optionOjb->title = $o->name;
                                    $optionOjb->content = $o->option;
                                    $optionOjb->biz_type = 1;
                                    $optionOjb->biz_id = $questionId;
                                    $optionOjb->save();
                                }
                                if (in_array($optionOjb->title, $answer)) {
                                    $temp_answer[] = $optionOjb->id;
                                }
                            }
                            $questionObj->answer = $temp_answer;
                            $questionObj->save();
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    case 10: //表格匹配题
                        $collect = json_encode($val['table']);
                        $groupTitle = $val['table_header'] ?? '';
                        //保存题目
                        $opMap = [];
                        //保存选项
                        foreach ($val['option'] as $op) {
                            $opObj = ListeningExamQuestionOption::findOne(['biz_type' => 2, 'biz_id' => $groupId, 'title' => $op->name]);
                            if (empty($opObj)) {
                                $opObj = new ListeningExamQuestionOption();
                                $opObj->biz_type = 2;
                                $opObj->biz_id = $groupId;
                                $opObj->title = $op->name;
                                $opObj->content = $op->option;
                                try {
                                    $opObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目选项【 " . $op->name . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            }
                            $opMap[$opObj->title] = $opObj->id;
                        }
                        foreach ($questionList as $item) {
                            //保存题目
                            $item = (array)$item;
                            if (!isset($item['question_num'])) {
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . json_encode($item) . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }
                            //查询题目是否存在
                            $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $item['question_num']]);
                            if (empty($questionObj)) {
                                $questionObj = new ListeningExamQuestion();
                                $questionObj->paper_id = $paperId;
                                $questionObj->group_id = $groupId;
                                $questionObj->number = $item['question_num'];
                                $questionObj->title = $item['title'];
                                $questionObj->answer = [$opMap[$item['answer']]];
                                $questionObj->analyze = $item['analyze'] ?? '';
                                $questionObj->display_answer = $item['display_answer'] ?? '';
                                $questionObj->locating_words = $item['locating_words'] ?? [];
                                $questionObj->key_locating_words = $item['locating_words'] ?? [];
                                $questionObj->ai_data = $item['ai_data'] ?? [];
                                if (!empty($item['lyc_time'])) {
                                    $lyc_time = (array)$item['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                }
                                if (!empty($item['answer_sentences'])) {
                                    $questionObj->answer_sentences = $item['answer_sentences'];
                                }
                                try {
                                    $questionObj->insert();
                                } catch (\Throwable $e) {
                                    Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $item['question_num'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                    continue;
                                }
                            } else {
                                $questionObj->title = $item['title'];
                            }
                            $collect = str_replace('\u3010' . $item['question_num'] . '\u3011', '$' . $questionObj->id . '$', $collect);
                            //                            $questionObj->save();
                        }

                        //                        var_dump($collect);die;
                        //处理表格数据
                        //                        $group->title = $groupTitle;
                        $group->content = json_decode($collect);
                        $group->save(false);
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
        }
    }

    //恢复剑雅数据
    public function actionRestoreJyData(): void
    {
        ini_set('memory_limit', '1G');
        $data = [];
        $type_list = array_flip($this->getGroupType());
        //        $list = ListeningExamPaper::find()
        //            ->where(['id'=>5])
        //            ->where(['<=', 'id', 224])
        //            ->orWhere(['>=', 'id', 2129])
        //            ->all();
        $id = 0;
        $num = 1;
        while ($list = ListeningExamPaper::find()->where(['>', 'id', $id])->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                $item = [];
                $item['header'] = $value->complete_title;
                $item['_id'] = str_replace('Section ', '', str_replace(' Test ', '-', str_replace('', '', $item['header'])));
                $item['listening_id'] = $item['_id'];
                $item['audio'] = $value->file_url;
                $item['lyc'] = $value->content;
                $item['questions'] = [];
                $group_list = ListeningExamQuestionGroup::find()->where(['paper_id' => $value->id])->all();
                foreach ($group_list as $val) {
                    $group_item = [];
                    $group_item['question_desc'] = $val->desc;
                    $group_item['title'] = $val->title;
                    if (!isset($type_list[$val->type])) {
                        continue 2;
                    }
                    if ($val->type == 9) {
                        $group_item['question_type'] = '不定项选择题';
                    } else {
                        $group_item['question_type'] = $type_list[$val->type] ?? '';
                    }
                    $group_item['option'] = [];
                    $question_list = ListeningExamQuestion::find()->where(['group_id' => $val->id])->all();
                    switch ($val->type) {
                        case 1:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['title'] = $v->title;
                                $question_item['answer'] = $v->display_answer;
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['option'] = [];
                                //获取选项
                                $optionList = ListeningExamQuestionOption::find()->where(['biz_id' => $v->id, 'biz_type' => 1])->all();
                                foreach ($optionList as $o) {
                                    $question_item['option'][] = [
                                        'name' => $o->title,
                                        'option' => $o->content
                                    ];
                                }
                                $group_item['question_num_range'][] = $v->number;
                                $group_item['context'][] = $question_item;
                            }
                            break;
                        case 2:
                            $question_item = [];
                            $question_item['question_num'] = $question_list[0]->number . '-' . $question_list[array_key_last($question_list)]->number;
                            $optionList = ListeningExamQuestionOption::find()->where(['biz_id' => $val->id, 'biz_type' => 2])->all();
                            $question_item['answer'] = [];
                            $question_item['display_answer'] = $question_list[0]->display_answer;
                            foreach ($optionList as $o) {
                                $question_item['option'][] = [
                                    'name' => $o->title,
                                    'option' => $o->content
                                ];
                            }
                            foreach ($question_list as $v) {
                                $group_item['question_num_range'][] = $v->number;
                                foreach ($optionList as $vo) {
                                    if ($v->answer == $vo->id) {
                                        $question_item['answer'][] = [
                                            'name' => $vo->title,
                                            'option' => $vo->content
                                        ];
                                    }
                                }
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                            }
                            $group_item['context'][] = $question_item;
                            break;
                        case 3:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['answer'] = $v->display_answer;
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['title'] = $v->title;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $group_item['context'][] = $question_item;
                                $group_item['question_num_range'][] = $v->number;
                            }
                            $group_item['table'] = $val->content;
                            break;
                        case 4:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['answer'] = $v->answer;
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['title'] = $v->title;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $group_item['context'][] = $question_item;
                                $group_item['question_num_range'][] = $v->number;
                            }
                            break;
                        case 5:
                            $group_item['title'] = $val->content['collect'];
                            $group_item['question_type'] = $type_list[$val->type];
                            $group_item['question_num_range'] = [];
                            $group_item['context'] = [];
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['title'] = $v->title;
                                $question_item['answer'] = $v->answer;
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $group_item['question_num_range'][] = $v->number;
                                $group_item['context'][] = $question_item;
                            }
                            break;
                        case 6:
                        case 7:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['title'] = $v->title;
                                $question_item['question_num'] = $v->number;
                                $question_item['answer'] = $v->display_answer;
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $group_item['context'][] = $question_item;
                                $group_item['question_num_range'][] = $v->number;
                            }
                            $group_item['img_url'] = $val->img_url;
                            break;
                        case 8:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['answer'] = $v->display_answer;
                                $question_item['title'] = $v->title;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $group_item['context'][] = $question_item;
                                $group_item['question_num_range'][] = $v->number;
                            }
                            break;
                        case 9:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['answer'] = explode(',', $v->display_answer);
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['title'] = $v->title;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $question_item['option'] = [];
                                //获取选项
                                $optionList = ListeningExamQuestionOption::find()->where(['biz_id' => $v->id, 'biz_type' => 1])->all();
                                foreach ($optionList as $o) {
                                    $question_item['option'][] = [
                                        'name' => $o->title,
                                        'option' => $o->content
                                    ];
                                }
                                $group_item['context'][] = $question_item;
                                $group_item['question_num_range'][] = $v->number;
                            }
                            break;
                        case 10:
                            foreach ($question_list as $v) {
                                $question_item = [];
                                $question_item['question_num'] = $v->number;
                                $question_item['answer'] = $v->display_answer;
                                $question_item['display_answer'] = $v->display_answer;
                                $question_item['title'] = $v->title;
                                $question_item['answer_sentences'] = $v->answer_sentences;
                                $question_item['locating_words'] = $v->locating_words;
                                $question_item['lyc_time'] = [
                                    'start_time' => $v->start_time,
                                    'end_time' => $v->end_time
                                ];
                                $question_item['analyze'] = $v->analyze;
                                $question_item['ai_data'] = $v->ai_data;
                                $question_item['file_url'] = $v->file_url;
                                $group_item['context'][] = $question_item;
                                $group_item['question_num_range'][] = $v->number;
                            }
                            //获取选项
                            $optionList = ListeningExamQuestionOption::find()->where(['biz_id' => $val->id, 'biz_type' => 2])->all();
                            foreach ($optionList as $o) {
                                $group_item['option'][] = [
                                    'name' => $o->title,
                                    'option' => $o->content
                                ];
                            }
                            break;
                    }
                    $item['questions'][] = $group_item;
                }
                $data[] = $item;
                var_dump("导出 $value->id 完成");
            }
            //        var_dump(json_encode($data,JSON_UNESCAPED_UNICODE));die;
            $local_path = dirname(__FILE__, 2);
            $file = $local_path . '/runtime/tmp/listening_all_list.json';
            file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE));
            var_dump("$num 导出完成");
        }

        var_dump("全部导出完成");
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
            Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }

        //查询话题
        $topic_list = ListeningExamPaperTopic::find()->all();
        $topic_map = [];
        foreach ($topic_list as $topic_item) {
            $topic_map[$topic_item->name] = $topic_item->id;
        }

        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['_id'];
            var_dump($titleStr);
            $titleArr = explode('-', $titleStr);
            $subType = '剑雅' . $titleArr[0];
            $unit = 'Test ' . $titleArr[1];
            $title = 'Section ' . $titleArr[2];
            //获取类型
            $subTypeId = (new ListeningExamPaperType())->getByName($type, $subType);
            if (empty($subTypeId)) {
                var_dump("试卷：$titleStr,获取类型错误，参数[type:$type,subType:$subType]");
                Yii::getLogger()->log("获取类型错误，参数[type:$type,subType:$subType]", Logger::LEVEL_ERROR);
                continue;
            }
            //获取考试id
            $unitId = (new ListeningExamPaperUnit())->getByName($subTypeId, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]");
                Yii::getLogger()->log("获取考试id错误，参数[subTypeId:$subTypeId,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }

            $paperInfo = ListeningExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                $paperInfo->content = $value['lyc'];
                $paperInfo->save();
                Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                //                $paper = new ListeningExamPaper();
                //                $paper->title = $title;
                //                $paper->complete_title = $subType . ' ' . $unit . '-' . $title;
                //                $paper->unit = $unitId;
                //                try {
                //                    $paper->insert();
                //                } catch (\Throwable $e) {
                //                    Yii::getLogger()->log("生成试卷失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                //                    continue;
                //                }
                //
                //                $paperId = $paper->id;
            }

            //            $topic = explode(',',$value['topic']);
            //            $curr_topic = [];
            //            foreach ($topic as $v) {
            //                if (isset($topic_map[$v])) {
            //                    $curr_topic[] = $topic_map[$v];
            //                }
            //            }

            //            $paperInfo->file_url = $value['audio'];
            //            $paperInfo->content = $value['lyc'];
            //            $paperInfo->topic = $curr_topic;
            //            $paperInfo->save();

            $paperId = $paperInfo->id;

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $question_type = $questionType[$val['question_type']];
                // if ($question_type == 2 && substr_count($val['context'][0]->question_num, '-') == 0) {
                //     $question_type = 9;
                // }

                $descArr = explode("\n", $val['question_desc']);
                $groupDesc = $descArr[0];
                $groupQuery = ListeningExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $question_type]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    continue;
                    //生成题目分组
                    //                    $group = new ListeningExamQuestionGroup();
                    //                    $group->paper_id = $paperId;
                    //                    $group->type = $question_type;
                    //                    $group->desc = $val['question_desc'];
                    //                    try {
                    //                        $group->insert();
                    //                    } catch (\Throwable $e) {
                    //                        Yii::getLogger()->log("题目【 $titleStr 】分组【 ".$val['question_type'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                        continue;
                    //                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = $val['context'];
                //生成题目，填空题：3-7
                switch ($group->type) {
                    //                     case 1:
                    //                     case 3:
                    //                     case 4:
                    //                     case 5:
                    //                     case 6:
                    //                     case 7:
                    //                     case 8:
                    //                     case 9:
                    //                     case 10:
                    //                         //保存题目
                    //                         foreach ($questionList as $va) {
                    //                             $va = (array)$va;
                    //                             if (!isset($va['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }

                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$va['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 var_dump("题目不存在");
                    //                                 continue;
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $va['question_num'];
                    //                                 $questionObj->title = $va['title'];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    // //                                $questionObj->title = $va['title'];
                    //                                 $questionObj->locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                    //                                 $questionObj->key_locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                    // //                                $questionObj->analyze_print =  empty($va['analyze_print_ary']) ? [] : $va['analyze_print_ary'];
                    //                                 if (!empty($va['lyc_time'])) {
                    //                                     $lyc_time = (array)$va['lyc_time'];
                    //                                     $questionObj->start_time = $lyc_time['start_time'];
                    //                                     $questionObj->end_time = $lyc_time['end_time'];
                    //                                 }
                    //                                $questionObj->answer_sentences = $va['answer_sentences'] ?? [];
                    //                                $questionObj->display_answer = empty($va['display_answer']) ? '' : $va['display_answer'];
                    //                                $questionObj->analyze = empty($va['analysis']) ? '' : $va['analysis'];
                    //                                $questionObj->ai_data = empty($va['ai_data']) ? '' : $va['ai_data'];
                    // //                                $questionObj->title = $va['title'];
                    // //                                if (!is_array($va['answer'])) {
                    // //                                    $questionObj->answer = $this->dealWithAnswer(explode('|', $va['answer']));
                    // //                                } else{
                    // //                                    $questionObj->answer = $this->dealWithAnswer($va['answer']);
                    // //                                }

                    // //                                $questionObj->answer =  [$va['answer'], strtolower($va['answer'])];
                    // //                                $questionObj->display_answer = $va['answer'];

                    //                                $questionObj->answer = isset($va['all_answer']) ? $this->dealWithAnswer($va['all_answer']) : [];

                    // //                                $questionObj->display_answer = empty($questionObj->answer) ? '' : implode(" / ", $questionObj->answer);
                    //                                 $questionObj->save(false);
                    //                             }


                    //                             //保存问题内容信息
                    //                             $question_context = ListeningExamContext::findOne(['biz_id'=>$questionObj->id, 'biz_type'=>2]);
                    //                             if (empty($question_context)) {
                    //                                 $contextObj = new ListeningExamContext();
                    //                                 $contextObj->content = $va;
                    //                                 $contextObj->biz_id = $questionObj->id;
                    //                                 $contextObj->biz_type = 2;
                    //                                 try {
                    //                                     $contextObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                    //                                     Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]",Logger::LEVEL_ERROR);
                    //                                 }
                    //                             } else {
                    //                                 $question_context->content = $va;
                    //                                 $question_context->save();
                    //                             }

                    //                         }
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    case 2: //多选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            //                            $group->title = $va['title'];
                            //                            $group->save();

                            if (!isset($val['question_num_range'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }

                            $answer = array_column($va['answer'], 'answer');
                            $questionNum = $val['question_num_range'];
                            $optionMap = [];

                            foreach ($questionNum as $k => $num) {
                                //查询题目是否存在
                                $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                if (empty($questionObj)) {
                                    //                                    $questionObj = new ListeningExamQuestion();
                                    //                                    $questionObj->paper_id = $paperId;
                                    //                                    $questionObj->group_id = $groupId;
                                    //                                    $questionObj->number = $num;
                                    //                                    try {
                                    //                                        $questionObj->insert();
                                    //                                    } catch (\Throwable $e) {
                                    //                                        Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$num ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                                    //                                        continue;
                                    //                                    }
                                } else {
                                    $questionObj->locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                                    $questionObj->key_locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                                    //                                    $questionObj->analyze_print =  empty($va['analyze_print_ary']) ? [] : $va['analyze_print_ary'];
                                    if (!empty($va['lyc_time'])) {
                                        $lyc_time = (array)$va['lyc_time'];
                                        $questionObj->start_time = $lyc_time['start_time'];
                                        $questionObj->end_time = $lyc_time['end_time'];
                                    }
                                    $questionObj->answer_sentences = $va['answer_sentences'] ?? [];
                                    $questionObj->display_answer = empty($va['display_answer']) ? '' : $va['display_answer'];
                                    $questionObj->analyze = empty($va['analysis']) ? '' : $va['analysis'];
                                    $questionObj->ai_data = empty($va['ai_data']) ? '' : $va['ai_data'];
                                    $questionObj->save(false);
                                }

                                //保存问题内容信息
                                $question_context = ListeningExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                if (empty($question_context)) {
                                    $contextObj = new ListeningExamContext();
                                    $contextObj->content = $va;
                                    $contextObj->biz_id = $questionObj->id;
                                    $contextObj->biz_type = 2;
                                    try {
                                        $contextObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                        Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                    }
                                } else {
                                    $question_context->content = $va;
                                    $question_context->save();
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }

            var_dump("$titleStr 初始化完成");
        }
    }

    public function actionDealWithContent()
    {
        $local_path = dirname(__FILE__, 2);
        $question_file = $local_path . '/runtime/tmp/更新音频content数据.json';
        $question_content = file_get_contents($question_file);
        $question_content = json_decode($question_content);
        foreach ($question_content as $key => $val) {
            $val = (array)$val;
            $paper = ListeningExamPaper::find()->where(['complete_title' => $val['complete_title']])->one();
            if (empty($paper)) {
                var_dump("试卷不存在，complete_title:" . $val['complete_title']);
                continue;
            }
            $paper->content = $val['content'];
            $paper->save();
            var_dump("试卷：" . $val['complete_title'] . "，content更新完成");
        }
    }

    public function actionFixJjData($url)
    {
        $type = 2;
        $question_url = "/exercises/imgs/paper/listening/";
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            $titleArr = explode('-', $titleStr);
            $unit = $titleArr[0];
            $title = ucwords($titleArr[1]);

            //获取考试id
            $unitId = (new ListeningExamPaperUnit())->getByName($type, $unit);
            if (empty($unitId)) {
                var_dump("获取考试id错误，参数[type:$type,unit:$unit]");
                Yii::getLogger()->log("获取考试id错误，参数[type:$type,unit:$unit]", Logger::LEVEL_ERROR);
                continue;
            }

            $topic_list = ListeningExamPaperTopic::find()->all();
            $topic_map = [];
            foreach ($topic_list as $topic_item) {
                $topic_map[$topic_item->name] = $topic_item->id;
            }

            //            $topic = explode(',',$value['topic']);
            //            $curr_topic = [];
            //            foreach ($topic as $v) {
            //                if (isset($topic_map[$v])) {
            //                    $curr_topic[] = $topic_map[$v];
            //                }
            //            }

            $paperInfo = ListeningExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                //                $paperInfo->content = $value['lyc'];
                //                $paperInfo->save();
                Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ListeningExamPaper();
                $paper->title = $title;
                $paper->complete_title = '机经' . ' ' . $unit . '-' . $title;
                $paper->unit = $unitId;
                $paper->file_url = '/exercises/listening/' . str_replace(' ', '_', $value['audio']);
                $paper->file_json_url = str_replace('mp3', 'json', $paper->file_url);
                $paper->content = $value['lyc'];
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $question_type = $questionType[$val['question_type']];
                if ($question_type == 2 && substr_count($val['context'][0]->question_num, '-') == 0) {
                    $question_type = 9;
                }

                //                if ($question_type != 1) {
                //                    continue;
                //                }
                $groupDesc = substr($val['question_desc'], 0, 20);
                $groupQuery = ListeningExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $question_type]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    //                    if ($question_type == 1) {
                    //                        $group->desc = $val['question_desc'];
                    //                        $group->save();
                    //                        continue;
                    //                    }
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ListeningExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $question_type;
                    $group->desc = $val['question_desc'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = $val['context'];
                //生成题目
                switch ($group->type) {
                    //                     case 1:
                    //                     case 3:
                    //                     case 4:
                    //                     case 5:
                    //                     case 6:
                    //                     case 7:
                    //                     case 8:
                    //                     case 9:
                    //                     case 10:
                    //                         //保存题目
                    //                         foreach ($questionList as $va) {
                    //                             $va = (array)$va;
                    //                             if (!isset($va['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$va['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $va['question_num'];
                    //                                 $questionObj->title = $va['title'];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                    //                                 $questionObj->key_locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                    // //                                $questionObj->analyze_print =  empty($va['analyze_print_ary']) ? [] : $va['analyze_print_ary'];
                    // //                                if (!empty($va['lyc_time'])) {
                    // //                                    $lyc_time = (array)$va['lyc_time'];
                    // //                                    $questionObj->start_time = $lyc_time['start_time'];
                    // //                                    $questionObj->end_time = $lyc_time['end_time'];
                    // //                                }
                    // //                                if (!empty($va['answer_sentences'])) {
                    //                                     $questionObj->answer_sentences = $va['answer_sentences'] ?? [];
                    // //                                }
                    // //                                $questionObj->file_url = empty($va['img_url']) ? '' : $question_url . $va['img_url'];
                    // //                                $questionObj->display_answer = empty($va['display_answer']) ? '' : $va['display_answer'];
                    // //                                $questionObj->analyze = empty($va['AI_analysis']) ? '' : $va['AI_analysis'];
                    // //                                $questionObj->answer = isset($va['all_answer']) ? $this->dealWithAnswer($va['all_answer']) : [];
                    // //                              $questionObj->display_answer = empty($questionObj->answer) ? '' : implode(" / ", $questionObj->answer);
                    //                                 $questionObj->analyze = empty($va['analyze']) ? '' : $va['analyze'];
                    //                                 $questionObj->ai_data = empty($va['ai_data']) ? '' : $va['ai_data'];
                    //                                 try {
                    //                                     $questionObj->save();
                    //                                 } catch (\Throwable $e) {
                    //                                     var_dump("解析答案失败失败，参数[question_id:$questionObj->id]");
                    //                                     continue;
                    //                                 }
                    //                             }

                    //                             //保存问题内容信息
                    //                             $question_context = ListeningExamContext::findOne(['biz_id'=>$questionObj->id, 'biz_type'=>2]);
                    //                             if (empty($question_context)) {
                    //                                 $contextObj = new ListeningExamContext();
                    //                                 $contextObj->content = $va;
                    //                                 $contextObj->biz_id = $questionObj->id;
                    //                                 $contextObj->biz_type = 2;
                    //                                 try {
                    //                                     $contextObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                    //                                     Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]",Logger::LEVEL_ERROR);
                    //                                 }
                    //                             } else {
                    //                                 $question_context->content = $va;
                    //                                 $question_context->save();
                    //                                 var_dump("$questionObj->id 题目解析数据保存成功");
                    //                             }

                    //                         }
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    case 2: //多选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            //                            $group->title = $va['title'];
                            //                            $group->save();

                            if (!isset($va['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }

                            $answer = array_column($va['answer'], 'answer');
                            $questionNum = $val['question_num_range'];
                            $optionMap = [];

                            foreach ($questionNum as $k => $num) {
                                //查询题目是否存在
                                $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                if (empty($questionObj)) {
                                    $questionObj = new ListeningExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $num;
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                                    $questionObj->key_locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                                    //                                    $questionObj->analyze_print =  empty($va['analyze_print_ary']) ? [] : $va['analyze_print_ary'];
                                    //                                    if (!empty($va['lyc_time'])) {
                                    $lyc_time = (array)$va['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                    //                                    }
                                    //                                    if (!empty($va['answer_sentences'])) {
                                    $questionObj->answer_sentences = $va['answer_sentences'];
                                    //                                    }
                                    //                                    $questionObj->file_url = empty($va['img_url']) ? '' : $question_url . $va['img_url'];
                                    //                                    $questionObj->display_answer = empty($va['display_answer']) ? '' : $va['display_answer'];
                                    //                                    $questionObj->analyze = empty($va['AI_analysis']) ? '' : $va['AI_analysis'];
                                    $questionObj->analyze = $va['analysis'];
                                    // $questionObj->ai_data = empty($va['ai_data']) ? '' : $va['ai_data'];
                                    //                                    $questionObj->save();
                                }

                                //保存问题内容信息
                                $question_context = ListeningExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                if (empty($question_context)) {
                                    $contextObj = new ListeningExamContext();
                                    $contextObj->content = $va;
                                    $contextObj->biz_id = $questionObj->id;
                                    $contextObj->biz_type = 2;
                                    try {
                                        $contextObj->insert();
                                    } catch (\Throwable $e) {
                                        var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                        Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                    }
                                } else {
                                    $question_context->content = $va;
                                    $question_context->save();
                                    var_dump("保存：$questionObj->id 题目解析详情成功");
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
        }
    }

    public function actionFixYcData($url)
    {
        $type = 2;
        $question_url = "/exercises/imgs/paper/listening/";
        $questionType = $this->getGroupType();
        $unit_map = $this->getUnitMap();
        $content = file_get_contents($url);
        try {
            $arr = json_decode($content);
        } catch (\Throwable $e) {
            var_dump($e->getMessage());
        }

        if (empty($arr)) {
            Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }
        foreach ($arr as $value) {
            $value = (array)$value;
            //分析标题
            $titleStr = $value['header'];
            $title = $value['header'];

            //获取考试id
            $unitId  = 276;

            $topic_list = ListeningExamPaperTopic::find()->all();
            $topic_map = [];
            foreach ($topic_list as $topic_item) {
                $topic_map[$topic_item->name] = $topic_item->id;
            }

            //            $topic = explode(',',$value['topic']);
            //            $curr_topic = [];
            //            foreach ($topic as $v) {
            //                if (isset($topic_map[$v])) {
            //                    $curr_topic[] = $topic_map[$v];
            //                }
            //            }

            $paperInfo = ListeningExamPaper::findOne(['title' => $title, 'unit' => $unitId]);
            if (!empty($paperInfo)) {
                $paperInfo->content = $value['lyc'];
                //                $paperInfo->save();
                Yii::getLogger()->log("该试卷已存在，title:$titleStr", Logger::LEVEL_INFO);
                var_dump("该试卷已存在，title:$titleStr");
                $paperId = $paperInfo->id;
            } else {
                //创建试卷
                $paper = new ListeningExamPaper();
                $paper->title = $title;
                // $paper->complete_title = '机经' . ' ' . $unit . '-' . $title;
                $paper->unit = $unitId;
                $paper->file_url = '/exercises/listening/' . str_replace(' ', '_', $value['audio']);
                $paper->file_json_url = str_replace('mp3', 'json', $paper->file_url);
                $paper->content = $value['lyc'];
                try {
                    $paper->insert();
                } catch (\Throwable $e) {
                    Yii::getLogger()->log("生成试卷失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                    continue;
                }

                $paperId = $paper->id;
            }

            //保存题目分组
            $question = (array)$value['questions'];
            if (empty($question)) {
                var_dump("题目为空");
                Yii::getLogger()->log("题目为空", Logger::LEVEL_ERROR);
                continue;
            }

            foreach ($question as $val) {
                $val = (array)$val;
                if (!isset($questionType[$val['question_type']])) {
                    var_dump("题目类型不存在，question_type:" . $val['question_type']);
                    Yii::getLogger()->log("题目类型不存在，question_type:" . $val['question_type'], Logger::LEVEL_ERROR);
                    continue;
                }

                $question_type = $questionType[$val['question_type']];
                // if ($question_type == 2 && substr_count($val['context'][0]->question_num, '-') == 0) {
                //     $question_type = 9;
                // }

                //                if ($question_type != 1) {
                //                    continue;
                //                }
                $groupDesc = substr($val['question_desc'], 0, 20);
                $groupQuery = ListeningExamQuestionGroup::find();
                $groupQuery->andWhere(['paper_id' => $paperId, 'type' => $question_type]);
                $groupQuery->andWhere(['like', 'desc', $groupDesc]);
                $group = $groupQuery->one();

                if (!empty($group)) {
                    //                    if ($question_type == 1) {
                    //                        $group->desc = $val['question_desc'];
                    //                        $group->save();
                    //                        continue;
                    //                    }
                    var_dump("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc);
                    Yii::getLogger()->log("该分组已存在，paper_id:$paperId,question_type:" . $val['question_type'] . ',desc:' . $groupDesc, Logger::LEVEL_INFO);
                } else {
                    //生成题目分组
                    $group = new ListeningExamQuestionGroup();
                    $group->paper_id = $paperId;
                    $group->type = $question_type;
                    $group->desc = $val['question_desc'];
                    try {
                        $group->insert();
                    } catch (\Throwable $e) {
                        Yii::getLogger()->log("题目【 $titleStr 】分组【 " . $val['question_type'] . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                        continue;
                    }
                }
                $groupId = $group->id;

                $groupAnalyze = '';
                $collect = '';
                $groupTitle = '';
                $questionList = $val['context'];
                //生成题目
                switch ($group->type) {
                    //                     case 1:
                    //                     case 3:
                    //                     case 4:
                    //                     case 5:
                    //                     case 6:
                    //                     case 7:
                    //                     case 8:
                    //                     case 9:
                    //                     case 10:
                    //                         //保存题目
                    //                         foreach ($questionList as $va) {
                    //                             $va = (array)$va;
                    //                             if (!isset($va['question_num'])) {
                    //                                 Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['title'] ." 】题号不存在", Logger::LEVEL_ERROR);
                    //                                 continue;
                    //                             }
                    //                             //查询题目是否存在
                    //                             $questionObj = ListeningExamQuestion::findOne(['paper_id'=>$paperId,'group_id'=>$groupId, 'number' =>$va['question_num']]);
                    //                             if (empty($questionObj)) {
                    //                                 $questionObj = new ListeningExamQuestion();
                    //                                 $questionObj->paper_id = $paperId;
                    //                                 $questionObj->group_id = $groupId;
                    //                                 $questionObj->number = $va['question_num'];
                    //                                 $questionObj->title = $va['title'];
                    //                                 try {
                    //                                     $questionObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     Yii::getLogger()->log("试卷【 $titleStr 】题目【 ".$va['question_num'] ." 】生成失败，err:".$e->getMessage(), Logger::LEVEL_ERROR);
                    //                                     continue;
                    //                                 }
                    //                             } else {
                    //                                 $questionObj->locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                    //                                 $questionObj->key_locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                    // //                                $questionObj->analyze_print =  empty($va['analyze_print_ary']) ? [] : $va['analyze_print_ary'];
                    // //                                if (!empty($va['lyc_time'])) {
                    // //                                    $lyc_time = (array)$va['lyc_time'];
                    // //                                    $questionObj->start_time = $lyc_time['start_time'];
                    // //                                    $questionObj->end_time = $lyc_time['end_time'];
                    // //                                }
                    // //                                if (!empty($va['answer_sentences'])) {
                    // //                                    $questionObj->answer_sentences = $va['answer_sentences'];
                    // //                                }
                    // //                                $questionObj->file_url = empty($va['img_url']) ? '' : $question_url . $va['img_url'];
                    // //                                $questionObj->display_answer = empty($va['display_answer']) ? '' : $va['display_answer'];
                    // //                                $questionObj->analyze = empty($va['AI_analysis']) ? '' : $va['AI_analysis'];
                    // //                                $questionObj->answer = isset($va['all_answer']) ? $this->dealWithAnswer($va['all_answer']) : [];
                    // //                              $questionObj->display_answer = empty($questionObj->answer) ? '' : implode(" / ", $questionObj->answer);
                    //                                 $questionObj->analyze = empty($va['analyze']) ? '' : $va['analyze'];
                    //                                 $questionObj->ai_data = empty($va['ai_data']) ? '' : $va['ai_data'];
                    //                                 $questionObj->answer_sentences = $va['answer_sentences'] ?? [];
                    //                                 try {
                    //                                     $questionObj->save();
                    //                                 } catch (\Throwable $e) {
                    //                                     var_dump("解析答案失败失败，参数[question_id:$questionObj->id]");
                    //                                     continue;
                    //                                 }
                    //                             }

                    //                             //保存问题内容信息
                    //                             $question_context = ListeningExamContext::findOne(['biz_id'=>$questionObj->id, 'biz_type'=>2]);
                    //                             if (empty($question_context)) {
                    //                                 $contextObj = new ListeningExamContext();
                    //                                 $contextObj->content = $va;
                    //                                 $contextObj->biz_id = $questionObj->id;
                    //                                 $contextObj->biz_type = 2;
                    //                                 try {
                    //                                     $contextObj->insert();
                    //                                 } catch (\Throwable $e) {
                    //                                     var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                    //                                     Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]",Logger::LEVEL_ERROR);
                    //                                 }
                    //                             } else{
                    //                                 $question_context->content = $va;
                    //                                 $question_context->save();
                    //                             }

                    //                         }
                    //                         var_dump("试卷：$titleStr 题目分组：".$val['question_type']."，$groupDesc 初始化完成");
                    //                         break;
                    case 2: //多选题
                        //保存题目
                        foreach ($questionList as $va) {
                            $va = (array)$va;
                            //                            $group->title = $va['title'];
                            //                            $group->save();

                            if (!isset($va['question_num'])) {
                                var_dump("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在");
                                Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $va['title'] . " 】题号不存在", Logger::LEVEL_ERROR);
                                continue;
                            }

                            $answer = array_column($va['answer'], 'answer');
                            $questionNum = $val['question_num_range'];
                            $optionMap = [];

                            foreach ($questionNum as $k => $num) {
                                //查询题目是否存在
                                $questionObj = ListeningExamQuestion::findOne(['paper_id' => $paperId, 'group_id' => $groupId, 'number' => $num]);
                                if (empty($questionObj)) {
                                    $questionObj = new ListeningExamQuestion();
                                    $questionObj->paper_id = $paperId;
                                    $questionObj->group_id = $groupId;
                                    $questionObj->number = $num;
                                    try {
                                        $questionObj->insert();
                                    } catch (\Throwable $e) {
                                        Yii::getLogger()->log("试卷【 $titleStr 】题目【 " . $num . " 】生成失败，err:" . $e->getMessage(), Logger::LEVEL_ERROR);
                                        continue;
                                    }
                                } else {
                                    $questionObj->locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                                    $questionObj->key_locating_words = empty($va['locating_words']) ? [] : $va['locating_words'];
                                    //                                    $questionObj->analyze_print =  empty($va['analyze_print_ary']) ? [] : $va['analyze_print_ary'];
                                    //                                    if (!empty($va['lyc_time'])) {
                                    $lyc_time = (array)$va['lyc_time'];
                                    $questionObj->start_time = $lyc_time['start_time'];
                                    $questionObj->end_time = $lyc_time['end_time'];
                                    //                                    }
                                    //                                    if (!empty($va['answer_sentences'])) {
                                    $questionObj->answer_sentences = $va['answer_sentences'];
                                    //                                    }
                                    //                                    $questionObj->file_url = empty($va['img_url']) ? '' : $question_url . $va['img_url'];
                                    $questionObj->display_answer = $va['display_answer'];
                                    //                                    $questionObj->analyze = empty($va['AI_analysis']) ? '' : $va['AI_analysis'];
                                    $questionObj->analyze = $va['analysis'];
                                    // $questionObj->ai_data = empty($va['ai_data']) ? '' : $va['ai_data'];
                                    //                                    $questionObj->save();
                                }

                                //保存问题内容信息
                                $question_context = ListeningExamContext::findOne(['biz_id' => $questionObj->id, 'biz_type' => 2]);
                                if (empty($question_context)) {
                                    $contextObj = new ListeningExamContext();
                                    $contextObj->content = $va;
                                    $contextObj->biz_id = $questionObj->id;
                                    $contextObj->biz_type = 2;
                                    try {
                                        $contextObj->insert();
                                        var_dump("多选题：$questionObj->id 题目详情保存成功");
                                    } catch (\Throwable $e) {
                                        var_dump("保存问题内容信息失败，参数[question_id:$questionObj->id]");
                                        Yii::getLogger()->log("保存问题内容信息失败，参数[question_id:$questionObj->id]", Logger::LEVEL_ERROR);
                                    }
                                } else {
                                    $question_context->content = $va;
                                    $question_context->save();
                                }
                            }
                        }
                        var_dump("试卷：$titleStr 题目分组：" . $val['question_type'] . "，$groupDesc 初始化完成");
                        break;
                    default:
                        break;
                }
            }
            var_dump("试卷：$titleStr 初始化完成");
        }
    }

    public function actionFixSort()
    {
        $g_list = ListeningExamPaperUnit::find()->where(['>', 'id', 56])->all();
        $num = 1000;
        foreach ($g_list as $item) {
            $list = ListeningExamPaper::find()->where(['unit' => $item->id])->orderBy('id desc')->all();
            foreach ($list as $value) {
                $num += 1;
                $value->weight = $num;
                $value->save();
                var_dump("id:$value->id,更新权重成功");
            }
            $num += 10;
        }
    }

    public function actionFilterEmptyAnswer()
    {
        $limit = 100;
        $offset = 0;

        while ($list = ListeningExamQuestion::find()->where(['>', 'id', 17565])->orderBy("id asc")->limit($limit)->offset($offset)->all()) {
            $offset += $limit;
            if (!empty($list)) {
                foreach ($list as $key => $value) {
                    if (empty($value->answer)) {
                        continue;
                    }
                    $value->answer = $this->deleteEmpty($value->answer);
                    $value->save();
                }
                echo "处理题目id：" . $value->id . "答案结束\n";
            }
        }
    }

    public function deleteEmpty($data): array
    {
        foreach ($data as $key => $value) {
            if ($value === '') {
                unset($data[$key]);
            }
        }
        return array_values($data);
    }

    public function actionDealWithFile()
    {
        $oss = $this->getOssClient();
        $list = ListeningExamPaper::find()->all();
        if (empty($list)) {
            var_dump("暂无数据");
        }
        foreach ($list as $value) {
            $org_url = $value->file_url;
            $url_arr = explode('/', $org_url);
            $filename_key = array_key_last($url_arr);
            $filename = $url_arr[$filename_key];
            $json_filename = str_replace('mp3', 'json', $filename);

            $local_path = dirname(__FILE__, 2) . '/runtime/tmp';
            try {
                //下载文件
                $org_content = file_get_contents($org_url);
            } catch (\Throwable $e) {
                var_dump('获取文件失败，' . $e->getMessage());
                continue;
            }

            $local_file = $local_path . '/' . $filename;

            //生成本地文件
            if (!file_put_contents($local_file, $org_content)) {
                var_dump("写入文件错误");
                continue;
            }

            //上传文件
            $remote_file = 'exercises/listening/' . $filename;
            $remote_json_file = '/exercises/listening/' . $json_filename;
            $this->upload($oss, $remote_file, $local_file);
            $value->file_url = '/' . $remote_file;
            $value->file_json_url = $remote_json_file;
            $value->save();
        }
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

        $result = array();
        try {
            $getOssInfo = $oss->uploadFile($bucket, $object, $filepath);
            var_dump($getOssInfo);
            $result['url'] = $getOssInfo['info']['url'];
        } catch (OssException $e) {
            var_dump($e->getMessage());
        };
        return $result['url'];
    }

    public function getGroupType(): array
    {
        $listMap = [];
        $query = ListeningExamQuestionType::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
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

    public function getAnswerArr($str): array
    {
        $ret = [];
        if (empty($str)) {
            return $ret;
        }
        if (substr_count($str, ")") > 0) {
            var_dump("原始数据：" . $str);
            $all = array_filter(explode(")", $str));
            $end = explode("/", trim(end($all)));
            $ret = $end;
            $len = count($all);
            if ($len > 2) {
                for ($i = $len - 2; $i >= 0; $i -= 1) {
                    $all[$i] = explode("/", str_replace("(", "", $all[$i]));
                    var_dump($all[$i]);
                    $o_len = count($ret);
                    foreach ($all[$i] as $value) {
                        for ($j = 0; $j < $o_len; $j++) {
                            $ret[] = trim($value) . ' ' . trim($ret[$j]);
                        }
                    }
                }
            } elseif ($len == 1) {
                $tmp = explode("(", $end[0]);
                $ret = [
                    trim($tmp[0]),
                    trim($tmp[0]) . ' ' . trim($tmp[1])
                ];
            } elseif ($len == 2) {
                $f = trim(str_replace("(", "", $all[0]));
                $ret = [
                    $f . ' ' . trim($all[1]),
                    trim($all[1])
                ];
            }
            var_dump("处理后数据：");
            var_dump($ret);
        } else {
            $ret = explode("/", $str);
        }

        foreach ($ret as &$v) {
            $v = trim($v);
        }

        return $ret;
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

    public function actionDealWithQuestionAnalyze(): void
    {
        $id = 0;
        $size = 500;
        while ($list = ListeningExamQuestion::find()->where(['>', 'id', $id])->limit($size)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (preg_match('/^：\n/', $value->analyze)) {
                    $value->analyze = preg_replace('/^：\n/', '', $value->analyze);
                    $value->save();
                    var_dump("$value->id,处理完成");
                } elseif (preg_match('/^:\n/', $value->analyze)) {
                    $value->analyze = preg_replace('/^:\n/', '', $value->analyze);
                    $value->save();
                    var_dump("$value->id,处理完成");
                }
            }
        }
        var_dump("处理完成");
    }

    public function actionExportAudio(): void
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/listening_audio';
        $oss = "https://duy-ielts-public.oss-cn-hangzhou.aliyuncs.com";
        $list = ListeningExamPaper::find()->where(['<=', 'id', 2112])->andWhere(['>=', 'id', 1258])->all();
        foreach ($list as $value) {
            $url = $oss . $value->file_url;
            $url_arr = explode('/', $value->file_url);
            $original_filename = end($url_arr);
            // 保留扩展名，生成新文件名：在原文件名后拼接时间戳
            $extension = pathinfo($original_filename, PATHINFO_EXTENSION);
            $basename = pathinfo($original_filename, PATHINFO_FILENAME);
            $new_filename = $value->complete_title . '.' . $extension;
            $local_file = $file . '/' . $new_filename;

            try {
                $file_content = file_get_contents($url);
            } catch (\Throwable $e) {
                var_dump("获取文件失败，" . $e->getMessage());
                continue;
            }
            if (file_put_contents($local_file, $file_content) === false) {
                var_dump("写入文件错误");
                continue;
            }
            var_dump("$value->complete_title 文件下载成功");
        }
        var_dump("文件下载完成");
    }

    public function actionFixAnswer($id = 0)
    {
        $question = ReadingExamQuestion::find()->where(['>', 'id', $id])->all();
        foreach ($question as $value) {
            //全部转为小写，然后去重
            $answer = (array)$value->answer;
            if ((count($answer) == 1) && ($answer[0] > 73722)) {
                $answer[0] = intval($answer[0]);
            }
            $value->answer = $answer;
            $value->save();
        }
    }

    public function actionFixAudioUrl($id = 0)
    {
        $list = ListeningExamPaper::find()->where(['>', 'id', $id])->andWhere(['like', 'complete_title', '剑雅'])->all();
        foreach ($list as $value) {
            var_dump($value->complete_title);
            $arr = explode(' ', $value->complete_title);
            $title = $arr[0] . '-' . str_replace('-Part', '', $arr[2]) . '-' . $arr[3];
            var_dump($title);
            $value->file_url = '/exercises/listening/' . $title . '.mp3';
            $value->save(false);
            var_dump("$value->id 处理完成：" . $value->file_url);
        }
        var_dump("处理完成");
    }

    public function actionFixRecord($id = 0)
    {
        $limit = 500;
        while (true) {
            //获取听力所有题型做题记录
            $list = ListeningExamRecord::find()->where(['>', 'question_type', 0])->andWhere(['status' => 1])->where(['>', 'id', $id])->orderBy(['id' => SORT_ASC])->limit($limit)->all();
            if (empty($list)) {
                var_dump("处理完成");
                break;
            }
            foreach ($list as $value) {
                $id = $value->id;
                //获取题组信息
                $group_list = ListeningExamQuestionGroup::find()->where(['type' => $value->question_type, 'paper_id' => $value->paper_id])->asArray()->all();
                $group_ids = array_column($group_list, 'id');
                if (empty($group_ids)) {
                    continue;
                }
                //获取题目信息
                $question_count = ListeningExamQuestion::find()->where(['group_id' => $group_ids])->count();
                if ($value->total == $question_count) {
                    var_dump("第 $value->id 条记录题目数量正确");
                    continue;
                }
                $value->total = $question_count;
                $value->save(false);
                var_dump("更新 $value->id 完成");
            }
        }
    }

    public function actionFixRecordAnswer()
    {
        $question_ids = [1220, 1672];
        //获取听力所有题型做题记录
        $list = ListeningExamRecord::find()->where(['paper_id' => [148, 204]])->all();
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
                        } else if (substr_count($v['answer'][0],  'and') > 0) {
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

    public function actionBuildContentText($startId = 0)
    {
        $query = ListeningExamPaper::find()->where(['>', 'id', $startId])->orderBy(['id' => SORT_ASC]);
        // $query = ListeningExamPaper::find()->where(['id' => 1])->orderBy(['id' => SORT_ASC]);
        $processed = 0;
        foreach ($query->batch(200) as $papers) {
            /** @var ListeningExamPaper $paper */
            foreach ($papers as $paper) {
                $processed++;
                if (empty($paper->content)) {
                    if ($paper->content_text !== null) {
                        $paper->content_text = null;
                        $paper->save(false, ['content_text']);
                    }
                    continue;
                }
                $decoded = $paper->content;
                $normalized = $this->normalizePaperDialogue($decoded);
                $paper->content_text = $normalized;
                $paper->save(false, ['content_text']);
                var_dump("试卷 {$paper->id} 处理完成");
            }
        }
        var_dump("共处理 {$processed} 条记录");
    }

    private function normalizePaperDialogue($data)
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $this->normalizePaperDialogue($value);
                    continue;
                }
                if ($key === 'cn_text' && is_string($value)) {
                    $data[$key] = $this->stripSpeakerLabel($value, true);
                } elseif ($key === 'en_text' && is_string($value)) {
                    $data[$key] = $this->stripSpeakerLabel($value, false);
                }
            }
        }

        return $data;
    }

    private function stripSpeakerLabel(string $text, bool $isChinese): string
    {
        var_dump($text);
        $result = ltrim($text);
        if ($result === '') {
            return $result;
        }

        $patterns = [];
        if ($isChinese) {
            $patterns[] = '/^(?:[\x{4e00}-\x{9fa5}]{1,8})[：:]\s*/u';
            $patterns[] = '/^(?:[A-Z][A-Z0-9\s\'\x{2019}\x{00B7}\-\/]{0,30})[:：]\s*/u';
        } else {
            $patterns[] = '/^(?:[A-Z][A-Z0-9\s\'\x{2019}\x{00B7}\-\/]{0,30})[:：]\s*/u';
            $patterns[] = '/^(?:[A-Z][a-z]{1,20})[:：]\s*/u';
        }

        var_dump($patterns);
        $limit = 0;
        while ($limit < 5) {
            $matched = false;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $result)) {
                    $result = preg_replace($pattern, '', $result, 1);
                    $result = ltrim($result);
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                break;
            }
            $limit++;
        }

        return $result;
    }

    public function actionFixSimulateRecordAnswer()
    {
        $question_ids = [1220, 1672];
        //获取听力所有题型做题记录
        $list = SimulateExamListening::find()->where(['paper_group_id' => [37, 51]])->all();
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
     * Export listening data for 剑雅15 to json.
     * Run: php yii listening/export-jy15-json [outFile]
     */
    public function actionExportJy15Json(?string $outFile = null): void
    {
        ini_set('memory_limit', '1G');

        if ($outFile === null || $outFile === '') {
            $local_path = dirname(__FILE__, 2);
            $outFile = $local_path . '/runtime/tmp/jy15_listening1215.json';
        }

        $papers = ListeningExamPaper::find()
            ->where(['like', 'complete_title', '剑雅15'])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $data = [];
        foreach ($papers as $paper) {
            // Preload questions for this paper so we can map $id$ placeholders back to numbers.
            $questionList = ListeningExamQuestion::find()
                ->where(['paper_id' => $paper->id])
                ->orderBy(['number' => SORT_ASC])
                ->all();

            $idToNumber = [];
            $questionsByGroup = [];
            $questionIds = [];
            foreach ($questionList as $question) {
                $idToNumber[$question->id] = $question->number;
                $questionsByGroup[$question->group_id][] = $question;
                $questionIds[] = $question->id;
            }

            $paperItem = [
                'id' => $paper->id,
                'complete_title' => $paper->complete_title,
                'content' => $paper->content,
                'groups' => [],
            ];

            $groupList = ListeningExamQuestionGroup::find()
                ->where(['paper_id' => $paper->id])
                ->orderBy(['id' => SORT_ASC])
                ->all();

            $groupIds = array_map(static function (ListeningExamQuestionGroup $group): int {
                return $group->id;
            }, $groupList);
            $questionOptionMap = $this->indexOptionsByBizId($questionIds, 1);
            $groupOptionMap = $this->indexOptionsByBizId($groupIds, 2);

            foreach ($groupList as $group) {
                $content = $this->transformGroupContentForExport($group->content, $idToNumber);

                $groupItem = [
                    'id' => $group->id,
                    'content' => $content,
                    'questions' => [],
                ];

                foreach ($questionsByGroup[$group->id] ?? [] as $question) {
                    $options = $questionOptionMap[$question->id] ?? ($groupOptionMap[$group->id] ?? []);
                    $answerInfo = $this->buildQuestionAnswerForExport($question, $options);
                    $groupItem['questions'][] = [
                        'id' => $question->id,
                        'number' => $question->number,
                        'title' => $question->title,
                        'answer' => $answerInfo['answer'],
                        'answer_content' => $answerInfo['answer_content'],
                        'answer_sentences' => $question->answer_sentences,
                    ];
                }

                $paperItem['groups'][] = $groupItem;
            }

            $data[] = $paperItem;
        }

        FileHelper::createDirectory(dirname($outFile));
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('JSON encode failed: ' . json_last_error_msg());
        }

        if (file_put_contents($outFile, $json) === false) {
            throw new \RuntimeException('Write file failed: ' . $outFile);
        }

        $this->stdout("Exported " . count($papers) . " papers to {$outFile}\n");
    }

    /**
     * 检查/修复 listening_exam_question.start_time/end_time 是否覆盖 answer_sentences.lyc_index 对应的正文时间范围
     *
     * 规则：
     * - answer_sentences 结构包含 lyc_index: [startOrder, endOrder]（或单个 order）
     * - listening_exam_paper.content 为正文数组，包含 order/start_time/end_time
     * - 若题目 start_time/end_time 未包含该 order 范围对应的时间段，则更新为该范围的最小 start_time 和最大 end_time
     *
     * 用法示例：
     * - php yii listening/fix-question-time-from-lyc-index --dryRun=1
     * - php yii listening/fix-question-time-from-lyc-index --paperId=123 --dryRun=0
     */
    public function actionFixQuestionTimeFromLycIndex(
        int $paperId = 0,
        int $dryRun = 1,
        int $batchSize = 200,
        int $startId = 0,
        int $endId = 0,
        int $limit = 0,
        int $operatorId = 0
    ): void {
        if ($paperId === 0 && $this->paperId !== 0) {
            $paperId = $this->paperId;
        }
        if ($dryRun === 1 && $this->dryRun !== 1) {
            $dryRun = $this->dryRun;
        }
        if ($batchSize === 200 && $this->batchSize !== 200) {
            $batchSize = $this->batchSize;
        }
        if ($startId === 0 && $this->startId !== 0) {
            $startId = $this->startId;
        }
        if ($endId === 0 && $this->endId !== 0) {
            $endId = $this->endId;
        }
        if ($limit === 0 && $this->limit !== 0) {
            $limit = $this->limit;
        }
        if ($operatorId === 0 && $this->operatorId !== 0) {
            $operatorId = $this->operatorId;
        }

        $dryRun = $dryRun ? 1 : 0;
        $batchSize = max(1, $batchSize);

        $now = time();

        $scanned = 0;
        $updated = 0;
        $skippedEmpty = 0;
        $skippedInvalidIndex = 0;
        $skippedMissingOrder = 0;
        $skippedPaperMissing = 0;

        $db = Yii::$app->db;
        $lastId = $startId > 0 ? ($startId - 1) : 0;
        $remaining = $limit > 0 ? $limit : null;

        while (true) {
            $pageLimit = $remaining === null ? $batchSize : min($batchSize, $remaining);
            if ($pageLimit <= 0) {
                break;
            }

            $pageQuery = ListeningExamQuestion::find()
                ->select(['id', 'paper_id', 'number', 'answer_sentences', 'start_time', 'end_time'])
                ->orderBy(['id' => SORT_ASC])
                ->andWhere(['>', 'id', $lastId]);

            if ($paperId > 0) {
                $pageQuery->andWhere(['paper_id' => $paperId]);
            }
            if ($endId > 0) {
                $pageQuery->andWhere(['<=', 'id', $endId]);
            }

            $questions = $pageQuery->limit($pageLimit)->all();
            if (empty($questions)) {
                break;
            }

            $paperIds = [];
            foreach ($questions as $question) {
                $pid = (int)$question->paper_id;
                if ($pid > 0) {
                    $paperIds[$pid] = $pid;
                }
            }

            $orderTimeMapsByPaperId = [];
            if (!empty($paperIds)) {
                $paperRows = ListeningExamPaper::find()
                    ->select(['id', 'content'])
                    ->where(['id' => array_values($paperIds)])
                    ->indexBy('id')
                    ->asArray()
                    ->all();

                foreach ($paperRows as $pid => $paperRow) {
                    $orderTimeMapsByPaperId[(int)$pid] = $this->buildPaperOrderTimeMap($paperRow['content'] ?? null);
                }
            }

            $tx = $dryRun ? null : $db->beginTransaction();
            try {
                /** @var ListeningExamQuestion $question */
                foreach ($questions as $question) {
                    $scanned++;

                    $answerSentences = $this->decodeJsonArray($question->answer_sentences);
                    if (empty($answerSentences)) {
                        $skippedEmpty++;
                        continue;
                    }

                    $orderRange = $this->extractLycOrderRange($answerSentences);
                    if ($orderRange === null) {
                        $skippedInvalidIndex++;
                        continue;
                    }

                    [$orderStart, $orderEnd] = $orderRange;
                    $paperIdForQuestion = (int)$question->paper_id;
                    if ($paperIdForQuestion <= 0) {
                        $skippedPaperMissing++;
                        continue;
                    }

                    $orderTimeMap = $orderTimeMapsByPaperId[$paperIdForQuestion] ?? null;
                    if (empty($orderTimeMap)) {
                        $skippedPaperMissing++;
                        continue;
                    }

                    $expectedRange = $this->computeTimeRangeByOrderRange($orderTimeMap, $orderStart, $orderEnd);
                    if ($expectedRange === null) {
                        $skippedMissingOrder++;
                        continue;
                    }

                    [$expectedStart, $expectedEnd] = $expectedRange;
                    $currStart = (int)$question->start_time;
                    $currEnd = (int)$question->end_time;

                    $contains = $currStart > 0 && $currEnd > 0
                        && $currStart <= $expectedStart
                        && $currEnd >= $expectedEnd
                        && $currStart <= $currEnd;

                    if ($contains) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->stdout(sprintf(
                            "MISMATCH qid=%d paper_id=%d number=%s order=[%d,%d] curr=[%d,%d] expected=[%d,%d]\n",
                            (int)$question->id,
                            $paperIdForQuestion,
                            (string)$question->number,
                            $orderStart,
                            $orderEnd,
                            $currStart,
                            $currEnd,
                            $expectedStart,
                            $expectedEnd
                        ));
                        continue;
                    }
                    var_dump(sprintf(
                        "MISMATCH qid=%d paper_id=%d number=%s order=[%d,%d] curr=[%d,%d] expected=[%d,%d]\n",
                        (int) $question->id,
                        $paperIdForQuestion,
                        (string) $question->number,
                        $orderStart,
                        $orderEnd,
                        $currStart,
                        $currEnd,
                        $expectedStart,
                        $expectedEnd
                    ));

                    $question->updateAttributes([
                        'start_time' => $expectedStart,
                        'end_time' => $expectedEnd,
                        'update_by' => $operatorId,
                        'update_time' => $now,
                    ]);
                    $updated++;
                }

                if ($tx !== null) {
                    $tx->commit();
                }
            } catch (\Throwable $e) {
                if ($tx !== null) {
                    $tx->rollBack();
                }
                throw $e;
            }

            $lastId = (int)end($questions)->id;
            if ($remaining !== null) {
                $remaining -= count($questions);
            }

            unset($questions, $paperRows, $orderTimeMapsByPaperId);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $this->stdout(sprintf(
            "Done. scanned=%d updated=%d dryRun=%d skipped_empty=%d skipped_invalid_lyc_index=%d skipped_missing_order=%d skipped_paper_missing=%d\n",
            $scanned,
            $updated,
            $dryRun ? 1 : 0,
            $skippedEmpty,
            $skippedInvalidIndex,
            $skippedMissingOrder,
            $skippedPaperMissing
        ));
    }

    private function indexOptionsByBizId(array $bizIds, int $bizType): array
    {
        $bizIds = array_values(array_unique(array_filter($bizIds, static function ($id): bool {
            return $id !== null && $id !== '';
        })));
        if (empty($bizIds)) {
            return [];
        }

        $options = ListeningExamQuestionOption::find()
            ->where(['biz_type' => $bizType, 'biz_id' => $bizIds])
            ->orderBy(['id' => SORT_ASC])
            ->all();

        $result = [];
        foreach ($options as $option) {
            $result[$option->biz_id][] = $option;
        }

        return $result;
    }

    private function decodeJsonArray($value): ?array
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \stdClass) {
            return (array)$value;
        }

        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        if (str_starts_with($trimmed, 'a:') || str_starts_with($trimmed, 'O:')) {
            $unserialized = @unserialize($trimmed);
            if (is_array($unserialized)) {
                return $unserialized;
            }
        }

        return null;
    }

    /**
     * 从 answer_sentences 中提取 lyc_index 覆盖的整体 order 区间
     * 支持：
     * - {"lyc_index":[10,11], ...}
     * - [{"lyc_index":[10,11]}, {"lyc_index":[20,21]}]
     */
    private function extractLycOrderRange(array $answerSentences): ?array
    {
        $ranges = [];

        if (isset($answerSentences['lyc_index'])) {
            $ranges[] = $answerSentences['lyc_index'];
        } else {
            foreach ($answerSentences as $item) {
                if ($item instanceof \stdClass) {
                    $item = (array)$item;
                }
                if (!is_array($item) || !isset($item['lyc_index'])) {
                    continue;
                }
                $ranges[] = $item['lyc_index'];
            }
        }

        $minOrder = null;
        $maxOrder = null;
        foreach ($ranges as $range) {
            if ($range instanceof \stdClass) {
                $range = (array)$range;
            }

            if (is_int($range)) {
                $start = $range;
                $end = $range;
            } elseif (is_array($range)) {
                $values = array_values($range);
                if (count($values) === 0) {
                    continue;
                }
                if (count($values) === 1) {
                    $start = (int)$values[0];
                    $end = (int)$values[0];
                } else {
                    $start = (int)$values[0];
                    $end = (int)$values[1];
                }
            } else {
                continue;
            }

            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            $minOrder = $minOrder === null ? $start : min($minOrder, $start);
            $maxOrder = $maxOrder === null ? $end : max($maxOrder, $end);
        }

        if ($minOrder === null || $maxOrder === null) {
            return null;
        }

        return [$minOrder, $maxOrder];
    }

    /**
     * @param mixed $paperContent listening_exam_paper.content
     * @return array<int, array{start:int,end:int}>
     */
    private function buildPaperOrderTimeMap($paperContent): array
    {
        $content = $this->decodeJsonArray($paperContent);
        if (empty($content) || !is_array($content)) {
            return [];
        }

        $map = [];
        foreach ($content as $item) {
            if ($item instanceof \stdClass) {
                $item = (array)$item;
            }
            if (!is_array($item)) {
                continue;
            }

            if (!isset($item['order'], $item['start_time'], $item['end_time'])) {
                continue;
            }

            $order = (int)$item['order'];
            $start = (int)$item['start_time'];
            $end = (int)$item['end_time'];
            if ($order < 0 || $start <= 0 || $end <= 0) {
                continue;
            }
            if ($start > $end) {
                [$start, $end] = [$end, $start];
            }

            $map[$order] = ['start' => $start, 'end' => $end];
        }

        return $map;
    }

    /**
     * @param array<int, array{start:int,end:int}> $orderTimeMap
     * @return array{0:int,1:int}|null
     */
    private function computeTimeRangeByOrderRange(array $orderTimeMap, int $orderStart, int $orderEnd): ?array
    {
        if ($orderStart > $orderEnd) {
            [$orderStart, $orderEnd] = [$orderEnd, $orderStart];
        }

        $minStart = null;
        $maxEnd = null;
        for ($order = $orderStart; $order <= $orderEnd; $order++) {
            if (!isset($orderTimeMap[$order])) {
                return null;
            }
            $t = $orderTimeMap[$order];
            $minStart = $minStart === null ? $t['start'] : min($minStart, $t['start']);
            $maxEnd = $maxEnd === null ? $t['end'] : max($maxEnd, $t['end']);
        }

        if ($minStart === null || $maxEnd === null || $minStart <= 0 || $maxEnd <= 0) {
            return null;
        }

        if ($minStart > $maxEnd) {
            [$minStart, $maxEnd] = [$maxEnd, $minStart];
        }

        return [$minStart, $maxEnd];
    }

    private function buildQuestionAnswerForExport(ListeningExamQuestion $question, array $options): array
    {
        $displayAnswer = is_string($question->display_answer) ? trim($question->display_answer) : '';
        $rawAnswer = $this->normalizeExportAnswerValue($question->answer);

        if (empty($options)) {
            return [
                'answer' => $displayAnswer !== '' ? $displayAnswer : $rawAnswer,
                'answer_content' => null,
            ];
        }

        $optionsById = [];
        $optionsByTitle = [];
        foreach ($options as $option) {
            if (!$option instanceof ListeningExamQuestionOption) {
                continue;
            }
            $optionsById[(int)$option->id] = $option;
            $titleKey = strtoupper(trim((string)$option->title));
            if ($titleKey !== '') {
                $optionsByTitle[$titleKey] = $option;
            }
        }

        $answerOptionIds = [];

        if (is_int($rawAnswer)) {
            if (isset($optionsById[$rawAnswer])) {
                $answerOptionIds[] = $rawAnswer;
            }
        } elseif (is_array($rawAnswer)) {
            foreach ($rawAnswer as $value) {
                $value = $this->normalizeExportAnswerValue($value);
                if (is_int($value) && isset($optionsById[$value])) {
                    $answerOptionIds[] = $value;
                }
            }
        }

        if (empty($answerOptionIds)) {
            $source = $displayAnswer;
            if ($source === '' && is_string($rawAnswer)) {
                $source = trim($rawAnswer);
            }

            if ($source !== '') {
                $tokens = preg_split('/\\s*,\\s*|\\s*\\/\\s*|\\s*;\\s*|\\s+and\\s+|\\s+or\\s+|\\s+/i', $source, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($tokens as $token) {
                    $token = trim($token, " \t\n\r\0\x0B()[]{}");
                    if ($token === '') {
                        continue;
                    }

                    if (ctype_digit($token)) {
                        $id = (int)$token;
                        if (isset($optionsById[$id])) {
                            $answerOptionIds[] = $id;
                        }
                        continue;
                    }

                    $key = strtoupper($token);
                    if (isset($optionsByTitle[$key])) {
                        $answerOptionIds[] = (int)$optionsByTitle[$key]->id;
                    }
                }
            }
        }

        $answerOptionIds = array_values(array_unique($answerOptionIds));
        if (empty($answerOptionIds)) {
            return [
                'answer' => $displayAnswer !== '' ? $displayAnswer : $rawAnswer,
                'answer_content' => null,
            ];
        }

        $answerContents = [];
        foreach ($answerOptionIds as $id) {
            $answerContents[] = (string)$optionsById[$id]->content;
        }

        $answerValue = count($answerOptionIds) === 1 ? $answerOptionIds[0] : $answerOptionIds;
        $answerContentValue = count($answerContents) === 1 ? $answerContents[0] : $answerContents;

        return [
            'answer' => $answerValue,
            'answer_content' => $answerContentValue,
        ];
    }

    private function normalizeExportAnswerValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }
            if (ctype_digit($trimmed)) {
                return (int)$trimmed;
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            return $trimmed;
        }

        if (is_array($value)) {
            return $value;
        }

        return $value;
    }

    private function transformGroupContentForExport($content, array $idToNumber)
    {
        if ($content === null) {
            return null;
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $content = $decoded;
            } else {
                return $this->transformContentStringForExport($content, $idToNumber);
            }
        }

        if ($content instanceof \stdClass) {
            $content = (array)$content;
        }

        if (is_array($content)) {
            $result = [];
            foreach ($content as $k => $v) {
                $result[$k] = $this->transformGroupContentForExport($v, $idToNumber);
            }
            return $result;
        }

        return $content;
    }

    private function transformContentStringForExport(string $text, array $idToNumber): string
    {
        $text = preg_replace_callback('/\\$(\\d+)\\$/', function ($m) use ($idToNumber) {
            $qid = (int)$m[1];
            if (isset($idToNumber[$qid])) {
                return '【' . $idToNumber[$qid] . '】';
            }
            return $m[0];
        }, $text);

        // Remove only &nbsp; entity, keep normal spaces/newlines.
        $text = str_replace('&nbsp;', '', $text);

        return $text;
    }
}

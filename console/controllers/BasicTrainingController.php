<?php

namespace console\controllers;

use app\models\BasicTrainingListeningGrammar;
use app\models\BasicTrainingListeningGrammarQuery;
use app\models\BasicTrainingListeningQuestion;
use app\models\BasicTrainingListeningRecord;
use app\models\BasicTrainingReadingGrammar;
use app\models\BasicTrainingReadingGroup;
use app\models\BasicTrainingReadingQuestion;
use app\models\BasicTrainingWritingGrammar;
use app\models\BasicTrainingWritingGroup;
use app\models\BasicTrainingWritingQuestion;
use app\models\BasicTrainingWritingRecord;
use app\models\BasicTrainingWritingTopic;
use app\models\ExamCollectionPage;
use app\models\ExamCollectionRecord;
use app\models\ExamCollectionRelation;
use app\models\ExamQuestionCollection;
use app\models\ListeningExamQuestionType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use yii\console\Controller;
use yii\log\Logger;

class BasicTrainingController extends BaseController
{
    public function actionInitWriting($url)
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

        $grammar_list = $this->getWritingGrammar();
        $topic_list = $this->getWritingTopic();
        $type_list = $this->getWritingType();

        foreach ($arr as $value) {
            if ($type_list[$value->group_type] != 3) {
                continue;
            }
            $group = BasicTrainingWritingGroup::find()->where(["source_id" => $value->group_id])->one();
            if (empty($group)) {
                var_dump("$value->header 不存在，新增");
                //                $group = new BasicTrainingWritingGroup();
                //                $group->title = $value->header;
                //                $group->source_id = $value->group_id;
                //                $group->type = $type_list[$value->group_type];
                //                $group->grammar = $grammar_list[$group->type][$value->grammar];
                //                $group->topic = $topic_list[$value->topic];
                //                $group->difficulty = $value->difficulty;
                //                try {
                //                    $group->insert();
                //                } catch (\Throwable $e) {
                //                    var_dump("$value->header 插入数据失败" . $e->getMessage());
                //                    die;
                //                }
            } else {
                $group->title = $value->header;
                $group->save(false);
                var_dump("$value->header 已存在, 更新成功");
            }

            $question = BasicTrainingWritingQuestion::find()->where(['step' => $value->step, 'group_id' => $group->id])->one();
            if (empty($question)) {
                $question = new BasicTrainingWritingQuestion();
                $question->group_id = $group->id;

                if ($group->type == 1) {
                    $question->stem = $value->context->translation;
                    $question->answer = $value->context->answer;
                    $question->content = ['question_stem' => $value->context->question_stem, 'word_translation' => $value->context->word_translation];
                } elseif ($group->type == 2) {
                    $question->answer = [$value->context->answer];
                    $question->stem = $value->context->question_stem;
                    $question->content = [];
                } elseif ($group->type == 3) {
                    $question->stem = $value->context->question_stem;
                    $question->answer = [$value->context->answer];
                    $question->content = [];
                } elseif ($group->type == 4) {
                    $question->answer = [$value->context->answer];
                    $question->content = $value->context->question_stem;
                } elseif ($group->type == 5) {
                    $question->stem = $value->context->rewrite_stem;
                    $question->answer = [$value->context->answer];
                    $question->content = ["question_stem" => $value->context->question_stem, "highlighted_part" => $value->context->highlighted_part];
                }

                try {
                    $question->insert(false);
                } catch (\Throwable $e) {
                    var_dump("$value->header ,$value->group_id 插入题目失败" . $e->getMessage());
                }
                if (empty($question->id)) {
                    var_dump("$value->header ,$value->group_id 插入失败");
                    die;
                }
                var_dump("$value->header ,$value->group_id ,$question->id 新增成功 ");
            } else {
                //                if ($group->type == 1) {
                //                    $question->stem = $value->context->translation;
                //                    $question->answer = $value->context->answer;
                //                    $question->content = ['question_stem'=>$value->context->question_stem,'word_translation'=>$value->context->word_translation];
                //                }elseif ($group->type == 2){
                //                    $question->answer = [$value->context->answer];
                //                    $question->stem = $value->context->question_stem;
                //                    $question->content = [];
                //                } elseif ($group->type == 3) {
                //                    $question->stem = $value->context->question_stem;
                //                    $question->answer = [$value->context->answer];
                //                    $question->content = [];
                //                } elseif ($group->type == 4) {
                //                    $question->answer = [$value->context->answer];
                //                    $question->content = $value->context->question_stem;
                //                } elseif ($group->type == 5) {
                //                    $question->stem = $value->context->rewrite_stem;
                //                    $question->answer = [$value->context->answer];
                //                    $question->content = ["question_stem"=>$value->context->question_stem, "highlighted_part"=>$value->context->highlighted_part];
                //                }
                if ($group->type == 3) {
                    $question->stem = $value->context->question_stem;
                    $question->answer = [$value->context->answer];
                    $question->content = [];
                    $question->save(false);
                    var_dump("question $question->id , $value->step 已存在,更新成功");
                }
            }
        }
    }

    public function actionInitWritingCollocation()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/writing_collocation_pair_single_selecion_questions.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);

        if (empty($content)) {
            \Yii::getLogger()->log("初始化数据为空", Logger::LEVEL_ERROR);
            exit('初始化数据为空');
        }


        $grammar_list = $this->getWritingGrammar();
        $topic_list = $this->getWritingTopic();
        $type_list = $this->getWritingType();
        $sub_type = 2;

        foreach ($content as $value) {
            $group = BasicTrainingWritingGroup::find()->where(["source_id" => $value->group_id])->one();
            if (empty($group)) {
                var_dump("$value->header 不存在，新增");
                $group = new BasicTrainingWritingGroup();
                $group->title = $value->header;
                $group->source_id = $value->group_id;
                $group->type = $type_list[$value->group_type];
                $group->sub_type = $sub_type;
                $group->grammar = $grammar_list[$group->type . $sub_type][$value->grammar];
                $group->difficulty = $value->difficulty;
                try {
                    $group->insert();
                } catch (\Throwable $e) {
                    var_dump("$value->header 插入数据失败" . $e->getMessage());
                    die;
                }
            } else {
                // $group->title = $value->header;
                // $group->save(false);
                var_dump("$value->header 已存在, 更新成功");
            }

            $question = BasicTrainingWritingQuestion::find()->where(['step' => $value->step, 'group_id' => $group->id])->one();
            if (empty($question)) {
                $question = new BasicTrainingWritingQuestion();
                $question->group_id = $group->id;
                $question->stem = $value->context->question_stem;
                foreach ($value->context->options as $k => $v) {
                    if ($v->name == $value->context->answer) {
                        $question->answer = [$k];
                    }
                }
                $question->content = $value->context->options;

                try {
                    $question->insert(false);
                } catch (\Throwable $e) {
                    var_dump("$value->header ,$value->group_id 插入题目失败" . $e->getMessage());
                }
                if (empty($question->id)) {
                    var_dump("$value->header ,$value->group_id 插入失败");
                    die;
                }
                var_dump("$value->header ,$value->group_id ,$question->id 新增成功 ");
            } else {
                var_dump("question $value->step 已存在");
            }
        }
    }

    public function actionInitReading($url)
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

        $grammar_list = $this->getReadingGrammar();
        $type_list = $this->getReadingType();
        $sub_type_list = $this->getReadingSubType();

        foreach ($arr as $value) {
            $group = BasicTrainingReadingGroup::find()->where(["source_id" => $value->group_id, "title" => $value->header])->one();
            //            $group = BasicTrainingReadingGroup::find()->where([ "title" => $value->header, "type" => $type_list[$value->group_type]])->one();
            if (empty($group)) {
                var_dump("$value->header 不存在，新增");
                $group = new BasicTrainingReadingGroup();
                $group->title = $value->header;
                $group->source_id = $value->group_id;
                $group->type = $type_list[$value->group_type];
                if (!empty($value->grammar)) {
                    $group->grammar = $grammar_list[$group->type][$value->grammar];
                }
                $group->difficulty = $value->difficulty;
                try {
                    $group->insert();
                } catch (\Throwable $e) {
                    var_dump("$value->header 插入数据失败" . $e->getMessage());
                    die;
                }
            } else {
                //                $group->title = $value->header;
                //                if (!empty($value->grammar)) {
                //                    $group->grammar = $grammar_list[$group->type][$value->grammar];
                //                }
                //                $group->save(false);
                var_dump("$value->header 已存在");
            }

            $question = BasicTrainingReadingQuestion::find()->where(['step' => $value->step, 'group_id' => $group->id])->one();
            if (empty($question)) {
                $question = new BasicTrainingReadingQuestion();
                $question->group_id = $group->id;

                if ($group->type == 1) {
                    if ($value->group_type != $value->question_type) {
                        $question->type = $sub_type_list[$value->question_type];
                        if ($question->type == 1) {
                            $question->locating_words = [];
                            $answer_arr = [];
                            $question_stem = array_flip($value->context->question_stem);
                            foreach ($value->context->answer as $val) {
                                $answer_arr[] = $question_stem[$val];
                            }
                            $question->answer = $answer_arr;
                            $question->content = $value->context->question_stem;
                        } elseif ($question->type == 2) {
                            $question->locating_words = array_column($value->context->locating_words, 'locating_word');
                            $question->stem = $value->context->question_stem;
                            $question->content = $value->context->paragraph_split_by_sentence;
                            $central_sentences = $value->context->central_sentences[0];
                            $content = array_flip($value->context->paragraph_split_by_sentence);
                            $question->answer = [$content[$central_sentences]];
                        } elseif ($question->type == 3) {
                            $question->stem = $value->context->question_stem;
                            $question->content = $value->context->answer_sentence_split_by_words;
                            $question->locating_words = array_column($value->context->locating_words, 'locating_word');
                            $answer_arr = [];
                            $content = array_flip($value->context->answer_sentence_split_by_words);
                            foreach ($value->context->locating_words as $v) {
                                $answer_arr[] = $content[$v->syn_ary[0]];
                            }
                            $question->answer = $answer_arr;
                        }
                        $question->step = $value->step;
                    }
                } elseif ($group->type == 2) {
                    $question->stem = $value->context->question_stem;
                    $question->translation = $value->context->translation;
                    $question->content = $value->context->single_word_translation;
                    $question->locating_words = $value->context->options;
                    foreach ($value->context->options as $k => $v) {
                        if ($v->name == $value->context->answer) {
                            $question->answer = [$k];
                        }
                    }
                } elseif ($group->type == 3) {
                    $question->stem = $value->context->question_stem;
                    $question->answer = [$value->context->answer];
                    $question->content = $value->context->single_word_translation;
                    $question->locating_words = [];
                } elseif ($group->type == 4) {
                    $question->step = $value->step;
                    $question->stem = $value->context->question_stem;
                    $question->content = $value->context->paragraph;
                    $answer = [];
                    $locating = [];
                    foreach ($value->context->locating_words as $val) {
                        $answer[] = $val->syn_ary[0];
                        $locating[] = $val->locating_word;
                    }
                    $question->answer = $answer;
                    $question->locating_words = $locating;
                    $question->answer_count = count($answer);
                }

                try {
                    $question->insert(false);
                } catch (\Throwable $e) {
                    var_dump("$value->header ,$value->group_id 插入题目失败" . $e->getMessage());
                }
                var_dump("$value->header ,$value->group_id ,$question->id 新增成功 ");
            } else {
                //                if ($group->type == 1) {
                //                    if ($value->group_type != $value->question_type) {
                //                        $is_save = false;
                //                        $question->type = $sub_type_list[$value->question_type];
                ////                        if ($question->type == 2) {
                ////                            $question->locating_words = array_column($value->context->locating_words,'locating_word');
                ////                            $is_save = true;
                ////                        } else
                //                            if ($question->type == 3) {
                //                            $answer_arr = [];
                //                            $content = array_flip($value->context->answer_sentence_split_by_words);
                //                            foreach ($value->context->locating_words as $v) {
                //                                $answer_arr[] = $content[$v->syn_ary[0]];
                //                            }
                //                            $question->answer = $answer_arr;
                //                            $question->content = $value->context->answer_sentence_split_by_words;
                //                            $question->locating_words = array_column($value->context->locating_words,'locating_word');
                //                            $is_save = true;
                //                        }
                //                        if ($is_save) {
                //                            $question->save(false);
                //                            var_dump("question $question->id 保存完成");
                //                        }
                //                    }
                //                }
                //                else
                if ($group->type == 2) {
                    //                    $question->translation = $value->context->translation;
                    $question->content = $value->context->single_word_translation;
                    $question->save(false);
                    var_dump("question $question->id 保存完成");
                }

                var_dump("question $value->step 已存在");
            }
        }
    }

    public function actionInitReadingSimpleSentence()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/清明节版本-阅读-简单句翻译练习.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);

        $grammar = 45;
        $type = 3;
        foreach ($content as $value) {
            $group = BasicTrainingReadingGroup::find()->where(["type" => $type, "grammar" => $grammar, "title" => $value->header])->one();
            if (empty($group)) {
                var_dump("$value->header 不存在，新增");
                $group = new BasicTrainingReadingGroup();
                $group->title = $value->header;
                $group->grammar = $grammar;
                $group->type = $type;
                $group->difficulty = $value->difficulty;
                try {
                    $group->insert();
                } catch (\Throwable $e) {
                    var_dump("$value->header 插入数据失败" . $e->getMessage());
                    die;
                }
            } else {
                var_dump("$value->header 已存在");
            }

            $question = BasicTrainingReadingQuestion::find()->where(['group_id' => $group->id])->one();
            if (empty($question)) {
                $question = new BasicTrainingReadingQuestion();
                $question->group_id = $group->id;
                $question->stem = $value->question_stem;
                $question->answer = [$value->answer];
                $question->content = $value->single_word_translation;
                $question->locating_words = [];
                try {
                    $question->insert(false);
                    var_dump("question $value->header ,$question->id 新增成功 ");
                } catch (\Throwable $e) {
                    var_dump("$value->header ,$group->id 插入题目失败" . $e->getMessage());
                    die;
                }
            } else {
                var_dump("question $value->header 已存在");
            }
        }
    }

    public function actionInitReadingSynonymousSubstitution()
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/清明节版本-阅读替换词题目-拆解添加符号.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);

        $grammar_list = $this->getReadingGrammarNameMap();
        $type = 6;

        foreach ($content as $value) {
            $group = BasicTrainingReadingGroup::find()->where(["source_id" => $value->id, "type" => $type])->one();
            if (empty($group)) {
                var_dump("$value->id 不存在，新增");
                $group = new BasicTrainingReadingGroup();
                $group->source_id = $value->id;
                $group->type = $type;
                if (!empty($value->group)) {
                    $group->grammar = $grammar_list[$group->type][$value->group];
                }
                try {
                    $group->insert();
                } catch (\Throwable $e) {
                    var_dump("$value->id 插入数据失败" . $e->getMessage());
                    die;
                }
            } else {
                var_dump("$value->id 已存在");
            }

            $question = BasicTrainingReadingQuestion::find()->where(['group_id' => $group->id])->one();
            if (empty($question)) {
                $question = new BasicTrainingReadingQuestion();
                $question->group_id = $group->id;
                $question->stem = $value->ai_res1->sentence;
                $question->content = $value->ai_res2->extract;
                $question->answer = [$value->word2];
                $question->locating_words = [$value->word1];
                $question->answer_count = 1;

                try {
                    $question->insert(false);
                } catch (\Throwable $e) {
                    var_dump("$value->id ,$group->id 插入题目失败" . $e->getMessage());
                }
                var_dump("$value->id ,$group->id ,$question->id 新增成功 ");
            } else {
                $question->content = $value->ai_res2->extract;
                $question->save(false);
                var_dump("question $value->id 已存在");
            }
        }
    }

    public function actionFixReading($id = 0)
    {
        $num = 10;
        while ($list = BasicTrainingReadingQuestion::find()->where(['<', 'id', $id])->orderBy('id desc')->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                $value->answer_count = count($value->answer);
                $value->save(false);
                var_dump("id:$value->id 更新成功");
            }
        }
    }

    public function actionFixWriting($id = 0)
    {
        $num = 10;
        while ($list = BasicTrainingWritingQuestion::find()->where(['>', 'id', $id])->andWhere(['<=', 'group_id', 9980])->andWhere(['>=', 'group_id', 9501])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                var_dump($value->answer);
                $answer = $value->answer;
                $value->stem = str_replace("**$answer[0]**", str_replace(" ", "", "**$answer[0]**"), $value->stem);
                $value->save(false);
                var_dump("$id 处理完成");
            }
        }
    }

    public function actionFixListening($id = 0)
    {
        $num = 10;
        while ($list = BasicTrainingListeningQuestion::find()->where(['>', 'id', $id])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                if (!empty($value->context)) {
                    $arr = [];
                    foreach ($value->context as $k => $v) {
                        $answer = [];
                        if (!empty($v['answer'])) {
                            foreach ($v['answer'] as $a) {
                                $answer[] = strval($a);
                            }
                        }
                        $v['answer'] = $answer;
                        $arr[$k] = $v;
                    }
                    $value->context = $arr;
                    $value->save(false);
                    var_dump("id:$value->id 更新成功");
                }
                var_dump($value->context);
            }
        }
    }

    public function actionFixListeningRecord($id = 0)
    {
        $num = 10;

        while ($list = BasicTrainingListeningQuestion::find()->where(['>', 'id', $id])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                $total = count($value->answer);
                BasicTrainingListeningRecord::updateAll(['total' => $total], "question_id = $value->id and status = 2 and total = 0");
                var_dump("更新 question_id = $value->id,total = $total 完成");
            }
        }

        die;
        while ($list = BasicTrainingListeningRecord::find()->where(['>', 'id', $id])->andWhere(['status' => 2])->limit($num)->all()) {
            foreach ($list as $value) {
                $id = $value->id;
                $question = BasicTrainingListeningQuestion::find()->where(['id' => $value->question_id])->one();
                $answer = $question->answer;
                $sub_answer = json_decode($value->sub_answer);
                $value->total = is_array($answer) ? count($answer) : 0;
                $value->save(false);
                var_dump("$id 更新完成");
                continue;
                $result = [];
                if (empty($sub_answer)) {
                    foreach ($answer as $val) {
                        $result[] = 2;
                    }
                    $value->result = json_encode($result);
                    $value->save(false);
                    var_dump("$value->id 处理完成");
                    continue;
                }
                if (count($sub_answer) == count($answer)) {
                    foreach ($sub_answer as $key => $val) {
                        if (empty($val) && $val != '0') {
                            $result[] = 2;
                        } else {
                            $val = str_replace(" ", "", str_replace(",", "", $val));
                            $is_correct = 2;
                            if (is_numeric($val)) {
                                foreach ($answer[$key] as $v) {
                                    $v = str_replace(" ", "", str_replace(",", "", $v));
                                    if ($val == $v) {
                                        $is_correct = 1;
                                        break;
                                    }
                                }
                            } else {
                                $val = strtolower($val);
                                foreach ($answer[$key] as $v) {
                                    $v = strtolower(str_replace(" ", "", str_replace(",", "", $v)));
                                    if ($val == $v) {
                                        $is_correct = 1;
                                        break;
                                    }
                                }
                            }
                            $result[] = $is_correct;
                        }
                    }
                } else {
                    foreach ($sub_answer as $key => $val) {
                        if (empty($val)) {
                            $result[] = 2;
                        } else {
                            $val = str_replace(" ", "", str_replace(",", "", $val));
                            $is_correct = 2;
                            if (is_numeric($val)) {
                                foreach ($answer[$key] as $v) {
                                    $v = str_replace(" ", "", str_replace(",", "", $v));
                                    if ($val == $v) {
                                        $is_correct = 1;
                                        break;
                                    }
                                }
                            } else {
                                $val = strtolower($val);
                                foreach ($answer[$key] as $v) {
                                    $v = strtolower(str_replace(" ", "", str_replace(",", "", $v)));
                                    if ($val == $v) {
                                        $is_correct = 1;
                                        break;
                                    }
                                }
                            }
                            $result[] = $is_correct;
                        }
                    }
                    foreach ($answer as $kk => $vv) {
                        if (!isset($sub_answer[$kk])) {
                            $result[] = 2;
                        }
                    }
                }
                $value->result = json_encode($result);
                $value->save(false);
                var_dump("$value->id 处理完成");
            }
        }
    }

    public function actionFixListeningAnswer($url)
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

        foreach ($arr as $value) {
            $info = BasicTrainingListeningQuestion::find()->where(["id" => $value->id])->one();
            if (!empty($info)) {
                $info->answer = $value->answer;
                $info->save(false);
                var_dump("更新 $value->id 成功");
            } else {
                var_dump("$value->id 不存在");
            }
        }
    }

    public function actionInitListening($url)
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

        $grammar_list = $this->getListeningGrammar();
        $type_list = $this->getListeningType();

        $audio_path = "/exercises/basic_training/multi_turn_dialogue/";
        foreach ($arr as $value) {
            $header = str_replace("类", "", $value->header);
            $group = BasicTrainingListeningQuestion::find()->where(["source_id" => $value->id, "title" => $header, "type" => 3])->one();
            if (empty($group)) {
                var_dump("$value->header 不存在，新增");
                $group = new BasicTrainingListeningQuestion();
                $group->title = $header;
                $group->source_id = $value->id;
                $group->type = 3;
                $group->grammar = $grammar_list[$group->type][str_replace("类", "", $value->type)];
                $group->audio_url = $audio_path . $value->id . '.mp3';
                $content_arr = [];
                $content = $value->context->conversation;
                $answer = [];
                foreach ($content as $val) {
                    $content_arr[] = $val->sentence;
                    if (!empty($val->answer)) {
                        foreach ($val->answer as $v) {
                            $sub_answer = [];
                            foreach ($v as $vv) {
                                $sub_answer[] = strval($vv);
                            }
                            $answer[] = $sub_answer;
                        }
                    }
                }
                $group->answer = $answer;
                $group->context = $content;
                $group->content = $content_arr;
                try {
                    $group->insert(false);
                } catch (\Throwable $e) {
                    var_dump("$value->header 插入数据失败" . $e->getMessage());
                    die;
                }
                var_dump("$value->header 新增成功");
            } else {
                $content = $value->context->conversation;
                $answer = [];
                foreach ($content as $val) {
                    $content_arr[] = $val->sentence;
                    if (!empty($val->answer)) {
                        foreach ($val->answer as $v) {
                            $sub_answer = [];
                            foreach ($v as $vv) {
                                $sub_answer[] = strval($vv);
                            }
                            $answer[] = $sub_answer;
                        }
                    }
                }
                $group->answer = $answer;
                //                $group->audio_url = $audio_path . $value->id . '.mp3';
                $group->save(false);
                var_dump("$value->header 已存在, 更新成功");
            }
        }
    }

    /**
     * 导出听力题目（question_type != 2）的类型、语法、标题、内容到 Excel。
     * 示例：php yii basic-training/export-listening-questions "@runtime/tmp/listening_questions.xlsx"
     */
    public function actionExportListeningQuestions($output = '@runtime/tmp/listening_questions.xlsx')
    {
        $outputPath = \Yii::getAlias($output);
        $directory = dirname($outputPath);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("无法创建导出目录：{$directory}");
        }

        $query = BasicTrainingListeningQuestion::find()
            ->alias('q')
            ->select([
                'q.type',
                'q.title',
                'q.content',
                'grammar_name' => 'g.name',
            ])
            ->leftJoin(BasicTrainingListeningGrammar::tableName() . ' g', 'g.id = q.grammar')
            ->andWhere(['<>', 'q.question_type', 2])
            ->orderBy(['q.id' => SORT_ASC])
            ->asArray();

        $typeLabels = $this->getListeningTypeLabels();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('听力题目');

        $headers = [
            'A1' => '类型',
            'B1' => '语法/场景',
            'C1' => '标题',
            'D1' => '内容',
        ];
        foreach ($headers as $cell => $title) {
            $sheet->setCellValue($cell, $title);
        }

        $row = 2;
        $exported = 0;
        foreach ($query->each(200) as $question) {
            $exported++;
            $typeLabel = $typeLabels[$question['type']] ?? (string)$question['type'];
            $sheet->setCellValue('A' . $row, $typeLabel);
            $sheet->setCellValue('B' . $row, $question['grammar_name'] ?? '');
            $sheet->setCellValue('C' . $row, $question['title'] ?? '');
            $sheet->setCellValue('D' . $row, $this->normalizeContentForExport($question['content']));
            $row++;
        }

        if ($exported === 0) {
            echo "没有符合条件的题目，已仅导出表头。\n";
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        echo "导出成功，文件位置：{$outputPath}，共 {$exported} 条记录。\n";
    }

    public function actionFixReadingScanning()
    {
        $title_num = 0;
        $group_id = 1;
        while ($list = BasicTrainingReadingGroup::find()->where(['type' => 4, 'difficulty' => 3])->andWhere(['>', 'id', $group_id])->all()) {
            foreach ($list as $value) {
                $group_id = $value->id;
                $question_list = BasicTrainingReadingQuestion::find()->where(['group_id' => $value->id])->all();
                foreach ($question_list as $val) {
                    $title_num += 1;
                    $group = new BasicTrainingReadingGroup();
                    $group->type = 5;
                    $group->difficulty = $value->difficulty;
                    $group->title = $title_num > 9 ? '困难 ' . $title_num : '困难 0' . $title_num;
                    $group->status = 1;
                    $group->source_id = $value->source_id;
                    $group->insert(false);

                    $question = new BasicTrainingReadingQuestion();
                    $question->group_id = $group->id;
                    $question->answer_count = $val->answer_count;
                    $question->stem = $val->stem;
                    $question->answer = $val->answer;
                    $question->content = $val->content;
                    $question->locating_words = $val->locating_words;
                    $question->insert(false);

                    var_dump("原group_id = $value->id ，question_id = $val->id ;新group_id = $group->id ，question_id = $question->id 插入完成");
                }
            }
        }
        var_dump("数据处理完成");
    }

    public function actionInitCollectionWriting()
    {
        $list = BasicTrainingWritingGroup::find()->select(['type', 'grammar', 'topic', 'difficulty'])->groupBy(['type', 'grammar', 'topic', 'difficulty'])->asArray()->all();
        foreach ($list as $value) {
            $collection = new ExamQuestionCollection();
            $collection->type = 1;
            $collection->question_type = $value['type'];
            $collection->grammar = $value['grammar'];
            $collection->topic = $value['topic'];
            $collection->difficulty = $value['difficulty'];
            $collection->insert(false);

            $question_list = BasicTrainingWritingGroup::find()->where(['type' => $value['type'], 'grammar' => $value['grammar'], 'topic' => $value['topic'], 'difficulty' => $value['difficulty']])->asArray()->all();
            foreach ($question_list as $val) {
                $collection_relation = new ExamCollectionRelation();
                $collection_relation->collection_id = $collection->id;
                $collection_relation->question_id = $val['id'];
                $collection_relation->insert(false);
                var_dump("group_id = " . $val['id'] . ",collection_id = " . $collection->id . "插入成功");
            }

            var_dump("type=" . $value['type'] . ",grammar=" . $value['grammar'] . ",topic=" . $value['topic'] . ",difficulty=" . $value['difficulty'] . "处理完成");
        }
        var_dump("全部处理完成");
    }

    public function actionInitCollectionWritingCollocations()
    {
        $grammar_list = $this->getWritingGrammarMap();
        $list = BasicTrainingWritingGroup::find()->select(['type', 'sub_type', 'grammar'])->where(['type' => 6])->groupBy(['type', 'sub_type', 'grammar'])->asArray()->all();
        foreach ($list as $value) {
            $page = 1;
            $id = 0;
            while ($question_list = BasicTrainingWritingGroup::find()->where(['type' => $value['type'], 'sub_type' => $value['sub_type'], 'grammar' => $value['grammar']])->andWhere(['>', 'id', $id])->limit(10)->asArray()->all()) {
                if (count($question_list) < 10) {
                    break;
                }
                //生成新搜索条件
                $new_page = new ExamCollectionPage();
                $new_page->name = $page > 9 ? $grammar_list[$value['type'] . $value['sub_type']][$value['grammar']] . ' ' . $page : $grammar_list[$value['type'] . $value['sub_type']][$value['grammar']] . ' 0' . $page;
                $new_page->grammar = $value['grammar'];
                $new_page->type = 1;
                $new_page->question_type = $value['type'];
                $new_page->question_sub_type = $value['sub_type'];
                $new_page->insert(false);

                //生成题集
                $collection = new ExamQuestionCollection();
                $collection->type = 1;
                $collection->question_type = $value['type'];
                $collection->grammar = $value['grammar'];
                $collection->question_sub_type = $value['sub_type'];
                $collection->page = $new_page->id;
                $collection->insert(false);

                foreach ($question_list as $val) {
                    $id = $val['id'];
                    $collection_relation = new ExamCollectionRelation();
                    $collection_relation->collection_id = $collection->id;
                    $collection_relation->question_id = $val['id'];
                    $collection_relation->insert(false);
                    var_dump("group_id = " . $val['id'] . ",collection_id = " . $collection->id . ",page=" . $page . "插入成功");
                }
                $page++;
            }

            var_dump("type=" . $value['type'] . ",grammar=" . $value['grammar'] . ",sub_type=" . $value['sub_type'] . "处理完成");
        }
        var_dump("全部处理完成");
    }

    public function actionInitCollectionListening()
    {
        $list = BasicTrainingListeningQuestion::find()->select(['type', 'grammar'])->groupBy(['type', 'grammar'])->asArray()->all();
        foreach ($list as $value) {
            //获取语法分类列表
            $grammar_list = BasicTrainingListeningGrammar::find()->where(['type' => $value['type']])->indexBy('id')->asArray()->all();
            $page = 1;
            $id = 0;
            while ($question_list = BasicTrainingListeningQuestion::find()->where(['type' => $value['type'], 'grammar' => $value['grammar']])->andWhere(['>', 'id', $id])->limit(10)->asArray()->all()) {
                if (count($question_list) < 10) {
                    break;
                }
                //生成新搜索条件
                $new_page = new ExamCollectionPage();
                $new_page->name = $page > 9 ? $grammar_list[$value['grammar']]['name'] . ' ' . $page : $grammar_list[$value['grammar']]['name'] . ' 0' . $page;
                $new_page->grammar = $value['grammar'];
                $new_page->type = 3;
                $new_page->question_type = $value['type'];
                $new_page->insert(false);

                //生成题集
                $collection = new ExamQuestionCollection();
                $collection->type = 3;
                $collection->question_type = $value['type'];
                $collection->grammar = $value['grammar'];
                $collection->page = $new_page->id;
                $collection->insert(false);

                foreach ($question_list as $val) {
                    $id = $val['id'];
                    $collection_relation = new ExamCollectionRelation();
                    $collection_relation->collection_id = $collection->id;
                    $collection_relation->question_id = $val['id'];
                    $collection_relation->insert(false);
                    var_dump("group_id = " . $val['id'] . ",collection_id = " . $collection->id . ",page=" . $page . "插入成功");
                }
                $page++;
            }
            var_dump("type=" . $value['type'] . ",grammar=" . $value['grammar'] . "处理完成");
        }
        var_dump("全部处理完成");
    }

    public function actionInitCollectionReading()
    {
        $grammar_list = [
            46 => '同义词',
            47 => '同根词',
            48 => '上下义词',
            49 => '反义词'
        ];
        $list = BasicTrainingReadingGroup::find()->select(['type', 'grammar', 'difficulty'])->where(['type' => 6])->groupBy(['type', 'grammar', 'difficulty'])->asArray()->all();
        foreach ($list as $value) {
            if ($value['type'] == 1) {
                //定位练习，一题为一个题集
                $question_list = BasicTrainingReadingGroup::find()->where(['type' => $value['type'], 'difficulty' => $value['difficulty']])->asArray()->all();
                foreach ($question_list as $val) {
                    //生成新搜索条件
                    $new_page = new ExamCollectionPage();
                    $new_page->name = $val['title'];
                    $new_page->difficulty = $value['difficulty'];
                    $new_page->type = 2;
                    $new_page->question_type = $value['type'];
                    $new_page->insert(false);

                    //生成题集
                    $collection = new ExamQuestionCollection();
                    $collection->type = 2;
                    $collection->question_type = $value['type'];
                    $collection->difficulty = $value['difficulty'];
                    $collection->page = $new_page->id;
                    $collection->insert(false);

                    $collection_relation = new ExamCollectionRelation();
                    $collection_relation->collection_id = $collection->id;
                    $collection_relation->question_id = $val['id'];
                    $collection_relation->insert(false);
                    var_dump("group_id = " . $val['id'] . ",collection_id = " . $collection->id . ",page=" . $new_page->name . "插入成功");
                }
            } elseif ($value['type'] == 5) {
                $page = 1;
                $id = 0;
                while ($question_list = BasicTrainingReadingGroup::find()->where(['type' => $value['type'], 'difficulty' => $value['difficulty']])->andWhere(['>', 'id', $id])->limit(10)->asArray()->all()) {
                    if (count($question_list) < 10) {
                        break;
                    }
                    //生成新搜索条件
                    $new_page = new ExamCollectionPage();
                    $new_page->name = $page > 9 ? $grammar_list[$value['grammar']] . ' ' . $page : $grammar_list[$value['grammar']] . ' 0' . $page;
                    $new_page->difficulty = $value['difficulty'];
                    $new_page->type = 2;
                    $new_page->question_type = $value['type'];
                    $new_page->insert(false);

                    //生成题集
                    $collection = new ExamQuestionCollection();
                    $collection->type = 2;
                    $collection->question_type = $value['type'];
                    $collection->difficulty = $value['difficulty'];
                    $collection->page = $new_page->id;
                    $collection->insert(false);

                    foreach ($question_list as $val) {
                        $id = $val['id'];
                        $collection_relation = new ExamCollectionRelation();
                        $collection_relation->collection_id = $collection->id;
                        $collection_relation->question_id = $val['id'];
                        $collection_relation->insert(false);
                        var_dump("group_id = " . $val['id'] . ",collection_id = " . $collection->id . ",page=" . $page . "插入成功");
                    }
                    $page++;
                }
            } else {
                $page = 1;
                $id = 0;
                while ($question_list = BasicTrainingReadingGroup::find()->where(['type' => $value['type'], 'difficulty' => $value['difficulty'], 'grammar' => $value['grammar']])->andWhere(['>', 'id', $id])->limit(10)->asArray()->all()) {
                    if (count($question_list) < 10) {
                        break;
                    }
                    //生成新搜索条件
                    $new_page = new ExamCollectionPage();
                    $new_page->name = $page > 9 ? $grammar_list[$value['grammar']] . ' ' . $page : $grammar_list[$value['grammar']] . ' 0' . $page;
                    $new_page->grammar = $value['grammar'];
                    $new_page->difficulty = $value['difficulty'];
                    $new_page->type = 2;
                    $new_page->question_type = $value['type'];
                    $new_page->insert(false);

                    //生成题集
                    $collection = new ExamQuestionCollection();
                    $collection->type = 2;
                    $collection->question_type = $value['type'];
                    $collection->grammar = $value['grammar'];
                    $collection->difficulty = $value['difficulty'];
                    $collection->page = $new_page->id;
                    $collection->insert(false);

                    foreach ($question_list as $val) {
                        $id = $val['id'];
                        $collection_relation = new ExamCollectionRelation();
                        $collection_relation->collection_id = $collection->id;
                        $collection_relation->question_id = $val['id'];
                        $collection_relation->insert(false);
                        var_dump("group_id = " . $val['id'] . ",collection_id = " . $collection->id . ",page=" . $page . "插入成功");
                    }
                    $page++;
                }
            }

            var_dump("type=" . $value['type'] . ",grammar=" . $value['grammar'] . ',difficulty=' . $value['difficulty'] . "处理完成");
        }
        var_dump("全部处理完成");
    }

    public function actionInitListeningBasicScenario(): void
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/清明节前-听力PDF解析题目-格式化后 2.json';
        $question_content = file_get_contents($file);
        $content = json_decode($question_content);
        $question_type = [
            '填空题' => 1,
            '选择题' => 2,
        ];
        foreach ($content as $value) {
            $audio_url = '/exercises/basic_training/listening/basic-scenario/P' . $value->page . '.mp3';
            $unit = BasicTrainingListeningGrammar::find()->where(['name' => $value->unit])->one();
            if (empty($unit)) {
                $unit = new BasicTrainingListeningGrammar();
                $unit->name = $value->unit;
                $unit->type = 5;
                $unit->insert(false);
            } else {
                var_dump("unit_id = " . $unit->id . "已存在");
            }
            $sub_unit = BasicTrainingListeningGrammar::find()->where(['name' => $value->listening, 'pid' => $unit->id])->one();
            if (empty($sub_unit)) {
                $sub_unit = new BasicTrainingListeningGrammar();
                $sub_unit->name = $value->listening;
                $sub_unit->pid = $unit->id;
                $sub_unit->type = 5;
                $sub_unit->insert(false);
            } else {
                var_dump("sub_unit_id = " . $sub_unit->id . "已存在");
            }
            $grammar_id = $sub_unit->id;
            $question = BasicTrainingListeningQuestion::find()->where(['type' => 5, 'question_type' => $question_type[$value->type], 'grammar' => $grammar_id])->one();
            if (empty($question)) {
                $question = new BasicTrainingListeningQuestion();
                $question->type = 5;
                $question->question_type = $question_type[$value->type];
                $question->grammar = $grammar_id;
                $question->audio_url = $audio_url;
                $question->context = [];
                if ($question_type[$value->type] == 1) {
                    $question->content = [$value->ai_res->replaced_text];
                    $question->answer = $value->ai_res->answer;
                } else {
                    $question_content = [];
                    $answer = [];
                    foreach ($value->ai_res->questions->question as $ke => $val) {
                        $question_content[] = [
                            'number' => $val->no,
                            'title' => $val->title,
                            'option' => $val->option,
                        ];
                        foreach ($val->option as $k => $v) {
                            if ($v->label == $val->answer) {
                                $answer[] = $k;
                            }
                        }
                    }
                    $question->answer = $answer;
                    $question->content = $question_content;
                }

                $question->insert(false);
                var_dump("question_id = " . $question->id . "数据插入成功");
            } else {
                $question->audio_url = $audio_url;
                $question->save(false);
                var_dump("question_id = " . $question->id . "已存在");
            }
            var_dump("unit:$value->unit , listening:$value->listening , type:$value->type ,page:$value->page 处理完成");
        }
    }

    public function actionInitCollectionListeningBasicScenario()
    {
        $list = BasicTrainingListeningQuestion::find()->select(['type', 'grammar', 'id'])->where(['type' => 5])->asArray()->all();
        foreach ($list as $value) {
            //生成题集
            $collection = new ExamQuestionCollection();
            $collection->type = 3;
            $collection->question_type = $value['type'];
            $collection->grammar = $value['grammar'];
            $collection->insert(false);

            $collection_relation = new ExamCollectionRelation();
            $collection_relation->collection_id = $collection->id;
            $collection_relation->question_id = $value['id'];
            $collection_relation->insert(false);
            var_dump("group_id = " . $value['id'] . ",collection_id = " . $collection->id  . "插入成功");

            var_dump("type=" . $value['type'] . ",grammar=" . $value['grammar'] . "处理完成");
        }
        var_dump("全部处理完成");
    }

    public function actionFixCollection(): void
    {
        //获取题集列表
        $list = ExamQuestionCollection::find()->Where(['question_num' => 0, 'type' => 1, 'question_type' => 3])->all();
        foreach ($list as $value) {
            //获取题集关联题目
            $relation_list = ExamCollectionRelation::find()->where(['collection_id' => $value->id])->asArray()->all();
            $group_ids = array_column($relation_list, 'question_id');
            //写作
            if ($value->type == 1) {
                $question_list = BasicTrainingWritingQuestion::find()->where(['group_id' => $group_ids])->asArray()->all();
                switch ($value->question_type) {
                    case 1: //连词成句
                        $total_num = 0;
                        foreach ($question_list as $val) {
                            $decoded = json_decode($val['answer'], true);
                            $total_num += count($decoded);
                        }
                        $value->question_num = $total_num;
                        $value->save(false);
                        var_dump("collection_id = $value[id] , total_num = $total_num 保存成功");
                        break;
                    case 3:
                    case 6:
                        $value->question_num = count($question_list);
                        $value->save(false);
                        var_dump("collection_id = $value[id] , total_num = $value->question_num 保存成功");
                        break;
                    default:
                        $value->question_num = count($question_list) * 5;
                        $value->save(false);
                        var_dump("collection_id = $value[id] , total_num = $value->question_num 保存成功");
                        break;
                }
            } else if ($value->type == 2) {
                //阅读
                $question_list = BasicTrainingReadingQuestion::find()->where(['group_id' => $group_ids])->asArray()->all();
                switch ($value->question_type) {
                    case 3: //评分
                        $value->question_num = count($question_list) * 5;
                        $value->save(false);
                        var_dump("collection_id = $value[id] , total_num = $value->question_num 保存成功");
                        break;
                    default:
                        $total_num = 0;
                        foreach ($question_list as $val) {
                            $decoded = json_decode($val['answer'], true);
                            $total_num += count($decoded);
                        }
                        $value->question_num = $total_num;
                        $value->save(false);
                        var_dump("collection_id = $value[id] , total_num = $total_num 保存成功");
                        break;
                }
            } else if ($value->type == 3) {
                //听力
                $question_list = BasicTrainingListeningQuestion::find()->where(['id' => $group_ids])->asArray()->all();
                $total_num = 0;
                foreach ($question_list as $val) {
                    $decoded = json_decode($val['answer'], true);
                    $total_num += count($decoded);
                }
                $value->question_num = $total_num;
                $value->save(false);
                var_dump("collection_id = $value[id] , total_num = $total_num 保存成功");
            }
        }
        return;
    }

    public function actionFixCollectionRecord(): void
    {
        $list = ExamQuestionCollection::find()->select('id')->where(['type' => 1, 'question_type' => 1])->asArray()->all();
        if (empty($list)) {
            var_dump("无数据处理");
            die;
        }

        $ids = array_column($list, 'id');
        $record_list = ExamCollectionRecord::find()->where(['status' => 2, 'collection_id' => $ids])->all();
        foreach ($record_list as $value) {
            $value->correct = 0;
            $value->total = 10;
            $sub_list = BasicTrainingWritingRecord::find()->where(['collection_record_id' => $value->id])->asArray()->all();
            foreach ($sub_list as $val) {
                if ($val['correct'] == 1) {
                    $value->correct += 1;
                }
            }
            $value->rate = $value->correct / $value->total;
            // var_dump($value);die;
            $value->save(false);
            var_dump("更新 $value->id 成功");
        }
        var_dump("处理完成");
    }

    public function actionFixReadingLianCi(): void
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/同义替换数据.csv';
        $content = $this->getDataByCsv($file);
        foreach ($content as $key => $value) {
            $info = BasicTrainingReadingQuestion::find()->where(['id' => $value['id']])->one();
            $info->answer = json_decode($value['answer']);
            $info->content = json_decode($value['content']);
            $info->locating_words = json_decode($value['locating_words']);
            $info->save(false);
            var_dump("$info->id 保存成功");
        }
    }

    public function actionGetCollectionList(): void
    {
        $data = [];
        $writing_grammar = $this->getWritingGrammarMap();
        $writing_topic = $this->getWritingTopicMap();
        $reading_grammar = $this->getReadingGrammarMap();
        $listening_grammar = $this->getListeningGrammarMap();
        $page_list = $this->getPageList();
        $list = ExamQuestionCollection::find()->where(['>', 'id', 0])->asArray()->all();
        foreach ($list as $value) {
            $item = [
                "type" => $this->collectionTypeMap()[$value['type']],
                "question_type" => $this->collectionQuestionTypeMap()[$value['type']][$value['question_type']],
                "difficulty" => $value['difficulty'],
            ];
            if ($value["question_sub_type"] > 0) {
                $item["question_sub_type"] = $this->getSubtypeMap()[$value["question_sub_type"]];
            }
            if ($value["type"] == 1) {
                if ($value["grammar"] > 0) {
                    $item["grammar"] = $writing_grammar[$value['question_type']][$value["grammar"]] ?? '';
                }
                if ($value["topic"] > 0) {
                    $item["topic"] = $writing_topic[$value["topic"]];
                }
            } else if ($value["type"] == 2) {
                if ($value["grammar"] > 0) {
                    $item["grammar"] = $reading_grammar[$value['question_type']][$value["grammar"]];
                }
            } else if ($value["type"] == 3) {
                if ($value["grammar"] > 0) {
                    $item["grammar"] = $listening_grammar[$value['question_type']][$value["grammar"]];
                }
            }
            if ($value['page'] > 0) {
                $item['page'] = $page_list[$value['page']];
            }
            $data[] = $item;
        }
        //将数据生成json写入文件
        $file = dirname(__FILE__, 2) . '/runtime/tmp/collection_list.json';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        file_put_contents($file, $json);
        var_dump("写入文件成功");
    }

    public function actionAddListeningCollection(): void
    {
        $local_path = dirname(__FILE__, 2);
        $file = $local_path . '/runtime/tmp/tingli.json';
        $content = file_get_contents($file);
        $content = json_decode($content);

        foreach ($content as $value) {
            var_dump($value);
            var_dump("开始处理");
            $list = BasicTrainingListeningQuestion::find()->where(['type' => $value->type, 'title' => $value->list])->asArray()->all();
            if (count($list) != count($value->list)) {
                var_dump($value->list);
                var_dump("数据不匹配");
                die;
            }
            $question = $list[0];
            $collection = new ExamQuestionCollection();
            $collection->exam_type = 2;
            $collection->type = 3;
            $collection->question_type = $value->type;
            $collection->grammar = $question['grammar'];
            $collection->question_num = count($list);
            $collection->insert(false);
            var_dump("collection_id = " . $collection->id . "插入成功");
            foreach ($list as $val) {
                $relation = new ExamCollectionRelation();
                $relation->collection_id = $collection->id;
                $relation->question_id = $val['id'];
                $relation->insert(false);
                var_dump("collection_id = " . $collection->id . ",question_id = " . $val['id'] . "插入成功");
            }
            var_dump("处理完成");
        }
        var_dump("全部处理完成");
    }

    public function collectionTypeMap(): array
    {
        return [
            1 => '写作',
            2 => '阅读',
            3 => '听力',
        ];
    }

    /**
     * 
     * WritingGroupTypeIsConnectingWordSentence       int32 = 1 // 连词成句 有对错
	WritingGroupTypeIsTranslateTask                int32 = 2 // 翻译练习 无对错
	WritingGroupTypeIsSentenceCorrection           int32 = 3 // 语法纠错 有对错
	WritingGroupTypeIsSentenceCombination          int32 = 4 // 句子合并 无对错
	WritingGroupTypeIsSentenceRewrite              int32 = 5 // 句子改写 无对错
	WritingGroupTypeIsCollocationsPairSingleChoice int32 = 6 // 固定搭配 有对错

	WritingGroupTypeIsCollocationsPairSingleChoiceSubTypeIsFixed int32 = 1 // 固定搭配辨析
	WritingGroupTypeIsCollocationsPairSingleChoiceSubTypeIsPair  int32 = 2 // 固定搭配补全

	ReadingGroupTypeIsLocating                          int32 = 1 // 定位练习
	ReadingGroupTypeIsSentenceUnderstandingSingleChoice int32 = 2 // 长难句理解
	ReadingGroupTypeIsSentenceTranslateToChinese        int32 = 3 // 句子精读
	ReadingGroupTypeIsScanningTraining                  int32 = 4 // 扫读练习
	ReadingGroupTypeIsScanningTrainingV1                int32 = 5 // 扫读练习新版
	ReadingGroupTypeIsSynonymousSubstitution            int32 = 6 // 同义替换

	ListeningQuestionTypeIsSingleSentenceDictation     int32 = 1 // 单句独白
	ListeningQuestionTypeIsSingleConversationDictation int32 = 2 // 单轮对话
	ListeningQuestionTypeIsMonologue                   int32 = 3 // 段落独白
	ListeningQuestionTypeIsMultiTurnDialogue           int32 = 4 // 多轮对话
	ListeningQuestionTypeIsBasicScenario               int32 = 5 // 基础场景
     * @return array<int|string>[]
     */
    public function collectionQuestionTypeMap(): array
    {
        return [
            1 => [
                1 => '连词成句',
                2 => "翻译练习",
                3 => "语法纠错",
                4 => "句子合并",
                5 => "句子改写",
                6 => "固定搭配",
            ],
            2 => [
                1 => "定位练习",
                2 => "长难句理解",
                3 => "句子精读",
                4 => "扫读练习",
                5 => "扫读练习新版",
                6 => "同义替换",
            ],
            3 => [
                1 => "单句独白",
                2 => "单轮对话",
                3 => "段落独白",
                4 => "多轮对话",
                5 => "基础场景",
            ]
        ];
    }

    public function getSubtypeMap(): array
    {
        return [
            1 => "固定搭配辨析",
            2 => "固定搭配补全",
        ];
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
            while (($row = fgetcsv($handle, 1000, ',', '"')) !== false) {
                // 处理 CSV 的表头
                if (!$header) {
                    var_dump($row);
                    $header = $row;
                } else {
                    if (count($header) == count($row)) {
                        $data[] = array_combine($header, $row);
                    }
                }
            }
            fclose($handle);
        }

        // 将数据转换为 JSON 格式
        return $data;
    }

    public function getWritingGrammar(): array
    {
        $listMap = [];
        $query = BasicTrainingWritingGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->key] = $value->id;
        }

        return $listMap;
    }

    public function getWritingGrammarMap(): array
    {
        $listMap = [];
        $query = BasicTrainingWritingGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->id] = $value->name;
        }

        return $listMap;
    }

    public function getWritingTopic(): array
    {
        $listMap = [];
        $query = BasicTrainingWritingTopic::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->name] = $value->id;
        }

        return $listMap;
    }

    public function getWritingTopicMap(): array
    {
        $listMap = [];
        $query = BasicTrainingWritingTopic::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->id] = $value->name;
        }

        return $listMap;
    }

    public function getWritingType(): array
    {
        return [
            "connecting_word_sentence" => 1,
            "translate_task" => 2,
            "sentence_correction" => 3,
            "sentence_combination" => 4,
            "sentence_rewrite" => 5,
            "collocations_single_choice" => 6,
            "collocations_pair_single_choice" => 6,
        ];
    }

    public function getReadingGrammar(): array
    {
        $listMap = [];
        $query = BasicTrainingReadingGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->key] = $value->id;
        }

        return $listMap;
    }

    public function getReadingGrammarNameMap(): array
    {
        $listMap = [];
        $query = BasicTrainingReadingGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->name] = $value->id;
        }

        return $listMap;
    }

    public function getReadingGrammarMap(): array
    {
        $listMap = [];
        $query = BasicTrainingReadingGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->id] = $value->name;
        }

        return $listMap;
    }

    public function getReadingType(): array
    {
        return [
            "locating" => 1,
            "sentence_understanding_single_choice" => 2,
            "sentence_translate_to_chinese" => 3,
            "scanning_training" => 4,
        ];
    }

    public function getReadingSubType(): array
    {
        return [
            "identify_keyword" => 1,
            "identify_central_sentence" => 2,
            "identify_synonym" => 3,
        ];
    }

    public function getListeningGrammar(): array
    {
        $listMap = [];
        $query = BasicTrainingListeningGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->name] = $value->id;
        }

        return $listMap;
    }

    public function getListeningGrammarMap(): array
    {
        $listMap = [];
        $query = BasicTrainingListeningGrammar::find();
        $list = $query->andWhere(['>', 'id', 0])->all();
        foreach ($list as $value) {
            $listMap[$value->type][$value->id] = $value->name;
        }

        return $listMap;
    }

    public function getListeningType(): array
    {
        return [
            "single_sentence_dictation" => 1,
            "single_conversation_dictation" => 2
        ];
    }

    public function getListeningTypeLabels(): array
    {
        return [
            1 => '单句独白',
            2 => '单轮对话',
            3 => '段落独白',
            4 => '多轮对话',
            5 => '基础场景',
        ];
    }

    protected function normalizeContentForExport($content): string
    {
        if ($content === null || $content === '') {
            return '';
        }

        if (is_string($content)) {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->stringifyContentLines($decoded);
            }

            return $content;
        }

        return $this->stringifyContentLines($content);
    }

    protected function stringifyContentLines($content): string
    {
        if (is_array($content)) {
            $lines = [];
            foreach ($content as $item) {
                if ($item === null || $item === '') {
                    continue;
                }
                if (is_scalar($item)) {
                    $lines[] = (string)$item;
                    continue;
                }
                $lines[] = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            return implode("\n", $lines);
        }

        if (is_scalar($content)) {
            return (string)$content;
        }

        return json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function getPageList(): array
    {
        $query = ExamCollectionPage::find();
        $list = $query->andWhere([">", "id", 0])->all();
        $listMap = [];
        foreach ($list as $value) {
            $listMap[$value->id] = $value->name;
        }
        return $listMap;
    }
}

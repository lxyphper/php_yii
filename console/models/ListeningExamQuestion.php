<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_question".
 *
 * @property int $id 主键id
 * @property string $title 题目标题
 * @property int $paper_id 试卷id
 * @property int $group_id 分组id
 * @property int $number 题目序号
 * @property string $sub_essay_code 小标题题号
 * @property string|null $answer 题目答案
 * @property string $display_answer 答案
 * @property string|null $answer_sentences 答案解析
 * @property int $is_multiple 是否是多个答案：1是 2否
 * @property string|null $analyze_print 题目解析数据
 * @property string $analyze 题目解析
 * @property string $analyze_en 题目解析英文
 * @property string|null $key_locating_words 核心词数据
 * @property string|null $locating_words 选中词数据
 * @property string $base_analyze 第三方题目分析
 * @property string $parsed_answer
 * @property string $id_text 填空题id字符串
 * @property int $start_time
 * @property int $end_time
 * @property string $file_url 题目图片
 * @property string|null $ai_data
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'group_id', 'number', 'is_multiple', 'start_time', 'end_time', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['answer', 'answer_sentences', 'analyze_print', 'key_locating_words', 'locating_words', 'ai_data'], 'safe'],
            [['title'], 'string', 'max' => 1000],
            [['sub_essay_code'], 'string', 'max' => 10],
            [['display_answer', 'parsed_answer', 'file_url'], 'string', 'max' => 500],
            [['analyze', 'analyze_en'], 'string', 'max' => 5000],
            [['base_analyze'], 'string', 'max' => 2000],
            [['id_text'], 'string', 'max' => 20],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'paper_id' => 'Paper ID',
            'group_id' => 'Group ID',
            'number' => 'Number',
            'sub_essay_code' => 'Sub Essay Code',
            'answer' => 'Answer',
            'display_answer' => 'Display Answer',
            'answer_sentences' => 'Answer Sentences',
            'is_multiple' => 'Is Multiple',
            'analyze_print' => 'Analyze Print',
            'analyze' => 'Analyze',
            'analyze_en' => 'Analyze En',
            'key_locating_words' => 'Key Locating Words',
            'locating_words' => 'Locating Words',
            'base_analyze' => 'Base Analyze',
            'parsed_answer' => 'Parsed Answer',
            'id_text' => 'Id Text',
            'start_time' => 'Start Time',
            'end_time' => 'End Time',
            'file_url' => 'File Url',
            'ai_data' => 'Ai Data',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamQuestionQuery(get_called_class());
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_question_group".
 *
 * @property int $id 主键id
 * @property int $paper_id 试卷id
 * @property int $type 题目分组类型
 * @property string $desc 题目分组说明
 * @property string $title 分组题目标题
 * @property string|null $content 分组题目题干json
 * @property string $analyze 分组题目分析
 * @property string $base_analyze 第三方分组题目分析
 * @property string $img_url 题目图片
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 * @property string $question_title 题目列表标题
 */
class ListeningExamQuestionGroup extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_question_group';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'type', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['content'], 'safe'],
            [['desc', 'title'], 'string', 'max' => 1000],
            [['analyze', 'base_analyze'], 'string', 'max' => 5000],
            [['img_url'], 'string', 'max' => 500],
            [['question_title'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => '主键id',
            'paper_id' => '试卷id',
            'type' => '题目分组类型',
            'desc' => '题目分组说明',
            'title' => '分组题目标题',
            'content' => '分组题目题干json',
            'analyze' => '分组题目分析',
            'base_analyze' => '第三方分组题目分析',
            'img_url' => '题目图片',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
            'question_title' => '题目列表标题',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestionGroupQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamQuestionGroupQuery(get_called_class());
    }
}

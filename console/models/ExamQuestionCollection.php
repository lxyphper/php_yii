<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exam_question_collection".
 *
 * @property int $id
 * @property int $exam_type 练习类型：1题集练习 2lms关联练习
 * @property int $type 类型：1写作 2阅读 3听力
 * @property int $question_type 题型
 * @property int $question_sub_type 题型子类型
 * @property int $grammar 知识点id
 * @property int $topic 内容id
 * @property int $difficulty 难度
 * @property int $page 分页
 * @property int $status 状态：1正常 2禁用
 * @property int $question_num 题目数量
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ExamQuestionCollection extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exam_question_collection';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['exam_type', 'type', 'question_type', 'question_sub_type', 'grammar', 'topic', 'difficulty', 'page', 'status', 'question_num', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'exam_type' => 'Exam Type',
            'type' => 'Type',
            'question_type' => 'Question Type',
            'question_sub_type' => 'Question Sub Type',
            'grammar' => 'Grammar',
            'topic' => 'Topic',
            'difficulty' => 'Difficulty',
            'page' => 'Page',
            'status' => 'Status',
            'question_num' => 'Question Num',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ExamQuestionCollectionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExamQuestionCollectionQuery(get_called_class());
    }
}

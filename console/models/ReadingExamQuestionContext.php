<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reading_exam_question_context".
 *
 * @property int $id
 * @property int $question_id 问题id
 * @property string|null $content 问题详情json
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ReadingExamQuestionContext extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reading_exam_question_context';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['question_id', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['content'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'question_id' => '问题id',
            'content' => '问题详情json',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamQuestionContextQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ReadingExamQuestionContextQuery(get_called_class());
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_question_option".
 *
 * @property int $id
 * @property int $biz_type 选项分类：1、题目 2、分组
 * @property int $biz_id 选项分类id
 * @property string $title
 * @property string $content
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamQuestionOption extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_question_option';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['biz_type', 'biz_id', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title'], 'string', 'max' => 50],
            [['content'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'biz_type' => '选项分类：1、题目 2、分组',
            'biz_id' => '选项分类id',
            'title' => 'Title',
            'content' => 'Content',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestionOptionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamQuestionOptionQuery(get_called_class());
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_question_type".
 *
 * @property int $id
 * @property string $name
 * @property string $name_en
 * @property int $weight
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamQuestionType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_question_type';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 20],
            [['name_en'], 'string', 'max' => 300],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Name',
            'name_en' => 'Name En',
            'weight' => 'Weight',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamQuestionTypeQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamQuestionTypeQuery(get_called_class());
    }
}

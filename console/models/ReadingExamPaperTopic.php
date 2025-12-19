<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "reading_exam_paper_topic".
 *
 * @property int $id
 * @property string $name
 * @property string $name_en
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ReadingExamPaperTopic extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reading_exam_paper_topic';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
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
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ReadingExamPaperTopicQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ReadingExamPaperTopicQuery(get_called_class());
    }
}

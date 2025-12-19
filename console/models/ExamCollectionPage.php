<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exam_collection_page".
 *
 * @property int $id
 * @property string $name 名称
 * @property int $type 类型：1写作 2阅读 3听力
 * @property int $question_type 题型
 * @property int $question_sub_type 题型子类型
 * @property int $grammar 场景
 * @property int $difficulty 难度
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ExamCollectionPage extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exam_collection_page';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'question_type', 'question_sub_type', 'grammar', 'difficulty', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 50],
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
            'type' => 'Type',
            'question_type' => 'Question Type',
            'question_sub_type' => 'Question Sub Type',
            'grammar' => 'Grammar',
            'difficulty' => 'Difficulty',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ExamCollectionPageQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExamCollectionPageQuery(get_called_class());
    }
}

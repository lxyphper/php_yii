<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_exam_paper_category".
 *
 * @property int $id
 * @property string $name 分类名称
 * @property int $weight
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingExamPaperCategory extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_exam_paper_category';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['weight', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 200],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => '分类名称',
            'weight' => 'Weight',
            'status' => '状态：1正常 2禁用',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingExamPaperCategoryQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingExamPaperCategoryQuery(get_called_class());
    }
}

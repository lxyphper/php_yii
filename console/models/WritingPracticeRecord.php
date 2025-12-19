<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "writing_practice_record".
 *
 * @property int $id
 * @property int $paper_id
 * @property int $student_id
 * @property int $type 练习类型：1非整篇 2整篇
 * @property int $step 整篇练习进度：1审题练习 2思路拓展练习 3分段练习
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class WritingPracticeRecord extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'writing_practice_record';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'student_id', 'type', 'step', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'paper_id' => 'Paper ID',
            'student_id' => 'Student ID',
            'type' => 'Type',
            'step' => 'Step',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return WritingPracticeRecordQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new WritingPracticeRecordQuery(get_called_class());
    }
}

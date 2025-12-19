<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "sys_ai_task".
 *
 * @property int $id 主键id
 * @property int $type 任务类型：1大作文分段练习批改 2大作文分段练习评分 3大作文评分 4小作文评分 5大作文模考评分 6小作文模考评分 7大作文题目解析  8口语模考
 * @property int $record_id 练习记录id（练习，批改，模考）
 * @property int $status 状态：1未完成 2完成
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class SysAiTask extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'sys_ai_task';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'record_id', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Type',
            'record_id' => 'Record ID',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SysAiTaskQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SysAiTaskQuery(get_called_class());
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "listening_exam_context".
 *
 * @property int $id
 * @property int $biz_id 业务主键id
 * @property int $biz_type 业务类型：1正文 2问题
 * @property string|null $content 问题详情json
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ListeningExamContext extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'listening_exam_context';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['biz_id', 'biz_type', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
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
            'biz_id' => '业务主键id',
            'biz_type' => '业务类型：1正文 2问题',
            'content' => '问题详情json',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ListeningExamContextQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ListeningExamContextQuery(get_called_class());
    }
}

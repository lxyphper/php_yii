<?php

namespace app\models;

/**
 * This is the model class for table "reading_exam_context".
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
class ReadingExamContext extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'reading_exam_context';
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
     * @return ReadingExamContextQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ReadingExamContextQuery(get_called_class());
    }
}

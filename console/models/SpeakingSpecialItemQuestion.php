<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_special_item_question".
 *
 * @property int $id
 * @property string $title 标题
 * @property string $title_audio 题目音频
 * @property string $answer 答案，ai使用
 * @property string $answer_audio 答案音频
 * @property string $tip 提示信息
 * @property int $group_id 分组id
 * @property int $weight 排序权重
 * @property int $status 状态：1正常 2禁用
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingSpecialItemQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_special_item_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['group_id', 'weight', 'status', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title', 'answer'], 'string', 'max' => 1000],
            [['title_audio', 'answer_audio'], 'string', 'max' => 500],
            [['tip'], 'string', 'max' => 2000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'title' => 'Title',
            'title_audio' => 'Title Audio',
            'answer' => 'Answer',
            'answer_audio' => 'Answer Audio',
            'tip' => 'Tip',
            'group_id' => 'Group ID',
            'weight' => 'Weight',
            'status' => 'Status',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSpecialItemQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingSpecialItemQuestionQuery(get_called_class());
    }
}

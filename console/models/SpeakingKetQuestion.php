<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_ket_question".
 *
 * @property int $id
 * @property string $name 名称
 * @property int $topic 话题id
 * @property string $tips 提示内容json
 * @property string $emoji 表情
 * @property string $more_tips 更多提示内容json
 * @property string $more_emoji 更多提示表情
 * @property string $audio_url 音频地址
 * @property int $create_by 创建人
 * @property int $create_time 创建时间
 * @property int $update_by 更新人
 * @property int $update_time 更新时间
 */
class SpeakingKetQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_ket_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['topic', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['name'], 'string', 'max' => 200],
            [['tips', 'more_tips'], 'string', 'max' => 5000],
            [['emoji', 'more_emoji'], 'string', 'max' => 50],
            [['audio_url'], 'string', 'max' => 500],
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
            'topic' => 'Topic',
            'tips' => 'Tips',
            'emoji' => 'Emoji',
            'more_tips' => 'More Tips',
            'more_emoji' => 'More Emoji',
            'audio_url' => 'Audio Url',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingKetQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingKetQuestionQuery(get_called_class());
    }
}

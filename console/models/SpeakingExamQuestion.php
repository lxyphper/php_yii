<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_exam_question".
 *
 * @property int $id
 * @property string $title 题目内容
 * @property int $paper_id 试卷id
 * @property int $weight 排序权重
 * @property string $source_id
 * @property string $tips 提示内容json
 * @property string $emoji 表情
 * @property string $more_tips 更多提示内容json
 * @property string $more_emoji 更多提示表情
 * @property string $audio_url 音频地址
 * @property string $sub_questions 子问题
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingExamQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_exam_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_id', 'weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title', 'audio_url'], 'string', 'max' => 500],
            [['source_id', 'emoji', 'more_emoji'], 'string', 'max' => 50],
            [['tips', 'more_tips'], 'string', 'max' => 5000],
            [['sub_questions'], 'string', 'max' => 2000],
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
            'paper_id' => 'Paper ID',
            'weight' => 'Weight',
            'source_id' => 'Source ID',
            'tips' => 'Tips',
            'emoji' => 'Emoji',
            'more_tips' => 'More Tips',
            'more_emoji' => 'More Emoji',
            'audio_url' => 'Audio Url',
            'sub_questions' => 'Sub Questions',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingExamQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingExamQuestionQuery(get_called_class());
    }
}

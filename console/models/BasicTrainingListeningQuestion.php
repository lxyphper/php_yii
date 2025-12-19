<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "basic_training_listening_question".
 *
 * @property int $id
 * @property int $type 题目分类:1单句独白 2单轮对话 3段落独白 4多轮对话
 * @property int $grammar 题目语法、场景
 * @property int $question_type 题目类型：1填空题 2选择题
 * @property string $title 标题
 * @property string|null $content 题干
 * @property string|null $answer 答案
 * @property string $context 原始数据
 * @property string $audio_url 音频文件
 * @property string $source_id 数据来源id
 * @property int $done_num
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class BasicTrainingListeningQuestion extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'basic_training_listening_question';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['type', 'grammar', 'question_type', 'done_num', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['content', 'answer', 'context'], 'safe'],
            [['context'], 'required'],
            [['title', 'audio_url'], 'string', 'max' => 500],
            [['source_id'], 'string', 'max' => 100],
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
            'grammar' => 'Grammar',
            'question_type' => 'Question Type',
            'title' => 'Title',
            'content' => 'Content',
            'answer' => 'Answer',
            'context' => 'Context',
            'audio_url' => 'Audio Url',
            'source_id' => 'Source ID',
            'done_num' => 'Done Num',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return BasicTrainingListeningQuestionQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new BasicTrainingListeningQuestionQuery(get_called_class());
    }
}

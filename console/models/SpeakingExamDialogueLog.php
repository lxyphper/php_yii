<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_exam_dialogue_log".
 *
 * @property int $id
 * @property int $course_type 练习课程类型
 * @property int $relation_id 关联用户消息id
 * @property int $group_id 分组id
 * @property int $role 发送角色：1用户 2AI
 * @property int $student_id 用户id
 * @property int $scene 场景：1、part1 2、part2 3、part3 4、随便聊聊
 * @property int $topic 话题id
 * @property int $question_id 问题id
 * @property string $content 发送内容
 * @property int $type 消息类型：1语音消息 2文字消息 3切换场景 4用户长时间未响应 5总结 6功能消息
 * @property string $streaming_id 流式输出关联id
 * @property int $communication_mode 交流方式：1、text 2、phone_call
 * @property int $duration 音频时长
 * @property int $last_id 总结的最后一个消息id
 * @property int $send_time 发送时间戳
 * @property int $version 消息请求版本
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingExamDialogueLog extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_exam_dialogue_log';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['course_type', 'relation_id', 'group_id', 'role', 'student_id', 'scene', 'topic', 'question_id', 'type', 'communication_mode', 'duration', 'last_id', 'send_time', 'version', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['content'], 'string', 'max' => 5000],
            [['streaming_id'], 'string', 'max' => 100],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'course_type' => 'Course Type',
            'relation_id' => 'Relation ID',
            'group_id' => 'Group ID',
            'role' => 'Role',
            'student_id' => 'Student ID',
            'scene' => 'Scene',
            'topic' => 'Topic',
            'question_id' => 'Question ID',
            'content' => 'Content',
            'type' => 'Type',
            'streaming_id' => 'Streaming ID',
            'communication_mode' => 'Communication Mode',
            'duration' => 'Duration',
            'last_id' => 'Last ID',
            'send_time' => 'Send Time',
            'version' => 'Version',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingExamDialogueLogQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingExamDialogueLogQuery(get_called_class());
    }
}

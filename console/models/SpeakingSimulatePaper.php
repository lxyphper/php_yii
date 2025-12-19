<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "speaking_simulate_paper".
 *
 * @property int $id
 * @property string $title 名称
 * @property int $paper_group 模考分组
 * @property string $part1_topic part1话题
 * @property int $part2_topic part2话题
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class SpeakingSimulatePaper extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'speaking_simulate_paper';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['paper_group', 'part2_topic', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['title'], 'string', 'max' => 200],
            [['part1_topic'], 'string', 'max' => 100],
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
            'paper_group' => 'Paper Group',
            'part1_topic' => 'Part1 Topic',
            'part2_topic' => 'Part2 Topic',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SpeakingSimulatePaperQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SpeakingSimulatePaperQuery(get_called_class());
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "simulate_exam_speaking_report".
 *
 * @property int $id
 * @property int $record_id
 * @property string $fc 流利度
 * @property string $gra 语法
 * @property string $pron_accuracy 发音
 * @property string $vocabulary 词汇
 * @property string $summary 总结
 * @property int $create_by
 * @property int $create_time
 * @property int $update_by
 * @property int $update_time
 */
class SimulateExamSpeakingReport extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'simulate_exam_speaking_report';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['record_id', 'create_by', 'create_time', 'update_by', 'update_time'], 'integer'],
            [['fc', 'gra', 'pron_accuracy', 'vocabulary', 'summary'], 'string', 'max' => 500],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'record_id' => 'Record ID',
            'fc' => 'Fc',
            'gra' => 'Gra',
            'pron_accuracy' => 'Pron Accuracy',
            'vocabulary' => 'Vocabulary',
            'summary' => 'Summary',
            'create_by' => 'Create By',
            'create_time' => 'Create Time',
            'update_by' => 'Update By',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SimulateExamSpeakingReportQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SimulateExamSpeakingReportQuery(get_called_class());
    }
}

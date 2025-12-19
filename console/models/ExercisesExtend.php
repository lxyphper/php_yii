<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "exercises_extend".
 *
 * @property int $id
 * @property int $exercises_id
 * @property string $requirement 写作要求
 * @property string $requirement_en 写作要求英文
 * @property string $statement_question 写作分析
 * @property string $statement_question_en 写作分析英文
 * @property int $create_by
 * @property int $update_by
 * @property int $create_time
 * @property int $update_time
 */
class ExercisesExtend extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'exercises_extend';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['exercises_id', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['requirement'], 'string', 'max' => 500],
            [['requirement_en'], 'string', 'max' => 3000],
            [['statement_question'], 'string', 'max' => 2000],
            [['statement_question_en'], 'string', 'max' => 5000],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'exercises_id' => 'Exercises ID',
            'requirement' => 'Requirement',
            'requirement_en' => 'Requirement En',
            'statement_question' => 'Statement Question',
            'statement_question_en' => 'Statement Question En',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return ExercisesExtendQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new ExercisesExtendQuery(get_called_class());
    }
}

<?php

namespace app\models;

use Yii;

/**
 * This is the model class for table "simulate_paper_type".
 *
 * @property int $id
 * @property string $name 名称
 * @property string $name_cn 中文名称
 * @property string $desc 描述
 * @property int $weight 展示顺序权重
 * @property int $create_by 创建人
 * @property int $update_by 更新人
 * @property int $create_time 创建时间
 * @property int $update_time 更新时间
 */
class SimulatePaperType extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'simulate_paper_type';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['weight', 'create_by', 'update_by', 'create_time', 'update_time'], 'integer'],
            [['name', 'name_cn'], 'string', 'max' => 50],
            [['desc'], 'string', 'max' => 200],
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
            'name_cn' => 'Name Cn',
            'desc' => 'Desc',
            'weight' => 'Weight',
            'create_by' => 'Create By',
            'update_by' => 'Update By',
            'create_time' => 'Create Time',
            'update_time' => 'Update Time',
        ];
    }

    /**
     * {@inheritdoc}
     * @return SimulatePaperTypeQuery the active query used by this AR class.
     */
    public static function find()
    {
        return new SimulatePaperTypeQuery(get_called_class());
    }
}

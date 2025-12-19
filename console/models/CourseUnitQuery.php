<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[CourseUnit]].
 *
 * @see CourseUnit
 */
class CourseUnitQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return CourseUnit[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return CourseUnit|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

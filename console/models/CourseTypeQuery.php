<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[CourseType]].
 *
 * @see CourseType
 */
class CourseTypeQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return CourseType[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return CourseType|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

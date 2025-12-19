<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[LmsLesson]].
 *
 * @see LmsLesson
 */
class LmsLessonQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return LmsLesson[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return LmsLesson|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

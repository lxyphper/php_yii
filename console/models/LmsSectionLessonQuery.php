<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[LmsSectionLesson]].
 *
 * @see LmsSectionLesson
 */
class LmsSectionLessonQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return LmsSectionLesson[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return LmsSectionLesson|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

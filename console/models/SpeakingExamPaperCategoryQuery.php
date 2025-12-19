<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingExamPaperCategory]].
 *
 * @see SpeakingExamPaperCategory
 */
class SpeakingExamPaperCategoryQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingExamPaperCategory[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingExamPaperCategory|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

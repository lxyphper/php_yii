<?php

namespace app\models;

/**
 * This is the ActiveQuery class for [[SpeakingExamDialogueLog]].
 *
 * @see SpeakingExamDialogueLog
 */
class SpeakingExamDialogueLogQuery extends \yii\db\ActiveQuery
{
    /*public function active()
    {
        return $this->andWhere('[[status]]=1');
    }*/

    /**
     * {@inheritdoc}
     * @return SpeakingExamDialogueLog[]|array
     */
    public function all($db = null)
    {
        return parent::all($db);
    }

    /**
     * {@inheritdoc}
     * @return SpeakingExamDialogueLog|array|null
     */
    public function one($db = null)
    {
        return parent::one($db);
    }
}

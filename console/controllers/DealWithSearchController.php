<?php

namespace console\controllers;

use app\models\Exercises;
use app\models\ListeningExamPaper;
use app\models\ListeningExamQuestion;
use app\models\ListeningExamQuestionGroup;
use app\models\ReadingExamPaper;
use app\models\ReadingExamQuestion;
use app\models\ReadingExamQuestionGroup;
use app\models\Vocabulary;
use app\models\VocabularyExt;
use app\models\VocabularyUnitRelation;
use app\models\WritingEssay;
use Elastic\Elasticsearch;
use Elastic\Elasticsearch\ClientBuilder;
use GuzzleHttp\Exception\GuzzleException;
use yii\console\Controller;
use GuzzleHttp\Client;

//处理搜索数据到ES
//1听力 2阅读 3写作 5作文 6词汇
class DealWithSearchController extends BaseController
{

    private function putData($id, $data)
    {
        try {
            $client = ClientBuilder::create()->setHosts(['http://test-y1z.public.cn-hangzhou.es-serverless.aliyuncs.com:9200/'])->build();
        } catch (Elasticsearch\Exception\AuthenticationException $e) {
            var_dump("AuthenticationException:" . $e->getMessage());
            die;
        }

        $params = [
            'index' => 'duy-search',
            'id' => $id,
            'body' => $data,
        ];


        try {
            $response = $client->putScript($params);
            var_dump($response);
        } catch (Elasticsearch\Exception\ClientResponseException $e) {
            var_dump("ClientResponseException:" . $e->getMessage());
        } catch (Elasticsearch\Exception\MissingParameterException $e) {
            var_dump("MissingParameterException:" . $e->getMessage());
        } catch (Elasticsearch\Exception\ServerResponseException $e) {
            var_dump("ServerResponseException:" . $e->getMessage());
        }
    }

    private function guzzlePutData($id, $data)
    {
        $client = new Client();
        $url = 'http://test-y1z.public.cn-hangzhou.es-serverless.aliyuncs.com:9200' . '/' . 'duy-search' . '/' . '_doc' . '/' . $id;

        try {
            $response = $client->put(
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode("test-y1z:9YHdbeazsJWGrd8Q1DL9u8BX0uHi6O"),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => $data,
                ],
            );
            var_dump("id:" . $id . ',推送成功');
        } catch (GuzzleException $e) {
            var_dump("推送失败，" . $e->getMessage());
        }
    }

    //初始化听力搜索数据
    public function actionInitListening()
    {
        $list = ListeningExamPaper::find()->where(['>', 'id', 0])->andWhere(['!=', 'unit', 276])->all();
        if (empty($list)) {
            var_dump("数据为空");
            die;
        }

        foreach ($list as $item) {
            $id = 1 . '-' . $item->id;
            $data = [
                "metadata" => [
                    "paper_id" => $item->id,
                    "type" => 1,
                    "paper_type" => substr_count($item->complete_title, '剑雅') > 0 ? 1 : 2,
                    "author" => 1,
                    "create_time" => $item->create_time,
                    "update_time" => $item->update_time,
                ],
                "title" => $item->complete_title,
                "title_en" => $item->complete_title_en,
                //                "content" => implode('\n',array_column((array)$item->content, 'en_text')),
                "content" => '',
                "question_list" => [],
            ];
            //查询题目
            $qList = ListeningExamQuestion::findAll(['paper_id' => $item->id]);
            if (empty($qList)) {
                var_dump("题目为空");
            } else {
                $gIds = [];
                $gMap = [];
                foreach ($qList as $qItem) {
                    $gIds[] = $qItem->group_id;
                }
                $gList = ListeningExamQuestionGroup::findAll(['id' => $gIds]);
                if (empty($gList)) {
                    var_dump("题目分组为空");
                } else {
                    foreach ($gList as $gItem) {
                        $gStr = [];
                        if (!empty($gItem->title)) {
                            $gStr[] = $gItem->title;
                        }
                        if (!empty($gItem->content)) {
                            $gContent = (array)$gItem->content;
                            if (key_exists('collect', $gContent)) {
                                $gStr[] = $gContent['collect'];
                            } else {
                                foreach ($gContent as $value) {
                                    if (is_array($value)) {
                                        foreach ($value as $val) {
                                            if (!is_array($val)) {
                                                $gStr[] = $val;
                                            }
                                        }
                                    } else {
                                        $gStr[] = $value;
                                    }
                                }
                            }
                        }
                        $gMap[$gItem->id] = implode(' ', $gStr);
                    }
                }

                foreach ($qList as $key => $qItem) {
                    $group_title = isset($gMap[$qItem->group_id]) ? preg_replace('/\s+/', ' ', strip_tags(preg_replace('/(&nbsp;)+/', ' ', preg_replace('/(\$\d+\$)/', ' ', $gMap[$qItem->group_id])))) : '';
                    if (!empty($qItem->title)) {
                        $data['question_list'][] = [
                            "question_id" => $qItem->id,
                            "title" => $group_title . ' ' . $qItem->title,
                        ];
                    } else {
                        if (isset($gMap[$qItem->group_id])) {
                            if (($key != 0 && $qItem->group_id != $qList[$key - 1]->group_id) || $key == 0) {
                                $data['question_list'][] = [
                                    "question_id" => $qItem->id,
                                    "title" => $group_title . ' ' . $qItem->title,
                                ];
                            }
                        }
                    }
                }
            }

            $this->guzzlePutData($id, $data);
        }
    }

    //初始化阅读搜索数据
    public function actionInitReading()
    {
        $list = ReadingExamPaper::find()->where(['!=', 'unit', '963'])->all();
        if (empty($list)) {
            var_dump("数据为空");
            die;
        }

        foreach ($list as $item) {
            $id = 2 . '-' . $item->id;
            $data = [
                "metadata" => [
                    "paper_id" => $item->id,
                    "type" => 2,
                    "paper_type" => substr_count($item->complete_title, '剑雅') > 0 ? 1 : (substr_count($item->complete_title, 'V') > 0 ? 3 : 2),
                    "author" => 1,
                    "create_time" => $item->create_time,
                    "update_time" => $item->update_time,
                ],
                "title" => $item->complete_title,
                "title_en" => $item->complete_title_en,
                "question_list" => [],
                "content" => '',
            ];

            $contentArr = [];
            if (!empty($item->essay_title)) {
                $contentArr[] = $item->essay_title;
            }
            if (!empty($item->content)) {
                $content = (array)$item->content;
                if (isset($content['image'])) {
                    unset($content['image']);
                }
                if (isset($content['summary'])) {
                    unset($content['summary']);
                }
                if (isset($content['summary_en'])) {
                    unset($content['summary_en']);
                }
                if (isset($content['order_ary'])) {
                    unset($content['order_ary']);
                }

                if (isset($content['paragraph'])) {
                    if (!empty($content['title'])) {
                        $contentArr[] = $content['title'];
                    }
                    if (!empty($content['sub_title'])) {
                        $contentArr[] = $content['sub_title'];
                    }
                    $contentArr[] = implode(' ', $content['paragraph']);
                    if (!empty($content['notes'])) {
                        if (is_array($content['notes'])) {
                            foreach ($content['notes'] as $v) {
                                $contentArr[] = $v;
                            }
                        } else {
                            $contentArr[] = $content['notes'];
                        }
                    }
                    $data['content'] = implode(' ', $contentArr);
                } else {
                    if (!empty($content['notes'])) {
                        $notes = $content['notes'];
                        unset($content['notes']);
                    }
                    $contentArr = array_merge($contentArr, $content);
                }
            }
            if (!empty($contentArr)) {
                foreach ($contentArr as $key => $value) {
                    if (!is_array($value)) {
                        $contentArr[$key] = $value;
                    } else {
                        $contentArr[$key] = implode(' ', $value);
                    }
                }
                $data['content'] = implode(' ', $contentArr);
            }

            //查询题目
            $qList = ReadingExamQuestion::findAll(['paper_id' => $item->id]);
            if (empty($qList)) {
                var_dump("题目为空");
            } else {
                $gIds = [];
                $gMap = [];
                foreach ($qList as $qItem) {
                    $gIds[] = $qItem->group_id;
                }
                $gList = ReadingExamQuestionGroup::findAll(['id' => $gIds]);
                if (empty($gList)) {
                    var_dump("题目分组为空");
                } else {
                    foreach ($gList as $gItem) {
                        $gStr = [];
                        if (!empty($gItem->title)) {
                            $gStr[] = $gItem->title;
                        }
                        if (!empty($gItem->content)) {
                            $gContent = (array)$gItem->content;
                            if (key_exists('collect', $gContent)) {
                                $gStr[] = $gContent['collect'];
                            } else {
                                foreach ($gContent as $value) {
                                    foreach ($value as $val) {
                                        if (!is_array($val)) {
                                            $gStr[] = $val;
                                        } else {
                                            foreach ($val as $v) {
                                                if (!is_array($v)) {
                                                    $gStr[] = $v;
                                                } else {
                                                    $gStr[] = implode(' ', $v);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        $gMap[$gItem->id] = implode(' ', $gStr);
                    }
                }

                foreach ($qList as $key => $qItem) {
                    $group_title = isset($gMap[$qItem->group_id]) ? preg_replace('/\s+/', ' ', strip_tags(preg_replace('/(&nbsp;)+/', ' ', preg_replace('/(\$\d+\$)/', '', $gMap[$qItem->group_id])))) : '';
                    if (!empty($qItem->title)) {
                        $data['question_list'][] = [
                            "question_id" => $qItem->id,
                            "title" => $group_title . ' ' . $qItem->title,
                        ];
                    } else {
                        if (isset($gMap[$qItem->group_id])) {
                            if (($key != 0 && $qItem->group_id != $qList[$key - 1]->group_id) || $key == 0) {
                                $data['question_list'][] = [
                                    "question_id" => $qItem->id,
                                    "title" => $group_title . ' ' . $qItem->title,
                                ];
                            }
                        }
                    }
                }
            }

            $this->guzzlePutData($id, $data);
        }
    }

    public function actionInitWriting()
    {
        $list = Exercises::find()->all();
        if (empty($list)) {
            var_dump("数据为空");
            die;
        }

        foreach ($list as $item) {
            $id = 3 . '-' . $item->id;
            $data = [
                "metadata" => [
                    "paper_id" => $item->id,
                    "type" => 3,
                    "paper_type" => substr_count($item->title, '剑雅') > 0 ? 1 : 2,
                    "author" => 1,
                    "create_time" => $item->create_time,
                    "update_time" => $item->update_time,
                    "creator" => $item->enter_by,
                ],
                "title" => $item->title,
                "question_list" => [],
                "content" => $item->content,
            ];

            $title = $data['title'];
            $title_arr = [];
            $len = mb_strlen($title);
            if ($len > 0) {
                for ($i = 0; $i < $len; $i++) {
                    for ($j = 1; $j <= $len; $j++) {
                        $title_arr[] = mb_substr($title, $i, $j);
                    }
                }
                $data['title'] = implode(" ", array_unique($title_arr));
            }

            $this->guzzlePutData($id, $data);
        }
    }

    public function actionInitEssay()
    {
        $list = WritingEssay::find()->all();
        if (empty($list)) {
            var_dump("数据为空");
            die;
        }

        foreach ($list as $item) {
            $id = 5 . '-' . $item->id;
            $data = [
                "metadata" => [
                    "paper_id" => $item->id,
                    "type" => 5,
                    "paper_type" => substr_count($item->title, '剑雅') > 0 ? 1 : 2,
                    "author" => 1,
                    "create_time" => $item->create_time,
                    "update_time" => $item->update_time,
                ],
                "title" => $item->title,
                "question_list" => [],
                "content" => $item->content,
            ];

            $title = $data['title'];
            $title_arr = [];
            $len = mb_strlen($title);
            if ($len > 0) {
                for ($i = 0; $i < $len; $i++) {
                    for ($j = 1; $j <= $len; $j++) {
                        $title_arr[] = mb_substr($title, $i, $j);
                    }
                }
                $data['title'] = implode(" ", array_unique($title_arr));
            }

            $this->guzzlePutData($id, $data);
        }
    }

    /**
     * php yii deal-with-search/init-vocabulary
     * Summary of actionInitVocabulary
     * @param mixed $start_id
     * @return void
     */
    public function actionInitVocabulary($start_id = 0)
    {
        $targetBookIds = [195, 196, 197, 198, 199];
        $targetVocabularyIds = VocabularyUnitRelation::find()
            ->select('vocabulary_id')
            ->distinct()
            ->where(['book_id' => $targetBookIds])
            ->column();
        $targetVocabularyIds = array_values(array_filter(array_map('intval', $targetVocabularyIds)));

        if (empty($targetVocabularyIds)) {
            var_dump("指定词书下没有关联的单词");
            return;
        }

        $pageSize = 1000;  // 每页处理1000条数据
        $page = 0;
        $totalProcessed = 0;

        $baseQuery = Vocabulary::find()->where(['id' => $targetVocabularyIds]);
        if ($start_id > 0) {
            $baseQuery->andWhere(['>', 'id', $start_id]);
        }

        $totalCount = (clone $baseQuery)->count();
        if ($totalCount == 0) {
            var_dump("符合条件的数据为空");
            return;
        }

        var_dump("总记录数: " . $totalCount);

        // 分页处理数据
        while (true) {
            $offset = $page * $pageSize;

            // 分页查询数据
            $list = (clone $baseQuery)
                ->orderBy(['id' => SORT_ASC])
                ->limit($pageSize)
                ->offset($offset)
                ->all();

            // 如果没有更多数据，退出循环
            if (empty($list)) {
                break;
            }

            var_dump("正在处理第 " . ($page + 1) . " 页，当前页记录数: " . count($list));

            foreach ($list as $item) {
                $id = 6 . '-' . $item->id;
                $data = [
                    "metadata" => [
                        "paper_id" => $item->id,
                        "type" => 6,
                        "paper_type" => 1,
                        "author" => 1,
                        "create_time" => $item->create_time,
                        "update_time" => $item->update_time,
                    ],
                    "title" => $item->name,
                    "content" => '',
                    "question_list" => [],
                ];

                // 构建搜索内容：词汇名称 + 中文翻译
                $searchContent = [];
                $searchContent[] = $item->name;

                // 从扩展表获取core_meanings字段中的中文翻译
                $vocabularyExt = VocabularyExt::findOne(['vocabulary_id' => $item->id]);
                if ($vocabularyExt && !empty($vocabularyExt->core_meanings)) {
                    $coreMeanings = is_array($vocabularyExt->core_meanings) ? $vocabularyExt->core_meanings : json_decode($vocabularyExt->core_meanings, true);
                    if ($coreMeanings && is_array($coreMeanings)) {
                        foreach ($coreMeanings as $meaning) {
                            if (isset($meaning['chineseTranslation']) && !empty($meaning['chineseTranslation'])) {
                                $searchContent[] = $meaning['chineseTranslation'];
                            }
                        }
                    }
                }

                $data['content'] = implode(' ', $searchContent);

                $this->guzzlePutData($id, $data);
                $totalProcessed++;
            }

            var_dump("已处理 " . $totalProcessed . " / " . $totalCount . " 条记录");

            $page++;

            // 释放内存
            unset($list);
        }

        var_dump("处理完成，共处理 " . $totalProcessed . " 条记录");

        $this->cleanupNonTargetVocabularyFromEs($targetVocabularyIds);
    }

    private function cleanupNonTargetVocabularyFromEs(array $allowedVocabularyIds): void
    {
        $allowedMap = [];
        foreach ($allowedVocabularyIds as $vocabularyId) {
            if ($vocabularyId > 0) {
                $allowedMap['6-' . $vocabularyId] = true;
            }
        }

        if (empty($allowedMap)) {
            return;
        }

        $esIds = $this->getEsDataIds();
        if (empty($esIds)) {
            var_dump("ES中没有现有词汇数据");
            return;
        }

        $idsToDelete = [];
        foreach ($esIds as $esId) {
            $esId = (string)$esId;
            if (strpos($esId, '6-') !== 0) {
                continue; // 只处理词汇相关的数据
            }

            if (!isset($allowedMap[$esId])) {
                $idsToDelete[] = $esId;
            }
        }

        if (empty($idsToDelete)) {
            var_dump("ES中没有需要额外删除的词汇数据");
            return;
        }

        var_dump("开始删除不在目标词书内的词汇，数量: " . count($idsToDelete));
        $this->deleteEsDataByIds($idsToDelete);
    }

    //清理ES中不在推送范围内的数据
    public function actionCleanupEsData()
    {
        var_dump("开始清理ES数据...");

        //获取当前应该推送的所有数据ID
        $currentIds = $this->getCurrentDataIds();
        var_dump("当前应该推送的数据数量: " . count($currentIds));

        //获取ES中现有的所有数据ID
        $esIds = $this->getEsDataIds();
        var_dump("ES中现有的数据数量: " . count($esIds));

        //找出需要删除的ID
        $idsToDelete = array_diff($esIds, $currentIds);
        var_dump("需要删除的数据数量: " . count($idsToDelete));

        if (empty($idsToDelete)) {
            var_dump("没有需要删除的数据");
            return;
        }

        //批量删除不在推送范围内的数据
        $this->deleteEsDataByIds($idsToDelete);
        var_dump("数据清理完成，共删除 " . count($idsToDelete) . " 条数据");
    }

    //获取当前应该推送的所有数据ID
    private function getCurrentDataIds()
    {
        $ids = [];

        //获取听力数据ID
        $listeningList = ListeningExamPaper::find()->where(['>', 'id', 0])->andWhere(['!=', 'unit', 276])->all();
        foreach ($listeningList as $item) {
            $ids[] = '1-' . $item->id;
        }

        //获取阅读数据ID
        $readingList = ReadingExamPaper::find()->where(['!=', 'unit', '963'])->all();
        foreach ($readingList as $item) {
            $ids[] = '2-' . $item->id;
        }

        //获取写作数据ID
        $writingList = Exercises::find()->all();
        foreach ($writingList as $item) {
            $ids[] = '3-' . $item->id;
        }

        //获取作文数据ID
        $essayList = WritingEssay::find()->all();
        foreach ($essayList as $item) {
            $ids[] = '5-' . $item->id;
        }

        //获取词汇数据ID
        $vocabularyList = Vocabulary::find()->where(['status' => 1])->all();
        foreach ($vocabularyList as $item) {
            $ids[] = '6-' . $item->id;
        }

        return $ids;
    }

    //获取ES中现有的所有数据ID
    private function getEsDataIds()
    {
        $client = new Client();
        $url = 'http://test-y1z.public.cn-hangzhou.es-serverless.aliyuncs.com:9200/duy-search/_search';

        try {
            $response = $client->post(
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Basic ' . base64_encode("test-y1z:9YHdbeazsJWGrd8Q1DL9u8BX0uHi6O"),
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'query' => [
                            'match_all' => new \stdClass()
                        ],
                        'size' => 10000,
                        '_source' => false
                    ],
                ],
            );

            $data = json_decode($response->getBody(), true);
            $ids = [];

            if (isset($data['hits']['hits'])) {
                foreach ($data['hits']['hits'] as $hit) {
                    $ids[] = $hit['_id'];
                }
            }

            return $ids;
        } catch (GuzzleException $e) {
            var_dump("获取ES数据失败：" . $e->getMessage());
            return [];
        }
    }

    //删除status=2的词汇数据
    public function actionDeleteDisabledVocabulary()
    {
        var_dump("开始删除status=2的词汇数据...");

        //查询所有status=2的词汇
        $disabledVocabularies = Vocabulary::find()->where(['status' => 2])->all();
        var_dump("找到 " . count($disabledVocabularies) . " 个禁用状态的词汇");

        if (empty($disabledVocabularies)) {
            var_dump("没有需要删除的禁用词汇");
            return;
        }

        //构建ES文档ID列表
        $esIdsToDelete = [];
        foreach ($disabledVocabularies as $vocabulary) {
            $esIdsToDelete[] = "6-{$vocabulary->id}";
        }

        var_dump("准备从ES中删除 " . count($esIdsToDelete) . " 条词汇数据");

        //删除ES中的数据
        $this->deleteEsDataByIds($esIdsToDelete);
        var_dump("删除完成");
    }

    //批量删除ES数据
    private function deleteEsDataByIds($ids)
    {
        $client = new Client();
        $url = 'http://test-y1z.public.cn-hangzhou.es-serverless.aliyuncs.com:9200/duy-search/_delete_by_query';

        //分批删除，避免请求过大
        $chunks = array_chunk($ids, 1000);

        foreach ($chunks as $chunk) {
            try {
                $response = $client->post(
                    $url,
                    [
                        'headers' => [
                            'Authorization' => 'Basic ' . base64_encode("test-y1z:9YHdbeazsJWGrd8Q1DL9u8BX0uHi6O"),
                            'Content-Type' => 'application/json',
                        ],
                        'json' => [
                            'query' => [
                                'ids' => [
                                    'values' => $chunk
                                ]
                            ]
                        ],
                    ],
                );

                $result = json_decode($response->getBody(), true);
                if (isset($result['deleted'])) {
                    var_dump("成功删除 " . $result['deleted'] . " 条数据");
                }
            } catch (GuzzleException $e) {
                var_dump("删除数据失败：" . $e->getMessage());
            }
        }
    }
}

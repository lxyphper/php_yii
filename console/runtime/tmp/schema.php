<?php
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../../vendor/yiisoft/yii2/helpers/ArrayHelper.php';
$config = yii\helpers\ArrayHelper::merge(
    require __DIR__ . '/../../../common/config/main.php',
    require __DIR__ . '/../../../common/config/main-local.php',
    require __DIR__ . '/../../../console/config/main.php',
    require __DIR__ . '/../../../console/config/main-local.php'
);
$app = new yii\console\Application($config);
$schema = Yii::$app->db->schema->getTableSchema('vocabulary_ext');
if (!$schema) {
    echo "no schema\n";
    exit(1);
}
echo json_encode(array_keys($schema->columns), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), "\n";

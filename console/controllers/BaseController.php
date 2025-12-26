<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;

/**
 * 基础控制器，提供数据库连接重试机制
 * 所有 console 控制器应继承此类
 */
class BaseController extends Controller
{
    /**
     * 在执行任何 action 之前自动确保数据库连接可用
     */
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // 自动重试连接数据库
        $this->ensureDbConnection();

        return true;
    }

    /**
     * 确保数据库连接可用，自动重试最多 3 次
     */
    protected function ensureDbConnection(int $maxRetries = 5, int $retryDelay = 2): void
    {
        $db = Yii::$app->db;

        for ($i = 0; $i < $maxRetries; $i++) {
            try {
                // 先关闭现有连接
                if ($db->isActive) {
                    $db->close();
                }
                // 尝试打开连接
                $db->open();
                if ($i > 0) {
                    $this->stdout("数据库连接成功（第 " . ($i + 1) . " 次尝试）\n");
                }
                return;
            } catch (\Exception $e) {
                $this->stderr("数据库连接失败（第 " . ($i + 1) . " 次尝试）: " . $e->getMessage() . "\n");
                if ($i < $maxRetries - 1) {
                    $this->stdout("等待 {$retryDelay} 秒后重试...\n");
                    sleep($retryDelay);
                }
            }
        }

        throw new \yii\db\Exception("无法连接数据库，已重试 {$maxRetries} 次");
    }
}

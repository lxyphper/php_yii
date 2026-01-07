# -*- coding: utf-8 -*-
"""
数据库配置
"""
import os

# 数据库连接配置
# 优先从环境变量读取，否则使用默认值
DB_CONFIG = {
    "host": os.getenv("DB_HOST", "rm-bp10uv3ty30e60qamuo.mysql.rds.aliyuncs.com"),
    "database": os.getenv("DB_NAME", "dauyan"),
    "user": os.getenv("DB_USER", "dauyan_user"),
    "password": os.getenv("DB_PASSWORD", "PpCwwY7aS48Utckg"),
    "charset": "utf8mb4",
    "connect_timeout": 30,
}

# 导出文件输出目录
OUTPUT_DIR = os.path.join(os.path.dirname(__file__), "output")

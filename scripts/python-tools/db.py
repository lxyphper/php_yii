# -*- coding: utf-8 -*-
"""
数据库连接模块
"""
import pymysql
from pymysql.cursors import DictCursor
from config import DB_CONFIG


def get_connection():
    """获取数据库连接"""
    return pymysql.connect(
        host=DB_CONFIG["host"],
        user=DB_CONFIG["user"],
        password=DB_CONFIG["password"],
        database=DB_CONFIG["database"],
        charset=DB_CONFIG["charset"],
        connect_timeout=DB_CONFIG.get("connect_timeout", 30),
        cursorclass=DictCursor,
    )


def fetch_all(sql: str, params: tuple = None) -> list:
    """执行查询并返回所有结果"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute(sql, params)
            return cursor.fetchall()
    finally:
        conn.close()


def fetch_one(sql: str, params: tuple = None) -> dict:
    """执行查询并返回单条结果"""
    conn = get_connection()
    try:
        with conn.cursor() as cursor:
            cursor.execute(sql, params)
            return cursor.fetchone()
    finally:
        conn.close()

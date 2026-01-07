# python-tools

## 依赖安装

```bash
pip install -r scripts/python-tools/requirements.txt
```

## 作为 HTTP 服务（给 Dify 调用）

安装服务端依赖：

```bash
pip install -r scripts/python-tools/requirements.txt -r scripts/python-tools/requirements-server.txt
```

启动：

```bash
cd scripts/python-tools
uvicorn api_server:app --host 0.0.0.0 --port 8000
```

可选鉴权：设置环境变量 `EXPORTER_API_KEY`，请求头带 `X-Api-Key`。

## Docker 部署（推荐）

在仓库根目录执行：

```bash
docker build -t my-yii2-exporter -f scripts/python-tools/Dockerfile .
docker run -d --name my-yii2-exporter -p 8000:8000 \
  -e DB_HOST=... -e DB_NAME=... -e DB_USER=... -e DB_PASSWORD=... \
  -e EXPORTER_API_KEY=... \
  my-yii2-exporter
```

## Docker Compose 启动（推荐）

1) 复制并填写环境变量：

```bash
cp scripts/python-tools/.env.example scripts/python-tools/.env
```

2) 启动 exporter 服务：

```bash
docker compose --env-file scripts/python-tools/.env -f docker-compose.exporter.yml up -d --build
```

3) 健康检查：

```bash
curl http://127.0.0.1:8000/healthz
```

自建 Dify（你的是 `http://192.168.76.4/`）在 Workflow 里请求：
- URL：`http://192.168.76.4:8000/export-record-multi-sheet`
- Method：POST
- Headers：`Content-Type: application/json`，以及 `X-Api-Key: <EXPORTER_API_KEY>`（如果设置了）

## 单独目录部署（与 PHP 项目无关）

如果你打算把 `scripts/python-tools/` 整个目录单独拷到服务器（例如 `/opt/exporter/`），直接在该目录下用 docker compose 启动即可：

```bash
cd /opt/exporter
cp .env.example .env
docker compose up -d --build
```

健康检查：

```bash
curl http://127.0.0.1:8000/healthz
```

接口示例：

```bash
curl -X POST http://127.0.0.1:8000/export-record-multi-sheet \
  -H 'Content-Type: application/json' \
  -H 'X-Api-Key: ...' \
  -o out.xlsx \
  -d '{"class_ids":"414,415","start_date":"2025-09-15","end_date":"2025-12-31","file_name":"25秋季所有北外国商"}'
```

## 环境变量

支持从环境变量读取数据库配置（也可放到 `.env` 文件里）：

- `DB_HOST`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`

## 导出：多个班级（每班一个 sheet）

对齐 `console/controllers/StudentController.php::actionExportRecordMultiSheet()`。

```bash
python scripts/python-tools/export_record_multi_sheet.py "414,415,416" 2025-09-15 2025-12-31 "25秋季所有北外国商" xlsx
```

输出目录默认：`scripts/python-tools/output/`

# -*- coding: utf-8 -*-
"""
给 Dify Workflow 调用的导出接口：直接返回 xlsx 文件（Content-Disposition: attachment）

启动：
  uvicorn api_server:app --host 0.0.0.0 --port 8000

鉴权（可选）：
  设置环境变量 EXPORTER_API_KEY，然后请求头带：X-Api-Key: <key>
"""

from __future__ import annotations

import os
import shutil
import sys
import tempfile
from datetime import datetime
from pathlib import Path
from uuid import uuid4

from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from fastapi.responses import FileResponse
from pydantic import BaseModel
from starlette.background import BackgroundTask


TOOLS_DIR = Path(__file__).resolve().parent
sys.path.insert(0, str(TOOLS_DIR))

from export_record_multi_sheet import (  # noqa: E402
    export_multi_sheet,
    parse_class_ids,
    parse_date_to_timestamp,
    sanitize_file_base_name,
)


XLSX_MEDIA_TYPE = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"


class ExportRecordMultiSheetRequest(BaseModel):
    class_ids: str
    start_date: str
    end_date: str
    file_name: str


def require_api_key(x_api_key: str | None) -> None:
    expected = (os.getenv("EXPORTER_API_KEY") or "").strip()
    if expected == "":
        return
    if (x_api_key or "").strip() != expected:
        raise HTTPException(status_code=401, detail="Unauthorized")


app = FastAPI(title="Exporter API", version="1.0.0")


@app.get("/healthz")
def healthz():
    return {"ok": True}


@app.post("/export-record-multi-sheet")
def export_record_multi_sheet(payload: ExportRecordMultiSheetRequest, x_api_key: str | None = Header(default=None)):
    require_api_key(x_api_key)

    class_ids = parse_class_ids(payload.class_ids)
    if not class_ids:
        raise HTTPException(status_code=400, detail="class_ids 不能为空")

    raw_file_name = (payload.file_name or "").strip()
    if raw_file_name == "":
        raise HTTPException(status_code=400, detail="导出文件名称不能为空")

    try:
        start_time = parse_date_to_timestamp(payload.start_date)
        end_time = parse_date_to_timestamp(payload.end_date)
    except ValueError:
        raise HTTPException(status_code=400, detail="日期格式错误，请使用 Y-m-d 格式，例如: 2025-05-12")

    if start_time > end_time:
        raise HTTPException(status_code=400, detail="开始日期不能大于结束日期")

    file_base_name = sanitize_file_base_name(raw_file_name)
    if file_base_name == "":
        file_base_name = f"export_{datetime.now().strftime('%Y%m%d_%H%M%S')}"

    tmp_dir = tempfile.mkdtemp(prefix="exporter_")
    unique_base_name = f"{file_base_name}_{uuid4().hex[:8]}"
    file_path = export_multi_sheet(
        class_ids=class_ids,
        start_time=start_time,
        end_time=end_time,
        file_base_name=unique_base_name,
        output_dir=tmp_dir,
    )

    download_name = f"{file_base_name}.xlsx"
    return FileResponse(
        path=file_path,
        media_type=XLSX_MEDIA_TYPE,
        filename=download_name,
        background=BackgroundTask(shutil.rmtree, tmp_dir, ignore_errors=True),
    )


def _load_env_once() -> None:
    load_dotenv()


_load_env_once()


"""Local record of jobs this agent already handled.

Guards against printing a job twice (e.g. a restart or a lost response) and
keeps status reports that could not reach the backend so they are retried.
Keyed by job id + queued_at, so a job re-queued for a reprint is a new entry.
"""
from __future__ import annotations

import json
import os
from pathlib import Path

MAX_ENTRIES = 500


class JobState:
    def __init__(self, path: Path):
        self.path = Path(path)
        self._entries: dict[str, dict] = self._load()

    @staticmethod
    def key(job: dict) -> str:
        return f"{job['id']}@{job.get('queued_at') or ''}"

    def get(self, key: str) -> dict | None:
        return self._entries.get(key)

    def record(self, key: str, job_id: int, status: str, error: str | None = None, reported: bool = False) -> None:
        self._entries.pop(key, None)  # re-insert so the newest entries survive pruning
        self._entries[key] = {"job_id": job_id, "status": status, "error": error, "reported": reported}
        self._save()

    def mark_reported(self, key: str) -> None:
        if key in self._entries:
            self._entries[key]["reported"] = True
            self._save()

    def unreported(self) -> list[tuple[str, dict]]:
        return [(k, e) for k, e in self._entries.items() if not e["reported"]]

    def _load(self) -> dict[str, dict]:
        if not self.path.is_file():
            return {}
        try:
            data = json.loads(self.path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            return {}
        return data if isinstance(data, dict) else {}

    def _save(self) -> None:
        while len(self._entries) > MAX_ENTRIES:
            oldest = next((k for k, e in self._entries.items() if e["reported"]), None)
            if oldest is None:
                break
            del self._entries[oldest]
        self.path.parent.mkdir(parents=True, exist_ok=True)
        tmp = self.path.with_suffix(".tmp")
        tmp.write_text(json.dumps(self._entries, indent=1), encoding="utf-8")
        os.replace(tmp, self.path)

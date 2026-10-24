"""HTTP client for the backend agent API (/api/agent/...)."""
from __future__ import annotations

import logging
from pathlib import Path

import requests

log = logging.getLogger("agent.api")


class ApiError(Exception):
    """Backend unreachable or returned an unexpected error; worth retrying later."""


class AuthError(ApiError):
    """Token rejected (401). Check agent_token."""


class NotFoundError(ApiError):
    """Job or file does not exist for this printer (404)."""


class ConflictError(ApiError):
    """Status change rejected (409), e.g. the job was cancelled meanwhile."""

    def __init__(self, message: str, current_status: str | None = None):
        super().__init__(message)
        self.current_status = current_status


class ApiClient:
    def __init__(self, base_url: str, token: str, timeout: int = 30, session: requests.Session | None = None):
        self.base_url = base_url.rstrip("/") + "/api/agent/"
        self.timeout = timeout
        self.session = session or requests.Session()
        self.session.headers.update({"Authorization": f"Bearer {token}", "Accept": "application/json"})

    def _request(self, method: str, path: str, **kwargs) -> requests.Response:
        try:
            resp = self.session.request(method, self.base_url + path, timeout=self.timeout, **kwargs)
        except requests.RequestException as e:
            raise ApiError(f"{method} {path}: {e}") from e

        if resp.status_code == 401:
            raise AuthError("backend rejected the agent token (401)")
        if resp.status_code == 404:
            raise NotFoundError(f"{method} {path}: not found")
        if resp.status_code == 409:
            body = _json_or_empty(resp)
            raise ConflictError(body.get("message", "conflict"), body.get("current_status"))
        if resp.status_code >= 400:
            raise ApiError(f"{method} {path}: HTTP {resp.status_code}")
        return resp

    def heartbeat(self) -> None:
        self._request("POST", "heartbeat")

    def next_job(self) -> dict | None:
        resp = self._request("GET", "jobs/next")
        if resp.status_code == 204:
            return None
        return resp.json()["data"]

    def download(self, job_id: int, dest: Path) -> Path:
        """Stream the job PDF to dest (written to a .part file first, so a cut download never looks complete)."""
        dest = Path(dest)
        dest.parent.mkdir(parents=True, exist_ok=True)
        part = dest.with_suffix(dest.suffix + ".part")
        resp = self._request("GET", f"jobs/{job_id}/file", stream=True)
        try:
            with part.open("wb") as f:
                for chunk in resp.iter_content(chunk_size=64 * 1024):
                    f.write(chunk)
        except requests.RequestException as e:
            part.unlink(missing_ok=True)
            raise ApiError(f"download of job {job_id} interrupted: {e}") from e
        part.replace(dest)
        return dest

    def report_status(self, job_id: int, status: str, error: str | None = None) -> dict:
        payload = {"status": status}
        if error:
            payload["error"] = error[:2000]
        return self._request("POST", f"jobs/{job_id}/status", json=payload).json()["data"]


def _json_or_empty(resp: requests.Response) -> dict:
    try:
        body = resp.json()
    except ValueError:
        return {}
    return body if isinstance(body, dict) else {}

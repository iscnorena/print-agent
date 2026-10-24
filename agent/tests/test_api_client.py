import sys
from pathlib import Path

import pytest
import requests

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from api_client import ApiClient, ApiError, AuthError, ConflictError, NotFoundError  # noqa: E402


def response(status, json_body=None, content=b""):
    resp = requests.Response()
    resp.status_code = status
    if json_body is not None:
        import json
        content = json.dumps(json_body).encode()
    resp._content = content
    resp._content_consumed = True  # lets iter_content() replay _content instead of reading a socket
    return resp


class FakeSession:
    def __init__(self, *responses):
        self.headers = {}
        self.responses = list(responses)
        self.calls = []

    def request(self, method, url, **kwargs):
        self.calls.append((method, url, kwargs))
        item = self.responses.pop(0)
        if isinstance(item, Exception):
            raise item
        return item


def client(*responses):
    session = FakeSession(*responses)
    return ApiClient("http://backend:8000/", "pa_secret", session=session), session


def test_sends_bearer_token_to_agent_api():
    api, session = client(response(200, {"server_time": "x"}))

    api.heartbeat()

    method, url, _ = session.calls[0]
    assert (method, url) == ("POST", "http://backend:8000/api/agent/heartbeat")
    assert session.headers["Authorization"] == "Bearer pa_secret"


def test_next_job_returns_none_on_204():
    api, _ = client(response(204))

    assert api.next_job() is None


def test_next_job_returns_job_data():
    api, _ = client(response(200, {"data": {"id": 3}}))

    assert api.next_job() == {"id": 3}


@pytest.mark.parametrize("status, error", [(401, AuthError), (404, NotFoundError), (500, ApiError)])
def test_maps_http_errors(status, error):
    api, _ = client(response(status, {}))

    with pytest.raises(error):
        api.next_job()


def test_conflict_carries_current_status():
    api, _ = client(response(409, {"message": "nope", "current_status": "cancelled"}))

    with pytest.raises(ConflictError) as exc:
        api.report_status(1, "printing")

    assert exc.value.current_status == "cancelled"


def test_network_error_becomes_api_error():
    api, _ = client(requests.ConnectionError("refused"))

    with pytest.raises(ApiError):
        api.heartbeat()


def test_report_status_sends_error_message():
    api, session = client(response(200, {"data": {"status": "failed"}}))

    api.report_status(5, "failed", "jam")

    assert session.calls[0][2]["json"] == {"status": "failed", "error": "jam"}


def test_download_writes_file(tmp_path):
    api, _ = client(response(200, content=b"%PDF-1.4 data"))

    dest = api.download(5, tmp_path / "job-5.pdf")

    assert dest.read_bytes() == b"%PDF-1.4 data"
    assert not (tmp_path / "job-5.pdf.part").exists()

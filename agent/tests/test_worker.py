import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from api_client import ApiError, ConflictError, NotFoundError  # noqa: E402
from printing import PrintError  # noqa: E402
from state import JobState  # noqa: E402
from worker import Worker  # noqa: E402

PDF_BYTES = b"%PDF-1.4 test"


def make_job(job_id=1, **extra):
    return {"id": job_id, "folio": str(job_id), "copies": 1, "color": False, "duplex": "simplex",
            "page_range": None, "queued_at": "2026-10-24T10:00:00-06:00", **extra}


class FakeClient:
    def __init__(self, jobs=(), file_bytes=PDF_BYTES):
        self.jobs = list(jobs)
        self.file_bytes = file_bytes
        self.reports = []
        self.heartbeats = 0
        self.fail_reports = 0  # number of report_status calls that raise ApiError
        self.conflict_on = set()  # statuses answered with 409
        self.missing_file = False

    def heartbeat(self):
        self.heartbeats += 1

    def next_job(self):
        return self.jobs.pop(0) if self.jobs else None

    def download(self, job_id, dest):
        if self.missing_file:
            raise NotFoundError("missing")
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_bytes(self.file_bytes)
        return dest

    def report_status(self, job_id, status, error=None):
        if status in self.conflict_on:
            raise ConflictError("conflict", "cancelled")
        if self.fail_reports:
            self.fail_reports -= 1
            raise ApiError("backend down")
        self.reports.append((job_id, status, error))
        return {}


class FakePrinter:
    def __init__(self, error=None):
        self.calls = []
        self.error = error

    def __call__(self, pdf, printer, opts, sumatra_path):
        assert Path(pdf).read_bytes().startswith(b"%PDF-")
        self.calls.append((printer, opts))
        if self.error:
            raise PrintError(self.error)


@pytest.fixture
def make_worker(tmp_path):
    def _make(client, printer=None, clock=lambda: 0.0):
        return Worker(client=client, state=JobState(tmp_path / "state.json"), printer_name="Brother",
                      download_dir=tmp_path / "downloads", print_fn=printer or FakePrinter(),
                      sleep=lambda s: None, clock=clock)
    return _make


def test_prints_job_and_reports_printing_then_printed(make_worker, tmp_path):
    client = FakeClient([make_job(7, copies=2, color=True, duplex="long", page_range="1-3")])
    printer = FakePrinter()

    assert make_worker(client, printer).tick() is True

    assert client.reports == [(7, "printing", None), (7, "printed", None)]
    name, opts = printer.calls[0]
    assert name == "Brother"
    assert (opts.copies, opts.color, opts.duplex, opts.page_range) == (2, True, "long", "1-3")
    assert not (tmp_path / "downloads" / "job-7.pdf").exists()


def test_returns_false_when_no_job(make_worker):
    assert make_worker(FakeClient()).tick() is False


def test_reports_failed_with_spooler_error(make_worker):
    client = FakeClient([make_job(3)])

    make_worker(client, FakePrinter(error="printer offline")).tick()

    assert client.reports == [(3, "printing", None), (3, "failed", "printer offline")]


def test_never_prints_the_same_queueing_twice(make_worker):
    client = FakeClient([make_job(5), make_job(5)])
    printer = FakePrinter()
    worker = make_worker(client, printer)

    worker.tick()
    worker.tick()

    assert len(printer.calls) == 1
    assert client.reports[-1][1] == "failed"


def test_prints_again_when_requeued_for_a_reprint(make_worker):
    client = FakeClient([make_job(5), make_job(5, queued_at="2026-10-24T12:00:00-06:00")])
    printer = FakePrinter()
    worker = make_worker(client, printer)

    worker.tick()
    worker.tick()

    assert len(printer.calls) == 2


def test_remembers_printed_jobs_across_restarts(make_worker):
    first = FakeClient([make_job(9)])
    printer = FakePrinter()
    make_worker(first, printer).tick()

    restarted = FakeClient([make_job(9)])
    make_worker(restarted, printer).tick()

    assert len(printer.calls) == 1


def test_retries_printed_report_after_backend_outage(make_worker):
    client = FakeClient([make_job(4)])
    worker = make_worker(client)

    # "printing" goes through, then the backend goes down before "printed".
    original = client.report_status

    def flaky(job_id, status, error=None):
        if status == "printed" and not getattr(flaky, "failed", False):
            flaky.failed = True
            raise ApiError("backend down")
        return original(job_id, status, error)

    client.report_status = flaky

    with pytest.raises(ApiError):
        worker.tick()
    assert client.reports == [(4, "printing", None)]

    worker.tick()  # next round flushes the pending report without printing again
    assert client.reports == [(4, "printing", None), (4, "printed", None)]


def test_skips_job_cancelled_before_printing(make_worker):
    client = FakeClient([make_job(2)])
    client.conflict_on = {"printing"}
    printer = FakePrinter()

    make_worker(client, printer).tick()

    assert printer.calls == []
    assert client.reports == []


def test_fails_job_when_file_is_missing(make_worker):
    client = FakeClient([make_job(6)])
    client.missing_file = True
    printer = FakePrinter()

    make_worker(client, printer).tick()

    assert printer.calls == []
    assert client.reports == [(6, "printing", None), (6, "failed", "PDF file not available on the server.")]


def test_fails_job_when_download_is_not_a_pdf(make_worker):
    client = FakeClient([make_job(8)], file_bytes=b"<html>error</html>")
    printer = FakePrinter()

    make_worker(client, printer).tick()

    assert printer.calls == []
    assert client.reports[-1] == (8, "failed", "Downloaded file is not a PDF.")


def test_sends_heartbeat_only_when_due(make_worker):
    now = [0.0]
    client = FakeClient()
    worker = make_worker(client, clock=lambda: now[0])

    worker.tick()
    now[0] = 30
    worker.tick()
    now[0] = 61
    worker.tick()

    assert client.heartbeats == 2


def test_backoff_grows_and_caps(make_worker):
    worker = make_worker(FakeClient())

    assert worker.backoff_delay(0) == 5
    assert worker.backoff_delay(1) == 10
    assert worker.backoff_delay(2) == 20
    assert worker.backoff_delay(10) == 60

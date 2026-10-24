"""Polling loop: heartbeat -> next job -> download -> print -> report."""
from __future__ import annotations

import logging
import time
from pathlib import Path
from typing import Callable

from api_client import ApiClient, ApiError, AuthError, ConflictError, NotFoundError
from printing import PrintError, PrintOptions, print_pdf
from state import JobState

log = logging.getLogger("agent.worker")

MAX_BACKOFF_SECONDS = 60


class Worker:
    def __init__(self, client: ApiClient, state: JobState, printer_name: str, download_dir: Path,
                 sumatra_path: str | None = None, paper: str = "letter",
                 poll_interval: float = 5, heartbeat_interval: float = 60,
                 print_fn: Callable = print_pdf, sleep: Callable[[float], None] = time.sleep,
                 clock: Callable[[], float] = time.monotonic):
        self.client = client
        self.state = state
        self.printer_name = printer_name
        self.download_dir = Path(download_dir)
        self.sumatra_path = sumatra_path
        self.paper = paper
        self.poll_interval = poll_interval
        self.heartbeat_interval = heartbeat_interval
        self.print_fn = print_fn
        self.sleep = sleep
        self.clock = clock
        self._last_heartbeat: float | None = None

    def run_forever(self) -> None:
        log.info("agent started; printer=%r", self.printer_name)
        failures = 0
        while True:
            try:
                handled_job = self.tick()
                failures = 0
            except AuthError as e:
                failures += 1
                log.error("%s", e)
                handled_job = False
            except ApiError as e:
                failures += 1
                log.warning("backend unavailable: %s", e)
                handled_job = False
            except Exception:
                # Keep the agent alive; the local state prevents reprinting the job.
                failures += 1
                log.exception("unexpected error")
                handled_job = False
            # After a job, ask again right away; after errors, back off exponentially.
            self.sleep(0 if handled_job else self.backoff_delay(failures))

    def backoff_delay(self, failures: int) -> float:
        if failures == 0:
            return self.poll_interval
        return min(self.poll_interval * 2 ** failures, MAX_BACKOFF_SECONDS)

    def tick(self) -> bool:
        """One polling round. Returns True when a job was handled."""
        self._heartbeat_if_due()
        self.flush_reports()
        job = self.client.next_job()
        if job is None:
            return False
        self.process(job)
        return True

    def flush_reports(self) -> None:
        """Send status reports that failed to reach the backend earlier."""
        for key, entry in self.state.unreported():
            try:
                self.client.report_status(entry["job_id"], entry["status"], entry.get("error"))
            except ConflictError as e:
                log.warning("job %s: backend refused %r (%s); dropping report", entry["job_id"], entry["status"], e)
            self.state.mark_reported(key)

    def process(self, job: dict) -> None:
        job_id = job["id"]
        key = JobState.key(job)

        if self.state.get(key) is not None:
            # Already handled this exact queueing; never print it again.
            log.warning("job %s was already handled by this agent; marking failed instead of reprinting", job_id)
            self._fail_without_printing(job_id, key, "Already processed by this agent; reprint from the panel if needed.")
            return

        pdf = self.download_dir / f"job-{job_id}.pdf"
        try:
            self.client.download(job_id, pdf)
        except NotFoundError:
            self._fail_without_printing(job_id, key, "PDF file not available on the server.")
            return

        try:
            if not _looks_like_pdf(pdf):
                self._fail_without_printing(job_id, key, "Downloaded file is not a PDF.")
                return

            try:
                self.client.report_status(job_id, "printing")
            except ConflictError as e:
                log.info("job %s no longer printable (%s); skipping", job_id, e.current_status)
                return
            self.state.record(key, job_id, "printing", reported=True)

            opts = PrintOptions(copies=job.get("copies", 1), color=bool(job.get("color")),
                                duplex=job.get("duplex", "simplex"), page_range=job.get("page_range"),
                                paper=self.paper)
            try:
                self.print_fn(pdf, self.printer_name, opts, self.sumatra_path)
            except PrintError as e:
                log.error("job %s failed: %s", job_id, e)
                self.state.record(key, job_id, "failed", error=str(e))
            else:
                log.info("job %s (folio %s) printed", job_id, job.get("folio"))
                self.state.record(key, job_id, "printed")
            self.flush_reports()
        finally:
            pdf.unlink(missing_ok=True)

    def _heartbeat_if_due(self) -> None:
        now = self.clock()
        if self._last_heartbeat is None or now - self._last_heartbeat >= self.heartbeat_interval:
            self.client.heartbeat()
            self._last_heartbeat = now

    def _fail_without_printing(self, job_id: int, key: str, reason: str) -> None:
        """A queued job can only fail after "printing", so report both."""
        try:
            self.client.report_status(job_id, "printing")
        except ConflictError:
            return
        self.state.record(key, job_id, "failed", error=reason)
        self.flush_reports()


def _looks_like_pdf(path: Path) -> bool:
    with path.open("rb") as f:
        return f.read(5) == b"%PDF-"

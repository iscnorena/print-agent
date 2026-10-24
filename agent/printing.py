"""Print backends for the local print agent.

Windows: SumatraPDF in silent mode.
Linux (Raspberry Pi): CUPS `lp`.
"""
from __future__ import annotations

import logging
import platform
import re
import shutil
import subprocess
from dataclasses import dataclass
from pathlib import Path

log = logging.getLogger("agent.printing")

PAGE_RANGE_RE = re.compile(r"^\s*\d+(\s*-\s*\d+)?(\s*,\s*\d+(\s*-\s*\d+)?)*\s*$")
DUPLEX_VALUES = {"simplex", "long", "short"}
PAPER_VALUES = {"letter": "Letter", "legal": "Legal", "a4": "A4"}


class PrintError(Exception):
    pass


@dataclass
class PrintOptions:
    copies: int = 1
    color: bool = False  # False = black & white
    duplex: str = "simplex"  # simplex | long | short
    page_range: str | None = None  # e.g. "1-3,5"
    fit_to_page: bool = True
    paper: str = "letter"  # letter | legal | a4

    def validate(self) -> None:
        if not 1 <= self.copies <= 99:
            raise PrintError("copies must be between 1 and 99")
        if self.duplex not in DUPLEX_VALUES:
            raise PrintError(f"duplex must be one of {sorted(DUPLEX_VALUES)}")
        if self.paper not in PAPER_VALUES:
            raise PrintError(f"paper must be one of {sorted(PAPER_VALUES)}")
        if self.page_range and not PAGE_RANGE_RE.match(self.page_range):
            raise PrintError(f"invalid page range: {self.page_range!r}")

    def normalized_range(self) -> str | None:
        return re.sub(r"\s+", "", self.page_range) if self.page_range else None


def build_windows_command(sumatra_path: str, printer: str, pdf: Path, opts: PrintOptions) -> list[str]:
    opts.validate()
    settings = [f"{opts.copies}x", "fit" if opts.fit_to_page else "noscale", f"paper={opts.paper}"]
    settings.append("color" if opts.color else "monochrome")
    settings.append({"simplex": "simplex", "long": "duplexlong", "short": "duplexshort"}[opts.duplex])
    if opts.normalized_range():
        settings.append(opts.normalized_range())
    return [
        sumatra_path,
        "-print-to", printer,
        "-print-settings", ",".join(settings),
        "-silent",
        "-exit-when-done",
        str(pdf),
    ]


def build_linux_command(printer: str, pdf: Path, opts: PrintOptions) -> list[str]:
    opts.validate()
    cmd = ["lp", "-d", printer, "-n", str(opts.copies), "-o", f"media={PAPER_VALUES[opts.paper]}"]
    if opts.fit_to_page:
        cmd += ["-o", "fit-to-page"]
    cmd += ["-o", "print-color-mode=" + ("color" if opts.color else "monochrome")]
    cmd += ["-o", "sides=" + {
        "simplex": "one-sided",
        "long": "two-sided-long-edge",
        "short": "two-sided-short-edge",
    }[opts.duplex]]
    if opts.normalized_range():
        cmd += ["-o", f"page-ranges={opts.normalized_range()}"]
    cmd.append(str(pdf))
    return cmd


def is_windows() -> bool:
    return platform.system() == "Windows"


def list_printers() -> list[str]:
    if is_windows():
        out = subprocess.run(
            ["powershell", "-NoProfile", "-Command", "Get-Printer | Select-Object -ExpandProperty Name"],
            capture_output=True, text=True, timeout=30,
        )
    else:
        if not shutil.which("lpstat"):
            raise PrintError("lpstat not found: install CUPS (sudo apt install cups)")
        out = subprocess.run(["lpstat", "-e"], capture_output=True, text=True, timeout=30)
    if out.returncode != 0:
        raise PrintError(out.stderr.strip() or "could not list printers")
    return [line.strip() for line in out.stdout.splitlines() if line.strip()]


def print_pdf(pdf: Path, printer: str, opts: PrintOptions, sumatra_path: str | None = None,
              timeout: int = 300) -> None:
    pdf = Path(pdf)
    if not pdf.is_file():
        raise PrintError(f"file not found: {pdf}")
    if pdf.suffix.lower() != ".pdf":
        raise PrintError("only PDF files can be printed by the agent")

    if is_windows():
        if not sumatra_path or not Path(sumatra_path).is_file():
            raise PrintError(f"SumatraPDF not found at {sumatra_path!r}; check sumatra_path in config.yaml")
        cmd = build_windows_command(sumatra_path, printer, pdf, opts)
    else:
        if not shutil.which("lp"):
            raise PrintError("lp not found: install CUPS (sudo apt install cups)")
        cmd = build_linux_command(printer, pdf, opts)

    log.info("printing %s on %r with %s", pdf.name, printer, opts)
    try:
        result = subprocess.run(cmd, capture_output=True, text=True, timeout=timeout)
    except subprocess.TimeoutExpired as e:
        raise PrintError(f"print command timed out after {timeout}s") from e
    except OSError as e:
        raise PrintError(f"could not run print command: {e}") from e
    if result.returncode != 0:
        raise PrintError(result.stderr.strip() or f"print command failed with code {result.returncode}")
    log.info("sent to spooler: %s", pdf.name)

"""Local print agent — Phase 1.

Usage:
    python agent.py --list-printers
    python agent.py --test-print archivo.pdf [--copies 2] [--color] [--duplex long] [--pages 1-3]
    python agent.py --run          # poll the backend and print queued jobs
"""
from __future__ import annotations

import argparse
import logging
import os
import sys
from logging.handlers import RotatingFileHandler
from pathlib import Path

import yaml
from dotenv import load_dotenv

from api_client import ApiClient
from printing import PrintError, PrintOptions, list_printers, print_pdf
from state import JobState
from worker import Worker

BASE_DIR = Path(__file__).resolve().parent
DEFAULT_CONFIG = BASE_DIR / "config.yaml"


def load_config(path: Path) -> dict:
    if not path.is_file():
        sys.exit(f"Config not found: {path}. Copy config.example.yaml to config.yaml and edit it.")
    with path.open(encoding="utf-8") as f:
        cfg = yaml.safe_load(f) or {}
    if not cfg.get("printer_name"):
        sys.exit("printer_name is required in config.yaml (run --list-printers to see names).")
    # The token is a secret: AGENT_TOKEN (environment or agent/.env) wins over config.yaml.
    cfg["agent_token"] = os.environ.get("AGENT_TOKEN") or cfg.get("agent_token") or ""
    return cfg


def setup_logging(log_dir: Path) -> None:
    log_dir.mkdir(parents=True, exist_ok=True)
    fmt = logging.Formatter("%(asctime)s %(levelname)s %(name)s: %(message)s")
    file_handler = RotatingFileHandler(log_dir / "agent.log", maxBytes=1_000_000, backupCount=5, encoding="utf-8")
    file_handler.setFormatter(fmt)
    console = logging.StreamHandler()
    console.setFormatter(fmt)
    root = logging.getLogger()
    root.setLevel(logging.INFO)
    root.addHandler(file_handler)
    root.addHandler(console)


def main() -> int:
    parser = argparse.ArgumentParser(description="Local print agent")
    parser.add_argument("--config", type=Path, default=DEFAULT_CONFIG)
    parser.add_argument("--list-printers", action="store_true", help="list printers installed on this system")
    parser.add_argument("--test-print", type=Path, metavar="PDF", help="print a local PDF")
    parser.add_argument("--run", action="store_true", help="poll the backend and print queued jobs")
    parser.add_argument("--copies", type=int, default=1)
    parser.add_argument("--color", action="store_true", help="print in color (default: black & white)")
    parser.add_argument("--duplex", choices=["simplex", "long", "short"], default="simplex")
    parser.add_argument("--pages", help='page range, e.g. "1-3,5"')
    parser.add_argument("--paper", choices=["letter", "legal", "a4"], help="paper size (default from config)")
    args = parser.parse_args()

    if args.list_printers:
        try:
            for name in list_printers():
                print(name)
        except PrintError as e:
            print(f"Error: {e}", file=sys.stderr)
            return 1
        return 0

    load_dotenv(BASE_DIR / ".env")
    cfg = load_config(args.config)
    setup_logging(BASE_DIR / cfg.get("log_dir", "logs"))

    if args.test_print:
        opts = PrintOptions(copies=args.copies, color=args.color, duplex=args.duplex, page_range=args.pages,
                            paper=args.paper or cfg.get("paper", "letter"))
        try:
            print_pdf(args.test_print, cfg["printer_name"], opts, cfg.get("sumatra_path"))
        except PrintError as e:
            logging.getLogger("agent").error("print failed: %s", e)
            return 1
        print("Enviado a la impresora.")
        return 0

    if args.run:
        if not cfg.get("api_base_url") or not cfg["agent_token"]:
            sys.exit("api_base_url and AGENT_TOKEN (or agent_token) are required for --run.")
        worker = Worker(
            client=ApiClient(cfg["api_base_url"], cfg["agent_token"]),
            state=JobState(BASE_DIR / cfg.get("state_file", "state.json")),
            printer_name=cfg["printer_name"],
            download_dir=BASE_DIR / cfg.get("download_dir", "downloads"),
            sumatra_path=cfg.get("sumatra_path"),
            paper=cfg.get("paper", "letter"),
            poll_interval=float(cfg.get("poll_interval_seconds", 5)),
            heartbeat_interval=float(cfg.get("heartbeat_interval_seconds", 60)),
        )
        try:
            worker.run_forever()
        except KeyboardInterrupt:
            logging.getLogger("agent").info("agent stopped")
        return 0

    parser.print_help()
    return 0


if __name__ == "__main__":
    sys.exit(main())

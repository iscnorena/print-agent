import sys
from pathlib import Path

import pytest

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from printing import PrintError, PrintOptions, build_linux_command, build_windows_command  # noqa: E402

PDF = Path("doc.pdf")


def test_windows_default_is_bw_simplex_fit():
    cmd = build_windows_command("Sumatra.exe", "Brother", PDF, PrintOptions())
    assert cmd[cmd.index("-print-settings") + 1] == "1x,fit,paper=letter,monochrome,simplex"
    assert "-silent" in cmd and cmd[-1] == "doc.pdf"
    assert cmd[cmd.index("-print-to") + 1] == "Brother"


def test_windows_color_duplex_copies_range():
    opts = PrintOptions(copies=3, color=True, duplex="long", page_range="1-3, 5")
    cmd = build_windows_command("Sumatra.exe", "Brother", PDF, opts)
    assert cmd[cmd.index("-print-settings") + 1] == "3x,fit,paper=letter,color,duplexlong,1-3,5"


def test_linux_command():
    opts = PrintOptions(copies=2, color=False, duplex="short", page_range="2-4")
    cmd = build_linux_command("Brother", PDF, opts)
    assert cmd[:5] == ["lp", "-d", "Brother", "-n", "2"]
    assert "media=Letter" in cmd
    assert "print-color-mode=monochrome" in cmd
    assert "sides=two-sided-short-edge" in cmd
    assert "page-ranges=2-4" in cmd


@pytest.mark.parametrize("bad", [
    PrintOptions(copies=0),
    PrintOptions(copies=100),
    PrintOptions(duplex="sideways"),
    PrintOptions(page_range="1-3;5"),
    PrintOptions(page_range="abc"),
    PrintOptions(paper="tabloid"),
])
def test_invalid_options(bad):
    with pytest.raises(PrintError):
        build_linux_command("Brother", PDF, bad)


def test_missing_print_program_raises_print_error(tmp_path, monkeypatch):
    import printing

    pdf = tmp_path / "doc.pdf"
    pdf.write_bytes(b"%PDF-1.4")
    monkeypatch.setattr(printing, "is_windows", lambda: True)

    with pytest.raises(PrintError, match="SumatraPDF not found"):
        printing.print_pdf(pdf, "Brother", PrintOptions(), str(tmp_path / "missing.exe"))

    monkeypatch.setattr(printing, "is_windows", lambda: False)
    monkeypatch.setattr(printing.shutil, "which", lambda name: None)

    with pytest.raises(PrintError, match="lp not found"):
        printing.print_pdf(pdf, "Brother", PrintOptions())

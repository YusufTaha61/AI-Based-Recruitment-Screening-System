"""Read a CV file (PDF or TXT) and print its plain text.

Usage: python extract_text.py path/to/cv.pdf
"""
import sys
from pathlib import Path


def extract_text(path: Path) -> str:
    if path.suffix.lower() == ".pdf":
        from pypdf import PdfReader

        reader = PdfReader(str(path))
        return "\n".join(page.extract_text() or "" for page in reader.pages)
    return path.read_text(encoding="utf-8", errors="ignore")


if __name__ == "__main__":
    if len(sys.argv) != 2:
        sys.exit("Usage: python extract_text.py <cv_file>")
    sys.stdout.reconfigure(encoding="utf-8")
    print(extract_text(Path(sys.argv[1])).strip())

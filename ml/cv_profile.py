"""Guess a candidate's profile from a CV file, to pre-fill the evaluation form.

Usage: python cv_profile.py path/to/cv.pdf
Prints JSON:
  {"name": "Ayşe Yılmaz", "level": "junior", "education_level": 1,
   "years_experience": 1.0, "technical_skill": 68, "communication_skill": 55,
   "job_stability": 1.0, "technical_found": [...], "communication_found": [...]}
These are only estimates; the user checks and corrects them in the form.
"""
import json
import re
import sys
from math import exp
from pathlib import Path

from extract_text import extract_text
from features import DATE_RANGE, education_level, years_of_experience

sys.stdout.reconfigure(encoding="utf-8")

TECHNICAL_WORDS = [
    "python", "java", "javascript", "typescript", "php", "laravel", "c#", "c++", "sql", "mysql",
    "postgresql", "mongodb", "html", "css", "react", "angular", "vue", "node", "django", "flask",
    "docker", "kubernetes", "git", "linux", "aws", "azure", "machine learning", "makine öğrenmesi",
    "yapay zeka", "deep learning", "derin öğrenme", "pandas", "numpy", "scikit", "tensorflow",
    "excel", "power bi", "tableau", "flutter", "kotlin", "swift", "rest api", "agile", "scrum",
    "sap", "autocad", "matlab", "veri analizi", "data analysis", "crm", "jira",
]
COMMUNICATION_WORDS = [
    "iletişim", "communication", "sunum", "presentation", "ekip", "takım", "team", "liderlik",
    "leadership", "müşteri", "customer", "müzakere", "negotiation", "ingilizce", "english",
    "almanca", "german", "koordinasyon", "mentor", "eğitmen", "topluluk", "gönüllü", "kulüp",
]
STUDENT_WORDS = re.compile(
    r"stajyer|staj başvuru|internship|\bintern\b|öğrenciyim|lisans öğrencisi|[1-4]\. sınıf|beklenen mezuniyet"
)


def lower_tr(text: str) -> str:
    """Turkish lower-case: I -> ı, İ -> i."""
    return text.replace("I", "ı").replace("İ", "i").lower()


def upper_tr(text: str) -> str:
    return text.replace("i", "İ").replace("ı", "I").upper()


def find_name(text: str) -> str:
    """First line with 2-4 words made only of letters, e.g. "ZEYNEP AYDIN"."""
    for line in text.splitlines():
        line = line.strip()
        if re.fullmatch(r"[A-Za-zÇĞİÖŞÜçğıöşü' ]{3,50}", line) and 2 <= len(line.split()) <= 4:
            if line.isupper():  # "ZEYNEP AYDIN" -> "Zeynep Aydın"
                line = " ".join(upper_tr(w[0]) + lower_tr(w[1:]) for w in line.split())
            return line
    return ""


def skill_score(count: int, speed: float) -> int:
    """30-95 points. The first skills add a lot, later ones less and less."""
    return round(30 + 65 * (1 - exp(-count / speed)))


def guess_level(text: str, years: float) -> str:
    if STUDENT_WORDS.search(text) and years < 1:
        return "intern"
    if years < 2:
        return "junior"
    return "mid" if years < 8 else "senior"


def cv_profile(path: Path) -> dict:
    text = extract_text(path)
    if not text.strip():
        raise ValueError("CV'den yazı okunamadı (taranmış/resim PDF olabilir).")
    low = lower_tr(text)

    years = min(20, years_of_experience(text))
    jobs = max(1, len(DATE_RANGE.findall(low)))  # number of "2019 - 2022" style job dates
    stability = round(min(10, max(0.5, years / jobs if years else 0.5)), 1)

    technical = [w for w in TECHNICAL_WORDS if re.search(r"(?<![a-zçğıöşü])" + re.escape(w), low)]
    communication = [w for w in COMMUNICATION_WORDS if w in low]

    return {
        "name": find_name(text),
        "level": guess_level(low, years),
        "education_level": max(1, education_level(text)),  # no degree found -> Bachelor
        "years_experience": float(years),
        "technical_skill": skill_score(len(technical), 8),
        "communication_skill": skill_score(len(communication), 5),
        "job_stability": stability,
        "technical_found": technical[:15],
        "communication_found": communication[:15],
    }


if __name__ == "__main__":
    try:
        print(json.dumps(cv_profile(Path(sys.argv[1])), ensure_ascii=False))
    except Exception as e:
        print(json.dumps({"error": str(e)}, ensure_ascii=False))

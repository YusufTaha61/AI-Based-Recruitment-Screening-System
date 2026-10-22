"""Turn a CV text and a job's requirements into numbers the model can use."""
import re
from datetime import date

# English and Turkish words for each degree
EDUCATION_LEVELS = {
    "phd": 3,
    "doctorate": 3,
    "doktora": 3,
    "master": 2,
    "msc": 2,
    "mba": 2,
    "yüksek lisans": 2,
    "bachelor": 1,
    "bsc": 1,
    "university": 1,
    "lisans": 1,
    "üniversite": 1,
}

# "2019 - 2022" or "2022 - Günümüz" style job dates
DATE_RANGE = re.compile(
    r"((?:19|20)\d{2})\s*[-–—]\s*((?:19|20)\d{2}|günümüz|halen|devam|present|now|current)"
)

FEATURE_NAMES = [
    "skill_match_ratio",
    "matched_skills",
    "years_experience",
    "education_level",
]


def parse_skills(requirements: str) -> list[str]:
    """'Python, SQL\\nDocker' -> ['python', 'sql', 'docker']"""
    parts = re.split(r"[,\n;/]+", requirements.lower())
    return [p.strip() for p in parts if p.strip()]


def lower_tr(text: str) -> str:
    """Lower-case that also works for Turkish "İ" (DENEYİM -> deneyim)."""
    return text.replace("İ", "i").lower()


def years_of_experience(text: str) -> int:
    text = lower_tr(text)
    # Written as a number: "5 years", "3+ yıl"
    written = [int(n) for n in re.findall(r"(\d{1,2})\+?\s*(?:years?|yrs|yıl|yil)", text)]

    # Job dates: add up every "start - end" range
    this_year = date.today().year
    from_dates = 0
    for start, end in DATE_RANGE.findall(text):
        end_year = int(end) if end[:2].isdigit() else this_year
        if 0 <= end_year - int(start) <= 40:
            from_dates += end_year - int(start)

    return min(40, max(written + [from_dates]))


def education_level(text: str) -> int:
    text = lower_tr(text)
    return max((lvl for word, lvl in EDUCATION_LEVELS.items() if word in text), default=0)


def extract_features(cv_text: str, requirements: str) -> dict:
    cv_lower = lower_tr(cv_text)
    skills = parse_skills(requirements)
    matched = [s for s in skills if s in cv_lower]
    return {
        "skill_match_ratio": len(matched) / len(skills) if skills else 0.0,
        "matched_skills": len(matched),
        "years_experience": years_of_experience(cv_text),
        "education_level": education_level(cv_text),
    }


def matched_skills(cv_text: str, requirements: str) -> list[str]:
    cv_lower = lower_tr(cv_text)
    return [s for s in parse_skills(requirements) if s in cv_lower]

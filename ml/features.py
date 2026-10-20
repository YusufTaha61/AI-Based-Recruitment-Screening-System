"""Turn a CV text and a job's requirements into numbers the model can use."""
import re

EDUCATION_LEVELS = {
    "phd": 3,
    "doctorate": 3,
    "master": 2,
    "msc": 2,
    "bachelor": 1,
    "bsc": 1,
    "university": 1,
}

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


def years_of_experience(text: str) -> int:
    found = re.findall(r"(\d{1,2})\+?\s*(?:years|yrs)", text.lower())
    return max((int(n) for n in found), default=0)


def education_level(text: str) -> int:
    text = text.lower()
    return max((lvl for word, lvl in EDUCATION_LEVELS.items() if word in text), default=0)


def extract_features(cv_text: str, requirements: str) -> dict:
    cv_lower = cv_text.lower()
    skills = parse_skills(requirements)
    matched = [s for s in skills if s in cv_lower]
    return {
        "skill_match_ratio": len(matched) / len(skills) if skills else 0.0,
        "matched_skills": len(matched),
        "years_experience": years_of_experience(cv_text),
        "education_level": education_level(cv_text),
    }


def matched_skills(cv_text: str, requirements: str) -> list[str]:
    cv_lower = cv_text.lower()
    return [s for s in parse_skills(requirements) if s in cv_lower]

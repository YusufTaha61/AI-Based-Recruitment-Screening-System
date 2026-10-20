"""Create a synthetic training set of (CV, requirements, label) rows.

Real labelled CVs are hard to find, so we build fake ones with a simple
rule ("good match" = enough skills + some experience) and add a little noise.
Usage: python generate_data.py
"""
import csv
import random
from pathlib import Path

SKILLS = [
    "python", "php", "java", "javascript", "sql", "mysql", "laravel", "django",
    "react", "html", "css", "docker", "git", "linux", "machine learning",
    "pandas", "excel", "communication", "teamwork", "english",
]
DEGREES = ["", "Bachelor of Computer Engineering", "Master of Science", "PhD in Computer Science"]

random.seed(42)


def make_row() -> dict:
    required = random.sample(SKILLS, random.randint(3, 6))
    others = [s for s in SKILLS if s not in required]

    # How many of the required skills this candidate has
    has = random.sample(required, random.randint(0, len(required)))
    extra = random.sample(others, random.randint(0, 4))
    years = random.randint(0, 10)
    degree_idx = random.choices(range(4), weights=[2, 5, 2, 1])[0]

    cv_text = (
        f"{DEGREES[degree_idx]}. {years} years of experience. "
        f"Skills: {', '.join(has + extra)}."
    )

    ratio = len(has) / len(required)
    good = ratio >= 0.6 and (years >= 2 or degree_idx >= 2)
    if random.random() < 0.08:  # noise: real decisions are not perfect
        good = not good

    return {
        "cv_text": cv_text,
        "requirements": ", ".join(required),
        "label": int(good),
    }


if __name__ == "__main__":
    out = Path(__file__).parent / "data" / "training_data.csv"
    out.parent.mkdir(exist_ok=True)
    rows = [make_row() for _ in range(2000)]
    with out.open("w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=["cv_text", "requirements", "label"])
        writer.writeheader()
        writer.writerows(rows)
    print(f"Wrote {len(rows)} rows to {out}")

"""Settings for the candidate scoring models.

A candidate is described by 5 features. Each position cares about them
differently, and each experience level changes those weights again
(an intern is compared with interns, not with a 10-year professional).
4 positions x 4 levels = 16 models.
"""

# Column names used in the training data and by the models
FEATURES = [
    "education_level",       # 1 = Bachelor, 2 = Master, 3 = PhD
    "years_experience",      # years, 0.5 steps
    "technical_skill",       # 30-100
    "communication_skill",   # 30-100
    "job_stability",         # average years spent in one job
]

# How important each feature is for each position (sums to 1)
POSITIONS = {
    "general": {
        "name": "Genel Pozisyon",
        "weights": {"education_level": 0.15, "years_experience": 0.20, "technical_skill": 0.30,
                    "communication_skill": 0.20, "job_stability": 0.15},
    },
    "software": {
        "name": "Yazılım Geliştirici",
        "weights": {"education_level": 0.10, "years_experience": 0.20, "technical_skill": 0.45,
                    "communication_skill": 0.10, "job_stability": 0.15},
    },
    "sales": {
        "name": "Satış / Müşteri İlişkileri",
        "weights": {"education_level": 0.05, "years_experience": 0.20, "technical_skill": 0.10,
                    "communication_skill": 0.45, "job_stability": 0.20},
    },
    "manager": {
        "name": "Proje Yöneticisi",
        "weights": {"education_level": 0.10, "years_experience": 0.30, "technical_skill": 0.15,
                    "communication_skill": 0.30, "job_stability": 0.15},
    },
}

# Experience levels
#  - years: experience range of candidates at this level
#  - multiplier: changes the position weights for this level
#    (for interns education matters more and job stability not at all;
#     for seniors experience and stability matter more)
#  - education_mix: share of Bachelor / Master / PhD candidates
LEVELS = {
    "intern": {
        "name": "Stajyer", "years": (0, 1),
        "multiplier": {"education_level": 2.0, "years_experience": 0.4, "technical_skill": 1.3,
                       "communication_skill": 1.3, "job_stability": 0.0},
        "education_mix": [0.85, 0.15, 0.00],
    },
    "junior": {
        "name": "Yeni Mezun (Junior)", "years": (0, 2),
        "multiplier": {"education_level": 1.6, "years_experience": 0.7, "technical_skill": 1.2,
                       "communication_skill": 1.1, "job_stability": 0.3},
        "education_mix": [0.75, 0.22, 0.03],
    },
    "mid": {
        "name": "Deneyimli (Orta Seviye)", "years": (2, 8),
        "multiplier": {"education_level": 1.0, "years_experience": 1.0, "technical_skill": 1.0,
                       "communication_skill": 1.0, "job_stability": 1.0},
        "education_mix": [0.60, 0.32, 0.08],
    },
    "senior": {
        "name": "Kıdemli (Senior)", "years": (8, 20),
        "multiplier": {"education_level": 0.5, "years_experience": 1.2, "technical_skill": 1.0,
                       "communication_skill": 1.2, "job_stability": 1.3},
        "education_mix": [0.50, 0.38, 0.12],
    },
}


def level_weights(position: str, level: str) -> dict:
    """Position weights adjusted for the level, scaled so they sum to 1."""
    base = POSITIONS[position]["weights"]
    mult = LEVELS[level]["multiplier"]
    raw = {f: base[f] * mult[f] for f in FEATURES}
    total = sum(raw.values())
    return {f: round(v / total, 4) for f, v in raw.items()}

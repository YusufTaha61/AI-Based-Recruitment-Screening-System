"""Create synthetic (fake) candidates for one position + level.

There is no public labelled hiring data, so we generate candidates and
mark the best 35% as "shortlisted" using the level's weights plus a
little noise. The model then has to learn this rule from the numbers.
"""
import numpy as np
import pandas as pd

from candidate_model import FEATURES

CANDIDATES = 2000     # candidates per model
SHORTLIST_RATE = 0.35  # best 35% are shortlisted


def make_candidates(weights: dict, level: dict, seed: int) -> pd.DataFrame:
    rng = np.random.default_rng(seed)
    y_min, y_max = level["years"]

    education = rng.choice([1, 2, 3], CANDIDATES, p=level["education_mix"])
    years = (rng.uniform(y_min, y_max, CANDIDATES) * 2).round() / 2
    technical = np.clip(rng.normal(66, 15, CANDIDATES), 30, 100).round()
    communication = np.clip(rng.normal(65, 14, CANDIDATES), 30, 100).round()
    # Average time in one job can not be longer than total experience
    stability = np.clip(rng.normal(1.5 + years * 0.25, 1.4), 0.5, 10)
    stability = np.minimum(stability, np.maximum(years, 0.5)).round(1)

    # Scale every feature to 0-1 (experience inside the level's own range)
    scaled = {
        "education_level": (education - 1) / 2,
        "years_experience": (years - y_min) / (y_max - y_min),
        "technical_skill": (technical - 30) / 70,
        "communication_skill": (communication - 30) / 70,
        "job_stability": (stability - 0.5) / 9.5,
    }
    hidden_score = sum(weights[f] * scaled[f] for f in FEATURES) + rng.normal(0, 0.05, CANDIDATES)
    cutoff = np.quantile(hidden_score, 1 - SHORTLIST_RATE)

    return pd.DataFrame({
        "education_level": education,
        "years_experience": years,
        "technical_skill": technical,
        "communication_skill": communication,
        "job_stability": stability,
        "shortlisted": (hidden_score >= cutoff).astype(int),
    })

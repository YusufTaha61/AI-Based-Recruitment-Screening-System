"""Fairness (bias) check for the candidate models.

The models never see a candidate's age or gender. This script checks whether
they still treat these groups differently, using the 4/5 rule: the lowest
shortlist rate must be at least 80% of the highest one.

It also compares two systems for age:
  - level-based (this project): an intern is compared with interns,
    a senior with seniors (the 16 saved models)
  - single model: everyone competes in one model, experience counted 0-20 years

Usage: python fairness.py   (run train_candidates.py first)
Creates ml/models/fairness.json
"""
import json
from datetime import datetime
from pathlib import Path

import joblib
import numpy as np
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.model_selection import train_test_split

from candidate_data import SHORTLIST_RATE, make_candidates
from candidate_model import FEATURES, LEVELS, POSITIONS, level_weights

MODELS = Path(__file__).parent / "models"
MIN_GROUP = 20  # groups smaller than this are skipped (not reliable)


def add_people_info(data: pd.DataFrame, seed: int) -> pd.DataFrame:
    """Add a made-up gender and age. These are NOT given to the model."""
    rng = np.random.default_rng(seed)
    n = len(data)
    data = data.copy()
    data["gender"] = rng.choice(["Kadın", "Erkek"], n)
    # Started working at 21-25, a bit later with a master's or PhD
    age = data["years_experience"] + rng.integers(21, 26, n) + (data["education_level"] - 1) * 2
    data["age_group"] = np.where(age >= 30, "30 yaş ve üstü", "30 yaş altı")
    return data


def group_rates(groups: pd.Series, shortlisted: np.ndarray) -> dict:
    """Shortlist rate of every group and the 4/5 rule result."""
    result = {}
    for name in sorted(groups.unique()):
        mask = (groups == name).values
        if mask.sum() >= MIN_GROUP:
            result[name] = {"count": int(mask.sum()), "rate": round(float(shortlisted[mask].mean()), 4)}

    rates = [g["rate"] for g in result.values()]
    if len(rates) < 2 or max(rates) == 0:
        return {"groups": result, "ratio": None, "fair": True}
    ratio = round(min(rates) / max(rates), 4)
    return {"groups": result, "ratio": ratio, "fair": ratio >= 0.8}


def level_based(position: str, seed: int) -> pd.DataFrame:
    """New candidates for every level, scored by that level's saved model."""
    parts = []
    for level, l in LEVELS.items():
        seed += 1
        data = make_candidates(level_weights(position, level), l, seed)
        data = add_people_info(data, seed)
        model = joblib.load(MODELS / f"rf_{position}_{level}.joblib")
        data["predicted"] = model.predict(data[FEATURES])
        parts.append(data)
    return pd.concat(parts, ignore_index=True)


def single_model(position: str, seed: int) -> pd.DataFrame:
    """Everyone in one model: experience is counted on one 0-20 year scale."""
    parts = []
    for level, l in LEVELS.items():
        seed += 1
        data = make_candidates(level_weights(position, "mid"), l, seed)
        parts.append(add_people_info(data, seed))
    data = pd.concat(parts, ignore_index=True)

    w = POSITIONS[position]["weights"]
    hidden = (w["education_level"] * (data["education_level"] - 1) / 2
              + w["years_experience"] * data["years_experience"] / 20
              + w["technical_skill"] * (data["technical_skill"] - 30) / 70
              + w["communication_skill"] * (data["communication_skill"] - 30) / 70
              + w["job_stability"] * (data["job_stability"] - 0.5) / 9.5)
    data["shortlisted"] = (hidden >= np.quantile(hidden, 1 - SHORTLIST_RATE)).astype(int)

    train, test = train_test_split(data, test_size=0.2, random_state=42, stratify=data["shortlisted"])
    model = RandomForestClassifier(n_estimators=100, max_depth=9, min_samples_leaf=3, random_state=42)
    model.fit(train[FEATURES], train["shortlisted"])
    test = test.copy()
    test["predicted"] = model.predict(test[FEATURES])
    return test


def main():
    report = {"created": datetime.now().strftime("%d.%m.%Y %H:%M"), "positions": {}}
    seed = 1000
    for position, p in POSITIONS.items():
        leveled = level_based(position, seed)
        single = single_model(position, seed + 50)
        seed += 100

        report["positions"][position] = {
            "age_single": group_rates(single["age_group"], single["predicted"].values),
            "age_levels": group_rates(leveled["age_group"], leveled["predicted"].values),
            "gender": group_rates(leveled["gender"], leveled["predicted"].values),
        }
        r = report["positions"][position]
        print(f"{p['name']:<28} age single: {r['age_single']['ratio']}  "
              f"age levels: {r['age_levels']['ratio']}  gender: {r['gender']['ratio']}")

    with (MODELS / "fairness.json").open("w", encoding="utf-8") as f:
        json.dump(report, f, ensure_ascii=False, indent=2)
    print("\nfairness.json saved to ml/models/")


if __name__ == "__main__":
    main()

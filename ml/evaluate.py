"""Score one candidate with the model for its position + level.

Reads JSON from stdin:
  {"position": "software", "level": "junior",
   "values": [education, years, technical, communication, stability]}
Prints JSON:
  {"score": 72.4, "shortlisted": 1, "baseline": 35.1,
   "contributions": [...], "importance": [...]}
"""
import json
import sys
from itertools import product
from math import factorial
from pathlib import Path

import joblib
import pandas as pd

from candidate_model import FEATURES, LEVELS, POSITIONS

sys.stdin.reconfigure(encoding="utf-8")
sys.stdout.reconfigure(encoding="utf-8")

MODELS = Path(__file__).parent / "models"


def explain(model, values: list, average: list) -> tuple[float, float, list]:
    """Shapley values: how much each feature moved the score.

    Start from the "average candidate" and swap in this candidate's values.
    We try every combination of the 5 features (2^5 = 32 rows); a feature's
    contribution is how much it changes the score on average.
    """
    n = len(FEATURES)
    masks = list(product([0, 1], repeat=n))
    rows = [[values[i] if mask[i] else average[i] for i in range(n)] for mask in masks]
    yes = list(model.classes_).index(1)
    prob = dict(zip(masks, model.predict_proba(pd.DataFrame(rows, columns=FEATURES))[:, yes]))

    contributions = []
    for i in range(n):
        total = 0.0
        for mask in masks:
            if mask[i]:
                continue
            k = sum(mask)
            weight = factorial(k) * factorial(n - k - 1) / factorial(n)
            with_i = tuple(1 if j == i else mask[j] for j in range(n))
            total += weight * (prob[with_i] - prob[mask])
        contributions.append(round(total * 100, 1))

    return float(prob[(1,) * n]), float(prob[(0,) * n]), contributions


def main():
    data = json.load(sys.stdin)
    position, level = data["position"], data["level"]
    if position not in POSITIONS or level not in LEVELS:
        raise ValueError("Unknown position or level")

    path = MODELS / f"rf_{position}_{level}.joblib"
    if not path.exists():
        raise FileNotFoundError("Model not found. Run: python ml/train_candidates.py")
    model = joblib.load(path)

    with (MODELS / "metrics.json").open(encoding="utf-8") as f:
        average = json.load(f)["positions"][position]["levels"][level]["average"]

    values = [float(v) for v in data["values"]]
    score, baseline, contributions = explain(model, values, average)

    print(json.dumps({
        "score": round(score * 100, 1),
        "shortlisted": int(score >= 0.5),
        "baseline": round(baseline * 100, 1),
        "contributions": contributions,
        "importance": [round(float(x), 4) for x in model.feature_importances_],
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"error": str(e)}))

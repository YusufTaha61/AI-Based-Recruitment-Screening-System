"""Score one candidate with the model for its position + level.

Reads JSON from stdin:
  {"position": "software", "level": "junior",
   "values": [education, years, technical, communication, stability]}
Prints JSON:
  {"score": 72.4, "shortlisted": 1, "importance": [...]}
"""
import json
import sys
from pathlib import Path

import joblib
import pandas as pd

from candidate_model import FEATURES, LEVELS, POSITIONS

sys.stdin.reconfigure(encoding="utf-8")
sys.stdout.reconfigure(encoding="utf-8")

MODELS = Path(__file__).parent / "models"


def main():
    data = json.load(sys.stdin)
    position, level = data["position"], data["level"]
    if position not in POSITIONS or level not in LEVELS:
        raise ValueError("Unknown position or level")

    path = MODELS / f"rf_{position}_{level}.joblib"
    if not path.exists():
        raise FileNotFoundError("Model not found. Run: python ml/train_candidates.py")
    model = joblib.load(path)

    values = [float(v) for v in data["values"]]
    yes = list(model.classes_).index(1)
    score = float(model.predict_proba(pd.DataFrame([values], columns=FEATURES))[0][yes])

    print(json.dumps({
        "score": round(score * 100, 1),
        "shortlisted": int(score >= 0.5),
        "importance": [round(float(x), 4) for x in model.feature_importances_],
    }))


if __name__ == "__main__":
    try:
        main()
    except Exception as e:
        print(json.dumps({"error": str(e)}))

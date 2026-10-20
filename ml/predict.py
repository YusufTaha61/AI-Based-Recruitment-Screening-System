"""Score one CV against a job.

Reads JSON from stdin: {"cv_text": "...", "requirements": "..."}
Prints JSON:          {"score": 78.5, "matched_skills": [...]}
"""
import json
import sys
from pathlib import Path

import joblib
import pandas as pd

from features import FEATURE_NAMES, extract_features, matched_skills

sys.stdin.reconfigure(encoding="utf-8")
sys.stdout.reconfigure(encoding="utf-8")

model = joblib.load(Path(__file__).parent / "model.joblib")
data = json.load(sys.stdin)

features = extract_features(data["cv_text"], data["requirements"])
X = pd.DataFrame([features])[FEATURE_NAMES]
probability = model.predict_proba(X)[0][1]

print(json.dumps({
    "score": round(probability * 100, 1),
    "matched_skills": matched_skills(data["cv_text"], data["requirements"]),
    "features": features,
}))

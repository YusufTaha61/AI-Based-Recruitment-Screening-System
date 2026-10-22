"""Train one Random Forest for every position + experience level (16 models).

Usage: python train_candidates.py
Creates ml/models/rf_<position>_<level>.joblib
"""
from pathlib import Path

import joblib
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score
from sklearn.model_selection import train_test_split

from candidate_data import make_candidates
from candidate_model import FEATURES, LEVELS, POSITIONS, level_weights

MODELS = Path(__file__).parent / "models"


def random_forest() -> RandomForestClassifier:
    return RandomForestClassifier(n_estimators=100, max_depth=9, min_samples_leaf=3, random_state=42)


def main():
    MODELS.mkdir(exist_ok=True)

    seed = 42
    for pos, p in POSITIONS.items():
        for lvl, l in LEVELS.items():
            seed += 1
            weights = level_weights(pos, lvl)
            data = make_candidates(weights, l, seed)
            X, y = data[FEATURES], data["shortlisted"]
            X_train, X_test, y_train, y_test = train_test_split(
                X, y, test_size=0.2, random_state=42, stratify=y
            )

            rf = random_forest().fit(X_train, y_train)
            accuracy = accuracy_score(y_test, rf.predict(X_test))

            joblib.dump(rf, MODELS / f"rf_{pos}_{lvl}.joblib", compress=3)
            print(f"{p['name']:<28} {l['name']:<24} accuracy: %{accuracy * 100:.1f}")

    print("\n16 models saved to ml/models/")


if __name__ == "__main__":
    main()

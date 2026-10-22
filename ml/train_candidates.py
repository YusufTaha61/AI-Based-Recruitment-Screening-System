"""Train one Random Forest for every position + experience level (16 models).

Usage: python train_candidates.py
Creates ml/models/rf_<position>_<level>.joblib
"""
from pathlib import Path

import joblib
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, confusion_matrix, f1_score, precision_score, recall_score
from sklearn.model_selection import cross_val_score, train_test_split

from candidate_data import make_candidates
from candidate_model import FEATURES, LEVELS, POSITIONS, level_weights

MODELS = Path(__file__).parent / "models"


def random_forest() -> RandomForestClassifier:
    return RandomForestClassifier(n_estimators=100, max_depth=9, min_samples_leaf=3, random_state=42)


def scores(y_true, y_pred) -> dict:
    return {
        "accuracy": round(accuracy_score(y_true, y_pred), 4),
        "precision": round(precision_score(y_true, y_pred, zero_division=0), 4),
        "recall": round(recall_score(y_true, y_pred, zero_division=0), 4),
        "f1": round(f1_score(y_true, y_pred, zero_division=0), 4),
    }


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
            rf_pred = rf.predict(X_test)
            m = scores(y_test, rf_pred)

            cv = cross_val_score(random_forest(), X, y, cv=5)
            cm = confusion_matrix(y_test, rf_pred)  # [[TN, FP], [FN, TP]]

            joblib.dump(rf, MODELS / f"rf_{pos}_{lvl}.joblib", compress=3)
            print(f"{p['name']:<28} {l['name']:<24} "
                  f"accuracy: %{m['accuracy'] * 100:.1f}  F1: {m['f1']:.3f}  "
                  f"CV: %{cv.mean() * 100:.1f}  wrong: {cm[0][1] + cm[1][0]}")

    print("\n16 models saved to ml/models/")


if __name__ == "__main__":
    main()

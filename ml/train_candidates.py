"""Train one Random Forest for every position + experience level (16 models).

Usage: python train_candidates.py
Creates ml/models/rf_<position>_<level>.joblib and ml/models/metrics.json
"""
import json
from datetime import datetime
from pathlib import Path

import joblib
import sklearn
from sklearn.ensemble import RandomForestClassifier
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import accuracy_score, confusion_matrix, f1_score, precision_score, recall_score
from sklearn.model_selection import cross_val_score, train_test_split
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler

from candidate_data import CANDIDATES, make_candidates
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
    report = {
        "created": datetime.now().strftime("%d.%m.%Y %H:%M"),
        "sklearn": sklearn.__version__,
        "candidates": CANDIDATES,
        "features": FEATURES,
        "levels": {k: {"name": v["name"], "years": list(v["years"])} for k, v in LEVELS.items()},
        "positions": {},
    }

    seed = 42
    for pos, p in POSITIONS.items():
        report["positions"][pos] = {"name": p["name"], "levels": {}}
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

            # Simple baseline to compare against
            lr = make_pipeline(StandardScaler(), LogisticRegression(max_iter=1000)).fit(X_train, y_train)
            lr_m = scores(y_test, lr.predict(X_test))

            cv = cross_val_score(random_forest(), X, y, cv=5)
            cm = confusion_matrix(y_test, rf_pred)  # [[TN, FP], [FN, TP]]

            joblib.dump(rf, MODELS / f"rf_{pos}_{lvl}.joblib", compress=3)

            report["positions"][pos]["levels"][lvl] = {
                "weights": weights,
                "train_size": int(len(X_train)),
                "test_size": int(len(X_test)),
                "random_forest": m,
                "logistic_regression": lr_m,
                "cross_validation": {"mean": round(float(cv.mean()), 4), "std": round(float(cv.std()), 4)},
                "confusion": {"tn": int(cm[0][0]), "fp": int(cm[0][1]), "fn": int(cm[1][0]), "tp": int(cm[1][1])},
                "importance": [round(float(x), 4) for x in rf.feature_importances_],
                # The "average candidate" used as the starting point of the score explanation
                "average": [round(float(x), 3) for x in X_train.mean().values],
            }
            print(f"{p['name']:<28} {l['name']:<24} "
                  f"RF: %{m['accuracy'] * 100:.1f}  LR: %{lr_m['accuracy'] * 100:.1f}  "
                  f"F1: {m['f1']:.3f}  CV: %{cv.mean() * 100:.1f}  wrong: {cm[0][1] + cm[1][0]}")

    with (MODELS / "metrics.json").open("w", encoding="utf-8") as f:
        json.dump(report, f, ensure_ascii=False, indent=2)
    print("\n16 models and metrics.json saved to ml/models/")


if __name__ == "__main__":
    main()

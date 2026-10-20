"""Train a Random Forest that predicts if a CV fits a job.

Usage: python train.py   (creates ml/model.joblib)
"""
from pathlib import Path

import joblib
import pandas as pd
from sklearn.ensemble import RandomForestClassifier
from sklearn.metrics import accuracy_score, classification_report
from sklearn.model_selection import train_test_split

from features import FEATURE_NAMES, extract_features

BASE = Path(__file__).parent

data = pd.read_csv(BASE / "data" / "training_data.csv")
X = pd.DataFrame(
    [extract_features(cv, req) for cv, req in zip(data["cv_text"], data["requirements"])]
)[FEATURE_NAMES]
y = data["label"]

X_train, X_test, y_train, y_test = train_test_split(
    X, y, test_size=0.2, random_state=42, stratify=y
)

model = RandomForestClassifier(n_estimators=200, max_depth=6, min_samples_leaf=5, random_state=42)
model.fit(X_train, y_train)

pred = model.predict(X_test)
print(f"Accuracy: {accuracy_score(y_test, pred):.2f}")
print(classification_report(y_test, pred))

joblib.dump(model, BASE / "model.joblib")
print("Model saved to ml/model.joblib")

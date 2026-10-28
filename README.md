# AI-Based Recruitment Screening System

A web app that helps HR teams screen job candidates with machine learning. Random Forest models give each candidate a **fit score**, a decision (*Shortlisted*, *Consider* or *Rejected*) and an explanation of **why** the model gave that score.

> This system only supports decisions. The final hiring decision always belongs to a human. The interface is in Turkish.

## Features

- **Job postings and online applications:** candidates apply with a PDF/TXT CV, and applicants are ranked by how well their CV matches the job's required skills
- **16 models (4 positions × 4 experience levels):** an intern is compared with interns, a senior with seniors, each with different criteria
- **Explainable scores:** which features raised or lowered a candidate's score (Shapley values), plus a what-if simulation with sliders
- **CV to form:** upload a CV and the evaluation form is filled in automatically
- **Candidate history:** search, filter, sort, compare 2-6 candidates side by side and track hiring status with notes
- **Reports:** CSV export for Excel and a printable PDF report for each candidate
- **Model report:** accuracy, precision, recall, F1, confusion matrix, 5-fold cross-validation and comparison with Logistic Regression
- **Fairness check:** age and gender shortlist rates compared with the 4/5 rule (level-based models raise the age ratio from about 0.1-0.2 to 0.7-0.85)
- **Roles and teams:** HR Manager and Evaluator; each HR team only sees its own candidates. A demo account is on the login page
- **Security:** CSRF tokens, safe session cookie, security headers, login lock after 5 wrong passwords, and the database and CVs can't be opened from the browser

## Tech stack

PHP 8 · Python · scikit-learn · pandas · pypdf · SQLite · HTML / CSS / JavaScript

## Score thresholds

| Score | Decision |
|---|---|
| 70% and above | Shortlisted |
| 40% - 70% | Consider |
| Below 40% | Rejected |

<details>
<summary><b>Setup</b></summary>

**Requirements:** PHP 8.1+ (with `pdo_sqlite`), Python 3.10+, Git

1. Get the code:
   ```
   git clone https://github.com/YusufTaha61/AI-Based-Recruitment-Screening-System.git
   cd AI-Based-Recruitment-Screening-System
   ```
2. Install the Python packages in `ml/.venv` (the app looks for Python there first):
   ```
   py -m venv ml\.venv
   ml\.venv\Scripts\pip install -r ml\requirements.txt
   ```
   On macOS / Linux use `python3 -m venv ml/.venv` and `ml/.venv/bin/pip`.
3. Train the models (model files are not stored in Git):
   ```
   cd ml
   .venv\Scripts\python train.py
   .venv\Scripts\python train_candidates.py
   .venv\Scripts\python fairness.py
   cd ..
   ```
4. Create the database and start the server:
   ```
   php setup.php
   php -S 127.0.0.1:8000 router.php
   ```
   `router.php` only lets the browser open the pages and `assets/`, so the database, the uploaded CVs and the code folders stay private.
5. Open http://127.0.0.1:8000/index.php and log in with `demo.yonetici@akilliik.com` / `Akilli-IK-Demo-7342`. Stop the server with `Ctrl + C`.

Sample CVs for testing are in `ornekler/`.

**Troubleshooting**
- *"Model çalıştırılamadı"*: Python or its packages are missing, repeat step 2.
- *"could not find driver"*: enable `extension=pdo_sqlite` in `php.ini`.
- *Port 8000 is busy*: use another port, e.g. `php -S 127.0.0.1:8080 router.php`.
- *"Çok fazla hatalı deneme yapıldı"*: 5 wrong passwords lock that e-mail for 15 minutes, wait and try again.

</details>

<details>
<summary><b>Project structure</b></summary>

```
index.php, job_*.php, apply.php      Job postings and applications
applicants.php, application.php      Applicants ranked by CV score
evaluate.php, candidate.php          Candidate evaluation and result
history.php, compare.php             History, filters, comparison
export.php, report.php               CSV export and PDF report
model_report.php                     Model performance and fairness
users.php, login.php, register.php   Users, roles and teams
router.php                           Blocks browser access to private files
includes/                            Database, auth, session, CSRF, security headers, helpers, layout
ml/                                  Python: training, scoring, CV reading, fairness
ml/models/                           Trained models, metrics.json, fairness.json
ornekler/                            Fictional sample CVs
```

</details>

## Notes

- The models are trained on **synthetic data** and must be retrained with real hiring data before real use.
- Logistic Regression is 1-2 points more accurate in most models; Random Forest is used because its scores can be explained feature by feature.
- The model never sees age, but experience is related to age (**proxy bias**). Level-based models fix most of this, but for two positions the age ratio is still a little under 0.8, so a human must review every decision.

## Author

Yusuf Taha Çebi

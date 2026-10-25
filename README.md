# AI-Based Recruitment Screening System

A web app that helps HR teams screen job candidates with machine learning. Candidates apply to job postings with their CV, and HR can evaluate any candidate by position and experience level. Random Forest models return a **fit score**, a decision (*Shortlisted*, *Consider* or *Rejected*) and an explanation of **why** the model gave that score.

> This system only supports decisions. The final hiring decision always belongs to a human.

The interface is in Turkish.

## Features

### Job postings and applications
- **Job postings:** HR managers create, edit and publish job postings with a list of required skills
- **Online application:** candidates apply with a PDF or TXT CV
- **CV scoring:** each CV is read and compared with the job's requirements; applicants are ranked by fit score
- **Application detail:** matched and missing skills, education and experience found in the CV

### Candidate evaluation
- **16 models (4 positions × 4 experience levels):** an intern is compared with other interns, not with a 10-year professional, and each level uses different criteria (job stability is ignored for interns; experience and stability matter more for seniors)
- **Positions:** General, Software Developer, Sales / Customer Relations, Project Manager · **Levels:** Intern, Junior, Mid, Senior
- **Fit score (0-100%)** from education, experience, technical skill, communication skill and job stability
- **CV to form:** upload a CV and the evaluation form is filled in automatically (the user checks and corrects the values)
- **Candidate-specific explanation:** which features raised or lowered *this* candidate's score (Shapley values), plus strong and weak sides
- **What-if simulation:** move a slider and see the new score instantly
- **Candidate tracking:** hiring status (New, Invited to Interview, Offer Made, Hired, Rejected) and notes

### History and reports
- **Candidate history:** all evaluated candidates with summary boxes (total, shortlisted, average score, hired)
- **Search, filter and sort:** by name, decision, position, level and status
- **Comparison:** 2 to 6 candidates side by side, the best value in each row is highlighted
- **CSV export:** the filtered list as a file that opens correctly in Turkish Excel (HR managers only)
- **Printable PDF report** for each candidate (browser "Save as PDF")

### Model report
- **Accuracy, precision, recall, F1** and a **confusion matrix** for each of the 16 models
- **Learned vs. real weights:** checks whether the model learned what the training data was built on
- **Comparison with Logistic Regression** and **5-fold cross-validation**
- **Fairness (bias) check:** the models never see age or gender. Shortlist rates of age groups and genders are compared with the **4/5 rule**. With a single model for everyone the age ratio is only about 0.1-0.2; comparing candidates within their own level raises it to about 0.7-0.85. Gender ratios are above 0.9 for every position

### Users and security
- **Two roles:** *HR Manager* (jobs, hiring status, CSV export, user management) and *Evaluator* (evaluate and view only)
- **User management:** HR managers add evaluators and change roles
- **Demo account:** created automatically and shown on the login page (`demo@akilliik.com` / `demo123`)
- Hashed passwords, CSRF protection on forms, prepared SQL statements and escaped output

## Tech stack

- **Web:** PHP 8, HTML, CSS, JavaScript
- **Machine learning:** Python, scikit-learn (Random Forest, Logistic Regression), pandas, joblib
- **CV reading:** pypdf
- **Database:** SQLite

## Project structure

```
index.php              Job postings
job_create.php         New job posting (HR only)
job_edit.php           Edit a job posting
apply.php              Apply to a job with a CV
applicants.php         Applicants of a job, ranked by score
application.php        Application detail (matched / missing skills)
evaluate.php           Candidate evaluation form (+ CV upload)
candidate.php          Result: score, explanation, simulation, tracking
history.php            Candidate history (search, filter, sort)
compare.php            Side-by-side comparison
export.php             CSV export
report.php             Printable / PDF candidate report
model_report.php       Model performance and fairness
users.php              User management (HR only)
login.php / register.php / logout.php
cv_profile.php         CV upload handler for the evaluation form
simulate.php           What-if score (used by candidate.php)
setup.php              Creates the database tables
import_old.php         One-time import of the old demo project's candidates
includes/              Database, login, roles, CSRF, helpers, filters, page layout
assets/style.css       Styles
ml/                    Python: data generation, training, scoring, CV reading, fairness check
ml/models/             The 16 trained models, metrics.json and fairness.json
ornekler/              Fictional sample CVs for testing (see ornekler/README.md)
```

## Score thresholds

| Score | Decision |
|---|---|
| 70% and above | Shortlisted |
| 40% - 70% | Consider |
| Below 40% | Rejected |

## Notes

- The models are trained on **synthetic data**. They must be retrained with real hiring data before real use.
- Logistic Regression is slightly more accurate in most models (by 1-2 points), because the synthetic data is built from a weighted sum. Random Forest is used because its scores can be explained feature by feature.
- The fairness check shows an example of **proxy bias**: the model never sees age, but experience is related to age. Level-based evaluation fixes most of it, but for General and Project Manager positions the age ratio is still a little under 0.8, so a human must review the final decision.
- Sample CVs in `ornekler/` are fictional and only for testing.
- The database (`database.sqlite`) and uploaded CVs are stored locally and are not added to Git.

## Author

Yusuf Taha Çebi

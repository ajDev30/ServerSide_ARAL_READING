# System Documentation: ARAL READING Program & Phil-IRI Assessment Platform

This document provides a comprehensive technical reference for the files comprising the ARAL READING Program and Philippine Informal Reading Inventory (Phil-IRI) automated assessment system.

---

## Table of Contents
1. [Architecture & System Flow](#architecture--system-flow)
2. [Core Configuration & Authentication](#1-core-configuration--authentication)
   - `config.php`
   - `index.php`
   - `register.php`
   - `logout.php`
3. [Student Portal & Assessment Modules](#2-student-portal--assessment-modules)
   - `dashboard_student.php`
   - `student_sidebar.php`
   - `assessment.php`
   - `submit_assessment.php`
   - `take_course_test.php`
   - `student_course.php`
   - `student_grades.php`
   - `student_submissions.php`
   - `student_settings.php`
4. [Teacher Management & Diagnostic Modules](#3-teacher-management--diagnostic-modules)
   - `dashboard_teacher.php`
   - `teacher_sidebar.php`
   - `manage_preassessment.php`
   - `manage_gst.php`
   - `assessment_result.php`
   - `review_detail.php`
   - `student_progress_list.php`
   - `student_progress.php`
   - `course.php`
   - `teacher_course_grades.php`
   - `teacher_student_grades.php`
   - `teacher_profile.php`
   - `teacher_settings.php`
   - `export_teacher_report.php`
5. [Reusable Widgets & Modals](#4-reusable-widgets--modals)
   - `comparison_widget.php`
   - `conditional_rules_modal.php`
6. [Backend APIs & Handlers](#5-backend-apis--handlers)
   - `api_assessment.php`
   - `api_recommendations.php`
   - `api_upload_image.php`
   - `apply_settings_update.php`
7. [Real-Time Speech Engine & Frontend Scripts](#6-real-time-speech-engine--frontend-scripts)
   - `v536/service.py`
   - `v536/public/assessment-core.js`
   - `v536/public/table_filter.js`
8. [DevOps & Infrastructure Configurations](#7-devops--infrastructure-configurations)
   - `docker-compose.yml`
   - `Dockerfile.php`
   - `Dockerfile.python`
   - `Caddyfile`
9. [Database Definition Files](#8-database-definition-files)
   - `schema.sql`
   - `aral_reading_db_dump.sql`

---

## Architecture & System Flow

```
[ Browser / Student / Teacher ]
           |
           v (Port 80 / 443 / 8011)
      [ Caddy Proxy ]
      /             \
 (HTTP / PHP)     (WebSockets / ASR)
    /                 \
   v                   v
[ Apache / PHP 8.2 ]   [ Python 3.11 FastAPI / Azure Speech ]
   (Web Container)        (Speech Service Container)
           \                 /
            v               v
           [ MySQL 8.0 Database ]
               (db Container)
```

---

## 1. Core Configuration & Authentication

### File: `config.php`
- **Overview**: Central bootstrap file responsible for database connection via PDO, environment variable resolution, and core Phil-IRI grading rule helper functions.
- **Variables**:
  - `$host`, `$dbname`, `$username`, `$password`: Database connection parameters from `getenv()`.
  - `$pdo`: Global `PDO` instance for database transactions.
  - `$_SESSION`: Global PHP session state.
- **Connected Files**:
  - Included by: All PHP files across the application (`index.php`, `dashboard_*.php`, `assessment.php`, etc.).
  - Reads from: Docker environment variables (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
- **Code Block Breakdown**:
  1. *Database Connection*: Connects to MySQL using PDO with UTF-8 character encoding and error mode set to exception.
  2. *`get_student_reading_level($pdo, $user_id)`*: Analyzes GST status, oral reading accuracy, WCPM, and comprehension to classify reading level (`Frustration`, `Instructional`, `Independent`, or `Pending`).
  3. *`calculate_philiri_profile($accuracy_percent, $comp_percent)`*: Implements standard DepEd Phil-IRI criteria matrix combining decoding accuracy and comprehension score.
  4. *`get_system_settings($pdo)`*: Fetches and caches system configurations from the `settings` key-value table.

---

### File: `index.php`
- **Overview**: User login authentication portal. Validates user credentials, role, and routes users to appropriate dashboards.
- **Variables**:
  - `$_POST['username']`: Submitted username or LRN.
  - `$_POST['password']`: Plain text password submitted for verification.
  - `$error`: Error feedback string displayed to the user.
- **Connected Files**:
  - Includes: `config.php`.
  - Redirects to: `dashboard_student.php` (if student) or `dashboard_teacher.php` (if teacher).
  - Links to: `register.php`.
- **Code Block Breakdown**:
  1. *Session Verification*: If the user is already logged in, immediately redirects to their designated role dashboard.
  2. *Authentication Processing*: Intercepts `POST` request, sanitizes input, queries the `users` table for a username match, and performs `password_verify()`.
  3. *Session Initialization*: Stores `user_id`, `username`, `role`, `fname`, `lname`, `grade_level`, and `section` into `$_SESSION`.
  4. *UI View*: Renders responsive ARAL login form with branding, logo (`aral_logoindex.png`), and toggle password visibility.

---

### File: `register.php`
- **Overview**: Student self-registration portal allowing learners to create an account with their 12-digit Learner Reference Number (LRN).
- **Variables**:
  - `$_POST['lrn']`: 12-digit student identification number.
  - `$_POST['first_name']`, `$_POST['last_name']`, `$_POST['middle_name']`: Student name fields.
  - `$_POST['grade_level']`, `$_POST['section']`: Grade and classroom section.
  - `$_POST['username']`, `$_POST['password']`, `$_POST['confirm_password']`: Account credentials.
- **Connected Files**:
  - Includes: `config.php`.
  - Redirects to: `index.php` upon successful registration.
- **Code Block Breakdown**:
  1. *Validation Block*: Ensures LRN is exactly 12 numeric digits, passwords match, and required fields are populated.
  2. *Collision Check*: Executes pre-check queries against `users` table to ensure username and LRN are unique.
  3. *Account Insertion*: Hashes password via `PASSWORD_BCRYPT`, inserts student record with default role `student`, and initializes reading status to `Pending`.
  4. *Registration View*: Renders registration card with dropdowns for grade and section configured from system settings.

---

### File: `logout.php`
- **Overview**: Session termination handler. Clears all session keys, invalidates session cookies, and destroys active user sessions.
- **Variables**:
  - `$_SESSION`: Session array emptied via `session_unset()`.
- **Connected Files**:
  - Redirects to: `index.php`.
- **Code Block Breakdown**:
  1. *Session Clearing*: Calls `session_start()`, `session_unset()`, and `session_destroy()`.
  2. *Cookie Invalidation*: Clears `PHPSESSID` cookie with an expired timestamp.
  3. *Redirection*: Redirects browser back to `index.php`.

---

## 2. Student Portal & Assessment Modules

### File: `dashboard_student.php`
- **Overview**: Primary home screen for students. Displays overall reading profile, Phil-IRI assessment cards, active ARAL courses, and recent scores.
- **Variables**:
  - `$user_id`, `$grade_level`: Extracted from active `$_SESSION`.
  - `$reading_level`: Determined via `get_student_reading_level()`.
  - `$available_passages`: Array of graded reading passages matching student grade.
  - `$active_courses`: Array of published remedial course tests assigned to student's profile.
- **Connected Files**:
  - Includes: `config.php`, `student_sidebar.php`.
  - Links to: `assessment.php`, `take_course_test.php`, `student_grades.php`.
- **Code Block Breakdown**:
  1. *Role Check*: Guards endpoint; redirects non-students to `index.php`.
  2. *Data Retrieval*: Queries completed attempts, baseline GST results, and eligible graded passages.
  3. *Progress Summary Card*: Visual cards showing reading classification badge (`Frustration`, `Instructional`, `Independent`).
  4. *Assessment Feed*: Lists pending assessments, completed scores, and direct action buttons ("Start Reading", "Take Test").

---

### File: `student_sidebar.php`
- **Overview**: Shared responsive navigation sidebar component for student pages.
- **Variables**:
  - `$_SERVER['PHP_SELF']`: Used to determine current page and apply active menu highlighting.
  - `$_SESSION['fname']`, `$_SESSION['lname']`: User display name.
- **Connected Files**:
  - Included by: `dashboard_student.php`, `student_course.php`, `student_grades.php`, `student_submissions.php`, `student_settings.php`.
- **Code Block Breakdown**:
  1. *Mobile Overlay & Sidebar Container*: Fixed sidebar drawer with toggle functionality for smaller screens.
  2. *Brand Header*: Displays Phil-IRI / ARAL logo and Student Portal title.
  3. *Navigation Links*: Routes to Dashboard, My Courses, My Grades, Submissions, Settings, and Logout.

---

### File: `assessment.php`
- **Overview**: Live interactive oral reading assessment client. Features voice recording, real-time audio streaming, speech recognition (ASR), live miscue tracking, and post-reading comprehension questions.
- **Variables**:
  - `$_GET['passage_id']`: Identifies the specific passage being read.
  - `$passage`: Passage record containing title, grade, target profile, text content, and questions JSON.
  - `$questions`: Parsed comprehension questionnaire items.
- **Connected Files**:
  - Includes: `config.php`.
  - Connects to: `v536/service.py` via WebSocket proxy `/azure-stream`.
  - Submits to: `submit_assessment.php`.
  - Scripts: `v536/public/assessment-core.js`, `v536/public/pcm-processor.js`.
- **Code Block Breakdown**:
  1. *Passage Loading*: Queries passage details and questionnaire JSON from `reading_passages`.
  2. *Assessment Workspace*: Two-column UI containing the reading passage with clickable word spans on the left, and live transcription feedback on the right.
  3. *Audio Controller*: Microphone permission request, timer countdown, Web Audio API stream capture, and start/stop controls.
  4. *Comprehension Modal*: Modal pop-up displayed immediately upon finishing reading containing multiple-choice questions.

---

### File: `submit_assessment.php`
- **Overview**: Backend ingestion endpoint for oral reading assessments. Computes WCPM, accuracy percentage, comprehension percentage, saves audio recording, and calculates Phil-IRI level.
- **Variables**:
  - `$_POST['passage_id']`, `$_POST['elapsed_time']`: Reading metadata.
  - `$_POST['miscues_json']`: Word-by-word miscue classification payload.
  - `$_POST['answers_json']`: Student answers to comprehension questionnaire.
  - `$_FILES['audio_file']`: Recorded `.webm` voice file.
- **Connected Files**:
  - Includes: `config.php`.
  - Receives data from: `assessment.php`.
  - Redirects to: `dashboard_student.php?success=submitted`.
- **Code Block Breakdown**:
  1. *Input Sanitization & Decoding*: Parses JSON payloads for miscues, answers, and timestamps.
  2. *Audio Storage*: Moves uploaded audio from temp storage to `/uploads/audio/` with a uniquely generated filename.
  3. *Score Computations*:
     - $\text{Accuracy} = \frac{\text{Total Words} - \text{Total Miscues}}{\text{Total Words}} \times 100\%$
     - $\text{WCPM} = \frac{(\text{Total Words} - \text{Total Miscues}) \times 60}{\text{Elapsed Seconds}}$
     - $\text{Comprehension} = \frac{\text{Correct Answers}}{\text{Total Questions}} \times 100\%$
  4. *Record Persistence*: Inserts assessment attempt into `assessment_attempts` table.
  5. *Level Updates*: Updates student reading profile in `users` table.

---

### File: `take_course_test.php`
- **Overview**: Assessment runner for ARAL Remedial Course Pre-Tests and Post-Tests.
- **Variables**:
  - `$_GET['id']`: Course assessment ID.
  - `$course`: Course assessment database record.
  - `$questions`: Decoded questionnaire array (supports multiple choice, true/false, enumeration, and essay).
- **Connected Files**:
  - Includes: `config.php`.
  - Submits to: `take_course_test.php` (POST handling).
  - Redirects to: `student_course.php`.
- **Code Block Breakdown**:
  1. *Authorization Guard*: Validates student role and checks course availability date (`available_from`).
  2. *Form Submission Handler*: Scores objective questions (MC, TF, Enumeration), flags subjective essays for teacher grading, calculates score percentage, and stores in `student_course_attempts`.
  3. *Test Interface*: Renders passage reading section and dynamic questionnaire with rich input controls.

---

### File: `student_course.php`
- **Overview**: Lists all ARAL course tests (Pre-Tests and Post-Tests) available to the logged-in student according to their reading classification.
- **Variables**:
  - `$student_level`: Student's current reading profile (`Instructional` or `Frustration`).
  - `$tests`: Array of available course tests.
- **Connected Files**:
  - Includes: `config.php`, `student_sidebar.php`.
  - Links to: `take_course_test.php`.
- **Code Block Breakdown**:
  1. *Eligibility Filtering*: Matches student grade and profile against `target_grade` and `target_profile` of `course_assessments`.
  2. *Attempt Lookup*: Identifies whether the student has already taken the Pre-Test or Post-Test and displays their score.
  3. *Course Cards*: Clean UI cards displaying test type badges, due dates, and action buttons.

---

### File: `student_grades.php`
- **Overview**: Student gradebook viewer displaying comprehensive assessment history for Phil-IRI and ARAL course tests.
- **Variables**:
  - `$attempts`: Phil-IRI reading assessment attempts.
  - `$course_attempts`: Course Pre/Post-Test attempts.
- **Connected Files**:
  - Includes: `config.php`, `student_sidebar.php`.
  - Scripts: `v536/public/table_filter.js`.
- **Code Block Breakdown**:
  1. *Data Queries*: Selects all attempts made by `$_SESSION['user_id']` joined with passage titles and course titles.
  2. *Tabular Breakdown*: Shows Date, Passage/Course Title, WCPM, Accuracy %, Comprehension %, and Phil-IRI Level.
  3. *Search and Filter*: In-browser filtering via `initTailwindTable`.

---

### File: `student_submissions.php`
- **Overview**: Detailed list of student assessment submissions with status indicators and audio playback.
- **Variables**:
  - `$submissions`: Complete set of student attempts.
- **Connected Files**:
  - Includes: `config.php`, `student_sidebar.php`.
- **Code Block Breakdown**:
  1. *Fetch Submissions*: Queries student attempts ordered by date descending.
  2. *List Display*: Displays submission cards with status badges and audio player for submitted voice recordings.

---

### File: `student_settings.php`
- **Overview**: Student account profile settings page. Enables updating profile details and changing passwords.
- **Variables**:
  - `$_POST['fname']`, `$_POST['lname']`, `$_POST['middle_name']`: Student name fields.
  - `$_POST['username']`, `$_POST['email']`: Credentials.
  - `$_POST['new_password']`: Updated password.
- **Connected Files**:
  - Includes: `config.php`, `student_sidebar.php`.
- **Code Block Breakdown**:
  1. *Duplicate Collision Checks*: Ensures updated username or email is not taken by another user (`id != ?`).
  2. *Empty String Normalization*: Converts empty email submissions to SQL `NULL` to avoid unique index collisions.
  3. *Password Hash Update*: Updates password with `PASSWORD_BCRYPT` when `new_password` is provided.
  4. *Profile Form*: Clean settings form equipped with appropriate HTML5 `autocomplete` attributes.

---

## 3. Teacher Management & Diagnostic Modules

### File: `dashboard_teacher.php`
- **Overview**: Teacher administrative command center. Features aggregate statistics, class reading profile charts, active students count, and quick access actions.
- **Variables**:
  - `$total_students`, `$frustration_count`, `$instructional_count`, `$independent_count`: Aggregate metrics.
  - `$recent_assessments`: Latest student submissions awaiting review.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
  - Links to: `assessment_result.php`, `student_progress_list.php`, `manage_preassessment.php`, `course.php`.
- **Code Block Breakdown**:
  1. *Security Check*: Redirects non-teacher sessions to `index.php`.
  2. *Analytics Aggregation*: Queries student counts grouped by reading profile (`Independent`, `Instructional`, `Frustration`, `Pending`).
  3. *Metrics Cards*: Top statistics counters for rapid overview.
  4. *Chart.js Integration*: Pie chart rendering class-wide reading profile distribution.
  5. *Recent Activity Table*: Displays the latest student attempts with direct links to `review_detail.php`.

---

### File: `teacher_sidebar.php`
- **Overview**: Responsive navigation sidebar component for teacher pages.
- **Variables**:
  - `$_SERVER['PHP_SELF']`: Highlights currently active menu item.
- **Connected Files**:
  - Included by: All teacher pages (`dashboard_teacher.php`, `assessment_result.php`, `course.php`, etc.).
- **Code Block Breakdown**:
  1. *Header Branding*: Phil-IRI / ARAL teacher portal branding.
  2. *Menu Items*: Links to Dashboard, Graded Reading Passages, Manage GST, Assessment Results, Courses, Course Grades, Student Progress, Profile, Settings, and Logout.
  3. *Responsive Drawer*: Mobile burger toggle support.

---

### File: `manage_preassessment.php`
- **Overview**: CRUD interface for Phil-IRI Graded Reading Passages and comprehension questionnaires.
- **Variables**:
  - `$action`: Action parameter (`list`, `create`, `edit`, `delete`).
  - `$passages`: List of configured reading passages.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
  - Uses: TinyMCE editor for formatted passage text.
- **Code Block Breakdown**:
  1. *Passage CRUD Actions*: Handles creation, updating, and deletion of passages in `reading_passages`.
  2. *Passage List View*: Table listing existing passages categorized by Grade Level and Language.
  3. *Passage Editor View*: Form for title, grade level, word count, rich-text passage body, and comprehension question builder.

---

### File: `manage_gst.php`
- **Overview**: Group Screening Test (GST) manager. Configures initial screening passages and cutoff scores to identify learners needing individual Phil-IRI testing.
- **Variables**:
  - `$gst_tests`: Array of configured GST assessments.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
- **Code Block Breakdown**:
  1. *GST Form Processing*: Saves GST reading materials and cutoff thresholds.
  2. *Table View*: Displays active GST questionnaires and assigned grade levels.

---

### File: `assessment_result.php`
- **Overview**: Master log of all student assessment attempts. Includes multi-column filtering by Grade and Reading Profile, search, and CSV export.
- **Variables**:
  - `$attempts`: Complete records of student assessment attempts joined with user data and passage titles.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
  - Links to: `review_detail.php`, `export_teacher_report.php`.
  - Script: `v536/public/table_filter.js`.
- **Code Block Breakdown**:
  1. *Data Retrieval*: Queries `assessment_attempts` with student details (LRN, name, grade, section).
  2. *Filter Bar*: Grade dropdown (`#assessmentGradeFilter`), Profile dropdown (`#assessmentProfileFilter`), and instant search input (`#assessmentSearch`).
  3. *Results Table*: Displays Student Name, Grade, Passage Title, Accuracy %, WCPM, Comprehension %, Phil-IRI Profile, and Review button.
  4. *Table Filter Script*: Initializes multi-criteria filter via `initTailwindTable`.

---

### File: `review_detail.php`
- **Overview**: Detailed diagnostic teacher review screen. Allows teachers to inspect voice recordings, view word-by-word transcription miscues, manually override grading classifications, recalculate metrics, and save official scores.
- **Variables**:
  - `$_GET['id']`: Target assessment attempt ID.
  - `$attempt`: Comprehensive attempt record.
  - `$miscues`: Parsed miscue array detailing word errors (omissions, substitutions, insertions, mispronunciations).
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
  - Receives audio from: `/uploads/audio/`.
- **Code Block Breakdown**:
  1. *Data Extraction*: Fetches attempt, student, passage text, and recorded miscue data.
  2. *Diagnostic Metrics Panel*: Shows live calculated WCPM, Accuracy %, and Reading Profile.
  3. *Audio Playback*: Embedded HTML5 audio player synced with reading timeline.
  4. *Interactive Diagnostic Transcript*: Renders passage words color-coded by miscue type. Teachers can click words to toggle miscue types (Correct, Omission, Substitution, Mispronunciation, Repetition).
  5. *Recalculation Engine*: JavaScript instantly updates accuracy, WCPM, and Phil-IRI level on user edits.
  6. *Save Official Scores*: POST handler updates official overrides in `assessment_attempts` and synchronizes the student's level in `users`.

---

### File: `student_progress_list.php`
- **Overview**: Student roster and profiling management directory. Displays 12-digit LRNs, assigned sections, reading profiles, and intervention statuses.
- **Variables**:
  - `$students`: List of all registered students with current reading levels.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
  - Links to: `student_progress.php`, `teacher_student_grades.php`.
  - Script: `v536/public/table_filter.js`.
- **Code Block Breakdown**:
  1. *Roster Query*: Fetches students with baseline GST status and latest Phil-IRI classification.
  2. *Compact LRN Badges*: Displays student name with a streamlined mono-spaced 12-digit LRN badge (`[ LRN 123456789012 ]`).
  3. *Filter & Search*: Multi-column filtering by Grade Level and Reading Level.

---

### File: `student_progress.php`
- **Overview**: Individual student longitudinal tracking sheet. Plots reading progress over time across multiple assessment attempts.
- **Variables**:
  - `$_GET['id']`: Student user ID.
  - `$student`: Student profile record.
  - `$history`: Time-series array of reading scores.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
- **Code Block Breakdown**:
  1. *Student Validation*: Queries target student profile and checks existence.
  2. *History Retrieval*: Gathers assessment attempts chronologically.
  3. *Progress Timeline*: Visual trend charts and tables showing WCPM and comprehension trajectory.

---

### File: `course.php`
- **Overview**: ARAL Remedial Course assessment builder. Enables teachers to create, edit, and publish Pre-Tests and Post-Tests targeted at Instructional or Frustration readers.
- **Variables**:
  - `$action`: `list` or `edit`.
  - `$assessments`: Existing course tests.
  - `$questions_json`: Serialized questionnaire items.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
  - Script: `v536/public/table_filter.js`.
- **Code Block Breakdown**:
  1. *Save / Update Handler*: POST handler saving course test title, target profile (`Instructional`/`Frustration`), target grade, status (`Draft`/`Published`), passage text, and questions JSON.
  2. *Course List View*: Table of course tests with Grade, Profile, Type, and Status filters.
  3. *Interactive Questionnaire Builder*: JavaScript question builder supporting Multiple Choice, True/False, Enumeration, and Essay questions.
  4. *Filter Setup*: Integrated with `initTailwindTable` for Type, Grade, and Profile filtering.

---

### File: `teacher_course_grades.php`
- **Overview**: Class gradebook for ARAL Remedial Courses. Summarizes Pre-Test vs Post-Test progress.
- **Variables**:
  - `$students`: Student records with course test scores.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`, `conditional_rules_modal.php`.
  - Links to: `teacher_student_grades.php`.
- **Code Block Breakdown**:
  1. *Scores Query*: Aggregates student course attempts, calculating baseline Pre-Test score and subsequent Post-Test score.
  2. *Comparison Table*: Displays learning gains (+/- score differential).
  3. *Rule Management Trigger*: Button opening the conditional rules modal.

---

### File: `teacher_student_grades.php`
- **Overview**: Individual student course grade sheet showing granular answers and performance in ARAL remedial modules.
- **Variables**:
  - `$_GET['student_id']`: Student identifier.
  - `$student`: Target student record.
  - `$attempts`: Course test submissions.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
- **Code Block Breakdown**:
  1. *Attempt Details Query*: Selects student course attempts joined with course assessment titles.
  2. *Score Sheet*: Lists test type, date taken, percentage score, and answer breakdown.

---

### File: `teacher_profile.php`
- **Overview**: Teacher account profile management interface.
- **Variables**:
  - `$_POST['fname']`, `$_POST['lname']`, `$_POST['middle_name']`: Name fields.
  - `$_POST['username']`, `$_POST['email']`: Account credentials.
  - `$_POST['new_password']`: Updated password.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
- **Code Block Breakdown**:
  1. *Pre-Check Collisions*: Queries `users` table for matching username or email belonging to other IDs (`id != ?`).
  2. *Null Sanitization*: Converts blank email input to SQL `NULL` to avoid unique key constraints.
  3. *Session Synchronization*: Updates active `$_SESSION['fname']` and `$_SESSION['username']` on change.
  4. *Profile Form*: Input controls with standard `autocomplete` attributes.

---

### File: `teacher_settings.php`
- **Overview**: System-wide administrative settings editor. Manages Azure Speech keys, OpenAI keys, ASR language, available sections, and prompts.
- **Variables**:
  - `$_POST['save_settings']`: Form submission flag.
  - `$settings`: Key-value configuration dictionary loaded from `settings` table.
- **Connected Files**:
  - Includes: `config.php`, `teacher_sidebar.php`.
- **Code Block Breakdown**:
  1. *Settings Ingestion*: Intercepts POST submission and updates key-value pairs in `settings` table.
  2. *Configuration Form*: Inputs for Speech Service key, region, language (`en-US`), available classroom sections, and transcription guidance prompts.

---

### File: `export_teacher_report.php`
- **Overview**: Generates downloadable CSV / Excel reports for Phil-IRI and ARAL reading results.
- **Variables**:
  - `$_GET['type']`: Report type (`philiri` or `course`).
- **Connected Files**:
  - Includes: `config.php`.
- **Code Block Breakdown**:
  1. *Data Aggregation*: Queries student results with sanitized null coalescing.
  2. *CSV Streaming*: Sets HTTP response headers (`Content-Type: text/csv`, `Content-Disposition: attachment; filename=...`) and streams formatted CSV rows.

---

## 4. Reusable Widgets & Modals

### File: `comparison_widget.php`
- **Overview**: Modular UI widget comparing a student's baseline Pre-Test performance against their Post-Test results.
- **Variables**:
  - `$pre_test`: Pre-Test score and accuracy data object.
  - `$post_test`: Post-Test score and accuracy data object.
  - `$gain`: Score improvement differential.
- **Connected Files**:
  - Included by: `teacher_course_grades.php`, `student_grades.php`.
- **Code Block Breakdown**:
  1. *Differential Calculation*: Computes percentage point gain ($\Delta = \text{Post} - \text{Pre}$).
  2. *Progress Meter*: Renders color-coded indicator bars (green for positive gain, yellow for neutral, red for decrease).

---

### File: `conditional_rules_modal.php`
- **Overview**: Interactive modal dialog for defining automated enrollment rules linking Phil-IRI profiles directly to ARAL course modules.
- **Variables**:
  - `$rules`: Active conditional rules loaded from database.
- **Connected Files**:
  - Included by: `teacher_course_grades.php`.
- **Code Block Breakdown**:
  1. *Rule Modal Template*: Backdrop and modal container.
  2. *Condition Definitions*: Allows teachers to set triggers (e.g., "If Phil-IRI Profile == Frustration, auto-assign Grade 7 Frustration Course").
  3. *AJAX Persistence*: Saves rules asynchronously.

---

## 5. Backend APIs & Handlers

### File: `api_assessment.php`
- **Overview**: REST JSON API returning real-time assessment data, attempt history, and miscue statistics for frontend consumers.
- **Variables**:
  - `$_GET['action']`: Requested API action (`get_attempt`, `get_stats`).
  - `$_GET['id']`: Target attempt ID.
- **Connected Files**:
  - Includes: `config.php`.
  - Consumed by: AJAX calls in `review_detail.php` and `assessment.php`.
- **Code Block Breakdown**:
  1. *Header Definition*: Sets `Content-Type: application/json`.
  2. *Route Dispatcher*: Checks action parameter and executes corresponding prepared queries.
  3. *JSON Encoding*: Returns JSON-encoded data or structured error responses.

---

### File: `api_recommendations.php`
- **Overview**: Generates targeted pedagogical reading recommendations based on a student's miscue patterns (e.g., frequent omissions vs mispronunciations).
- **Variables**:
  - `$_GET['user_id']`: Student identifier.
- **Connected Files**:
  - Includes: `config.php`.
- **Code Block Breakdown**:
  1. *Miscue Pattern Query*: Analyzes frequency of miscue categories in `assessment_attempts`.
  2. *Rule Matrix*: Matches dominant miscue type to instructional recommendations (e.g., phonics drills, fluency pacing, sight-word practice).
  3. *JSON Response*: Outputs actionable recommendations for teachers and learners.

---

### File: `api_upload_image.php`
- **Overview**: Secure image upload endpoint for TinyMCE rich text editor and passage illustrations.
- **Variables**:
  - `$_FILES['file']`: Uploaded image binary.
- **Connected Files**:
  - Saves images to: `/uploads/images/`.
- **Code Block Breakdown**:
  1. *MIME Validation*: Verifies image format (`image/jpeg`, `image/png`, `image/webp`).
  2. *Safe Renaming*: Generates unique hash-based filename.
  3. *Storage & URL Return*: Saves to `/uploads/images/` and returns JSON with image location `{ "location": "uploads/images/..." }`.

---

### File: `apply_settings_update.php`
- **Overview**: Standalone administrative handler for applying batch settings updates.
- **Variables**:
  - `$_POST`: Key-value settings map.
- **Connected Files**:
  - Includes: `config.php`.
- **Code Block Breakdown**:
  1. *Admin Authorization*: Ensures caller has teacher/admin privileges.
  2. *Batch UPSERT*: Updates or inserts entries into `settings` table.

---

## 6. Real-Time Speech Engine & Frontend Scripts

### File: `v536/service.py`
- **Overview**: Asynchronous Python microservice built with FastAPI and WebSockets. Connects to Azure Cognitive Speech SDK and OpenAI to provide live streaming speech-to-text, phoneme pronunciation scoring, and miscue corroboration.
- **Variables**:
  - `AZURE_SPEECH_KEY`, `AZURE_SPEECH_REGION`: Azure Speech API credentials from `.env`.
  - `AZURE_OPENAI_API_KEY`: OpenAI API key.
  - `APP_PORT`: Listening port (default: 8011).
- **Connected Files**:
  - Reads: `v536/.env`.
  - Receives WebSocket streams from: `v536/public/assessment-core.js` via Caddy proxy.
- **Code Block Breakdown**:
  1. *Configuration & Server Setup*: Loads environment credentials and starts FastAPI app.
  2. *WebSocket Endpoint (`/azure-stream`)*: Accepts live audio streams from student browsers in PCM format.
  3. *Azure Speech Push Stream*: Bridges browser audio chunks into `speechsdk.audio.PushAudioInputStream`.
  4. *Pronunciation Assessment Config*: Initializes `PronunciationAssessmentConfig` for fluency, accuracy, completeness, and phoneme scoring.
  5. *Streaming Event Handlers*: Dispatches real-time hypotheses, finalized phrases, and word-level timing offsets back over the WebSocket.

---

### File: `v536/public/assessment-core.js`
- **Overview**: Core frontend assessment engine running inside student's browser during reading tests.
- **Variables**:
  - `audioContext`: Web Audio API `AudioContext`.
  - `pcmNode`: `AudioWorkletNode` streaming raw 16kHz PCM audio.
  - `socket`: Active WebSocket connection to speech service.
  - `passageWords`: Normalized array of reference passage words.
- **Connected Files**:
  - Loaded by: `assessment.php`.
  - Works with: `v536/public/pcm-processor.js`.
  - Connects to: `v536/service.py` via WebSocket.
- **Code Block Breakdown**:
  1. *Audio Initialization*: Captures microphone stream and creates 16kHz down-sampling Audio Worklet.
  2. *WebSocket Protocol*: Handshakes with speech service, sending reference passage text and receiving live word hypotheses.
  3. *Alignment & Miscue Tracker*: Dynamic programming sequence alignment comparing spoken words against reference words to classify miscues (Omission, Substitution, Insertion, Correct).
  4. *Visual Highlighter*: Highlights current passage word and paints error badges on the student screen in real-time.

---

### File: `v536/public/table_filter.js`
- **Overview**: Lightweight, zero-dependency Tailwind CSS table utility providing multi-criteria dropdown filtering, live search, column sorting, and empty-state messaging.
- **Variables**:
  - `tableId`: DOM ID of target table.
  - `searchId`: DOM ID of search input.
  - `filterConfig`: Array of `{ id: 'selectId', colIndex: number }` mapping dropdowns to table columns.
- **Connected Files**:
  - Loaded by: `assessment_result.php`, `course.php`, `student_progress_list.php`, `student_grades.php`, `teacher_course_grades.php`.
- **Code Block Breakdown**:
  1. *Normalization*: Normalizes legacy and multi-column filter configurations into a unified array.
  2. *Filter Application (`applyFilters`)*: Trims cell text, performs case-insensitive comparisons, evaluates search term against row text, and hides non-matching rows.
  3. *Dynamic Empty State*: Appends a styled `#noResultsRow` ("No matching results found") across table columns when all rows are filtered out.
  4. *Interactive Sorting*: Adds clickable sort headers with ascending/descending font-awesome arrows.

---

## 7. DevOps & Infrastructure Configurations

### File: `docker-compose.yml`
- **Overview**: Docker multi-container composition file declaring services, networking, environment variables, and persistent storage volumes.
- **Variables**:
  - Services: `caddy`, `web`, `speech-service`, `db`.
  - Volumes: `db_data`, `caddy_data`, `caddy_config`, `./uploads`.
- **Connected Files**:
  - Builds from: `Dockerfile.php`, `Dockerfile.python`.
  - Mounts: `Caddyfile`, `v536/.env`, `./uploads`.
- **Code Block Breakdown**:
  1. *`caddy` Service*: Public gateway on ports 80, 443, and 8011 with automatic TLS and proxy routing.
  2. *`web` Service*: Apache PHP 8.2 container running the web application.
  3. *`speech-service` Service*: Python container running FastAPI/Azure ASR service.
  4. *`db` Service*: MySQL 8.0 server initialized with `init_db.sql`.

---

### File: `Dockerfile.php`
- **Overview**: Docker image definition for the PHP Apache web service.
- **Connected Files**:
  - Used by: `docker-compose.yml` (build context for `web`).
- **Code Block Breakdown**:
  1. *Base Image*: `php:8.2-apache`.
  2. *Extensions*: Installs `pdo` and `pdo_mysql` extensions.
  3. *Apache Configuration*: Enables Apache `rewrite` module for routing.
  4. *Code Copy & Permissions*: Copies codebase to `/var/www/html/` and creates `/uploads/audio` directory with permissions.

---

### File: `Dockerfile.python`
- **Overview**: Docker image definition for the Python speech recognition service.
- **Connected Files**:
  - Used by: `docker-compose.yml` (build context for `speech-service`).
  - Reads: `requirements.txt`.
- **Code Block Breakdown**:
  1. *Base Image*: `python:3.11-slim-bullseye`.
  2. *System Dependencies*: Installs `libasound2` required by Azure Speech SDK.
  3. *Pip Dependencies*: Installs requirements from `requirements.txt`.
  4. *Execution*: Runs `python3 v536/service.py` exposing port 8011.

---

### File: `Caddyfile`
- **Overview**: Caddy web server reverse proxy configuration.
- **Connected Files**:
  - Mounted into `caddy` service container.
- **Code Block Breakdown**:
  1. *Reverse Proxy Routes*: Forwards standard HTTP requests to `web:80`.
  2. *WebSocket Proxy*: Routes `/azure-stream` to `speech-service:8011` with protocol upgrade headers.

---

## 8. Database Definition Files

### File: `schema.sql`
- **Overview**: Clean DDL database schema defining table structures, primary keys, indexes, foreign keys, and constraints.
- **Key Tables**:
  - `users`: Accounts, roles (`student`, `teacher`), LRNs, grades, sections, and reading profiles.
  - `reading_passages`: Graded passages, word counts, and comprehension questions.
  - `assessment_attempts`: Reading logs, audio paths, WCPM, accuracy, miscues JSON, and official teacher overrides.
  - `course_assessments`: ARAL Pre/Post tests, target profiles, and questionnaire JSON.
  - `student_course_attempts`: Student submissions for ARAL course modules.
  - `gst_results`: Group Screening Test records.
  - `settings`: System configurations key-value store.
- **Connected Files**:
  - Executed to initialize or reset MySQL databases.

---

### File: `aral_reading_db_dump.sql`
- **Overview**: Full database dump containing both table structures (DDL) and initial seed data (DML), including default passages, system settings, and sample users.
- **Connected Files**:
  - Used for restoring complete working databases in development or production.

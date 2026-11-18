# QUEST Question Paper Management System

## Login accounts

| Super Admin  `admin@gmail.com   password 1234567890

 
| Teacher       draijaz@gmail.com  password 1111111111

## Demo screenshots

- [Login page](demo/login-page.png)
- [Admin dashboard](demo/admin-dashboard.png)
- [Saved papers](demo/admin-saved-papers.png)
- [Question paper preview](demo/question-paper-preview.png)

## Run with XAMPP

1. Place this folder at `C:\xampp\htdocs\quest-paper-management`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. For a fresh install or a shared ZIP, open phpMyAdmin at `http://localhost/phpmyadmin` and import `database/schema.sql`. This is the one complete SQL file for all application features.
4. If MySQL has a password or a different database name, update `config/database.php`.
5. Open `http://localhost/quest-paper-management/setup.php`, create the first super admin, then delete `setup.php`.
6. Sign in at `http://localhost/quest-paper-management/login.php`.

## Existing installations

`database/schema.sql` creates the complete database for a fresh installation. Do not import it over an existing database containing records; back up existing data and arrange a migration before upgrading.

## Application features

The database supports department and teacher management, academic years and semesters, batches and grading policies, question papers, and teacher student-mark sheets. Teachers can manage marks for their assigned subjects, calculate grades using the batch policy, and export or print registers.

## Paper workflow

- Create a paper, choose department, batch, year/semester, subject and an exam type (Midterm, Final or Practical), then add questions. The subject list reloads for the selected semester; teachers see only their active assignments, while administrators can select any active subject in that semester.
- Midterm and Final papers support descriptive, MCQ, fill-in-the-blanks and true/false questions. Practical papers use MCQ, fill-in-the-blanks and true/false only, with no CLO, taxonomy or PLO fields/columns. Practical printouts automatically group questions under type-specific section headings, restart numbering at 1 in each section, and show section marks; MCQ and True/False choices include empty checkboxes. MCQs require at least two options and a correct answer; the answer key is not printed.
- Optional CLO, taxonomy and PLO values can be saved for each non-practical question and appear in a clean, bordered examination table on the printed paper.
- Add a JPG, PNG or WebP image to an individual question if needed (maximum 5 MB). Question text, image, options and marks are saved together.
- Save drafts, reopen and edit them, preview a paper, then use **Print / Save as PDF** in the browser. The printout uses the QUEST logo and fills in the department, academic year, semester, subject, exam details, applicable outcomes and marks from the saved records. Practical printouts omit CLO, taxonomy and PLO columns. Draft papers can be finalized once their marks meet the validation rules.
- Search papers by department, academic year, semester, subject/exam title, exam type and date.
- Teachers can filter Saved Papers by subject; clicking a subject name opens the paper list filtered to that subject. Each paper retains its subject and semester association.
- Super Admins can create, edit and delete grading policies under **Grading Policies**, then maintain their percentage thresholds, grade labels and grade points under **Grade Bands**. Assign the policy to batches under **Batches**. Keep a band with a 0% minimum for every policy; policies assigned to a batch cannot be deleted.
- Teachers can maintain a private, database-backed marks register for each assigned subject, semester, batch and exam type. Theory Final sheets validate the official 100-mark (sessional 20 + midterm 20 + final 60) or 50-mark (sessional 10 + midterm 10 + final 30) allocation. Practical sheets default to the 30/10/20/40 assessment weights. Totals, percentages, grades and grade points calculate automatically using the batch grading policy selected by an administrator. Download the register as an Excel-compatible CSV or print/save it as a PDF.
- Records linked to saved papers cannot be hard-deleted. The admin receives a message explaining the links and can deactivate the record instead, preserving historical papers.

Bootstrap and icons load from a CDN, so an internet connection is needed for the application styling.

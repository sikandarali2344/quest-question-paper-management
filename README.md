# 🎓 QUEST Question Paper Management System

<p align="center">
  <strong>A Complete Digital Examination & Academic Assessment Management Platform</strong>
  <br>
  Developed for QUEST University
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-Backend-777BB4?style=for-the-badge&logo=php&logoColor=white" />
  <img src="https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white" />
  <img src="https://img.shields.io/badge/Bootstrap-UI-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" />
  <img src="https://img.shields.io/badge/JavaScript-Frontend-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black" />
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Platform-Web%20Application-blue?style=flat-square" />
  <img src="https://img.shields.io/badge/Environment-XAMPP-orange?style=flat-square" />
  <img src="https://img.shields.io/badge/Project-Academic%20Management-success?style=flat-square" />
</p>

---

## 🌟 Overview

**QUEST Question Paper Management System** is a web-based academic management application designed to digitize and simplify examination paper preparation, academic assessment, and student marks management.

The platform provides separate dashboards for Super Admins and Teachers, allowing efficient management of examination papers, academic records, grading policies, and student performance.

It replaces manual examination preparation workflows with a centralized, structured, and database-driven system.

## 📸 Demo Screenshots

Explore the application's interface and major modules.

| Login Page | Admin Dashboard |
|---|---|
| ![Login Page](demo/login-page.png) | ![Admin Dashboard](demo/admin-dashboard.png) |

| Saved Papers | Question Paper Preview |
|---|---|
| ![Saved Papers](demo/admin-saved-papers.png) | ![Paper Preview](demo/question-paper-preview.png) |

---

## ✨ Key Features

### 🔐 Authentication & User Management
- Role-based login for Super Admin and Teachers.
- Department and teacher management.
- Active teacher assignments and access restrictions.

### 📝 Question Paper Management
- Create, edit, save, and manage examination papers.
- Midterm, Final, and Practical examination types.
- Descriptive, MCQ, Fill in the Blanks, and True/False questions.
- Individual question image uploads.
- Automatic marks validation.
- Draft and finalized paper management.

### 🎯 Academic Outcome Mapping
- Course Learning Outcomes (CLOs).
- Bloom's Taxonomy.
- Program Learning Outcomes (PLOs).
- Optional outcome mapping for non-practical questions.

### 🖨️ Professional Examination Printing
- QUEST examination paper layout.
- Automatic department and semester information.
- Clean examination tables and question formatting.
- Print or save papers as PDF.
- Practical paper sections with separate numbering and marks.
- MCQ and True/False empty checkboxes.
- Answer keys are not displayed in printed papers.

### 📊 Student Marks Management
- Subject-wise and semester-wise marks registers.
- Teacher-specific subject assignments.
- Automatic totals and percentage calculations.
- Automatic grade and grade-point calculation.
- Configurable grading policies and grade bands.
- CSV export and PDF printing.

### 🔎 Search & Data Management
- Search papers by department, batch, semester, subject, exam type, and date.
- Filter saved papers by subject.
- Preserve historical records.
- Deactivate linked records instead of deleting them.

---

## 🛠️ Technology Stack

| Technology | Purpose |
|---|---|
| PHP | Backend development |
| MySQL | Database management |
| HTML5 | Application structure |
| CSS3 | Styling and layouts |
| JavaScript | Interactive functionality |
| Bootstrap | Responsive UI components |
| Bootstrap Icons | Interface icons |
| XAMPP | Local development environment |

---

## ⚙️ Installation & Setup

### Requirements
- Windows / Linux
- XAMPP
- PHP
- MySQL
- Modern web browser
- Internet connection for Bootstrap and icon CDN resources

### Step 1 — Clone Repository

```bash
git clone https://github.com/YOUR-USERNAME/quest-question-paper-management.git
```

### Step 2 — Move Project Folder

Place the project inside:

```text
C:\xampp\htdocs\quest-paper-management
```

### Step 3 — Start XAMPP

Open XAMPP Control Panel and start:

- Apache
- MySQL

### Step 4 — Import Database

Open:

```text
http://localhost/phpmyadmin
```

Import the complete database file:

```text
database/schema.sql
```

**Important:** This SQL file is intended for fresh installations. Do not import it over an existing database containing records.

### Step 5 — Configure Database

If your MySQL password or database name is different, update:

```text
config/database.php
```

### Step 6 — Create Super Admin

Open:

```text
http://localhost/quest-paper-management/setup.php
```

Create the first Super Admin account.

After successful setup, delete `setup.php`.

### Step 7 — Login

Open:

```text
http://localhost/quest-paper-management/login.php
```

Your application is now ready to use!

---

## 🔄 Examination Paper Workflow

```text
        Super Admin / Teacher
                 |
                 ▼
          Create New Paper
                 |
                 ▼
       Select Academic Details
                 |
                 ▼
        Select Examination Type
                 |
                 ▼
          Add Questions
                 |
                 ▼
       Add Marks & Outcomes
                 |
                 ▼
           Save Draft
                 |
                 ▼
          Preview Paper
                 |
                 ▼
        Validate Total Marks
                 |
                 ▼
        Finalize Examination
                 |
                 ▼
         Print / Save as PDF
```

---

## 📚 Academic Assessment

The system supports different assessment structures.

**Theory Final Examination**

| Component | Marks |
|---|---:|
| Sessional | 20 |
| Midterm | 20 |
| Final | 60 |
| **Total** | **100** |

An alternative 50-mark allocation is also supported:

| Component | Marks |
|---|---:|
| Sessional | 10 |
| Midterm | 10 |
| Final | 30 |
| **Total** | **50** |

**Practical Assessment**

Default assessment weights:

- Sessional: 30%
- Midterm: 10%
- Final: 20%
- Practical: 40%

Grades and grade points are calculated according to the grading policy assigned to each batch.

---

## 📁 Project Structure

```text
quest-paper-management/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/
│   └── database.php
│
├── database/
│   └── schema.sql
│
├── demo/
│   ├── login-page.png
│   ├── admin-dashboard.png
│   ├── admin-saved-papers.png
│   └── question-paper-preview.png
│
├── includes/
│
├── setup.php
├── login.php
├── index.php
└── README.md
```

*Additional application files and folders are maintained in the repository.*

---

## 🔒 Database & Security Notes

- Database-backed application records.
- Role-based access restrictions.
- Teachers can manage marks only for their assigned subjects.
- MCQ correct answers are not displayed in printed examination papers.
- Historical records linked to saved papers are protected from hard deletion.
- Use secure passwords and never commit real credentials or private configuration files to a public repository.

---

## 🚀 Future Improvements

Potential areas for further development:

- Online examination scheduling.
- Student portal integration.
- Advanced academic analytics.
- Automated examination reports.
- Additional export formats.
- Email notifications.

---

## 👨‍💻 Developer

**Developed by Sikandar Ali**

Computer Science Student  
Quaid-e-Awam University of Engineering, Science & Technology (QUEST), Nawabshah

**Project:** QUEST Question Paper Management System

---

<p align="center">
  <strong>🎓 Digitizing Examination Management at QUEST University</strong>
  <br>
  <sub>Designed to make academic workflows more organized, efficient, and accessible.</sub>
</p>

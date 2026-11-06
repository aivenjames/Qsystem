# Qsystem – School Digital Queueing & Appointment Management

## Setup (XAMPP, Windows/macOS/Linux)
1. Install XAMPP and start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Copy the `qsystem` folder into `C:\xampp\htdocs\` (macOS/Linux: `/opt/lampp/htdocs/`).
3. Open http://localhost/phpmyadmin → **Import** → choose `database.sql` → Go. (Creates `qsystem_db`.)
4. If your MySQL root has a password, edit `config.php`.
5. Open http://localhost/qsystem/setup.php once to create demo accounts, then **delete setup.php**.
6. Open http://localhost/qsystem/

| Role | Email | Password |
|---|---|---|
| Staff | staff@qsystem.local | Staff@123 |
| Student | student@qsystem.local | Student@123 |
| Guest | no login – use "Get Walk-in Ticket" | |

## Python display board / kiosk (Thonny)
Open `python/qsystem_display.py` in Thonny and press Run. Uses only the standard library.
Set the same `KIOSK_KEY` in `config.php` and the Python file (change the default!).

## Workflow
Student books → staff approves (or rejects with a note) → student may **Edit** and save changes (approved items return to pending) →
on appointment day student taps **Check-in** → auto ticket (e.g. R001) → staff **Call Next / Complete / Skip / Hold / Requeue**.
Ticket numbers reset daily per service. Add services in the `services` table via phpMyAdmin.

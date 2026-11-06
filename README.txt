Qsystem - School Queue and Appointment Management System
=======================================================

TECHNOLOGY
- PHP 8+ (website and account/appointment logic)
- MySQL/MariaDB through XAMPP and phpMyAdmin
- Python 3 in Thonny (optional queue monitor utility)

IMPORTANT: Thonny does not run the PHP website. XAMPP's Apache runs PHP. MySQL stores all records. The Python file is an optional helper that reads the same queue database.

INSTALLATION ON WINDOWS / XAMPP
1. Install/start XAMPP.
2. Extract the Qsystem folder into C:\xampp\htdocs\ so this file exists:
   C:\xampp\htdocs\Qsystem\index.php
3. Open the XAMPP Control Panel. Start Apache and MySQL.
4. Visit http://localhost/phpmyadmin/
5. Choose Import, select Qsystem/database.sql, then click Import/Go.
   (Alternatively open the SQL tab and run all of database.sql.)
6. Open http://localhost/Qsystem/setup.php
7. Create the initial administrator with your own name, email, and a strong password.
8. Sign in at http://localhost/Qsystem/
9. Students can create accounts using the Create an account link. New public registrations are student-only.
10. Create a staff account by registering normally, then in phpMyAdmin open qsystem > users > Browse > Edit that account's role from student to staff. To make another admin, change the role to admin. Do not expose this role-editing capability to public users.

DEFAULT DATABASE CONFIGURATION
config.php and python/queue_monitor.py assume:
Host: 127.0.0.1
Database: qsystem
User: root
Password: (blank, typical local XAMPP default)
If you set a MySQL root password, update config.php and python/queue_monitor.py.

HOW TO USE
- Student: create account, sign in, book a service/date/time, wait for approval, then view the queue number/status in Appointments.
- Admin: sign in, open Queue & Admin, approve/reject Pending requests. Approval assigns a queue number for that appointment date and changes status to Waiting.
- Staff: sign in, open Queue & Admin, change Waiting to Serving, then Completed. Staff cannot approve or reject requests; approval is admin-only.
- The dashboard and appointment pages are responsive for desktop and mobile.

OPTIONAL PYTHON / THONNY QUEUE MONITOR
1. Start XAMPP MySQL.
2. Open Thonny and open python/queue_monitor.py.
3. In Thonny, select Tools > Manage packages.
4. Search for mysql-connector-python and install it.
5. Run the file. It prints today's queue to the Thonny shell.

TROUBLESHOOTING
- Database connection failed: confirm MySQL is green/running in XAMPP and database.sql was imported into qsystem.
- Apache won't start: another program may be using port 80/443; use XAMPP's port configuration or stop the conflicting service.
- Page not found: verify the folder is exactly C:\xampp\htdocs\Qsystem and browse to http://localhost/Qsystem/.
- Access denied: check config.php MySQL username/password.
- Thonny says No module named mysql: install mysql-connector-python using Thonny's Manage packages.

SECURITY / PROJECT NOTES
- This is a local educational starter project. Do not deploy it publicly as-is.
- Use a strong admin password. Use HTTPS, backups, restricted DB credentials, rate limiting, and review security before production deployment.
- Remove or restrict setup.php after initial admin creation if the system will be used beyond local development.
- Queue numbering starts at 1 for each date based on existing queue numbers. For a high-traffic production system, use a dedicated daily counter table and stronger transaction/locking strategy.

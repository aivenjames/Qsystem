"""Qsystem helper for Thonny: display today's queue from the same MySQL database.
Install dependency in Thonny: Tools > Manage packages > mysql-connector-python.
Start MySQL in XAMPP before running this file.
"""
from datetime import date
import mysql.connector
from mysql.connector import Error

DB_CONFIG = {
    "host": "127.0.0.1",
    "user": "root",
    "password": "",  # Default XAMPP setup. Change if your MySQL root has a password.
    "database": "qsystem_db",
}


def main():
    connection = None
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor()
        cursor.execute(
            """SELECT t.ticket_no, t.guest_name, s.name, t.status, t.created_at
               FROM queue_tickets AS t
               JOIN services AS s ON s.id = t.service_id
               WHERE t.queue_date = %s
                 AND t.status IN ('waiting', 'serving', 'done')
               ORDER BY CASE t.status WHEN 'serving' THEN 0 WHEN 'waiting' THEN 1 ELSE 2 END,
                        s.name, t.seq""",
            (date.today(),),
        )
        rows = cursor.fetchall()
        print("\n=== Qsystem: Today's queue ===")
        print(date.today().strftime("%B %d, %Y"))
        print("-" * 72)
        if not rows:
            print("No queue entries for today.")
        else:
            print(f"{'QUEUE':<10}{'NAME':<24}{'SERVICE':<20}{'STATUS':<13}{'TIME'}")
            print("-" * 72)
            for number, name, service, status, created in rows:
                print(f"{number:<10}{name[:22]:<24}{service[:18]:<20}{status.capitalize():<13}{created.strftime('%H:%M')}")
        cursor.close()
    except Error as error:
        print("Database error. Check that XAMPP MySQL is running and database.sql was imported.")
        print("Details:", error)
    finally:
        if connection is not None and connection.is_connected():
            connection.close()


if __name__ == "__main__":
    main()
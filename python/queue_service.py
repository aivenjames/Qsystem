"""Optional Python queue-control API for Qsystem.
Install in Thonny: Tools > Manage packages > flask, pymysql.
Start MySQL in XAMPP, then run this file. Keep bound to localhost.
"""
import hmac
import os
from datetime import date
from flask import Flask, request, jsonify
import pymysql

app = Flask(__name__)
TOKEN = os.getenv("QSYSTEM_API_TOKEN", "change-this-local-token")


def connect_db():
    return pymysql.connect(
        host=os.getenv("QSYSTEM_DB_HOST", "127.0.0.1"),
        user=os.getenv("QSYSTEM_DB_USER", "root"),
        password=os.getenv("QSYSTEM_DB_PASS", ""),
        database=os.getenv("QSYSTEM_DB_NAME", "qsystem_db"),
        charset="utf8mb4", cursorclass=pymysql.cursors.DictCursor,
        autocommit=False,
    )


def finish(cur, ticket_id, new_status):
    """Set a ticket to done/skipped; a done ticket also completes its appointment."""
    cur.execute(
        "UPDATE queue_tickets SET status=%s, finished_at=IF(%s='done', NOW(), finished_at) WHERE id=%s",
        (new_status, new_status, ticket_id))
    if new_status == "done":
        cur.execute(
            "UPDATE appointments a JOIN queue_tickets t ON t.appointment_id = a.id "
            "SET a.status='completed' WHERE t.id=%s", (ticket_id,))


@app.get("/health")
def health():
    return jsonify({"ok": True, "service": "Qsystem Python queue service"})


@app.post("/queue/action")
def queue_action():
    data = request.get_json(silent=True) or {}
    if not hmac.compare_digest(str(data.get("token", "")), TOKEN):
        return jsonify({"ok": False, "message": "Unauthorized"}), 401
    action = data.get("action")
    if action not in {"next", "complete", "skip"}:
        return jsonify({"ok": False, "message": "Unknown action"}), 400
    try:
        service_id = int(data.get("service_id", 0))
    except (TypeError, ValueError):
        service_id = 0
    if service_id <= 0:
        return jsonify({"ok": False, "message": "service_id is required"}), 400

    conn = connect_db()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM queue_tickets WHERE service_id=%s AND queue_date=%s "
                "AND status='serving' ORDER BY id LIMIT 1 FOR UPDATE",
                (service_id, date.today()))
            current = cur.fetchone()

            if action == "next":
                if current:
                    finish(cur, current["id"], "done")
                cur.execute(
                    "SELECT id FROM queue_tickets WHERE service_id=%s AND queue_date=%s "
                    "AND status='waiting' ORDER BY seq ASC LIMIT 1 FOR UPDATE",
                    (service_id, date.today()))
                nxt = cur.fetchone()
                if nxt:
                    cur.execute("UPDATE queue_tickets SET status='serving', called_at=NOW() WHERE id=%s", (nxt["id"],))
                    message = "Next customer is now being served."
                else:
                    message = "There are no waiting customers."
            elif current:
                finish(cur, current["id"], "done" if action == "complete" else "skipped")
                message = "Current customer marked completed." if action == "complete" else "Current customer skipped."
            else:
                message = "No customer is currently being served."
        conn.commit()
        return jsonify({"ok": True, "message": message})
    except Exception:
        conn.rollback()
        app.logger.exception("Queue action failed")
        return jsonify({"ok": False, "message": "Queue action failed"}), 500
    finally:
        conn.close()


if __name__ == "__main__":
    # Local-only API; do not expose this development server to the public internet.
    app.run(host="127.0.0.1", port=5055, debug=False)
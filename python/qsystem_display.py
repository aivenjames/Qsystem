"""
Qsystem Display Board + Ticket Kiosk (Python 3 / Tkinter - works in Thonny, no installs needed).
1. Start Apache + MySQL in XAMPP.  2. Open this file in Thonny and press Run (F5).
Keys: F11 = fullscreen, Esc = leave fullscreen.
"""
import json
import tkinter as tk
from tkinter import messagebox, simpledialog
from urllib import request, parse

BASE = "http://localhost/qsystem"      # change if your folder name differs
KIOSK_KEY = "change-me-kiosk-key"      # must match KIOSK_KEY in config.php
REFRESH_MS = 3000


def fetch_queue():
    with request.urlopen(BASE + "/api_queue.php", timeout=4) as r:
        return json.loads(r.read().decode())["services"]


def take_ticket(service_id, name):
    data = parse.urlencode({"key": KIOSK_KEY, "service_id": service_id, "name": name}).encode()
    with request.urlopen(request.Request(BASE + "/api_queue.php?action=take", data=data), timeout=4) as r:
        return json.loads(r.read().decode())["ticket"]


class Board(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("Qsystem - Now Serving")
        self.configure(bg="#f4f6fb")
        self.geometry("900x600")
        self.bind("<F11>", lambda e: self.attributes("-fullscreen", True))
        self.bind("<Escape>", lambda e: self.attributes("-fullscreen", False))
        tk.Label(self, text="NOW SERVING", font=("Arial", 28, "bold"), bg="#2557d6", fg="white", pady=12).pack(fill="x")
        self.frame = tk.Frame(self, bg="#f4f6fb")
        self.frame.pack(expand=True, fill="both", padx=10, pady=10)
        self.status = tk.Label(self, text="Connecting...", bg="#f4f6fb", fg="#6b7488")
        self.status.pack(pady=4)
        self.ids = []
        self.refresh()

    def build(self, services):
        for w in self.frame.winfo_children():
            w.destroy()
        self.cells = {}
        cols = 3
        for i, s in enumerate(services):
            card = tk.Frame(self.frame, bg="white", highlightbackground="#e1e5ef", highlightthickness=1)
            card.grid(row=i // cols, column=i % cols, padx=8, pady=8, sticky="nsew")
            self.frame.columnconfigure(i % cols, weight=1)
            self.frame.rowconfigure(i // cols, weight=1)
            tk.Label(card, text=s["name"], font=("Arial", 14), bg="white").pack(pady=(10, 0))
            num = tk.Label(card, text="-", font=("Arial", 44, "bold"), fg="#2557d6", bg="white")
            num.pack()
            wait = tk.Label(card, text="", bg="white", fg="#6b7488")
            wait.pack()
            tk.Button(card, text="Get Ticket", bg="#2557d6", fg="white",
                      command=lambda sid=s["id"], n=s["name"]: self.kiosk(sid, n)).pack(pady=8)
            self.cells[s["id"]] = (num, wait)
        self.ids = [s["id"] for s in services]

    def kiosk(self, sid, name):
        who = simpledialog.askstring("Get Ticket", "Your name (optional):", parent=self) or "Kiosk guest"
        try:
            no = take_ticket(sid, who)
            messagebox.showinfo("Your ticket", f"{name}\n\nYour number: {no}")
        except Exception as ex:
            messagebox.showerror("Error", f"Could not create ticket:\n{ex}")

    def refresh(self):
        try:
            services = fetch_queue()
            if [s["id"] for s in services] != self.ids:
                self.build(services)
            for s in services:
                num, wait = self.cells[s["id"]]
                num.config(text=s["serving"] or "-")
                wait.config(text=f'{s["waiting"]} waiting')
            self.status.config(text="Live - updates every 3 seconds")
        except Exception as ex:
            self.status.config(text=f"Cannot reach server: {ex}")
        self.after(REFRESH_MS, self.refresh)


if __name__ == "__main__":
    Board().mainloop()

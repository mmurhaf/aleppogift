"""Open Social Bot in its own Windows window and stop it when that window closes."""

import os
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent
URL = "http://127.0.0.1:8501"
PORT = 8501
PROFILE_NAME = "AleppoGiftSocialBot"
CREATE_NO_WINDOW = 0x08000000


def app_python():
    venv = ROOT / ".venv" / "Scripts" / "python.exe"
    if venv.is_file():
        return str(venv)
    return sys.executable


def pid_alive(pid):
    if not pid:
        return False
    try:
        pid = int(pid)
    except (TypeError, ValueError):
        return False
    if pid <= 0:
        return False
    result = subprocess.run(
        ["tasklist", "/FI", f"PID eq {pid}", "/NH"],
        capture_output=True,
        text=True,
        creationflags=CREATE_NO_WINDOW,
    )
    return str(pid) in (result.stdout or "")


def read_pid(path):
    try:
        pid = path.read_text(encoding="utf-8").strip()
    except OSError:
        return None
    return pid if pid_alive(pid) else None


def write_pid(path, pid):
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(str(pid), encoding="utf-8")


def clear_pid(path):
    try:
        path.unlink()
    except OSError:
        pass


def port_open(port):
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.settimeout(0.4)
        return sock.connect_ex(("127.0.0.1", port)) == 0


def wait_until_ready(timeout=45):
    deadline = time.time() + timeout
    while time.time() < deadline:
        try:
            with urllib.request.urlopen(URL, timeout=2) as response:
                if response.status < 500:
                    return True
        except (urllib.error.URLError, TimeoutError, OSError):
            time.sleep(0.4)
    return False


def edge_path():
    candidates = [
        Path(os.environ.get("PROGRAMFILES(X86)", r"C:\Program Files (x86)")) / "Microsoft" / "Edge" / "Application" / "msedge.exe",
        Path(os.environ.get("PROGRAMFILES", r"C:\Program Files")) / "Microsoft" / "Edge" / "Application" / "msedge.exe",
    ]
    for candidate in candidates:
        if candidate.is_file():
            return str(candidate)
    return None


def edge_window_count():
    script = (
        "Get-CimInstance Win32_Process -Filter \"Name = 'msedge.exe'\" | "
        f"Where-Object {{ $_.CommandLine -like '*{PROFILE_NAME}*' }} | "
        "Measure-Object | Select-Object -ExpandProperty Count"
    )
    result = subprocess.run(
        ["powershell", "-NoProfile", "-Command", script],
        capture_output=True,
        text=True,
        creationflags=CREATE_NO_WINDOW,
    )
    try:
        return int((result.stdout or "0").strip() or "0")
    except ValueError:
        return 0


def ensure_database():
    db_path = ROOT / "social_bot.db"
    if db_path.is_file():
        return
    subprocess.run(
        [app_python(), str(ROOT / "init_database.py")],
        cwd=ROOT,
        check=False,
        creationflags=CREATE_NO_WINDOW,
    )


def start_streamlit(log_file):
    process = subprocess.Popen(
        [
            app_python(), "-m", "streamlit", "run", "app.py",
            "--server.port", str(PORT),
            "--server.address", "127.0.0.1",
            "--server.headless", "true",
            "--browser.gatherUsageStats", "false",
        ],
        cwd=ROOT,
        stdout=log_file,
        stderr=subprocess.STDOUT,
        creationflags=CREATE_NO_WINDOW,
    )
    write_pid(ROOT / "output" / "run" / "ui.pid", process.pid)
    return process


def start_scheduler(log_file):
    pid_path = ROOT / "output" / "run" / "scheduler.pid"
    if read_pid(pid_path):
        return None
    process = subprocess.Popen(
        [app_python(), str(ROOT / "scheduler_daemon.py")],
        cwd=ROOT,
        stdout=log_file,
        stderr=subprocess.STDOUT,
        creationflags=CREATE_NO_WINDOW,
    )
    write_pid(pid_path, process.pid)
    return process


def stop_process(process, pid_path):
    if process is not None and process.poll() is None:
        subprocess.run(
            ["taskkill", "/F", "/T", "/PID", str(process.pid)],
            capture_output=True,
            creationflags=CREATE_NO_WINDOW,
        )
    clear_pid(pid_path)


def open_window():
    edge = edge_path()
    profile = Path(os.environ.get("LOCALAPPDATA", str(ROOT))) / PROFILE_NAME / "edge"
    profile.mkdir(parents=True, exist_ok=True)
    if not edge:
        os.startfile(URL)
        print(f"Social Bot is running at {URL}")
        print("Close this window to quit.")
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            return
        return

    subprocess.Popen(
        [
            edge,
            f"--app={URL}",
            "--window-size=1400,900",
            f"--user-data-dir={profile}",
            "--no-first-run",
            "--no-default-browser-check",
            "--disable-sync",
        ],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
        creationflags=CREATE_NO_WINDOW,
    )
    print("Social Bot is open in its own window.")
    print("Close that window to quit.")
    deadline = time.time() + 20
    while time.time() < deadline and edge_window_count() == 0:
        time.sleep(0.3)
    if edge_window_count() == 0:
        print(f"If the window did not appear, open {URL}")
        try:
            while True:
                time.sleep(1)
        except KeyboardInterrupt:
            return
    while edge_window_count() > 0:
        time.sleep(0.6)


def main():
    os.chdir(ROOT)
    (ROOT / "output" / "logs").mkdir(parents=True, exist_ok=True)
    (ROOT / "output" / "run").mkdir(parents=True, exist_ok=True)
    ensure_database()

    ui_log = open(ROOT / "output" / "logs" / "ui.log", "w", encoding="utf-8")
    scheduler_log = open(ROOT / "output" / "logs" / "scheduler.log", "a", encoding="utf-8")
    ui_process = None
    scheduler_process = None
    owns_ui = False
    try:
        if port_open(PORT):
            print("Social Bot is already running. Opening the window.")
        else:
            ui_process = start_streamlit(ui_log)
            owns_ui = True
            if not wait_until_ready():
                print("Social Bot did not start. Details are in output\\logs\\ui.log")
                return 1
        scheduler_process = start_scheduler(scheduler_log)
        open_window()
        return 0
    finally:
        if owns_ui:
            stop_process(ui_process, ROOT / "output" / "run" / "ui.pid")
        if scheduler_process is not None:
            stop_process(scheduler_process, ROOT / "output" / "run" / "scheduler.pid")
        ui_log.close()
        scheduler_log.close()


if __name__ == "__main__":
    sys.exit(main())

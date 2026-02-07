import os
import subprocess
import glob

print("Checking for FFmpeg...")

found_path = None

# 1. Check specific path found in logs
specific_path = r"C:\Users\DeLL\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-8.0.1-full_build\bin\ffmpeg.exe"
if os.path.exists(specific_path):
    print(f"Found at specific path: {specific_path}")
    found_path = specific_path
else:
    print(f"Not found at specific path: {specific_path}")

# 2. Search pattern
search_pattern = os.path.expandvars(r"$LOCALAPPDATA\Microsoft\WinGet\Packages\**\ffmpeg.exe")
print(f"Searching pattern: {search_pattern}")
matches = glob.glob(search_pattern, recursive=True)
if matches:
    print(f"Found via glob: {matches[0]}")
    if not found_path:
        found_path = matches[0]
else:
    print("No matches found via glob")

if found_path:
    print(f"Testing execution of: {found_path}")
    try:
        result = subprocess.run([found_path, "-version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        print("Execution success!")
        print(result.stdout[:100])
    except Exception as e:
        print(f"Execution failed: {e}")
else:
    print("Trying 'ffmpeg' command directly...")
    try:
        result = subprocess.run(["ffmpeg", "-version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        print("Command 'ffmpeg' execution success!")
    except FileNotFoundError:
        print("Command 'ffmpeg' not found in PATH")

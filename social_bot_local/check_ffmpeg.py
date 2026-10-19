"""Report which ffmpeg binary the reel renderer will use."""

from modules.video_engine import FFMPEG_PATH, resolve_ffmpeg
import subprocess

print("Checking for FFmpeg...")
found = FFMPEG_PATH or resolve_ffmpeg()
if not found:
    print("FFmpeg was not found.")
    print("Install it and add it to PATH, or set the FFMPEG_PATH environment variable.")
    raise SystemExit(1)

print(f"Using: {found}")
result = subprocess.run([found, "-version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
if result.returncode != 0:
    print(result.stderr.strip() or "ffmpeg -version failed")
    raise SystemExit(result.returncode)
print("\n".join(result.stdout.splitlines()[:2]))

# Python Local Social Media Automation Prompt

## Project Overview

Create a robust, local Python desktop application (or local web app) for automated social media content creation and publishing. This tool will scrape product images from a specified website, generate engaging short-form videos (Reels/TikToks), and publish them to TikTok, Instagram, and Facebook.

**PROJECT NAME:** SocialMediaAutoBot - Local Edition

---

## CORE FUNCTIONALITY

1.  **Web Scraper & Asset Manager:**
    *   Input a target website URL (e.g., `https://aleppogift.com`).
    *   Scan and display available product images.
    *   One-click download of selected images to a local asset library.
2.  **Video Generation Engine:**
    *   Convert static images into 9:16 vertical videos.
    *   Apply "Smart Background" (blurred fill) for non-vertical images.
    *   Apply Ken Burns effects (zoom/pan).
    *   Add text overlays (Product Name, Price).
    *   Merge with local royalty-free audio files.
3.  **Multi-Platform Publishing:**
    *   **TikTok:** Upload via TikTok API.
    *   **Instagram:** Upload Reels via Instagram Graph API.
    *   **Facebook:** Upload Reels via Facebook Graph API.
4.  **Local Control Panel (GUI):**
    *   Manage API Credentials securely.
    *   Select images and preview generated videos.
    *   Schedule posts for specific times.

---

## TECHNICAL REQUIREMENTS

-   **Language:** Python 3.10+
-   **GUI Framework:** Streamlit (preferred for easy dashboard) or PyQt/Tkinter.
-   **Video Processing:** `MoviePy` (for editing) and `FFmpeg`.
-   **Web Scraping:** `BeautifulSoup4` or `Selenium` (for fetching images).
-   **Database:** SQLite (local `social_bot.db`) for tracking usage and settings.
-   **Scheduling:** `APScheduler` or native `schedule` library.

---

## MODULES & FEATURES

### 1. Admin Control Panel (Dashboard)
A local interface accessible via browser (Streamlit) or window.
-   **Tabs:**
    *   **Scraper:** Input URL -> Grid View of Images -> Select & Download.
    *   **Editor:** Select Downloaded Image -> Settings (Duration, Audio) -> Generate Video.
    *   **Scheduler:** Calendar view of pending posts.
    *   **Settings:** Input fields for API Keys (TikTok Client Key, FB App ID, Insta Access Token).

### 2. Smart Video Generator
-   **Input:** Local image file.
-   **Processing:**
    *   Resize logic: Checks aspect ratio. If not 9:16, creates a blurred background layer using the same image, places sharp image in center.
    *   Animation: Slow zoom in (1.0x to 1.1x) over 15 seconds.
    *   Audio: Randomly select `.mp3` from local `assets/audio/` folder.
    *   Output: `.mp4` file saved to `output/videos/`.

### 3. API Integrations
-   **TikTok:** OAuth flow handling (opening browser for user to authorize), managing Access/Refresh tokens.
-   **Meta (FB/Insta):** Graph API integration for "Reels Publish" endpoint.
-   **Token Management:** Store tokens in SQLite (encrypted if possible) and handle auto-refreshing.

---

## APP STRUCTURE (Suggested)

```
social_bot_local/
├── app.py                 # Main Streamlit/GUI entry point
├── config.py              # Configuration loader
├── database.py            # SQLite handler
├── requirements.txt       # Dependencies
├── assets/
│   ├── images/            # Downloaded images
│   ├── audio/             # Background music
│   ├── templates/         # Overlay assets
├── output/                # Generated videos
├── modules/
│   ├── scraper.py         # Website image operations
│   ├── video_engine.py    # MoviePy logic
│   ├── platforms/
│   │   ├── tiktok_api.py
│   │   ├── instagram_api.py
│   │   ├── facebook_api.py
│   ├── scheduler_service.py
```

---

## DATABASE SCHEMA (SQLite)

```sql
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT
);

CREATE TABLE media_assets (
    id INTEGER PRIMARY KEY,
    source_url TEXT,
    local_path TEXT,
    downloaded_at DATETIME
);

CREATE TABLE posts (
    id INTEGER PRIMARY KEY,
    video_path TEXT,
    caption TEXT,
    platform TEXT, -- 'tiktok', 'instagram', 'facebook'
    status TEXT, -- 'draft', 'scheduled', 'posted', 'failed'
    scheduled_time DATETIME
);
```

---

## USER WORKFLOW

1.  **Setup:** User runs `pip install -r requirements.txt` then `streamlit run app.py`.
2.  **Config:** User goes to "Settings" tab, enters their TikTok/Facebook App credentials.
3.  **Acquire:** User goes to "Scraper", enters `aleppogift.com`, selects 5 product photos, clicks "Download".
4.  **Create:** User goes to "Editor", picks a photo, adds caption "Best Handmade Tea Set!", clicks "Generate Video".
5.  **Preview:** User watches the generated MP4.
6.  **Publish:** User selects platforms (TikTok + Insta), sets time, clicks "Schedule".
7.  **Run:** The background scheduler picks up the task and executes the upload.

---

## DELIVERABLES

Please provide:
1.  Complete Python source code structured in the folders described.
2.  `requirements.txt` file.
3.  `README.md` with instructions on how to get API keys from TikTok/Meta developers portals.
4.  A script to initialize the SQLite database.

**Important:** Ensure code handles API rate limits and network errors (e.g., connection timeout during upload).

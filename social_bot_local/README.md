# SocialMediaAutoBot Pro - Local Edition

## Overview
A production-ready Python application for automated social media content creation and multi-platform publishing.

## Setup Instructions

### 1. Prerequisites
- **Python 3.10+**
- **FFmpeg**: Must be installed and added to your System PATH.
- **MySQL**: Connection to `aleppogift` database.

### 2. Installation
1.  Install dependencies:
    ```bash
    pip install -r requirements.txt
    ```
2.  Set up environment variables:
    - Copy `.env.example` to `.env`.
    - Fill in your API keys (TikTok, Instagram, Facebook) and Database credentials.
    - Generate an encryption key (you can use a simple python script `from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())`).

3.  Initialize the local database:
    ```bash
    python init_database.py
    ```

### 3. Running the Application
Double-click **`Social Bot.bat`**. The first launch creates a local Python environment, then opens Social Bot in its own window. Close that window to quit.

The scheduler starts with the window. The browser control panel in `dashboard/` can still start and stop the same services.

Open `USER_MANUAL.html` for the illustrated user manual.

## Features
- **Video Generation**: Creates 9:16 videos from product images with Ken Burns effects.
- **Multi-Platform**: Supports TikTok, Instagram Reels, and Facebook Reels.
- **Scheduling**: Plan posts in advance.
- **Analytics**: Track performance (mock implementation provided for now).

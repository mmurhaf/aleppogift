# Python Social Media Automation - IMPROVED PROMPT

## Project Overview

Create a production-ready, local Python application for automated social media content creation and multi-platform publishing. This system will connect to your existing product database, generate high-quality vertical videos with dynamic effects, and intelligently publish to TikTok, Instagram Reels, and Facebook Reels with proper API handling.

**PROJECT NAME:** SocialMediaAutoBot Pro - Local Edition

---

## CORE FUNCTIONALITY

1.  **Product Data Integration:**
    *   **Primary:** Direct MySQL connection to existing `aleppogift.com` products database
    *   **Fallback:** REST API endpoint integration (`/api/products.json`)
    *   **Manual:** CSV import for standalone usage
    *   Auto-sync product images, names, prices, descriptions
2.  **Professional Video Generation:**
    *   Convert product images to 9:16 vertical videos (1080x1920px)
    *   **Smart Background:** Blurred backdrop for non-vertical images
    *   **Ken Burns Effect:** Smooth zoom/pan using FFmpeg (not MoviePy)
    *   Dynamic text overlays (product name, price, call-to-action)
    *   Random background music from local royalty-free library
    *   **Quality Presets:** Low/Medium/High (balancing file size vs quality)
3.  **Multi-Platform Publishing:**
    *   **TikTok:** Direct video upload via Content Posting API
    *   **Instagram:** 2-step container upload + publish (with temporary hosting)
    *   **Facebook:** Reels API with proper page authentication
    *   **Safe Mode:** Upload as draft/private for manual review
4.  **Dual-Process Architecture:**
    *   **UI Process:** Streamlit dashboard (app.py)
    *   **Background Process:** Scheduler daemon (scheduler_daemon.py)
5.  **Analytics & Optimization:**
    *   Daily metrics sync (views, likes, shares, comments)
    *   Performance dashboard (best products, optimal times)
    *   Engagement trend analysis

---

## TECHNICAL REQUIREMENTS

-   **Language:** Python 3.10+
-   **GUI Framework:** Streamlit (web-based dashboard)
-   **Video Processing (CRITICAL):**
    *   **Primary:** FFmpeg via subprocess (zoompan filter for Ken Burns)
    *   **Secondary:** Pillow/PIL for image preprocessing
    *   **Avoid:** Pure MoviePy for effects (too slow)
-   **Database:** 
    *   SQLite for local app data (`social_bot.db`)
    *   MySQL connector for reading product database
-   **Scheduling:** Separate daemon process using `schedule` or `APScheduler`
-   **Security:** `cryptography.fernet` for token encryption
-   **Video Hosting (Instagram):** Flask + NGROK for temporary public URLs

---

## PLATFORM-SPECIFIC REQUIREMENTS

### TikTok API
-   **Endpoint:** `POST /v2/post/publish/video/init/`
-   **Auth:** OAuth 2.0 (Client Key, Client Secret, Access Token)
-   **Video Requirements:**
    *   Format: MP4 (H.264 codec)
    *   Max size: 287.6 MB
    *   Duration: 15-60 seconds (15-30 recommended)
-   **Rate Limits:** 10 posts per day (organic account)

### Instagram Graph API
-   **Process:** 2-step upload
    *   Step 1: `POST /{ig-user-id}/media` (video_url, caption)
    *   Step 2: `POST /{ig-user-id}/media_publish` (creation_id)
-   **Requirements:**
    *   Business/Creator account linked to Facebook Page
    *   Video must be at **publicly accessible URL** (temp hosting needed)
    *   Format: MP4 (H.264 video, AAC audio)
    *   Duration: 15-90 seconds
-   **Rate Limits:** 25 posts per 24 hours

### Facebook Graph API
-   **Endpoint:** `POST /{page-id}/video_reels`
-   **Auth:** Page Access Token
-   **Video Requirements:**
    *   Same as Instagram (H.264, AAC)
    *   Upload via multipart/form-data
-   **Rate Limits:** 50 calls per page per hour

### Platform Comparison Table

| Feature | TikTok | Instagram | Facebook |
|---------|--------|-----------|----------|
| **Upload Method** | Direct file | URL-based | Direct file |
| **Max Duration** | 60s | 90s | 60s |
| **Hashtag Limit** | 30 | 30 | 15 |
| **Draft Mode** | ✅ Yes | ✅ Yes | ❌ No |
| **Analytics API** | ✅ Full | ✅ Insights | ✅ Video Stats |

---

## APP STRUCTURE

```
social_bot_local/
├── app.py                      # Streamlit dashboard (UI only)
├── scheduler_daemon.py         # Background scheduler (separate process)
├── config.py                   # Configuration management
├── database.py                 # SQLite operations
├── requirements.txt            # Python dependencies
├── .env.example                # Template for environment variables
├── .gitignore                  # Git exclusions
├── init_database.py            # Database initialization script
├── README.md                   # Setup and API key instructions
│
├── assets/
│   ├── images/                 # Downloaded product images
│   ├── audio/                  # Royalty-free background music (MP3)
│   ├── templates/              # Video overlay assets (logo, watermark)
│   ├── fonts/                  # Custom fonts for text overlays
│
├── output/
│   ├── videos/                 # Generated MP4 files
│   ├── logs/                   # Error and activity logs
│
├── modules/
│   ├── __init__.py
│   ├── database_connector.py   # MySQL product database connection
│   ├── video_engine.py         # FFmpeg video generation
│   ├── caption_generator.py    # Smart caption creation
│   ├── analytics.py            # Metrics collection and reporting
│   ├── video_host_server.py    # Flask server for Instagram temp hosting
│   │
│   └── platforms/
│       ├── __init__.py
│       ├── tiktok_api.py       # TikTok API wrapper
│       ├── instagram_api.py    # Instagram Graph API
│       ├── facebook_api.py     # Facebook Graph API
│       └── oauth_handler.py    # Universal OAuth flow
│
└── templates/
    ├── captions.json           # Caption templates by category
    └── hashtags.json           # Platform-specific hashtag pools
```

---

## DATABASE SCHEMA

### SQLite (social_bot.db)

```sql
-- App settings and encrypted API credentials
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT,                      -- Encrypted if sensitive
    is_encrypted BOOLEAN DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Synced product catalog
CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    external_id INTEGER,             -- ID from aleppogift.com
    name TEXT,
    price DECIMAL(10,2),
    category TEXT,
    description TEXT,
    image_url TEXT,
    local_image_path TEXT,
    is_blacklisted BOOLEAN DEFAULT 0,
    synced_at DATETIME
);

-- Generated videos inventory
CREATE TABLE videos (
    id INTEGER PRIMARY KEY,
    product_id INTEGER,
    video_path TEXT,
    thumbnail_path TEXT,
    duration INTEGER,
    quality_preset TEXT,             -- 'low', 'medium', 'high'
    created_at DATETIME,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Post queue and history
CREATE TABLE posts (
    id INTEGER PRIMARY KEY,
    video_id INTEGER,
    platform TEXT,                   -- 'tiktok', 'instagram', 'facebook'
    caption TEXT,
    hashtags TEXT,
    status TEXT,                     -- 'draft', 'queued', 'posted', 'failed'
    scheduled_time DATETIME,
    posted_time DATETIME,
    post_url TEXT,                   -- Link to published content
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    FOREIGN KEY (video_id) REFERENCES videos(id)
);

-- Analytics data
CREATE TABLE post_metrics (
    id INTEGER PRIMARY KEY,
    post_id INTEGER,
    views INTEGER DEFAULT 0,
    likes INTEGER DEFAULT 0,
    shares INTEGER DEFAULT 0,
    comments INTEGER DEFAULT 0,
    engagement_rate DECIMAL(5,2),
    fetched_at DATETIME,
    FOREIGN KEY (post_id) REFERENCES posts(id)
);

-- OAuth tokens (encrypted)
CREATE TABLE oauth_tokens (
    platform TEXT PRIMARY KEY,
    access_token TEXT,               -- Encrypted
    refresh_token TEXT,              -- Encrypted
    expires_at DATETIME,
    created_at DATETIME
);
```

### MySQL Connection (Read-Only)
Connect to existing `aleppogift.com` database to read:
-   `products` table (id, name, price, category, main_image)
-   `product_images` table (for additional images)

---

## KEY MODULES & CLASSES

### 1. video_engine.py (FFmpeg-Based)

```python
class VideoGenerator:
    def __init__(self, quality='medium'):
        """
        quality: 'low' (720p), 'medium' (1080p), 'high' (1080p, high bitrate)
        """
        self.quality_presets = {
            'low': {'width': 720, 'height': 1280, 'bitrate': '2M'},
            'medium': {'width': 1080, 'height': 1920, 'bitrate': '4M'},
            'high': {'width': 1080, 'height': 1920, 'bitrate': '8M'}
        }
    
    def create_video(self, image_path, duration=15, audio_path=None):
        """
        1. Check aspect ratio of input image
        2. If not 9:16, create blurred background
        3. Apply zoompan filter (Ken Burns effect)
        4. Add text overlay (product name, price)
        5. Merge audio track
        6. Export as MP4
        """
    
    def apply_smart_resize(self, image_path):
        """Create blurred background for non-vertical images"""
    
    def add_ken_burns_effect(self, input_video, zoom_start=1.0, zoom_end=1.1):
        """FFmpeg zoompan filter"""
    
    def add_text_overlay(self, video_path, text, position='bottom'):
        """FFmpeg drawtext filter with custom fonts"""
    
    def add_watermark(self, video_path, logo_path, position='top-right'):
        """Brand logo overlay"""
    
    def merge_audio(self, video_path, audio_path):
        """Merge background music using FFmpeg"""
```

### 2. platforms/tiktok_api.py

```python
class TikTokAPI:
    def __init__(self):
        self.base_url = "https://open.tiktokapis.com/v2"
        self.client_key = get_setting('tiktok_client_key')
        self.client_secret = get_setting('tiktok_client_secret')
    
    def authenticate(self):
        """Launch OAuth flow in browser, capture callback"""
    
    def refresh_access_token(self):
        """Auto-refresh if token expires within 1 hour"""
    
    def upload_video(self, video_path, caption, hashtags, as_draft=False):
        """
        Upload video to TikTok
        as_draft: If True, save as draft instead of publishing
        Returns: post_url or error
        """
    
    def get_analytics(self, post_id):
        """Fetch views, likes, shares, comments"""
```

### 3. platforms/instagram_api.py

```python
class InstagramAPI:
    def __init__(self):
        self.graph_url = "https://graph.facebook.com/v18.0"
        self.ig_user_id = get_setting('instagram_user_id')
        self.access_token = get_decrypted_token('instagram')
    
    def upload_reel(self, video_path, caption, hashtags):
        """
        Step 1: Start local Flask server to serve video
        Step 2: Get public URL via NGROK
        Step 3: POST to /media with video_url
        Step 4: Wait for processing
        Step 5: POST to /media_publish
        Step 6: Shutdown temp server
        """
    
    def create_container(self, video_url, caption):
        """Step 1: Create media container"""
    
    def publish_container(self, creation_id):
        """Step 2: Publish the container"""
    
    def get_insights(self, media_id):
        """Fetch reel performance metrics"""
```

### 4. platforms/facebook_api.py

```python
class FacebookAPI:
    def upload_reel(self, video_path, caption):
        """Direct multipart upload to page"""
    
    def get_video_stats(self, video_id):
        """Fetch views and engagement"""
```

### 5. modules/oauth_handler.py

```python
class OAuthHandler:
    def __init__(self, platform):
        """platform: 'tiktok', 'instagram', 'facebook'"""
    
    def start_oauth_flow(self):
        """
        1. Start local HTTP server on http://localhost:8080/callback
        2. Open browser to authorization URL
        3. Capture auth code from redirect
        4. Exchange for tokens
        5. Store encrypted tokens in database
        """
    
    def refresh_token_if_needed(self, platform):
        """Check expiry and auto-refresh"""
```

### 6. modules/caption_generator.py

```python
class CaptionGenerator:
    def generate(self, product, platform, theme='default'):
        """
        Load template from templates/captions.json
        Replace placeholders: {product_name}, {price}, {emoji}
        Add platform-specific hashtags from hashtags.json
        
        Themes:
        - new_arrival
        - product_spotlight
        - limited_offer
        - behind_the_scenes
        """
    
    def get_hashtags(self, platform, category):
        """
        Platform-specific hashtag strategy:
        - TikTok: 25-30 hashtags (include trending)
        - Instagram: 20-30 hashtags
        - Facebook: 10-15 hashtags
        """
```

### 7. modules/analytics.py

```python
class AnalyticsDashboard:
    def sync_metrics(self):
        """Fetch latest metrics from all platforms"""
    
    def get_top_performing_products(self, limit=10):
        """Products with highest engagement"""
    
    def get_optimal_post_times(self):
        """Analyze when posts perform best"""
    
    def generate_weekly_report(self):
        """Summary PDF/HTML report"""
```

### 8. modules/video_host_server.py

```python
class TemporaryVideoHost:
    """
    Flask server to serve videos for Instagram API
    Starts on-demand, exposes via NGROK, shuts down after upload
    """
    def start_server(self, video_path):
        """Returns public URL to video"""
    
    def stop_server(self):
        """Clean shutdown"""
```

---

## SECURITY IMPLEMENTATION

### Encryption
```python
from cryptography.fernet import Fernet
import os

# Generate key once, store in environment variable
def get_encryption_key():
    key = os.getenv('ENCRYPTION_KEY')
    if not key:
        key = Fernet.generate_key()
        print(f"Add to .env: ENCRYPTION_KEY={key.decode()}")
    return key

def encrypt_token(token):
    f = Fernet(get_encryption_key())
    return f.encrypt(token.encode()).decode()

def decrypt_token(encrypted_token):
    f = Fernet(get_encryption_key())
    return f.decrypt(encrypted_token.encode()).decode()
```

### .env.example
```
# Copy this to .env and fill in your values
ENCRYPTION_KEY=your_generated_key_here

# Database
MYSQL_HOST=localhost
MYSQL_USER=root
MYSQL_PASSWORD=your_password
MYSQL_DATABASE=aleppogift

# TikTok
TIKTOK_CLIENT_KEY=your_client_key
TIKTOK_CLIENT_SECRET=your_client_secret

# Instagram
INSTAGRAM_USER_ID=your_ig_user_id
INSTAGRAM_APP_ID=your_app_id
INSTAGRAM_APP_SECRET=your_app_secret

# Facebook
FACEBOOK_PAGE_ID=your_page_id
FACEBOOK_APP_ID=your_app_id
FACEBOOK_APP_SECRET=your_app_secret

# NGROK (for Instagram)
NGROK_AUTH_TOKEN=your_ngrok_token
```

### .gitignore
```
# Environment
.env
*.env

# Database
*.db
*.sqlite

# Media files
assets/images/*
!assets/images/.gitkeep
output/videos/*
!output/videos/.gitkeep

# Logs
output/logs/*

# Python
__pycache__/
*.pyc
.venv/
venv/
```

---

## STREAMLIT DASHBOARD (app.py)

### Tab Structure

#### 1. Dashboard Tab
-   Active posts count
-   Pending posts queue
-   Today's scheduled posts
-   Quick action buttons (Generate, Schedule, Sync)

#### 2. Products Tab
-   **Sync Products** button (from MySQL database)
-   Grid view of products with images
-   Filters: Category, Price Range, Posted Status
-   Blacklist toggle for each product

#### 3. Video Generator Tab
-   Select product from dropdown
-   Quality preset selector (Low/Medium/High)
-   Caption template selector
-   Audio track selector (random or specific)
-   **Generate Preview** button
-   Video player for preview
-   Batch mode: Generate 7 videos at once

#### 4. Scheduler Tab
-   Calendar view (weekly grid)
-   Drag-and-drop video assignments
-   Platform selectors (TikTok, Instagram, Facebook)
-   Optimal time suggestions based on analytics
-   **Safe Mode** toggle (draft vs public)

#### 5. Analytics Tab
-   Total metrics (views, likes, engagement rate)
-   Line chart: Engagement over time
-   Bar chart: Top 10 performing products
-   Heatmap: Best posting times by day/hour
-   Export report button

#### 6. Settings Tab
-   **Connection Test** buttons for each platform
-   OAuth authorization buttons
-   Token status indicators (✅ Valid / ⚠️ Expiring / ❌ Invalid)
-   Database connection settings
-   Quality presets configuration
-   Caption templates editor

---

## SCHEDULER DAEMON (scheduler_daemon.py)

Runs as separate background process:

```python
import schedule
import time
from modules.platforms.tiktok_api import TikTokAPI
from modules.platforms.instagram_api import InstagramAPI
from modules.platforms.facebook_api import FacebookAPI

def process_queue():
    """Check database for posts scheduled within next 5 minutes"""
    pending = get_pending_posts()
    for post in pending:
        try:
            if post.platform == 'tiktok':
                TikTokAPI().upload_video(post.video_path, post.caption, post.hashtags)
            elif post.platform == 'instagram':
                InstagramAPI().upload_reel(post.video_path, post.caption, post.hashtags)
            elif post.platform == 'facebook':
                FacebookAPI().upload_reel(post.video_path, post.caption)
            
            update_post_status(post.id, 'posted')
        except Exception as e:
            log_error(post.id, str(e))
            update_post_status(post.id, 'failed')

def sync_analytics():
    """Fetch metrics for all posted content"""
    from modules.analytics import AnalyticsDashboard
    AnalyticsDashboard().sync_metrics()

# Run every 5 minutes
schedule.every(5).minutes.do(process_queue)

# Sync analytics daily at 3 AM
schedule.every().day.at("03:00").do(sync_analytics)

while True:
    schedule.run_pending()
    time.sleep(60)
```

---

## REQUIREMENTS.TXT

```txt
# Core Framework
streamlit>=1.30.0
python-dotenv>=1.0.0

# Database
mysql-connector-python>=8.2.0

# Media Processing (CRITICAL: FFmpeg must be installed on system)
Pillow>=10.0.0
# moviepy>=1.0.3  # Optional, not primary

# Web Scraping (optional, if not using direct DB)
beautifulsoup4>=4.12.0
requests>=2.31.0

# Scheduling
schedule>=1.2.0
APScheduler>=3.10.4

# Security
cryptography>=41.0.0

# API SDKs
facebook-sdk>=3.1.0
# Note: TikTok has no official Python SDK, use requests

# Instagram Temp Hosting
flask>=3.0.0
pyngrok>=6.0.0

# Utilities
pandas>=2.1.0  # For analytics
plotly>=5.18.0  # For dashboard charts
```

---

## USER WORKFLOW (COMPLETE)

### Initial Setup
1.  **Install Dependencies:**
    ```bash
    pip install -r requirements.txt
    ```

2.  **Install FFmpeg:**
    -   Windows: Download from https://ffmpeg.org, add to PATH
    -   Verify: `ffmpeg -version`

3.  **Initialize Database:**
    ```bash
    python init_database.py
    ```

4.  **Configure Environment:**
    -   Copy `.env.example` to `.env`
    -   Fill in database credentials and API keys

5.  **Get API Credentials:**
    -   **TikTok:** Apply at https://developers.tiktok.com
    -   **Instagram:** Create app at https://developers.facebook.com
    -   **Facebook:** Same as Instagram (Meta Developer Portal)

### Daily Operation
1.  **Start UI:**
    ```bash
    streamlit run app.py
    ```

2.  **Start Scheduler (separate terminal):**
    ```bash
    python scheduler_daemon.py
    ```

3.  **First Time: Authorize Platforms**
    -   Go to Settings tab
    -   Click "Connect TikTok" → Browser opens → Authorize
    -   Repeat for Instagram and Facebook

4.  **Sync Product Data:**
    -   Go to Products tab
    -   Click "Sync from Database"
    -   Review and blacklist any unwanted products

5.  **Generate Videos:**
    -   Go to Video Generator tab
    -   Select product
    -   Choose quality preset (Medium recommended)
    -   Click "Generate Preview"
    -   Review video

6.  **Schedule Posts:**
    -   Go to Scheduler tab
    -   Drag video to calendar slot
    -   Select platforms (can post to multiple)
    -   Enable "Safe Mode" for first posts (draft upload)
    -   Click "Schedule"

7.  **Monitor:**
    -   Check Dashboard tab for active posts
    -   Review Analytics tab after 24 hours

### Batch Processing (Weekly Prep)
1.  Go to Video Generator tab
2.  Enable "Batch Mode"
3.  Select 7 products (one per day)
4.  Click "Generate All"
5.  Go to Scheduler
6.  Assign to calendar (Mon-Sun)
7.  Set optimal times based on analytics suggestions

---

## CONTENT STRATEGY TEMPLATES

### templates/captions.json
```json
{
  "new_arrival": {
    "templates": [
      "New arrival! 🎉 {product_name} - Perfect for your home ✨ Only ${price}",
      "Just in! {product_name} 🌟 Limited stock available! Shop now 🛒"
    ],
    "hashtags_common": ["#NewArrival", "#JustIn", "#ShopNow"]
  },
  "product_spotlight": {
    "templates": [
      "Spotlight on {product_name}! 💎 Handcrafted perfection at ${price}",
      "{product_name} - Our customer favorite! ⭐ Get yours today!"
    ],
    "hashtags_common": ["#ProductSpotlight", "#CustomerFavorite"]
  },
  "limited_offer": {
    "templates": [
      "Limited time! 🔥 {product_name} now only ${price}",
      "Don't miss out! {product_name} - Special price ${price} ⏰"
    ],
    "hashtags_common": ["#Sale", "#LimitedOffer", "#DealOfTheDay"]
  }
}
```

### templates/hashtags.json
```json
{
  "tiktok": {
    "brand": ["#AleppoGift", "#AleppoCrafts", "#HandmadeGifts"],
    "home_decor": ["#HomeDecor", "#InteriorDesign", "#HomeInspo", "#DecorIdeas"],
    "trending": ["#FYP", "#ForYou", "#Viral", "#TikTokShop"]
  },
  "instagram": {
    "brand": ["#AleppoGift", "#AleppoCrafts"],
    "home_decor": ["#HomeDecor", "#HomeStyle", "#InteriorDesign", "#DecorInspiration"],
    "engagement": ["#InstaGood", "#InstaDaily", "#ShopSmall"]
  },
  "facebook": {
    "brand": ["#AleppoGift"],
    "home_decor": ["#HomeDecor", "#GiftIdeas", "#Handmade"],
    "location": ["#UAEBusiness", "#DubaiShopping"]
  }
}
```

---

## CONTENT CALENDAR (Default Schedule)

| Day | Theme | Optimal Time | Platforms |
|-----|-------|--------------|-----------|
| Monday | New Arrivals | 6:00 PM | TikTok, Instagram |
| Tuesday | Product Spotlight | 7:00 PM | TikTok, Facebook |
| Wednesday | Behind The Scenes | 6:30 PM | Instagram, Facebook |
| Thursday | Customer Favorites | 7:00 PM | TikTok, Instagram |
| Friday | Weekend Deals | 5:00 PM | All platforms |
| Saturday | Gift Ideas | 2:00 PM | Instagram, Facebook |
| Sunday | Traditional Crafts Story | 7:00 PM | TikTok, Instagram |

*Times based on UAE timezone, adjust based on analytics*

---

## TROUBLESHOOTING GUIDE

### Issue: FFmpeg not found
**Solution:**
```bash
# Windows
# Download from https://ffmpeg.org/download.html
# Add bin folder to System PATH
# Restart terminal

# Verify
ffmpeg -version
```

### Issue: OAuth callback not working
**Solution:**
-   Ensure `http://localhost:8080/callback` is added to app's redirect URIs
-   Check firewall isn't blocking port 8080
-   Use `http://` not `https://` for localhost

### Issue: Instagram upload fails "Invalid video URL"
**Solution:**
-   Video must be at publicly accessible HTTPS URL
-   Ensure NGROK is running: `ngrok http 5000`
-   Check video format: Must be H.264 codec, AAC audio
-   Test URL in browser before submitting

### Issue: Video encoding too slow
**Solution:**
-   Lower quality preset (use 'low' or 'medium')
-   Reduce video duration to 15 seconds
-   Use batch processing overnight
-   Upgrade to SSD if using HDD

### Issue: Token expired errors
**Solution:**
-   Check Settings tab → Token status
-   Click "Refresh Token" button
-   Re-authorize if refresh fails
-   Ensure `scheduler_daemon.py` is running (auto-refreshes)

### Issue: Database connection failed
**Solution:**
```python
# Test MySQL connection
import mysql.connector
conn = mysql.connector.connect(
    host='localhost',
    user='root',
    password='your_password',
    database='aleppogift'
)
print(conn.is_connected())
```

---

## VIDEO QUALITY PRESETS

### Low (Fast, Small Files)
-   Resolution: 720x1280
-   Bitrate: 2 Mbps
-   File Size: ~5 MB for 15s
-   Use Case: Daily batch posts, testing

### Medium (Balanced) ⭐ RECOMMENDED
-   Resolution: 1080x1920
-   Bitrate: 4 Mbps
-   File Size: ~10 MB for 15s
-   Use Case: Regular posting

### High (Best Quality)
-   Resolution: 1080x1920
-   Bitrate: 8 Mbps
-   File Size: ~20 MB for 15s
-   Use Case: Featured products, important campaigns

---

## ADVANCED FEATURES

### Multi-Image Carousel (Future Enhancement)
Generate videos showing 3 products in sequence:
```python
def create_carousel_video(self, image_paths, transition='fade'):
    """
    Combine 3 products with transitions
    5 seconds per product = 15 second total
    """
```

### AI Caption Generation (Optional)
If OpenAI API key provided:
```python
def generate_ai_caption(self, product, platform):
    """Use GPT-4 to create engaging captions"""
    prompt = f"Write a {platform} caption for {product.name}..."
```

### Trend Detection (Future)
Monitor trending hashtags and auto-include:
```python
def get_trending_hashtags(self, platform):
    """Fetch current trending hashtags"""
```

---

## EXPECTED DELIVERABLES

1.  ✅ **Complete Python source code** structured as outlined
2.  ✅ **requirements.txt** with exact versions
3.  ✅ **init_database.py** script to create SQLite tables
4.  ✅ **README.md** with:
    *   Installation instructions
    *   API credential setup guides (TikTok, Instagram, Facebook)
    *   Troubleshooting section
5.  ✅ **.env.example** template
6.  ✅ **Sample templates** (captions.json, hashtags.json)
7.  ✅ **Sample audio files** (3 royalty-free MP3s)
8.  ✅ **Documentation** for each module

---

## TESTING CHECKLIST

Before first production use:

- [ ] FFmpeg installed and working
- [ ] MySQL connection successful
- [ ] TikTok OAuth flow completed
- [ ] Instagram OAuth completed (Business account confirmed)
- [ ] Facebook OAuth completed (Page access granted)
- [ ] Test video generation (3 quality presets)
- [ ] Test TikTok upload (draft mode)
- [ ] Test Instagram upload (verify NGROK works)
- [ ] Test Facebook upload
- [ ] Scheduler daemon running
- [ ] Analytics sync working
- [ ] Token auto-refresh working
- [ ] Error logging configured

---

## SUCCESS METRICS (90-Day Goals)

- ✅ **Automation:** 80% of posts fully automated
- 📈 **Growth:** 500+ new followers across platforms
- 💬 **Engagement:** 5%+ average engagement rate
- ⏱️ **Time Saved:** 20+ hours per month
- 📊 **Data:** Complete analytics for optimization
- 🎯 **Consistency:** 1 post per day on each platform

---

## NOTES & BEST PRACTICES

1.  **Always test in draft mode first** (Safe Mode enabled)
2.  **Monitor analytics weekly** to optimize posting times
3.  **Rotate product categories** to avoid repetitive content
4.  **Keep 2-3 weeks of videos in backup** before deleting
5.  **Update hashtags monthly** based on trends
6.  **Respond to comments** (automation can't replace engagement)
7.  **Comply with platform policies** (no misleading content)
8.  **Respect copyright** for music (use provided royalty-free tracks)
9.  **Regular token checks** (schedule daemon handles this)
10. **Backup database weekly** (`social_bot.db`)

---

## API DOCUMENTATION LINKS

- **TikTok:** https://developers.tiktok.com/doc/content-posting-api-get-started
- **Instagram:** https://developers.facebook.com/docs/instagram-api/guides/content-publishing
- **Facebook:** https://developers.facebook.com/docs/video-api/guides/reels-publishing
- **NGROK:** https://ngrok.com/docs

---

**🚀 This improved prompt addresses all critical issues and provides a production-ready roadmap for implementation. Ready to build!**

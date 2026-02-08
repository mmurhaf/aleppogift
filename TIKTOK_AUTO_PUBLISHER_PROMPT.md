# TikTok Auto-Publisher Prompt

## Project Overview

Create a comprehensive PHP application for automated TikTok content publishing with the following specifications:

**PROJECT NAME:** AleppoGift TikTok Auto-Publisher

---

## CORE FUNCTIONALITY

1. Scan assets/images directory from https://aleppogift.com for product images
2. Convert static images into engaging short-form video content (9:16 ratio)
3. Add dynamic effects: zoom, pan, transitions, text overlays
4. Overlay trending TikTok audio/music
5. Automatically publish to @aleppogift TikTok account daily
6. Schedule posts at optimal times for engagement
7. "Safe Mode" option to upload as draft for manual review before publishing

---

## TECHNICAL REQUIREMENTS

- PHP 8.x application
- TikTok Content Posting API integration
- FFmpeg for video creation from images
- MySQL database to track posted content and analytics
- Admin dashboard for:
  * Viewing scheduled posts
  * Manual post approval/editing
  * Analytics dashboard (views, likes, shares)
  * Content calendar management
  * Caption and hashtag customization

---

## VIDEO CREATION FEATURES

- Convert product images to 15-30 second videos
- Add Ken Burns effect (zoom/pan) to images
- Include brand watermark/logo
- Add text overlays with product names and prices
- Transition effects between multiple product images
- **Smart Resizing:** If image is not 9:16, center it with a blurred background version filling the frame
- **Audio Handling:** Merge random track from local `/assets/tiktok-audio/` using FFmpeg (avoid API copyright issues)

---

## CONTENT STRATEGY

- Pull product data from database (name, price, description, category)
- Auto-generate captions with relevant hashtags
- Rotate through different product categories
- Create themed content (e.g., "Product of the Day", "New Arrivals")
- A/B test different caption styles

---

## TIKTOK API INTEGRATION

- OAuth 2.0 authentication
- Video upload endpoint integration
- Caption and hashtag management
- Analytics data retrieval
- **Token Self-Healing:** Auto-use Refresh Token if Access Token is expired during upload
- Compliance with TikTok API rate limits

---

## SCHEDULING SYSTEM

- Cron job for daily automated posting
- Best time optimization based on analytics
- Queue system for multiple posts per day
- Retry mechanism for failed uploads

---

## ADMIN FEATURES

- Web-based control panel at `/admin/tiktok-publisher/`
- Preview videos before publishing
- Edit captions and hashtags
- Blacklist certain images from auto-posting
- Performance metrics and reports
- Content approval workflow

---

## DATABASE SCHEMA

### Tables Required:

```sql
-- tiktok_posts table
CREATE TABLE tiktok_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_path VARCHAR(255),
    video_path VARCHAR(255),
    caption TEXT,
    hashtags VARCHAR(500),
    status ENUM('pending', 'scheduled', 'posted', 'failed'),
    scheduled_time DATETIME,
    posted_time DATETIME,
    product_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- tiktok_settings table
CREATE TABLE tiktok_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- tiktok_analytics table
CREATE TABLE tiktok_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT,
    views INT DEFAULT 0,
    likes INT DEFAULT 0,
    shares INT DEFAULT 0,
    comments INT DEFAULT 0,
    engagement_rate DECIMAL(5,2),
    date DATE,
    FOREIGN KEY (post_id) REFERENCES tiktok_posts(id)
);
```

---

## SECURITY

- Secure API credential storage (encrypted)
- Rate limiting
- Error logging
- Backup system for generated videos
- Input validation and sanitization
- CSRF protection
- SQL injection prevention

---

## FILE STRUCTURE

```
/public/admin/tiktok-publisher/
  - index.php (dashboard)
  - schedule.php (content calendar)
  - settings.php (API config)
  - analytics.php (performance metrics)
  - preview.php (video preview)
  - queue.php (post queue management)

/includes/tiktok/
  - TikTokAPI.php (API wrapper class)
  - VideoGenerator.php (FFmpeg video creation)
  - ContentManager.php (image selection logic)
  - Scheduler.php (cron job handler)
  - AnalyticsCollector.php (fetch TikTok analytics)
  - CaptionGenerator.php (auto-generate captions)

/assets/tiktok-videos/ (generated video storage)
/assets/tiktok-audio/ (background music library)
/assets/tiktok-templates/ (video templates)

/cron/
  - tiktok_post_scheduler.php (daily cron job)
  - tiktok_analytics_sync.php (analytics sync)
```

---

## INITIAL SETUP WIZARD

Step-by-step setup process:
1. TikTok Business Account connection
2. API credentials configuration
3. Brand customization (logo, colors, fonts)
4. Posting schedule setup
5. Test post functionality
6. Music/audio library setup

---

## KEY CLASSES & METHODS

### TikTokAPI.php
```php
- authenticate() // OAuth 2.0 authentication
- uploadVideo($videoPath, $caption, $hashtags)
- getAnalytics($postId)
- checkRateLimit()
- refreshAccessToken()
```

### VideoGenerator.php
```php
- createVideoFromImage($imagePath, $duration)
- addKenBurnsEffect($image)
- addTextOverlay($text, $position)
- addWatermark($logoPath)
- addBackgroundMusic($audioPath)
- addTransitions($images[])
- exportVideo($outputPath)
```

### ContentManager.php
```php
- scanProductImages()
- selectDailyContent($category)
- getProductDetails($imageId)
- generateHashtags($product)
- checkAlreadyPosted($imagePath)
```

### Scheduler.php
```php
- schedulePost($postData, $datetime)
- getNextPostTime()
- processQueue()
- retryFailedPosts()
```

---

## VIDEO SPECIFICATIONS

- **Aspect Ratio:** 9:16 (1080x1920px)
- **Format:** MP4
- **Duration:** 15-30 seconds
- **FPS:** 30
- **Audio:** 128kbps AAC
- **Max File Size:** 287.6 MB (TikTok limit)

---

## HASHTAG STRATEGY

Auto-generate relevant hashtags:
- Brand hashtags: #AleppoGift #AleppoCrafts
- Product hashtags: #HomeDecor #KitchenWare #TraditionalCrafts
- Trending hashtags: Dynamic based on TikTok trends
- Location hashtags: #UAE #Dubai #MiddleEast
- Call-to-action: #ShopNow #LimitedEdition

---

## CONTENT THEMES

Rotate through different themes:
- **Monday:** New Arrivals
- **Tuesday:** Product Spotlight
- **Wednesday:** Behind The Scenes
- **Thursday:** Customer Favorites
- **Friday:** Weekend Deals
- **Saturday:** Gift Ideas
- **Sunday:** Traditional Crafts Story

---

## ANALYTICS & REPORTING

Track and display:
- Total views, likes, shares, comments
- Engagement rate trends
- Best performing content
- Optimal posting times
- Hashtag performance
- Follower growth
- Video completion rate

---

## ERROR HANDLING

- Failed upload retry mechanism (max 3 attempts)
- API error logging
- Video generation error handling
- Email notifications for critical failures
- Fallback content system
- Queue backup system

---

## PREREQUISITES

1. **TikTok Developer Account:** Apply at https://developers.tiktok.com
2. **API Credentials:** Client Key and Client Secret
3. **FFmpeg:** Install on server
4. **PHP Extensions:** GD or ImageMagick, cURL
5. **Server Requirements:** Cron job support
6. **Storage:** Minimum 5GB for video storage

---

## DEPLOYMENT STEPS

1. Install FFmpeg on server
2. Create database tables
3. Upload application files
4. Configure TikTok API credentials
5. Set up cron jobs
6. Upload brand assets (logo, fonts)
7. Configure posting schedule
8. Test with sample content
9. Monitor first week of automated posts

---

## FUTURE ENHANCEMENTS

- Instagram Reels cross-posting
- Facebook Reels integration
- AI-powered caption generation
- Trend detection system
- Competitor analysis
- Multi-language support
- Voice-over generation
- Interactive effects

---

## NOTES

- Ensure compliance with TikTok's Terms of Service
- Respect copyright for music and images
- Monitor engagement and adjust strategy
- Regular content quality reviews
- Maintain brand consistency
- Stay updated with TikTok algorithm changes

---

## EXPECTED DELIVERABLES

1. Complete PHP application with all files
2. Database migration scripts
3. Installation guide
4. User manual for admin panel
5. API integration documentation
6. Cron job setup instructions
7. Troubleshooting guide
8. Sample video templates

---

## SUCCESS METRICS

- Daily automated posts without manual intervention
- Increased follower growth (target: 10% monthly)
- Improved engagement rate (target: 5%+)
- Video completion rate above 50%
- Consistent brand presence
- Reduced manual content creation time

---

**Please create all necessary files with complete, production-ready code including error handling, logging, and comprehensive documentation.**

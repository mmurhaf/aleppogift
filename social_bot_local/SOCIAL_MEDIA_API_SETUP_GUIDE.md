# Social Media API Setup Guide

Complete guide to obtaining API keys and access tokens for TikTok, Instagram, and Facebook to use with the Social Bot Creator.

---

## Table of Contents
1. [TikTok API Setup](#tiktok-api-setup)
2. [Instagram API Setup](#instagram-api-setup)
3. [Facebook API Setup](#facebook-api-setup)
4. [Testing Your Credentials](#testing-your-credentials)
5. [Troubleshooting](#troubleshooting)
6. [Security Best Practices](#security-best-practices)

---

## TikTok API Setup

### Prerequisites
- A TikTok Business Account
- A registered TikTok Developer account
- Your application approved by TikTok

### Step 1: Create a TikTok Developer Account
1. Go to [TikTok for Developers](https://developers.tiktok.com/)
2. Click **"Register"** or **"Sign In"** (top right)
3. Log in with your TikTok account credentials
4. Complete the developer profile form:
   - Full Name
   - Email Address
   - Company/Organization Name
   - Phone Number
5. Verify your email address

### Step 2: Create an Application
1. Once logged in, go to [My Apps](https://developers.tiktok.com/apps)
2. Click **"+ Create App"** or **"Connect an app"**
3. Fill in the application details:
   - **App Name**: Your application name (e.g., "Aleppo Gift Social Bot")
   - **App Description**: Brief description of your app's purpose
   - **Category**: Select "Marketing & Commerce" or "Content & Publishing"
   - **Icon**: Upload an app icon (512x512 px recommended)
4. Click **"Create"** or **"Submit"**

### Step 3: Request Content Posting API Access
1. In your app dashboard, navigate to **"Products"** or **"Add Products"**
2. Look for **"Content Posting API"** or **"Video Publishing"**
3. Click **"Apply"** or **"Request Access"**
4. Fill out the use case form:
   - Explain you need to post product videos automatically
   - Describe your business and content strategy
   - Provide examples if requested
5. Submit and wait for approval (can take 1-7 days)

### Step 4: Get Your API Credentials
1. Once approved, go to your app's **"Basic Information"** or **"Settings"**
2. Find and copy these values:
   - **Client Key** (also called App ID)
   - **Client Secret**
3. Save these securely - you'll need them

### Step 5: Set Up OAuth and Get Access Token
1. In your app settings, configure **"Redirect URI"**:
   - Add: `http://localhost:8501/oauth/callback`
   - Or use your production domain if deployed
2. Under **"Scopes"** or **"Permissions"**, enable:
   - `user.info.basic` - Get user profile
   - `video.upload` - Upload videos
   - `video.publish` - Publish videos
3. Save changes

### Step 6: Generate Access Token (First Time)
1. Go to **"Authorization"** or **"Tools"** section
2. Click **"Generate Access Token"**
3. Or use the OAuth flow:
   ```
   https://www.tiktok.com/v2/auth/authorize?
   client_key=YOUR_CLIENT_KEY&
   scope=user.info.basic,video.upload,video.publish&
   response_type=code&
   redirect_uri=YOUR_REDIRECT_URI
   ```
4. Authorize the app when prompted
5. Exchange the authorization code for an access token using the API or developer tools

### Step 7: Configure in Social Bot
1. Open Social Bot Creator application
2. Go to **Settings** tab
3. Enter your TikTok credentials:
   - **TikTok Client Key**: Your Client Key
   - **TikTok Client Secret**: Your Client Secret
   - **TikTok Access Token**: Your generated access token
4. Click **Save**

### Important Notes for TikTok:
- ⚠️ TikTok API access requires business verification and approval
- 🔄 Access tokens expire after 24 hours - implement refresh token mechanism
- 📹 Video requirements: MP4 format, max 287MB, duration 3s-60s for organic posts
- 🔒 TikTok has strict content policies - review before automating

---

## Instagram API Setup

Instagram requires Facebook Developer account and uses Facebook Graph API.

### Prerequisites
- Instagram Business or Creator Account (Professional Account)
- Facebook Page connected to your Instagram account
- Facebook Developer account

### Step 1: Convert to Instagram Business Account
1. Open Instagram app on mobile
2. Go to **Settings** → **Account**
3. Select **Switch to Professional Account**
4. Choose **Business** or **Creator**
5. Connect to your Facebook Page (required)

### Step 2: Create Facebook Developer Account
1. Go to [Facebook for Developers](https://developers.facebook.com/)
2. Click **"Get Started"** (top right)
3. Log in with your Facebook account
4. Complete registration:
   - Accept developer agreement
   - Verify your account (email or phone)

### Step 3: Create a Facebook App
1. Go to [My Apps](https://developers.facebook.com/apps)
2. Click **"Create App"** or **"Create New App"**
3. Select **"Business"** or **"Consumer"** type
4. Fill in app details:
   - **App Name**: "Aleppo Gift Social Bot"
   - **Contact Email**: Your email
   - **Business Account**: Select or create one
5. Click **"Create App"**

### Step 4: Add Instagram Product
1. In your app dashboard, find **"Add Products"**
2. Locate **"Instagram Graph API"** or **"Instagram Basic Display"**
3. Click **"Set Up"**
4. For posting, you need **Instagram Graph API** (not Basic Display)

### Step 5: Configure Instagram API
1. Go to **Instagram Graph API** → **Settings**
2. Add **Instagram Accounts**:
   - Click **"Add or Remove Instagram Accounts"**
   - Log in and authorize
   - Select your Instagram Business Account
3. Add required permissions:
   - `instagram_basic`
   - `instagram_content_publish`
   - `pages_show_list`
   - `pages_read_engagement`

### Step 6: Get Your Credentials
1. Go to **Settings** → **Basic**
2. Copy these values:
   - **App ID**
   - **App Secret** (click "Show")
3. Scroll down to add **Redirect URIs**:
   - Add: `https://localhost:8501/oauth/callback`
4. Save changes

### Step 7: Get Instagram Business Account ID
1. Use [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Select your app from dropdown
3. Add these permissions: `instagram_basic`, `pages_show_list`
4. Click **"Generate Access Token"**
5. In the query field, enter:
   ```
   me/accounts?fields=instagram_business_account
   ```
6. Click **Submit** - copy the `instagram_business_account` ID

### Step 8: Generate Long-Lived Access Token
Short-lived tokens expire in 1 hour. Convert to long-lived (60 days):

1. Get a short-lived User Access Token from Graph API Explorer
2. Use this endpoint:
   ```
   https://graph.facebook.com/v18.0/oauth/access_token?
   grant_type=fb_exchange_token&
   client_id=YOUR_APP_ID&
   client_secret=YOUR_APP_SECRET&
   fb_exchange_token=YOUR_SHORT_LIVED_TOKEN
   ```
3. Copy the returned `access_token`

### Step 9: Configure in Social Bot
1. Open Social Bot Creator → **Settings**
2. Enter Instagram credentials:
   - **Instagram App ID**: Your App ID
   - **Instagram App Secret**: Your App Secret
   - **Instagram Access Token**: Your long-lived access token
   - **Instagram Account ID**: Your business account ID
3. Click **Save**

### Important Notes for Instagram:
- 📱 Must be a Business or Creator account (not personal)
- 🔗 Must be connected to a Facebook Page
- 🎥 Instagram supports photos, videos, carousels, and Reels
- ⏱️ Long-lived tokens expire after 60 days - implement refresh mechanism
- 📏 Image requirements: JPG/PNG, 1080x1080px recommended
- 🎬 Video requirements: MP4, aspect ratio 9:16 for Reels

---

## Facebook API Setup

### Prerequisites
- Facebook Page (not personal profile)
- Facebook Developer account

### Step 1: Create Facebook Page (if needed)
1. Go to [facebook.com/pages/create](https://www.facebook.com/pages/create)
2. Select page type (Business/Brand)
3. Fill in page details
4. Upload profile and cover photos
5. Complete page setup

### Step 2: Create Facebook Developer Account
1. Go to [Facebook for Developers](https://developers.facebook.com/)
2. Click **"Get Started"**
3. Log in with Facebook account
4. Complete registration and verification

### Step 3: Create Facebook App
1. Go to [My Apps](https://developers.facebook.com/apps)
2. Click **"Create App"**
3. Select app type: **"Business"**
4. Fill in details:
   - **App Name**: "Aleppo Gift Social Bot"
   - **Contact Email**: Your email
   - **Business Account**: Select or skip
5. Click **"Create App"**

### Step 4: Add Facebook Login Product
1. In app dashboard, find **"Add Product"**
2. Select **"Facebook Login"**
3. Click **"Set Up"**
4. Choose **"Web"** platform
5. Enter your site URL: `http://localhost:8501`

### Step 5: Configure OAuth Settings
1. Go to **Facebook Login** → **Settings**
2. Add **Valid OAuth Redirect URIs**:
   ```
   http://localhost:8501/oauth/callback
   https://yourdomain.com/oauth/callback
   ```
3. Save changes

### Step 6: Request Page Permissions
1. Go to **App Review** → **Permissions and Features**
2. Request these permissions:
   - `pages_manage_posts` - Publish to pages
   - `pages_read_engagement` - Read page metrics
   - `pages_show_list` - List pages
   - `publish_video` - Upload videos (if needed)
3. For each permission:
   - Click **"Request"**
   - Fill out the use case form
   - Provide screenshots/examples
   - Submit for review

**Note**: Some permissions require app review by Facebook (can take 3-7 days)

### Step 7: Get App Credentials
1. Go to **Settings** → **Basic**
2. Copy these values:
   - **App ID**
   - **App Secret** (click "Show")
3. Keep these secure

### Step 8: Get Page Access Token
1. Use [Graph API Explorer](https://developers.facebook.com/tools/explorer/)
2. Select your app from dropdown
3. Click **"Get Token"** → **"Get User Access Token"**
4. Select permissions:
   - `pages_manage_posts`
   - `pages_read_engagement`
   - `pages_show_list`
5. Click **"Generate Access Token"** and authorize
6. In the query field, enter: `me/accounts`
7. Click **Submit**
8. Find your page in the results and copy its `access_token`

### Step 9: Convert to Long-Lived Page Token
1. First, convert user token to long-lived:
   ```
   https://graph.facebook.com/v18.0/oauth/access_token?
   grant_type=fb_exchange_token&
   client_id=YOUR_APP_ID&
   client_secret=YOUR_APP_SECRET&
   fb_exchange_token=YOUR_SHORT_LIVED_TOKEN
   ```

2. Then get the long-lived page token:
   ```
   https://graph.facebook.com/v18.0/me/accounts?
   access_token=YOUR_LONG_LIVED_USER_TOKEN
   ```
3. Copy the page `access_token` (this never expires unless you change your password)

### Step 10: Get Page ID
In the same API response from Step 9, copy the page `id`

### Step 11: Configure in Social Bot
1. Open Social Bot Creator → **Settings**
2. Enter Facebook credentials:
   - **Facebook App ID**: Your App ID
   - **Facebook App Secret**: Your App Secret
   - **Facebook Page Access Token**: Your long-lived page token
   - **Facebook Page ID**: Your page ID
3. Click **Save**

### Important Notes for Facebook:
- 📄 Must post to a Page, not personal profile
- 🎥 Video requirements: MP4, max 4GB, 1080p recommended
- 📷 Image requirements: JPG/PNG, 2048px width recommended
- 🔒 Page tokens don't expire unless password changes
- ⚠️ Some permissions require app review and business verification

---

## Testing Your Credentials

### Test API Connection
1. In Social Bot Creator, go to **Settings** tab
2. After entering credentials, click **"Test Connection"** for each platform
3. Verify success messages:
   - ✅ "TikTok: Connected successfully"
   - ✅ "Instagram: Connected successfully"
   - ✅ "Facebook: Connected successfully"

### Test Posting (Manual)
1. Create a simple test post:
   - Upload a test image
   - Write caption: "Test post - please ignore"
   - Select one platform
   - Click **"Post Now"**
2. Check your social media account to confirm post appears
3. Delete the test post after verification

### Common Test Failures
- ❌ **"Invalid access token"**: Token expired or incorrect
- ❌ **"Permission denied"**: Missing required permissions
- ❌ **"App not authorized"**: Complete app review process
- ❌ **"Account not found"**: Check Account ID is correct

---

## Troubleshooting

### TikTok Issues

**Problem**: "App not approved for Content Posting API"
- **Solution**: Complete the approval process in TikTok Developer portal. This can take 1-7 business days.

**Problem**: "Access token expired"
- **Solution**: TikTok tokens expire after 24 hours. Implement refresh token mechanism or generate new tokens regularly.

**Problem**: "Invalid video format"
- **Solution**: Ensure video is MP4, H.264 codec, AAC audio, max 287MB, 3-60 seconds duration.

**Problem**: "Rate limit exceeded"
- **Solution**: TikTok has posting limits. Wait before retrying. Typical limit: 5 posts per day for new apps.

### Instagram Issues

**Problem**: "Instagram account not found"
- **Solution**: Ensure your Instagram account is converted to Business/Creator and linked to a Facebook Page.

**Problem**: "Permission not granted"
- **Solution**: Re-authorize and ensure `instagram_content_publish` permission is granted.

**Problem**: "Media type not supported"
- **Solution**: Instagram Reels require 9:16 aspect ratio, MP4 format, H.264 codec.

**Problem**: "Page not authorized for Instagram"
- **Solution**: Verify the Facebook Page is properly connected to your Instagram Business account.

### Facebook Issues

**Problem**: "Page access token invalid"
- **Solution**: Regenerate the page access token using the steps in the setup guide.

**Problem**: "Permissions not approved"
- **Solution**: Some permissions require app review. Go to App Review and request approval.

**Problem**: "App not in production"
- **Solution**: In Facebook App settings, switch the app from Development to Production mode.

**Problem**: "OAuthException"
- **Solution**: Check that your app has all required permissions and the token hasn't been revoked.

### General Issues

**Problem**: "Network error" or "Connection timeout"
- **Solution**: Check your internet connection. Verify firewall isn't blocking API requests.

**Problem**: "SSL certificate error"
- **Solution**: Update your system certificates or disable SSL verification (not recommended for production).

**Problem**: "Invalid redirect URI"
- **Solution**: Ensure redirect URIs in developer console match exactly what your app uses (including http vs https).

---

## Security Best Practices

### Protecting Your Credentials

1. **Never Share Publicly**
   - Don't commit API keys to GitHub or public repositories
   - Don't share in chat, email, or screenshots
   - Don't hardcode in client-side code

2. **Use Environment Variables**
   ```bash
   # .env file (add to .gitignore)
   TIKTOK_CLIENT_KEY=your_key_here
   TIKTOK_CLIENT_SECRET=your_secret_here
   INSTAGRAM_APP_ID=your_id_here
   ```

3. **Rotate Keys Regularly**
   - Change tokens every 60-90 days
   - Rotate immediately if compromised
   - Remove old tokens from developer console

4. **Use Separate Apps for Dev/Production**
   - Create separate apps for testing
   - Use different credentials for each environment
   - Never test with production credentials

5. **Monitor API Usage**
   - Check developer dashboards regularly
   - Set up alerts for unusual activity
   - Review API logs for unauthorized access

6. **Implement Token Refresh**
   - Don't rely on expired tokens
   - Implement automatic refresh mechanism
   - Store refresh tokens securely

### Access Control

1. **Principle of Least Privilege**
   - Only request permissions you actually need
   - Remove unused permissions
   - Review permissions quarterly

2. **Team Access**
   - Limit who has access to API credentials
   - Use role-based access control
   - Log all credential access

3. **Revoke When Necessary**
   - Immediately revoke access if:
     - Employee leaves
     - Device is lost/stolen
     - Suspicious activity detected
     - Keys are accidentally exposed

### Compliance

1. **Follow Platform Policies**
   - Read and understand Terms of Service
   - Comply with content policies
   - Respect rate limits
   - Honor user privacy

2. **Data Protection**
   - Don't store unnecessary user data
   - Encrypt sensitive information
   - Comply with GDPR, CCPA if applicable
   - Have a privacy policy

3. **Content Guidelines**
   - No spam or automated abuse
   - No misleading content
   - Respect copyright and trademarks
   - Follow community standards

---

## Quick Reference

### TikTok
- **Developer Portal**: https://developers.tiktok.com/
- **API Documentation**: https://developers.tiktok.com/doc/
- **Content Posting API**: Requires approval
- **Token Expiry**: 24 hours
- **Support**: https://developers.tiktok.com/community

### Instagram
- **Developer Portal**: https://developers.facebook.com/
- **API Documentation**: https://developers.facebook.com/docs/instagram-api/
- **Graph API Explorer**: https://developers.facebook.com/tools/explorer/
- **Token Expiry**: 60 days (long-lived)
- **Support**: https://developers.facebook.com/support/

### Facebook
- **Developer Portal**: https://developers.facebook.com/
- **API Documentation**: https://developers.facebook.com/docs/graph-api/
- **Graph API Explorer**: https://developers.facebook.com/tools/explorer/
- **Token Expiry**: Never (page tokens)
- **Support**: https://developers.facebook.com/support/

---

## Need More Help?

### Resources
- 📖 Official documentation for each platform (links above)
- 💬 Developer community forums
- 📧 Platform support teams
- 🎥 YouTube tutorials for API setup

### Common Setup Time
- **TikTok**: 2-7 business days (pending approval)
- **Instagram**: 1-2 hours (immediate if no review needed)
- **Facebook**: 1-2 hours to 7 days (depending on permissions)

### Still Having Issues?
1. Double-check all credentials are entered correctly
2. Verify your accounts meet all prerequisites
3. Check platform status pages for outages
4. Review error messages carefully
5. Search developer forums for similar issues
6. Contact platform support with specific error details

---

**Last Updated**: February 2026

**Note**: Social media platforms frequently update their APIs and developer processes. Always refer to official documentation for the most current information.

# Hostinger Deployment Fix - aleppogift.com

## Current Issue
The website shows: "Database connection error: SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)"

This means the `.env` file is not being read on the production server.

## Files Updated

### 1. `.htaccess` - Updated for Hostinger Structure ✅
The root `.htaccess` file has been updated to properly redirect all traffic to the `/public` folder.

**Location on server:** `/domains/aleppogift.com/public_html/.htaccess`

### 2. `.env` - Fixed Database Variable Names ✅
The `.env` file now includes the correct variable names that match what `config.php` expects:
- `DB_NAME` (instead of DB_DATABASE)
- `DB_USER` (instead of DB_USERNAME)
- `DB_PASS` (instead of DB_PASSWORD)

**Location on server:** `/domains/aleppogift.com/public_html/.env`

## Deployment Steps

### Step 1: Upload the Updated .htaccess File
1. Upload the updated `.htaccess` file from your local folder to:
   ```
   /domains/aleppogift.com/public_html/.htaccess
   ```
2. Make sure to **overwrite** the existing file

### Step 2: Upload the Updated .env File
1. **IMPORTANT:** Upload the `.env` file to the **root** of your public_html folder:
   ```
   /domains/aleppogift.com/public_html/.env
   ```
   
2. **Verify the location:** The `.env` file should be in the SAME folder as:
   - `.htaccess`
   - `config/` folder
   - `includes/` folder
   - `public/` folder

3. **Set proper permissions:**
   - Right-click on `.env` in your FTP client
   - Change permissions to `644` (rw-r--r--)
   - This makes it readable by PHP but not publicly accessible

### Step 3: Verify File Permissions
Ensure these files/folders have correct permissions on the server:

```
/.env                   → 644
/.htaccess              → 644
/config/config.php      → 644
/includes/              → 755
/public/                → 755
/logs/                  → 755 (writable)
/uploads/               → 755 (writable)
```

### Step 4: Test the Website
1. Clear your browser cache (Ctrl+Shift+Del)
2. Visit: https://aleppogift.com/
3. The website should now load properly

### Step 5: Verify Database Connection
If you still see database errors, verify these settings in `.env`:

```env
DB_HOST=srv948.hstgr.io
DB_NAME=u933234997_aleppogift
DB_USER=u933234997_mmurhaf
DB_PASS=Salem1972#i
```

You can verify these credentials in Hostinger:
1. Login to Hostinger hPanel
2. Go to **Databases** → **MySQL Databases**
3. Confirm the database name, username, and host match your `.env` file

## Troubleshooting

### Issue: Still seeing "Access denied for user 'root'@'localhost'"
**Solution:** The `.env` file is not being loaded. Check:
1. Is `.env` in the correct location? (root of public_html, not inside public/)
2. Does the file have correct permissions? (644)
3. Does the `.env` file have correct line endings? (Unix LF, not Windows CRLF)

### Issue: Website shows blank page or 500 error
**Solution:** 
1. Check the error logs in Hostinger hPanel
2. Verify all file permissions are correct
3. Make sure `includes/bootstrap.php` exists and is readable

### Issue: CSS/Images not loading
**Solution:** The `.htaccess` rewrite rules are working correctly. Check:
1. Are the files in `/public/assets/` folder?
2. Clear browser cache
3. Check file permissions on assets folder (755)

### Issue: .env file not found
**Solution:** Some FTP clients hide files starting with `.` (dot)
1. In FileZilla: Server → Force showing hidden files
2. In other FTP clients: Enable "Show hidden files" option
3. Alternative: Upload the file via Hostinger File Manager

## Important Security Notes

1. **Never commit `.env` to Git** - It contains sensitive credentials
2. **Keep `.htaccess` protection** - The current .htaccess blocks direct access to .env files
3. **Use strong passwords** - Change default passwords in production
4. **Enable HTTPS** - Make sure SSL is active in Hostinger (should be automatic)

## Current .env Configuration

Your `.env` file now contains:
- ✅ Hostinger database credentials (srv948.hstgr.io)
- ✅ Correct variable names (DB_NAME, DB_USER, DB_PASS)
- ✅ Production site URL (https://aleppogift.com/)
- ✅ Email SMTP settings
- ✅ Ziina payment API key
- ✅ Security encryption keys

## Quick Verification Checklist

After deployment, verify:
- [ ] `.env` file uploaded to `/domains/aleppogift.com/public_html/.env`
- [ ] `.htaccess` file uploaded to `/domains/aleppogift.com/public_html/.htaccess`
- [ ] File permissions set correctly (.env = 644, folders = 755)
- [ ] Website loads at https://aleppogift.com/
- [ ] No database connection errors
- [ ] CSS and images load correctly
- [ ] Can browse products
- [ ] Can add items to cart

## Next Steps After Successful Deployment

1. Test all major functionality:
   - Product browsing
   - Search functionality
   - Add to cart
   - Checkout process
   - Email notifications

2. Monitor error logs for any issues:
   - Hostinger hPanel → Error Logs
   - Check `/logs/error.log` on the server

3. Set up regular backups:
   - Database backups (weekly)
   - File backups (weekly)

## Support

If you continue to experience issues after following these steps:
1. Check Hostinger error logs
2. Verify database credentials in Hostinger hPanel
3. Contact Hostinger support for server-specific issues
4. Check that PHP version is 7.4+ (recommended: 8.1)

---
**Last Updated:** November 5, 2025
**Server:** Hostinger (srv948.hstgr.io)
**Domain:** aleppogift.com

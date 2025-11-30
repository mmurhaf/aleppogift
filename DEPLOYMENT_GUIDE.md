# AleppoGift Deployment Scripts

Complete FTP deployment solution for deploying your local development files to the production server.

## 📁 Files Overview

- **`deploy.ps1`** - Main PowerShell deployment script with FTP upload
- **`deploy.bat`** - Interactive menu wrapper for easy deployment
- **`backup.ps1`** - Production backup script (download files from server)
- **`deploy-config.json`** - Configuration for file exclusions and options
- **`config/ftp_credentials.php`** - FTP credentials and path configuration (git-ignored)

## 🚀 Quick Start

### Option 1: Double-Click Deployment (Easiest)

1. Double-click **`deploy.bat`**
2. Choose from the menu:
   - `[1]` Deploy to Production (with backup prompt)
   - `[2]` Deploy to Production (skip backup)
   - `[3]` Dry Run (preview files)
   - `[4]` Force Deploy All Files
   - `[5]` Backup Production Files

### Option 2: PowerShell Command Line

```powershell
# Preview what will be deployed (recommended first step)
.\deploy.ps1 -DryRun

# Deploy with backup prompt
.\deploy.ps1

# Deploy without backup prompt
.\deploy.ps1 -SkipBackup

# Force upload all files
.\deploy.ps1 -Force

# Backup production files first
.\backup.ps1
```

## 📋 Deployment Workflow

### Recommended Steps

1. **Test Locally First**
   ```powershell
   # Visit http://localhost/aleppogift/ and verify everything works
   ```

2. **Preview Deployment**
   ```powershell
   .\deploy.ps1 -DryRun
   ```
   This shows exactly which files will be uploaded without actually uploading them.

3. **Backup Production** (Optional but Recommended)
   ```powershell
   .\backup.ps1
   ```
   Downloads critical production files to `backups\YYYY-MM-DD_HHMMSS\`

4. **Deploy**
   ```powershell
   .\deploy.ps1
   ```
   Uploads your local files to production server.

5. **Verify Production**
   Visit https://aleppogift.com/index.php and test functionality.

## ⚙️ Configuration

### File Exclusions

Edit **`deploy-config.json`** to customize which files are excluded from deployment:

```json
{
  "exclude": [
    "*.git*",
    "test_*.php",
    "debug_*.php",
    "config/ftp_credentials.php",
    "*.md",
    "*.log"
  ]
}
```

**Default Exclusions:**
- Git files (`.git*`, `.gitignore`)
- Test and debug files (`test_*.php`, `debug_*.php`)
- Credentials (`config/ftp_credentials.php`)
- Documentation (`*.md`, `*.txt`)
- Development tools (`node_modules`, `vendor`, `.vscode`, `.idea`)
- Logs and cache (`*.log`, `cache`, `temp`)
- Backups (`*.backup`, `*.bak`, `*.old`)
- Upload directories content (`public/uploads/*`, `public/quotations/*`, `public/invoice/*`)

### FTP Credentials

Credentials are stored in **`config/ftp_credentials.php`**:

```php
return [
    'ftp' => [
        'host'     => '89.117.188.183',
        'user'     => 'u933234997',
        'password' => 'Salem1972#h',
    ],
    'paths' => [
        'local_root'        => 'C:\\xampp\\htdocs\\aleppogift',
        'production_root'   => '/domains/aleppogift.com/public_html',
    ],
    'urls' => [
        'local'      => 'http://localhost/aleppogift/',
        'production' => 'https://aleppogift.com/index.php',
    ]
];
```

> **Security Note:** This file is excluded from git via `.gitignore`. Never commit credentials to version control.

## 📦 Backup Script

### Basic Backup

```powershell
# Backup critical files (config, uploads, invoices)
.\backup.ps1
```

Backs up to: `backups\YYYY-MM-DD_HHMMSS\`

**Critical files backed up:**
- `config/config.php`
- `public/.htaccess`
- `.htaccess`
- `public/uploads/`
- `public/quotations/`
- `public/invoice/`

### Full Backup

```powershell
# Backup ALL production files
.\backup.ps1 -FullBackup
```

### Custom Backup Location

```powershell
# Backup to specific directory
.\backup.ps1 -BackupPath "C:\Backups\aleppogift"
```

## 🔧 Advanced Usage

### Deploy Specific Files Only

Temporarily modify `deploy-config.json` to use `include_only`:

```json
{
  "include_only": [
    "public/css/*.css",
    "public/js/*.js",
    "config/config.php"
  ]
}
```

### Deployment Script Parameters

```powershell
# All available parameters
.\deploy.ps1 [-DryRun] [-Force] [-SkipBackup]

# -DryRun: Preview files without uploading
# -Force: Upload all files regardless of modification time
# -SkipBackup: Skip the backup prompt before deployment
```

### Check Deployment History

Backups are timestamped, allowing you to track deployments:

```powershell
# List all backups
Get-ChildItem backups\ | Sort-Object Name -Descending
```

## 🛠️ Troubleshooting

### Issue: "Credentials file not found"

**Solution:** Make sure `config/ftp_credentials.php` exists. If not, recreate it with your FTP details.

### Issue: "Access denied" or FTP connection errors

**Solutions:**
1. Verify FTP credentials in `config/ftp_credentials.php`
2. Check if FTP server is accessible: `Test-NetConnection 89.117.188.183 -Port 21`
3. Ensure firewall isn't blocking FTP connections
4. Try connecting with FileZilla using the same credentials

### Issue: Files not being uploaded

**Solutions:**
1. Run `.\deploy.ps1 -DryRun` to see which files would be uploaded
2. Check `deploy-config.json` exclude patterns
3. Verify file permissions on local files
4. Check FTP server disk space

### Issue: PowerShell execution policy error

**Solution:**
```powershell
# Allow script execution (run as Administrator)
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

Or use the bypass flag:
```powershell
powershell -ExecutionPolicy Bypass -File .\deploy.ps1
```

### Issue: Deployment is very slow

**Causes:**
- Large number of files
- Slow FTP server connection
- Large file sizes

**Solutions:**
- Use `-Force` only when necessary (it uploads ALL files)
- Exclude unnecessary files in `deploy-config.json`
- Consider using FTPS or SFTP for better compression (requires server support)

## 📊 Understanding Deployment Output

### Dry Run Output
```
=== DRY RUN MODE ===
The following files would be uploaded:

  → public/index.php
  → public/css/style.css
  → config/config.php
  
Total: 3 files
```

### Deployment Output
```
Uploading: public/index.php ✓
Uploading: public/css/style.css ✓
Uploading: config/config.php ✓

=== DEPLOYMENT SUMMARY ===
✓ Successfully uploaded: 3 files
Total processed: 3 files

🎉 Deployment completed successfully!
```

## 🔐 Security Best Practices

1. **Never commit credentials**
   - `config/ftp_credentials.php` is git-ignored
   - Keep credentials file local only

2. **Use FTPS when possible**
   - The script supports explicit FTP over TLS
   - Configure in FTP client settings

3. **Backup before deploying**
   - Always run `.\backup.ps1` before major deployments
   - Keep multiple backup versions

4. **Test in dry-run mode first**
   - Use `.\deploy.ps1 -DryRun` to preview changes
   - Verify file list before actual deployment

5. **Limit deployment access**
   - Only authorized users should have deployment scripts
   - Protect FTP credentials file permissions

## 📈 Deployment Checklist

- [ ] Test changes locally at `http://localhost/aleppogift/`
- [ ] Run dry-run: `.\deploy.ps1 -DryRun`
- [ ] Review files to be deployed
- [ ] Backup production: `.\backup.ps1`
- [ ] Deploy: `.\deploy.ps1`
- [ ] Verify production: https://aleppogift.com/index.php
- [ ] Test critical functionality on production
- [ ] Monitor error logs for issues

## 🆘 Emergency Rollback

If deployment causes issues:

1. **Restore from backup**
   ```powershell
   # Find your backup
   Get-ChildItem backups\ | Sort-Object Name -Descending | Select-Object -First 1
   
   # Upload backup files manually via FTP client (FileZilla)
   ```

2. **Or deploy previous version**
   ```powershell
   # If you have git commits
   git checkout <previous-commit>
   .\deploy.ps1
   git checkout main
   ```

## 📞 Support

For issues or questions:
- Check the troubleshooting section above
- Review deployment logs in terminal output
- Test FTP connection with FileZilla first
- Verify credentials in `config/ftp_credentials.php`

## 📝 File Paths Reference

### Local Development
- **Root:** `C:\xampp\htdocs\aleppogift\`
- **Public:** `C:\xampp\htdocs\aleppogift\public\`
- **URL:** `http://localhost/aleppogift/`

### Production Server
- **Root:** `/domains/aleppogift.com/public_html/`
- **Public:** `/domains/aleppogift.com/public_html/public/`
- **URL:** `https://aleppogift.com/index.php`

### FTP Server
- **Host:** `89.117.188.183`
- **User:** `u933234997`
- **Protocol:** FTP (TLS available)

---

**Version:** 1.0  
**Last Updated:** November 30, 2025

# Deployment Quick Start Guide

## ✅ Setup Complete

Your deployment system is ready to use!

## 📂 Files Created

1. **`deploy.ps1`** - Main PowerShell deployment script
2. **`deploy.bat`** - Interactive menu (double-click to run)
3. **`backup.ps1`** - Production backup script  
4. **`deploy-config.json`** - File exclusion configuration
5. **`config/ftp_credentials.php`** - FTP credentials (git-ignored)
6. **`DEPLOYMENT_GUIDE.md`** - Full documentation

## 🚀 Quick Deployment

### Option 1: Double-Click (Easiest)
1. Double-click **`deploy.bat`**
2. Choose option `[3]` to preview files
3. Choose option `[1]` to deploy

### Option 2: PowerShell Commands

```powershell
# Preview what will be deployed
.\deploy.ps1 -DryRun

# Deploy to production
.\deploy.ps1
```

## 📊 Your Last Dry Run Results

**Files to deploy:** 1916 files  
**Files excluded:** Uploads, test files, documentation, git files

## ⚙️ Configuration

### FTP Details (from config/ftp_credentials.php)
- **Host:** 89.117.188.183
- **User:** u933234997
- **Local:** C:\xampp\htdocs\aleppogift
- **Production:** /domains/aleppogift.com/public_html

### Excluded Files (from deploy-config.json)
- `public/uploads/*` (user uploaded content)
- `test_*.php`, `debug_*.php` (test files)
- `*.md`, `*.txt` (documentation)
- `.git*` (version control)
- Deployment scripts themselves

## 🛡️ Safety Features

✓ Dry-run mode to preview changes  
✓ Backup prompt before deployment  
✓ File exclusion filters  
✓ Credentials excluded from git  
✓ Interactive confirmations  

## 📝 Recommended Workflow

1. **Test locally** - Verify http://localhost/aleppogift/
2. **Dry run** - `.\deploy.ps1 -DryRun`
3. **Backup** - `.\backup.ps1` (optional)
4. **Deploy** - `.\deploy.ps1`
5. **Verify** - Test https://aleppogift.com/index.php

## 📖 Full Documentation

See **`DEPLOYMENT_GUIDE.md`** for:
- Troubleshooting
- Advanced usage
- Security best practices
- Emergency rollback procedures

## 🆘 Quick Help

### Issue: PowerShell execution policy error
```powershell
powershell -ExecutionPolicy Bypass -File .\deploy.ps1
```

### Issue: Files not excluded correctly
Edit `deploy-config.json` and add patterns to the `exclude` array.

### Issue: FTP connection failed
Verify credentials in `config/ftp_credentials.php`

---

**Ready to deploy!** Start with `.\deploy.ps1 -DryRun` to preview.

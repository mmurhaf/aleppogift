<#
.SYNOPSIS
    Deploy AleppoGift website to production via FTP

.DESCRIPTION
    Uploads local files to production server using FTP credentials from config/ftp_credentials.php
    Supports dry-run mode, file filtering, and incremental uploads

.PARAMETER DryRun
    If specified, shows what would be uploaded without actually uploading

.PARAMETER Force
    Upload all files regardless of modification time

.PARAMETER SkipBackup
    Skip the backup prompt before deployment

.EXAMPLE
    .\deploy.ps1
    Interactive deployment with backup prompt

.EXAMPLE
    .\deploy.ps1 -DryRun
    Show what would be deployed without uploading

.EXAMPLE
    .\deploy.ps1 -Force -SkipBackup
    Force upload all files without backup
#>

param(
    [switch]$DryRun,
    [switch]$Force,
    [switch]$SkipBackup
)

# Script configuration
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $ScriptDir "config\ftp_credentials.php"
$DeployConfigFile = Join-Path $ScriptDir "deploy-config.json"

# ANSI colors for output
$ColorReset = "`e[0m"
$ColorGreen = "`e[32m"
$ColorYellow = "`e[33m"
$ColorRed = "`e[31m"
$ColorBlue = "`e[34m"
$ColorCyan = "`e[36m"

function Write-ColorOutput {
    param([string]$Message, [string]$Color = $ColorReset)
    Write-Host "${Color}${Message}${ColorReset}"
}

function Parse-PHPArray {
    param([string]$Content, [string]$Key)
    
    if ($Content -match "'$Key'\s*=>\s*'([^']+)'") {
        return $matches[1]
    }
    return $null
}

# Load FTP credentials
Write-ColorOutput "Loading FTP credentials..." $ColorCyan
if (-not (Test-Path $ConfigFile)) {
    Write-ColorOutput "ERROR: Credentials file not found: $ConfigFile" $ColorRed
    exit 1
}

$CredContent = Get-Content $ConfigFile -Raw

# Parse credentials
$FtpHost = Parse-PHPArray $CredContent 'host'
$FtpUser = Parse-PHPArray $CredContent 'user'
$FtpPass = Parse-PHPArray $CredContent 'password'
$LocalRoot = Parse-PHPArray $CredContent 'local_root'
$RemoteRoot = Parse-PHPArray $CredContent 'production_root'

if (-not $FtpHost -or -not $FtpUser -or -not $FtpPass) {
    Write-ColorOutput "ERROR: Could not parse FTP credentials" $ColorRed
    exit 1
}

Write-ColorOutput "[OK] Credentials loaded" $ColorGreen
Write-ColorOutput "  Host: $FtpHost" $ColorBlue
Write-ColorOutput "  User: $FtpUser" $ColorBlue
Write-ColorOutput "  Remote: $RemoteRoot" $ColorBlue

# Load deployment configuration
$ExcludePatterns = @(
    '*.git*',
    'node_modules',
    'vendor',
    'cache',
    'temp',
    'tmp',
    '*.log',
    'test_*.php',
    'debug_*.php',
    'deploy.ps1',
    'deploy.bat',
    'deploy-config.json',
    'backup.ps1',
    '*.md',
    '*.bat',
    'config/ftp_credentials.php',
    '.vscode',
    '.idea',
    '*.backup',
    '*.bak',
    '*.old'
)

if (Test-Path $DeployConfigFile) {
    $DeployConfig = Get-Content $DeployConfigFile | ConvertFrom-Json
    if ($DeployConfig.exclude) {
        $ExcludePatterns = $DeployConfig.exclude
    }
}

# Backup prompt
if (-not $SkipBackup -and -not $DryRun) {
    Write-ColorOutput "`n*** DEPLOYMENT WARNING ***" $ColorYellow
    Write-Host "This will upload files to production server."
    Write-Host "It's recommended to backup production files first."
    Write-Host ""
    $Response = Read-Host "Run backup script first? (Y/n)"
    if ($Response -ne 'n' -and $Response -ne 'N') {
        $BackupScript = Join-Path $ScriptDir "backup.ps1"
        if (Test-Path $BackupScript) {
            & $BackupScript
        } else {
            Write-ColorOutput "Backup script not found, continuing anyway..." $ColorYellow
        }
    }
}

# Get files to upload
Write-ColorOutput "`nScanning local files..." $ColorCyan
$LocalPath = if ($LocalRoot) { $LocalRoot.Replace('\\', '\') } else { $ScriptDir }
$AllFiles = Get-ChildItem -Path $LocalPath -Recurse -File

# Filter files
$FilesToUpload = $AllFiles | Where-Object {
    $RelativePath = $_.FullName.Substring($LocalPath.Length).TrimStart('\')
    $Include = $true
    
    foreach ($Pattern in $ExcludePatterns) {
        if ($RelativePath -like $Pattern -or $_.Name -like $Pattern) {
            $Include = $false
            break
        }
        # Check directory patterns
        $DirPath = Split-Path $RelativePath -Parent
        if ($DirPath -and ($DirPath -like "*$Pattern*" -or $DirPath.Split('\') -contains $Pattern)) {
            $Include = $false
            break
        }
    }
    
    $Include
}

Write-ColorOutput "[OK] Found $($FilesToUpload.Count) files to upload (excluded $($AllFiles.Count - $FilesToUpload.Count) files)" $ColorGreen

if ($DryRun) {
    Write-ColorOutput "`n=== DRY RUN MODE ===" $ColorYellow
    Write-ColorOutput "The following files would be uploaded:`n" $ColorCyan
    
    $FilesToUpload | ForEach-Object {
        $RelativePath = $_.FullName.Substring($LocalPath.Length).TrimStart('\')
        Write-Host "  → $RelativePath"
    }
    
    Write-ColorOutput "`nTotal: $($FilesToUpload.Count) files" $ColorGreen
    Write-ColorOutput "Run without -DryRun to perform actual upload" $ColorYellow
    exit 0
}

# Confirm deployment
Write-Host "`n"
Write-ColorOutput "Ready to deploy $($FilesToUpload.Count) files to production" $ColorCyan
$Confirm = Read-Host "Continue? (yes/no)"
if ($Confirm -ne 'yes') {
    Write-ColorOutput "Deployment cancelled" $ColorYellow
    exit 0
}

# FTP Upload function
function Upload-FTPFile {
    param(
        [string]$LocalFile,
        [string]$RemoteFile,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )
    
    try {
        $FtpUri = "ftp://$FtpHost$RemoteFile"
        $FtpRequest = [System.Net.FtpWebRequest]::Create($FtpUri)
        $FtpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
        $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $FtpRequest.UseBinary = $true
        $FtpRequest.KeepAlive = $false
        
        $FileContent = [System.IO.File]::ReadAllBytes($LocalFile)
        $FtpRequest.ContentLength = $FileContent.Length
        
        $RequestStream = $FtpRequest.GetRequestStream()
        $RequestStream.Write($FileContent, 0, $FileContent.Length)
        $RequestStream.Close()
        
        $Response = $FtpRequest.GetResponse()
        $Response.Close()
        
        return $true
    }
    catch {
        Write-ColorOutput "  ERROR: $($_.Exception.Message)" $ColorRed
        return $false
    }
}

# Create remote directory structure
function Create-FTPDirectory {
    param(
        [string]$RemoteDir,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )
    
    try {
        $FtpUri = "ftp://$FtpHost$RemoteDir"
        $FtpRequest = [System.Net.FtpWebRequest]::Create($FtpUri)
        $FtpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
        $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
        
        $Response = $FtpRequest.GetResponse()
        $Response.Close()
        return $true
    }
    catch {
        # Directory might already exist, ignore error
        return $false
    }
}

# Upload files
Write-ColorOutput "`nStarting deployment..." $ColorGreen
$UploadCount = 0
$ErrorCount = 0
$CreatedDirs = @{}

foreach ($File in $FilesToUpload) {
    $RelativePath = $File.FullName.Substring($LocalPath.Length).TrimStart('\').Replace('\', '/')
    $RemoteFile = "$RemoteRoot/$RelativePath"
    $RemoteDir = Split-Path $RemoteFile -Parent
    
    # Create directory if needed
    if (-not $CreatedDirs[$RemoteDir]) {
        $DirParts = $RemoteDir.Split('/') | Where-Object { $_ }
        $CurrentPath = ""
        foreach ($Part in $DirParts) {
            $CurrentPath += "/$Part"
            if (-not $CreatedDirs[$CurrentPath]) {
                Create-FTPDirectory $CurrentPath $FtpHost $FtpUser $FtpPass | Out-Null
                $CreatedDirs[$CurrentPath] = $true
            }
        }
    }
    
    Write-Host "Uploading: " -NoNewline
    Write-Host "$RelativePath" -ForegroundColor Cyan -NoNewline
    
    if (Upload-FTPFile $File.FullName $RemoteFile $FtpHost $FtpUser $FtpPass) {
        Write-ColorOutput " [OK]" $ColorGreen
        $UploadCount++
    } else {
        Write-ColorOutput " [FAIL]" $ColorRed
        $ErrorCount++
    }
}

# Summary
Write-Host "`n"
Write-ColorOutput "=== DEPLOYMENT SUMMARY ===" $ColorCyan
Write-ColorOutput "Successfully uploaded: $UploadCount files" $ColorGreen
if ($ErrorCount -gt 0) {
    Write-ColorOutput "Failed: $ErrorCount files" $ColorRed
}
Write-ColorOutput "Total processed: $($FilesToUpload.Count) files" $ColorBlue

if ($ErrorCount -eq 0) {
    Write-ColorOutput "`nDeployment completed successfully!" $ColorGreen
} else {
    Write-ColorOutput "`nDeployment completed with errors" $ColorYellow
}

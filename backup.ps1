<#
.SYNOPSIS
    Backup production files from FTP server

.DESCRIPTION
    Downloads files from production server to a local backup directory
    Creates timestamped backups for versioning

.PARAMETER BackupPath
    Custom backup directory path (default: .\backups\YYYY-MM-DD_HHMMSS)

.PARAMETER FullBackup
    Download all files (default: only downloads critical files)

.EXAMPLE
    .\backup.ps1
    Downloads critical production files to timestamped backup folder

.EXAMPLE
    .\backup.ps1 -FullBackup
    Downloads all production files
#>

param(
    [string]$BackupPath,
    [switch]$FullBackup
)

# Script configuration
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ConfigFile = Join-Path $ScriptDir "config\ftp_credentials.php"

# ANSI colors
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
$FtpHost = Parse-PHPArray $CredContent 'host'
$FtpUser = Parse-PHPArray $CredContent 'user'
$FtpPass = Parse-PHPArray $CredContent 'password'
$RemoteRoot = Parse-PHPArray $CredContent 'production_root'

if (-not $FtpHost -or -not $FtpUser -or -not $FtpPass) {
    Write-ColorOutput "ERROR: Could not parse FTP credentials" $ColorRed
    exit 1
}

Write-ColorOutput "[OK] Credentials loaded" $ColorGreen

# Create backup directory
if (-not $BackupPath) {
    $Timestamp = Get-Date -Format "yyyy-MM-dd_HHmmss"
    $BackupPath = Join-Path $ScriptDir "backups\$Timestamp"
}

if (-not (Test-Path $BackupPath)) {
    New-Item -ItemType Directory -Path $BackupPath -Force | Out-Null
    Write-ColorOutput "[OK] Created backup directory: $BackupPath" $ColorGreen
}

# Define critical files/folders to backup
$CriticalPaths = @(
    "config/config.php",
    "public/.htaccess",
    ".htaccess",
    "public/uploads",
    "public/quotations",
    "public/invoice"
)

# FTP functions
function Get-FTPDirectoryListing {
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
        $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::ListDirectoryDetails
        
        $Response = $FtpRequest.GetResponse()
        $Stream = $Response.GetResponseStream()
        $Reader = New-Object System.IO.StreamReader($Stream)
        $List = $Reader.ReadToEnd()
        $Reader.Close()
        $Stream.Close()
        $Response.Close()
        
        return $List -split "`r`n" | Where-Object { $_ }
    }
    catch {
        return @()
    }
}

function Download-FTPFile {
    param(
        [string]$RemoteFile,
        [string]$LocalFile,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )
    
    try {
        # Create local directory if needed
        $LocalDir = Split-Path $LocalFile -Parent
        if (-not (Test-Path $LocalDir)) {
            New-Item -ItemType Directory -Path $LocalDir -Force | Out-Null
        }
        
        $FtpUri = "ftp://$FtpHost$RemoteFile"
        $FtpRequest = [System.Net.FtpWebRequest]::Create($FtpUri)
        $FtpRequest.Credentials = New-Object System.Net.NetworkCredential($FtpUser, $FtpPass)
        $FtpRequest.Method = [System.Net.WebRequestMethods+Ftp]::DownloadFile
        $FtpRequest.UseBinary = $true
        
        $Response = $FtpRequest.GetResponse()
        $Stream = $Response.GetResponseStream()
        $FileStream = [System.IO.File]::Create($LocalFile)
        
        $Buffer = New-Object byte[] 1024
        while (($BytesRead = $Stream.Read($Buffer, 0, $Buffer.Length)) -gt 0) {
            $FileStream.Write($Buffer, 0, $BytesRead)
        }
        
        $FileStream.Close()
        $Stream.Close()
        $Response.Close()
        
        return $true
    }
    catch {
        Write-ColorOutput "  ERROR: $($_.Exception.Message)" $ColorRed
        return $false
    }
}

function Backup-FTPDirectory {
    param(
        [string]$RemotePath,
        [string]$LocalPath,
        [string]$FtpHost,
        [string]$FtpUser,
        [string]$FtpPass
    )
    
    $Listing = Get-FTPDirectoryListing $RemotePath $FtpHost $FtpUser $FtpPass
    
    foreach ($Line in $Listing) {
        # Parse FTP listing (Unix format)
        if ($Line -match '([d-])([rwx-]{9})\s+\d+\s+\S+\s+\S+\s+(\d+)\s+(\w+\s+\d+\s+[\d:]+)\s+(.+)$') {
            $IsDir = $matches[1] -eq 'd'
            $FileName = $matches[5]
            
            if ($FileName -eq '.' -or $FileName -eq '..') {
                continue
            }
            
            $RemoteItem = "$RemotePath/$FileName"
            $LocalItem = Join-Path $LocalPath $FileName
            
            if ($IsDir) {
                Write-ColorOutput "  Entering directory: $FileName" $ColorBlue
                Backup-FTPDirectory $RemoteItem $LocalItem $FtpHost $FtpUser $FtpPass
            }
            else {
                Write-Host "  Downloading: " -NoNewline
                Write-Host "$RemoteItem" -ForegroundColor Cyan -NoNewline
                if (Download-FTPFile $RemoteItem $LocalItem $FtpHost $FtpUser $FtpPass) {
                    Write-ColorOutput " [OK]" $ColorGreen
                    $script:DownloadCount++
                }
                else {
                    Write-ColorOutput " [FAIL]" $ColorRed
                    $script:ErrorCount++
                }
            }
        }
    }
}

# Backup operation
Write-ColorOutput "`nStarting backup from production server..." $ColorCyan
Write-ColorOutput "Remote: $RemoteRoot" $ColorBlue
Write-ColorOutput "Local: $BackupPath" $ColorBlue

$script:DownloadCount = 0
$script:ErrorCount = 0

if ($FullBackup) {
    Write-ColorOutput "`nFull backup mode - downloading all files..." $ColorYellow
    Backup-FTPDirectory $RemoteRoot $BackupPath $FtpHost $FtpUser $FtpPass
}
else {
    Write-ColorOutput "`nCritical files backup mode..." $ColorYellow
    
    foreach ($Path in $CriticalPaths) {
        $RemotePath = "$RemoteRoot/$Path"
        $LocalPath = Join-Path $BackupPath $Path
        
        Write-ColorOutput "`nBacking up: $Path" $ColorCyan
        
        # Check if it's a file or directory
        $TestListing = Get-FTPDirectoryListing $RemotePath $FtpHost $FtpUser $FtpPass
        
        if ($TestListing.Count -gt 0) {
            # It's a directory
            Backup-FTPDirectory $RemotePath $LocalPath $FtpHost $FtpUser $FtpPass
        }
        else {
            # Try as a file
            $LocalFile = $LocalPath
            Write-Host "  Downloading: " -NoNewline
            Write-Host "$RemotePath" -ForegroundColor Cyan -NoNewline
            if (Download-FTPFile $RemotePath $LocalFile $FtpHost $FtpUser $FtpPass) {
                Write-ColorOutput " [OK]" $ColorGreen
                $script:DownloadCount++
            }
            else {
                Write-ColorOutput " (not found or error)" $ColorYellow
            }
        }
    }
}

# Summary
Write-Host "`n"
Write-ColorOutput "=== BACKUP SUMMARY ===" $ColorCyan
Write-ColorOutput "Successfully downloaded: $script:DownloadCount files" $ColorGreen
if ($script:ErrorCount -gt 0) {
    Write-ColorOutput "Failed: $script:ErrorCount files" $ColorRed
}
Write-ColorOutput "Backup location: $BackupPath" $ColorBlue

if ($script:ErrorCount -eq 0 -and $script:DownloadCount -gt 0) {
    Write-ColorOutput "`nBackup completed successfully!" $ColorGreen
}
elseif ($script:DownloadCount -eq 0) {
    Write-ColorOutput "`nWARNING: No files were backed up" $ColorYellow
}
else {
    Write-ColorOutput "`nWARNING: Backup completed with errors" $ColorYellow
}

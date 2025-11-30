# Credentials File

This folder contains sensitive credential files used locally for deployments and FTP access.

- **File:** `ftp_credentials.php`
- **Purpose:** Store FTP host, username, password, and local/production paths and URLs.
- **Security:** This file is excluded from git via the repository's `.gitignore`. Do not modify the `.gitignore` entry unless you understand the security implications.

Usage example in PHP:

```php
$creds = include __DIR__ . '/ftp_credentials.php';
$ftp = $creds['ftp'];
// Example: connect using ftp extension or an FTP client library
```

If you need to deploy these credentials to a production server, use environment variables or your hosting control panel rather than storing plaintext passwords in files under version control.

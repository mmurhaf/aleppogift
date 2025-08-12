@echo off
REM Create main directory
mkdir aleppogift

REM Create subdirectories
mkdir aleppogift\admin
mkdir aleppogift\config
mkdir aleppogift\includes
mkdir aleppogift\public
mkdir aleppogift\uploads
mkdir aleppogift\assets
mkdir aleppogift\assets\css
mkdir aleppogift\assets\js
mkdir aleppogift\assets\images
mkdir aleppogift\invoice

REM Create empty files in each directory (optional)
REM You can remove these lines if you don't need empty files created
type nul > aleppogift\admin\index.html
type nul > aleppogift\config\config.php
type nul > aleppogift\includes\functions.php
type nul > aleppogift\public\index.html
type nul > aleppogift\assets\css\style.css
type nul > aleppogift\assets\js\script.js
type nul > aleppogift\invoice\index.html

echo Folder structure and files created successfully.
pause
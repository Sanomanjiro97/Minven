@echo off
echo Running SQL script for database: minven_pro
C:\xampp\mysql\bin\mysql.exe -u root -p minven_pro < add_can_complete_column.sql
echo SQL script completed!
pause

@echo off
echo ========================================
echo    UPDATE SURAT JALAN PAYMENT SYSTEM
echo ========================================
echo.
echo Menambahkan kolom untuk fitur update pembayaran...
echo.

REM Set database connection details
set DB_HOST=localhost
set DB_USER=root
set DB_PASS=
set DB_NAME=minven_pro

REM Run SQL script (simple version)
mysql -h %DB_HOST% -u %DB_USER% -p%DB_PASS% %DB_NAME% < add_updated_at_column_simple.sql

if %ERRORLEVEL% EQU 0 (
    echo.
    echo ========================================
    echo    UPDATE BERHASIL!
    echo ========================================
    echo.
    echo Kolom baru telah ditambahkan:
    echo - updated_at (timestamp)
    echo - keterangan_pembayaran (text)
    echo.
    echo Fitur update pembayaran siap digunakan!
    echo.
) else (
    echo.
    echo ========================================
    echo    UPDATE GAGAL!
    echo ========================================
    echo.
    echo Terjadi kesalahan saat menjalankan script SQL.
    echo Pastikan:
    echo - Database MySQL berjalan
    echo - Kredensial database benar
    echo - Database 'minven_pro' ada
    echo.
)

pause 

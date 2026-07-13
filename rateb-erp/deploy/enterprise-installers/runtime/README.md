# Bundled PHP runtime (Phase D.3 self-contained mode)

Place a complete PHP 8.2+ build here:

- Linux: `runtime/php/bin/php` (with pdo_sqlite, sqlite3, openssl, gd, curl, zip, json)
- Windows: `runtime/php/php.exe` + `ext/` + `php.ini` enabling the same extensions

The Universal installer:

1. Uses system PHP if it already satisfies requirements  
2. Else uses this bundled runtime  
3. Else (Windows) downloads official NTS build from windows.php.net into this folder  
4. Else (Linux) installs distro packages via apt/dnf/yum  

Do not commit large PHP binaries to git unless your release pipeline requires it.

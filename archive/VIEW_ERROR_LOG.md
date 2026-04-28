# How to View Apache Error Log

## Apache Error Log Location

**For XAMPP on Windows:**
```
C:\xampp\apache\logs\error.log
```

## Ways to View the Error Log

### Method 1: Open in Text Editor
1. Open File Explorer
2. Navigate to: `C:\xampp\apache\logs\`
3. Double-click `error.log` to open in Notepad or any text editor

### Method 2: View in Command Prompt
1. Press `Win + R`
2. Type: `cmd` and press Enter
3. Run this command:
```bash
type C:\xampp\apache\logs\error.log
```

Or to see the last 50 lines:
```bash
powershell "Get-Content C:\xampp\apache\logs\error.log -Tail 50"
```

### Method 3: Open Direct Link
1. Press `Win + R`
2. Type this path and press Enter:
```
C:\xampp\apache\logs\error.log
```

### Method 4: View Real-time (as errors occur)
In Command Prompt:
```bash
powershell "Get-Content C:\xampp\apache\logs\error.log -Wait -Tail 20"
```

## Test History Logging

**Run this test script:**
```
http://localhost/ratibprogram/test-history-logging.php
```

This will show:
- If the history helper file exists
- If the function works
- Database connection status
- History table status
- Recent history records
- Error log location

## What to Look For

In the error log, search for:
- `⚠️ Failed to log history` - History logging failed
- `⚠️ History helper not found` - Path issue
- `GlobalHistory:` - Messages from history helper
- `PHP Error` - Any PHP errors

## Quick Access Link

**File Path (copy and paste in Windows Explorer address bar):**
```
C:\xampp\apache\logs
```


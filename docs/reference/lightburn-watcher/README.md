# LightBurn SFTP Watcher

A Node.js script that monitors the Kinsta SFTP server for new SVG files and automatically loads them into LightBurn for laser engraving.

## How It Works

1. **Polls SFTP server** every 3 seconds for new `.svg` files
2. **Downloads new files** to local `Incoming` folder
3. **Sends UDP command** to LightBurn to load the file
4. **Tracks processed files** to avoid re-downloading

## Prerequisites

1. **Node.js 18+** installed on the Windows workstation
2. **SSH private key** at `C:\Users\Production\LightBurn\lightburn-watcher\rlux`
3. **LightBurn** installed at `C:\Program Files\LightBurn\LightBurn.exe`

## Installation

### 1. Install dependencies

Open Command Prompt and run:

```cmd
cd C:\Users\Production\LightBurn\lightburn-watcher
npm install
```

This installs `ssh2-sftp-client` for SFTP connectivity.

### 2. Auto-Start on Windows Login

A shortcut is configured in the Windows Startup folder to auto-start the watcher when you log in:

**Location:** `%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\LightBurn-Watcher.lnk`

**Shortcut configuration:**
- **Target:** `wscript.exe`
- **Arguments:** `"C:\Users\Production\LightBurn\lightburn-watcher\start-watcher-hidden.vbs"`
- **Start in:** `C:\Users\Production\LightBurn\lightburn-watcher`

The VBS script launches Node.js hidden (no console window) so it runs silently in the background.

### 3. Verify it's running

Check if the watcher is running:

```cmd
tasklist | findstr node
```

Or check for the specific process:

```cmd
wmic process where "commandline like '%%lightburn-watcher%%'" get processid,commandline
```

## Manual Start/Stop

### Start manually

Double-click `start-watcher-hidden.vbs` or run:

```cmd
wscript.exe "C:\Users\Production\LightBurn\lightburn-watcher\start-watcher-hidden.vbs"
```

### Stop the watcher

```cmd
wmic process where "commandline like '%%lightburn-watcher-service%%'" call terminate
```

Or use Task Manager to end the `node.exe` process running `lightburn-watcher-service.js`.

## Log Files

The watcher writes logs to:

```
C:\Users\Production\LightBurn\lightburn-watcher.log
```

View logs in real-time (PowerShell):

```powershell
Get-Content "$env:USERPROFILE\LightBurn\lightburn-watcher.log" -Tail 50 -Wait
```

## State File

Tracks which files have been processed:

```
C:\Users\Production\.lightburn-watcher-state.json
```

To reprocess all files, delete this file and restart the watcher.

## Configuration

Edit `lightburn-watcher-service.js` to change:

| Setting | Default | Description |
|---------|---------|-------------|
| `sftp.host` | `34.71.83.227` | Kinsta SFTP server |
| `sftp.port` | `21264` | SFTP port |
| `remoteDir` | `/www/.../svg` | Remote directory to watch |
| `localDir` | `%USERPROFILE%\LightBurn\Incoming` | Local download directory |
| `pollInterval` | `3000` (3 seconds) | How often to check for files |
| `lightburn.port` | `19840` | LightBurn UDP port |

## Troubleshooting

### Watcher not starting on login

1. Check the Startup folder shortcut exists:
   ```
   %APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\LightBurn-Watcher.lnk
   ```

2. Verify the shortcut properties:
   - Target: `wscript.exe`
   - Arguments: `"C:\Users\Production\LightBurn\lightburn-watcher\start-watcher-hidden.vbs"`

3. If shortcut is missing or broken, recreate it (PowerShell as Admin):
   ```powershell
   $WshShell = New-Object -ComObject WScript.Shell
   $Shortcut = $WshShell.CreateShortcut("$env:APPDATA\Microsoft\Windows\Start Menu\Programs\Startup\LightBurn-Watcher.lnk")
   $Shortcut.TargetPath = "wscript.exe"
   $Shortcut.Arguments = '"C:\Users\Production\LightBurn\lightburn-watcher\start-watcher-hidden.vbs"'
   $Shortcut.WorkingDirectory = "C:\Users\Production\LightBurn\lightburn-watcher"
   $Shortcut.WindowStyle = 7
   $Shortcut.Save()
   ```

### Multiple instances running

Kill all instances and start fresh:

```cmd
wmic process where "commandline like '%%lightburn-watcher-service%%'" call terminate
wscript.exe "C:\Users\Production\LightBurn\lightburn-watcher\start-watcher-hidden.vbs"
```

### SFTP connection failing

1. Check the SSH key exists at `C:\Users\Production\LightBurn\lightburn-watcher\rlux`
2. Test SFTP connection manually (run in console to see errors):
   ```cmd
   cd C:\Users\Production\LightBurn\lightburn-watcher
   node lightburn-watcher-service.js
   ```

### LightBurn not loading files

1. Verify LightBurn is running
2. Check that UDP is enabled in LightBurn settings (Edit > Settings > Network)
3. Verify the incoming file path is accessible

### Files not being processed

1. Check the state file - the file may already be marked as processed
2. Delete the state file to reprocess all files:
   ```cmd
   del "%USERPROFILE%\.lightburn-watcher-state.json"
   ```

## Architecture

```
[Kinsta SFTP Server]
        |
        | (poll every 3 seconds)
        v
[LightBurn Watcher (Node.js)]
        |
        | (download new SVG files)
        v
[C:\Users\Production\LightBurn\Incoming\]
        |
        | (UDP LOADFILE command)
        v
[LightBurn Application]
```

## Files in this Directory

| File | Purpose |
|------|---------|
| `lightburn-watcher-service.js` | Main watcher script |
| `start-watcher-hidden.vbs` | Launches Node.js hidden (no console window) |
| `rlux` | SSH private key for SFTP connection |
| `package.json` | Node.js dependencies |
| `README.md` | This documentation |

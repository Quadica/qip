# LightBurn SFTP Watcher - Windows Service

A Windows Service that monitors the Kinsta SFTP server for new SVG files and automatically loads them into LightBurn for laser engraving.

## Why a Windows Service?

Running as a Windows Service instead of a Task Scheduler task provides:

1. **Runs independently of user sessions** - Won't stop when the user logs out or the system goes idle
2. **Automatic restart on failure** - Configured to restart up to 10 times if it crashes
3. **Starts at boot** - Runs before any user logs in
4. **Better visibility** - Shows status in Services (services.msc)
5. **No idle timeouts** - Not affected by Windows power management settings

## Prerequisites

1. **Node.js 18+** installed on the Windows workstation
2. **SSH private key** at `%USERPROFILE%\.ssh\rlux` (the Kinsta staging key)
3. **LightBurn** installed at `C:\Program Files\LightBurn\LightBurn.exe`
4. **Administrator access** (required to install Windows services)

## Installation

### 1. Copy files to the workstation

Copy this entire `lightburn-watcher` folder to a permanent location on the Windows workstation, for example:

```
C:\Tools\lightburn-watcher\
```

### 2. Install dependencies

Open Command Prompt **as Administrator** and run:

```cmd
cd C:\Tools\lightburn-watcher
npm install
```

This installs:
- `ssh2-sftp-client` - SFTP client library
- `node-windows` - Windows service wrapper

### 3. Install the Windows Service

Still in the Administrator Command Prompt:

```cmd
npm run install-service
```

Or:

```cmd
node install-service.js
```

You may see a UAC prompt - click **Yes** to allow the service installation.

### 4. Verify installation

1. Open **Services** (`services.msc`)
2. Find **"LightBurn SFTP Watcher"** in the list
3. Status should show **"Running"**
4. Startup Type should show **"Automatic"**

## Managing the Service

### Using Services.msc (GUI)

1. Press `Win + R`, type `services.msc`, press Enter
2. Find **"LightBurn SFTP Watcher"**
3. Right-click to **Start**, **Stop**, or **Restart**

### Using Command Line (Administrator)

```cmd
:: Start the service
net start "LightBurn SFTP Watcher"

:: Stop the service
net stop "LightBurn SFTP Watcher"

:: Query status
sc query "LightBurn SFTP Watcher"
```

### Using PowerShell (Administrator)

```powershell
# Start
Start-Service "LightBurn SFTP Watcher"

# Stop
Stop-Service "LightBurn SFTP Watcher"

# Restart
Restart-Service "LightBurn SFTP Watcher"

# Status
Get-Service "LightBurn SFTP Watcher"
```

## Log Files

The service writes logs to:

```
%USERPROFILE%\lightburn-watcher.log
```

View logs in real-time (PowerShell):

```powershell
Get-Content "$env:USERPROFILE\lightburn-watcher.log" -Tail 50 -Wait
```

Logs are automatically rotated when they exceed 5MB.

## State File

The service tracks which files have been processed in:

```
%USERPROFILE%\.lightburn-watcher-state.json
```

To reprocess all files, delete this file and restart the service.

## Uninstalling

Open Command Prompt **as Administrator**:

```cmd
cd C:\Tools\lightburn-watcher
npm run uninstall-service
```

Or:

```cmd
node uninstall-service.js
```

This removes the service but preserves log and state files.

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

After changing configuration, restart the service.

## Troubleshooting

### Service won't start

1. Check the log file for errors
2. Verify SSH key exists at `%USERPROFILE%\.ssh\rlux`
3. Test SFTP connection manually:
   ```cmd
   node lightburn-watcher-service.js --once
   ```

### Service stops unexpectedly

1. Check Windows Event Viewer → Windows Logs → Application
2. Check the service log file
3. Verify network connectivity to Kinsta

### Files not being processed

1. Check the state file - the file may already be marked as processed
2. Clear state and restart:
   ```cmd
   node lightburn-watcher-service.js --clear
   net stop "LightBurn SFTP Watcher"
   net start "LightBurn SFTP Watcher"
   ```

### LightBurn not loading files

1. Verify LightBurn is installed at the expected path
2. Check that UDP is enabled in LightBurn settings
3. Try loading a file manually to verify LightBurn works

## Migrating from Task Scheduler

If you previously ran the watcher via Task Scheduler:

1. **Disable the scheduled task** in Task Scheduler
2. **Stop PM2** if it's running: `pm2 stop all && pm2 delete all`
3. **Install the Windows Service** as described above
4. **Verify** the service is running in services.msc

The service uses the same state file, so it will continue from where the previous version left off.

## Architecture

```
[Kinsta SFTP Server]
        |
        | (poll every 3 seconds)
        v
[LightBurn Watcher Service]
        |
        | (download new SVG files)
        v
[%USERPROFILE%\LightBurn\Incoming\]
        |
        | (UDP LOADFILE command)
        v
[LightBurn Application]
```

## Files in this Directory

| File | Purpose |
|------|---------|
| `lightburn-watcher-service.js` | Main service script |
| `install-service.js` | Installs as Windows Service |
| `uninstall-service.js` | Removes Windows Service |
| `package.json` | Node.js dependencies |
| `README.md` | This documentation |
| `lightburn-watcher.js` | Original script (deprecated) |
| `start-lightburn-watcher.bat` | Original batch file (deprecated) |

# Session 046B: LightBurn Remote Integration

- **Date/Time:** 2026-01-06 ~15:00-16:00
- **Session Type(s):** feature|infrastructure
- **Primary Focus Area(s):** LightBurn integration, remote file transfer

## Overview

This session implemented the complete remote LightBurn integration system, enabling the cloud-hosted WordPress plugin (on Kinsta) to send SVG files to LightBurn software running on a local Windows workstation. This required architectural changes due to network constraints: the WordPress server cannot receive UDP responses from LightBurn because of firewall rules.

## Problem Statement

The original LightBurn integration (Phase 7) assumed WordPress and LightBurn were on the same network or that UDP responses could reach the server. In the production setup:

- **WordPress**: Hosted on Kinsta (cloud)
- **LightBurn**: Running on a local Windows workstation
- **Issue**: UDP responses from LightBurn cannot traverse firewalls back to the cloud server

## Solution Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         PRODUCTION FLOW                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│   ┌──────────────────┐         SFTP (21264)        ┌─────────────┐ │
│   │ WordPress/Kinsta │ ─────────────────────────── │   Windows   │ │
│   │                  │     SVG files uploaded      │  Workstation│ │
│   │  QSA Engraving   │         to server          │             │ │
│   │     Plugin       │                            │  lightburn- │ │
│   │                  │                            │  watcher.js │ │
│   │  SVG Generator   │     Polls every 3 sec      │     ↓       │ │
│   │       ↓          │ <─────────────────────────  │  Downloads  │ │
│   │  Saves to:       │                            │     ↓       │ │
│   │  /uploads/qsa-   │                            │  UDP LOADFILE│ │
│   │  engraving/svg/  │                            │     ↓       │ │
│   └──────────────────┘                            │  LightBurn  │ │
│                                                    └─────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

## Changes Made

### WordPress Plugin Files Modified

#### 1. `includes/Services/class-lightburn-client.php`

Added fire-and-forget UDP methods for remote setups:

**New Methods:**
- `init_output_socket()` - Initialize only the output socket (no input socket needed for fire-and-forget)
- `send_command_no_wait()` - Send UDP command without waiting for response
- `load_file_no_wait()` - Load file in LightBurn without waiting for confirmation

**Key Code Added:**
```php
/**
 * Send a command to LightBurn without waiting for response (fire-and-forget).
 * Use this for remote setups where the response cannot reach the server.
 */
public function send_command_no_wait( string $command ): array {
    $init = $this->init_output_socket();
    if ( is_wp_error( $init ) ) {
        return array( 'success' => false, 'response' => '', 'error' => $init->get_error_message() );
    }

    $sent = @socket_sendto( $this->out_socket, $command, strlen( $command ), 0, $this->host, $this->out_port );

    if ( false === $sent ) {
        // Handle error...
    }

    // Fire-and-forget: assume success if send succeeded
    return array( 'success' => true, 'response' => 'sent', 'error' => '' );
}
```

#### 2. `includes/Ajax/class-lightburn-ajax-handler.php`

Updated `load_in_lightburn()` method to use fire-and-forget mode:

**Before:**
```php
$result = $client->load_file_with_retry( $lightburn_path );
```

**After:**
```php
// Load file in fire-and-forget mode (no response wait)
$result = $client->load_file_no_wait( $lightburn_path );
```

Added documentation explaining the architectural decision:
```php
/**
 * Load SVG file in LightBurn.
 *
 * Uses fire-and-forget mode (no response wait) since the LightBurn machine
 * is typically on a different network and responses cannot reach the server.
 */
```

### New Files Created (Local Windows Machine)

#### 1. `lightburn-watcher.js`

A Node.js script that runs on the local Windows workstation to bridge the cloud-to-local gap.

**Features:**
- Connects to Kinsta SFTP server using SSH key authentication
- Polls `/wp-content/uploads/qsa-engraving/svg/` every 3 seconds for new SVG files
- Downloads new files to `C:\Users\Production\LightBurn\Incoming`
- Kills any running LightBurn instance (using `taskkill /F`)
- Starts fresh LightBurn instance
- Sends UDP `LOADFILE` command to load the SVG
- Tracks processed files in `~/.lightburn-watcher-state.json` to avoid reprocessing
- Auto-reconnects on SFTP connection drops
- Graceful shutdown on SIGINT/SIGTERM

**Configuration:**
```javascript
const CONFIG = {
    sftp: {
        host: '34.71.83.227',
        port: 21264,
        username: 'luxeonstarleds',
        privateKey: fs.readFileSync(path.join(os.homedir(), '.ssh', 'rlux')),
    },
    remoteDir: '/www/luxeonstarleds_546/public/wp-content/uploads/qsa-engraving/svg',
    localDir: path.join(os.homedir(), 'LightBurn', 'Incoming'),
    lightburn: {
        host: '127.0.0.1',
        port: 19840,
    },
    pollInterval: 3000,
};
```

**Command-line Options:**
- `node lightburn-watcher.js` - Start continuous polling
- `node lightburn-watcher.js --once` - Process files once and exit
- `node lightburn-watcher.js --clear` - Clear processed files state

#### 2. `start-lightburn-watcher.bat`

Windows batch file for auto-starting the watcher via Task Scheduler:

```batch
@echo off
:: LightBurn SFTP Watcher Startup Script (PM2)
:: This script is called by Windows Task Scheduler

pm2 resurrect
```

### Dependencies Installed (Windows Machine)

```bash
npm install ssh2-sftp-client
npm install -g pm2
```

### PM2 Process Management

The watcher is managed by PM2 for auto-restart on crashes:

```bash
pm2 start lightburn-watcher.js --name lightburn-watcher
pm2 save
```

### Windows Task Scheduler Configuration

Task configured to run `start-lightburn-watcher.bat` at user login to ensure the watcher starts automatically.

## Git Commits

| Commit | Message |
|--------|---------|
| `5537493` | Add fire-and-forget mode for LightBurn UDP communication |

## Technical Decisions

### 1. Fire-and-Forget vs Bidirectional UDP
- **Decision**: Use fire-and-forget for remote setups
- **Rationale**: Firewall rules prevent UDP responses from reaching cloud servers. The operator confirms successful engraving via UI buttons.

### 2. SFTP Polling vs WebSocket Push
- **Decision**: SFTP polling every 3 seconds
- **Rationale**: Simpler architecture, uses existing SSH key authentication, reliable over unreliable networks, no additional infrastructure needed.

### 3. Direct taskkill vs FORCECLOSE Command
- **Decision**: Use `taskkill /F /IM LightBurn.exe`
- **Rationale**: FORCECLOSE UDP command is blocked when LightBurn has dialogs open (like "Save Project?"). Direct taskkill is more reliable.

### 4. State Persistence
- **Decision**: Track processed files in JSON file
- **Rationale**: Prevents reprocessing files after watcher restart. State survives reboots.

## File Locations

| File | Location | Purpose |
|------|----------|---------|
| `class-lightburn-client.php` | WordPress plugin (Kinsta) | UDP client with fire-and-forget |
| `class-lightburn-ajax-handler.php` | WordPress plugin (Kinsta) | AJAX endpoints for LightBurn |
| `lightburn-watcher.js` | Windows machine | SFTP monitor and LightBurn loader |
| `start-lightburn-watcher.bat` | Windows machine | Auto-start script |
| `.lightburn-watcher-state.json` | Windows `~/.` | Processed files tracking |
| `Incoming/*.svg` | Windows LightBurn dir | Downloaded SVG files |

## Workflow Summary

1. **Operator** creates engraving batch in WordPress admin
2. **WordPress** generates SVG and saves to `/wp-content/uploads/qsa-engraving/svg/`
3. **lightburn-watcher.js** detects new file via SFTP polling (within 3 seconds)
4. **Watcher** downloads file to local `LightBurn/Incoming` directory
5. **Watcher** kills any running LightBurn, starts fresh instance
6. **Watcher** sends `LOADFILE:path` via UDP to LightBurn
7. **LightBurn** opens with the SVG file ready for engraving
8. **Operator** confirms completion via UI buttons in WordPress

## Testing Performed

- Verified SVG upload from WordPress reaches staging server
- Confirmed SFTP connection with SSH key authentication
- Tested file download and LightBurn loading
- Verified state persistence across watcher restarts
- Tested auto-reconnect on SFTP connection drops
- Confirmed PM2 auto-restart on crashes

## Known Limitations

1. 3-second polling delay (not instant)
2. No confirmation that LightBurn actually loaded the file (fire-and-forget)
3. Operator must manually confirm engraving completion

## Reference Files

- `docs/reference/lightburn-watcher/lightburn-watcher.js` - Watcher script reference copy
- `docs/reference/lightburn-watcher/start-lightburn-watcher.bat` - Startup script reference copy

## Notes for Future Sessions

- The lightburn-watcher.js runs on the Windows machine, NOT through the GitHub deployment workflow
- To update the watcher: edit directly on Windows machine, then `pm2 restart lightburn-watcher`
- Reference copies in `docs/reference/lightburn-watcher/` should be updated if significant changes are made
- SSH key (`~/.ssh/rlux`) must exist on Windows machine with correct permissions

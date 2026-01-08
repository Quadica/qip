# Session 051: LightBurn Watcher Windows Service Troubleshooting

- Date/Time: 2026-01-08 00:37
- Session Type(s): bugfix|feature|documentation
- Primary Focus Area(s): infrastructure|backend

## Overview

This session involved fixing SVG offset bugs and extensive troubleshooting of the LightBurn SFTP Watcher deployment on Windows. The SVG fixes ensured top offset applies correctly regardless of rotation angle and only affects engraved content (not the perimeter rectangle). The LightBurn watcher troubleshooting explored multiple deployment approaches before settling on a working solution using VBScript with hidden window startup.

## Changes Made

### Files Modified

- `wp-content/plugins/qsa-engraving/includes/SVG/class-svg-document.php`: Fixed top offset to only apply to engraved content (not perimeter/alignment marks) and to always apply relative to visual top regardless of rotation angle
- `wp-content/plugins/qsa-engraving/includes/Admin/class-admin-menu.php`: Updated Help & Information panel with Windows Service documentation for LightBurn watcher
- `docs/reference/lightburn-watcher/lightburn-watcher-service.js`: Multiple updates for Windows Service compatibility (file logging, absolute paths, graceful shutdown)
- `docs/reference/lightburn-watcher/install-service.js`: New file - Windows Service installation script using node-windows
- `docs/reference/lightburn-watcher/uninstall-service.js`: New file - Windows Service uninstallation script
- `docs/reference/lightburn-watcher/package.json`: New file - Node.js dependencies for watcher
- `docs/reference/lightburn-watcher/README.md`: New file - Windows Service documentation

### Tasks Addressed

- `DEVELOPMENT-PLAN.md` - Phase 7: LightBurn Integration - ongoing operational improvements
- SVG generation and coordinate transformation refinements (Phase 4 follow-up)

### New Functionality Added

1. **SVG Top Offset Fix**: The top offset setting now correctly shifts only the engraved content (modules, Micro-ID, Data Matrix, text) while leaving the perimeter rectangle and alignment marks fixed. This allows fine-tuning module positions without affecting the alignment boundary.

2. **SVG Rotation + Offset Interaction**: Top offset now correctly applies relative to the visual top of the canvas regardless of rotation angle. For 90/180/270 degree rotations, the translation direction is adjusted so "down" always means toward the visual bottom.

3. **Windows Service Version of Watcher**: Created a service-ready version of the LightBurn watcher with file-based logging, absolute paths, and graceful shutdown handling.

### Problems & Bugs Fixed

1. **SVG Top Offset Applied to Perimeter**: Fixed by moving alignment marks rendering OUTSIDE the offset group in `render()` method. Only module content is now wrapped in the offset transform.

2. **Top Offset Direction Wrong at Rotation**: Fixed by adjusting translation direction in `render_offset_group_open()` based on current rotation angle:
   - 0 degrees: translate(0, +offset) - down is +Y
   - 90 degrees: translate(+offset, 0) - after 90 CW, down is +X
   - 180 degrees: translate(0, -offset) - after 180, down is -Y
   - 270 degrees: translate(-offset, 0) - after 270 CW, down is -X

3. **Windows Service Session 0 Isolation** (not fully resolved): Discovered that Windows Services run in Session 0, which is isolated from the user desktop. This means services cannot display GUI applications like LightBurn regardless of service account configuration.

### Git Commits

Key commits from this session (newest first):
- `2e8a77c` - Update dashboard Help & Information for Windows Service
- `69d3a99` - Add Windows Service version of LightBurn watcher
- `20326c7` - Fix top offset to always apply relative to visual top regardless of rotation
- `aedf420` - Fix top offset to only apply to engraved content, not perimeter

## Technical Decisions

1. **SVG Structure Reorganization**: Moved alignment marks rendering OUTSIDE any transform groups. The render order is now: rotation group open > alignment marks > offset group open > modules > offset group close > rotation group close. This ensures the perimeter rectangle remains fixed while module content can be adjusted.

2. **Rotation-Aware Offset**: Rather than applying offset in a fixed direction, the offset translation is calculated based on the current rotation so it always moves content in the visual "down" direction from the user's perspective.

3. **Windows Service Abandoned for VBScript Approach**: After extensive troubleshooting, determined that Windows Services cannot interact with the user desktop due to Session 0 isolation. The working solution uses:
   - A VBScript wrapper that runs the Node.js script with a hidden window
   - The VBS shortcut placed in the Windows Startup folder
   - Runs in the user session with full desktop access

4. **Command-Line Argument vs UDP LOADFILE**: The final working watcher uses LightBurn command-line argument (`LightBurn.exe [file.svg]`) instead of UDP LOADFILE command, as this is 100% reliable compared to the intermittent UDP approach.

## Current State

### SVG Generation
- Top offset correctly shifts only engraved content
- Offset direction adjusts based on rotation for consistent visual behavior
- Perimeter rectangle and alignment marks remain fixed

### LightBurn Watcher
- Windows Service version exists in `docs/reference/lightburn-watcher/` but has Session 0 isolation limitation
- Working deployment uses VBScript + Startup folder approach on production workstation
- Watcher downloads SVG files via SFTP, kills LightBurn, restarts with file as command-line argument
- Dashboard documentation references Windows Service (may need updating)

### Dashboard
- Help & Information panel updated with Windows Service troubleshooting guide
- Settings page shows LightBurn watcher configuration details
- Technical setup guide includes service management commands

## Next Steps

### Immediate Tasks

- [ ] Update dashboard documentation to reflect actual VBS/Startup folder deployment (if Windows Service not used)
- [ ] Test VBS/Startup folder approach for long-term reliability on production workstation
- [ ] Clean up reference documentation to match actual deployment method
- [ ] Consider adding watcher health check indicator to dashboard

### Known Issues

- **Documentation Mismatch**: Dashboard Help & Information references Windows Service approach, but actual deployment may use VBS/Startup folder
- **Multiple Watcher Instances**: During troubleshooting, multiple watcher instances were left running; need to verify single instance on production
- **Log File Location**: LocalSystem vs user account have different home directories; production watcher needs consistent log location

## Notes for Next Session

### Windows Service Session 0 Isolation

This is a fundamental Windows limitation that cannot be worked around:
- Windows Services run in Session 0, which is isolated from all user sessions
- Even if the service runs under a user account, it still cannot interact with the user's desktop
- GUI applications started by services are invisible to users

### Working Deployment Approach

The VBScript wrapper approach that works:
1. `start-watcher.vbs` in user's Startup folder
2. VBS uses `WshShell.Run` with parameter 0 to hide console window
3. Runs in user's session with desktop access
4. LightBurn is visible because it's started in the same session

### UDP vs Command-Line

The original watcher used UDP LOADFILE command after starting LightBurn. This was intermittently unreliable. Since we restart LightBurn for each file anyway, passing the file as a command-line argument is simpler and 100% reliable.

### Key Troubleshooting Commands (Windows)

```cmd
:: Check for running watcher processes
tasklist /FI "IMAGENAME eq node.exe" /V

:: Kill all node processes
taskkill /F /IM node.exe

:: Check Windows Service status
sc query "LightBurn SFTP Watcher"

:: View watcher log
type C:\Users\Production\lightburn-watcher.log
```

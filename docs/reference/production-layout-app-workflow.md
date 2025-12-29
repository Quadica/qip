Production Layout App v1.4.py - High-Level Workflow

This desktop application manages the engraving process for LED module arrays (called "frets" or PCBs). Here's the step-by-step process:

### 1. Startup & Configuration
- Loads program settings from Google Sheets (including IR LED identifiers)
- Loads PCB type definitions from Google Sheets (available PCB types with X/Y offset calibrations)
- Loads production data from a CSV file on a shared network drive (production list.csv)
- Establishes UDP connection to LightBurn (laser engraving software)

### 2. PCB Type Selection
- Operator selects a PCB type from dropdown (shows only types present in current production batch)
- App loads layout definition for that PCB type from a local CSV file containing:
  - PCB dimensions (rows, columns)
  - Module positions (X/Y coordinates)
  - Text engraving positions for each module (LED codes, lens codes, connector codes)
  - Center alignment point

### 3. Instance Grouping
- Production items are automatically grouped into PCB instances (one instance = one fret)
- Each fret holds a fixed number of modules (varies by PCB type)
- Example: 24 modules in production → 3 frets of 8 modules each

### 4. Visual Review & Editing
- Displays the PCB layout with:
  - Background image of the PCB
  - LED codes positioned at each module location
  - Navigation between frets ("Previous PCB" / "Next PCB")
- Operator can:
  - Mark modules as faulty by clicking (red X appears, module skipped)
  - Mark entire rows/columns as faulty via checkboxes
  - Add modules manually for custom configurations
  - Clear fret to start fresh
- When modules are marked faulty, production data automatically redistributes to fill remaining positions

### 5. SVG Export
**Single Export:**
1. Generates SVG file with text positioned for laser engraving
2. Applies PCB-specific X/Y offset calibration
3. Adds alignment cross at center point
4. Adds 205x205mm boundary rectangle
5. Saves to shared network drive with naming: {BatchID}_{PCBType}_{Sequence}.svg

**Batch Export:**
- Exports all frets of the current PCB type sequentially
- Clears each fret's data after successful export

### 6. LightBurn Integration
After each SVG export:
1. Launches LightBurn application
2. Sends UDP command to load the SVG file
3. Shows "File Ready For Engraving" dialog
4. Operator performs engraving in LightBurn
5. Operator clicks "Engraving Finished"
6. App force-closes LightBurn
7. In batch mode: proceeds to next file automatically

### 7. Data Sources Summary
| Data            | Source                    | Purpose                                                      |
|-----------------|---------------------------|--------------------------------------------------------------|
| Production list | CSV on shared drive       | What modules to engrave (batch ID, product codes, LED codes) |
| PCB layouts     | Per-PCB CSV files locally | Where to position text on each PCB type                      |
| PCB offsets     | Google Sheets             | Calibration adjustments per PCB type                         |
| IR LED list     | Google Sheets             | Identifies IR variants requiring different PCB layouts       |
| Exported SVGs   | Shared drive folder       | Output files for laser engraving                             |

### Key Characteristics
- Windows desktop app (Tkinter GUI, uses Windows-specific features)
- Tightly coupled to LightBurn via UDP protocol on localhost
- Fret-centric workflow - manages one fret at a time with batch capability
- No serial numbers - this version only handles LED codes, lens codes, connector codes
- Manual operator control - operator must confirm each engraving completion
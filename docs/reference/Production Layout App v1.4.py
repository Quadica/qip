# Quadica Production Layout App V2.0 - 2024 Chris Warris

import tkinter as tk
import pandas as pd
import svgwrite
import os
import csv
import logging
import requests
import socket
import logging
import time
import win32gui
import win32con
import win32process
import psutil

from dataclasses import dataclass
from datetime import datetime
from PIL import Image, ImageTk
from functools import lru_cache
from tkinter import ttk, messagebox
from io import StringIO
from typing import List, Dict, Any, Optional, Tuple

WORKING_DIRECTORY = r"Q:/Shared drives/Quadica/Custom Software/Quadica Production Layout App/Text Position Data"
pcb_url = "https://docs.google.com/spreadsheets/d/1h8EJrRsPvCfTVxdSzLcAE-ID2eZ-scdMx913gR_Z1ZU/export?format=csv&gid=0"
program_settings = "https://docs.google.com/spreadsheets/d/1h8EJrRsPvCfTVxdSzLcAE-ID2eZ-scdMx913gR_Z1ZU/export?format=csv&gid=852408781"
PRODUCTION_FILE_PATH = r"Q:/Shared drives/Quadica/Production/production list.csv"

@dataclass
class ProductionData:
    product_name: str
    pcb_type: str
    batch_id: str
    order_number: str
    led_codes: List[str]
    lens_code: Optional[str]
    connector_code: Optional[str]

@dataclass
class PCBInstance:
    data: List[ProductionData]
    faulty_modules: List[bool]
    rows: int
    columns: int

    def __init__(self, modules_count, rows, columns):
        self.data = []
        self.faulty_modules = [False] * modules_count
        self.rows = rows
        self.columns = columns

    def clear_data(self):
        self.data = []

    def reset_faulty_modules(self):
        self.faulty_modules = [False] * len(self.faulty_modules)

    def clear_all(self):
        self.clear_data()
        self.reset_faulty_modules()

    def is_row_faulty(self, row):
        start = row * self.columns
        end = start + self.columns
        return all(self.faulty_modules[start:end])

    def is_column_faulty(self, col):
        return all(self.faulty_modules[i] for i in range(col, len(self.faulty_modules), self.columns))

@dataclass
class Monitor:
    x: int
    y: int
    width: int
    height: int

class LightBurnController:
    def __init__(self):
        self.udp_ip = "127.0.0.1"
        self.udp_out_port = 19840
        self.udp_in_port = 19841
        self.out_sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.in_sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        
        try:
            self.in_sock.bind((self.udp_ip, self.udp_in_port))
            self.in_sock.settimeout(1.0)  # Set 1 second timeout
        except Exception as e:
            logging.error(f"Failed to initialize LightBurn controller: {e}")

    def _find_lightburn_windows(self) -> list:
        """Find all LightBurn-related window handles."""
        lightburn_windows = []
        
        def enum_window_callback(hwnd, _):
            if win32gui.IsWindowVisible(hwnd):
                window_text = win32gui.GetWindowText(hwnd)
                if "LightBurn" in window_text:
                    _, process_id = win32process.GetWindowThreadProcessId(hwnd)
                    try:
                        process = psutil.Process(process_id)
                        if "lightburn" in process.name().lower():
                            lightburn_windows.append(hwnd)
                    except (psutil.NoSuchProcess, psutil.AccessDenied):
                        pass
        
        win32gui.EnumWindows(enum_window_callback, None)
        return lightburn_windows

    def _force_close_windows(self) -> None:
        """Force close all LightBurn windows."""
        windows = self._find_lightburn_windows()
        for hwnd in windows:
            try:
                # Send WM_CLOSE message first for graceful shutdown
                win32gui.PostMessage(hwnd, win32con.WM_CLOSE, 0, 0)
                time.sleep(0.5)  # Give window time to close gracefully
                
                # If window still exists, force close it
                if win32gui.IsWindow(hwnd):
                    _, process_id = win32process.GetWindowThreadProcessId(hwnd)
                    try:
                        process = psutil.Process(process_id)
                        process.terminate()
                    except (psutil.NoSuchProcess, psutil.AccessDenied) as e:
                        logging.warning(f"Failed to terminate LightBurn process: {e}")
            except Exception as e:
                logging.error(f"Error closing LightBurn window: {e}")

    def send_command(self, command: str) -> Tuple[bool, str]:
        try:
            self.out_sock.sendto(command.encode(), (self.udp_ip, self.udp_out_port))
            data, addr = self.in_sock.recvfrom(1024)
            response = data.decode()
            logging.debug(f"LightBurn command: {command}, Response: {response}")
            return True, response
        except socket.timeout:
            logging.debug(f"LightBurn command timeout: {command}")
            return False, "Timeout waiting for LightBurn response"
        except Exception as e:
            logging.debug(f"LightBurn command error: {command}, Error: {str(e)}")
            return False, str(e)

    def ping(self) -> bool:
        success, _ = self.send_command("PING")
        return success

    def load_file(self, filepath: str) -> bool:
        success, _ = self.send_command(f"LOADFILE:{filepath}")
        return success

    def close_file(self) -> bool:
        self._force_close_windows()  # Close windows before sending command
        #success, _ = self.send_command("CLOSE")
        #return success

    def force_close(self) -> bool:
        self._force_close_windows()  # Close windows before sending command
        #success, _ = self.send_command("FORCECLOSE")
        #return success

    def cleanup(self):
        self._force_close_windows()  # Ensure all windows are closed
        self.in_sock.close()
        self.out_sock.close()

class PCBViewer:


# 1. Core Setup and Initialization

    # Initialize the PCB Viewer application, set up the UI, and load initial data
    def __init__(self, root):
        self.root = root
        self.root.title("Quadica Production Layout App")

        # Get all monitor info
        monitors = self.get_monitors()
        if len(monitors) > 1:
            # Get the secondary monitor's geometry
            secondary_monitor = monitors[1]  # Second monitor
            
            # Set window properties
            self.root.state('zoomed')  # This maximizes the window
            self.root.attributes('-fullscreen', False)  # Ensure it's not fullscreen
            
            # Calculate position to center on secondary monitor
            x = secondary_monitor.x + (secondary_monitor.width - self.root.winfo_reqwidth()) // 2
            y = secondary_monitor.y + (secondary_monitor.height - self.root.winfo_reqheight()) // 2
            
            # Position the window on the secondary monitor
            self.root.geometry(f"{secondary_monitor.width}x{secondary_monitor.height}+{x}+{y}")
        else:
            # Fallback to original behavior for single monitor
            screen_width = self.root.winfo_screenwidth()
            screen_height = self.root.winfo_screenheight()
            self.root.state('zoomed')
            self.root.attributes('-fullscreen', False)
            self.root.geometry(f"{screen_width}x{screen_height}+0+0")
        
        # Ensure window appears on top initially
        self.root.lift()
        self.root.attributes('-topmost', True)
        self.root.after_idle(self.root.attributes, '-topmost', False)
        
        self.pcb_data_dir = WORKING_DIRECTORY
        self.pcb_data = None
        self.production_data = []
        self.current_pcb_type = None
        self.current_instance_index = 0
        self.pcb_instances = []
        self.file_number = 1
        self.row_checkboxes = []
        self.col_checkboxes = []
        self.unique_pcb_types = set()
        self.image_cache = {}
        self.batch_id = "N/A"  # Initialize batch_id with a default value
        self.program_settings = self.load_program_settings()
        self.ir_leds = self.program_settings.get('ir_leds', [])
        self.available_pcbs = self.load_available_pcbs()
        self.root.protocol("WM_DELETE_WINDOW", self.on_closing)
        self.scale = 1.0  # Default self.scale
        self.root.bind("<Configure>", self.on_window_resize)

        # Add after initial screen setup but before setup_ui()
        self.lightburn = LightBurnController()

        # Color scheme
        self.color_bg_main = '#7b90a4'
        self.color_bg_work = 'white'
        self.color_text_main = '#b0d0e3'
        self.color_text_muted = 'lightgrey'
        self.color_button_bg = '#4a6984'
        self.color_button_fg = 'white'
        self.color_button_active = '#b0d0e3'
        self.color_faulty = 'red'

        logging.basicConfig(level=logging.DEBUG, 
                            format='%(asctime)s - %(levelname)s - %(message)s',
                            handlers=[
                                logging.FileHandler("pcb_viewer.log"),
                                logging.StreamHandler()
                            ])

        logging.info("PCB Viewer initialized")

        self.setup_ui()  # Set up the UI first
        
        self.available_pcbs = self.load_available_pcbs()
        self.load_and_process_production_data()  # Load production data
        self.populate_pcb_list()  # Populate the PCB list after UI is set up

    # Set up the user interface components including frames, buttons, and canvas
    def setup_ui(self):
        self.root.configure(bg=self.color_bg_main)
        self.root.geometry("1920x1080")  # Set the window size to 1920x1080
        self.root.resizable(True, True)  # Disable window resizing
        
        # Create custom styles
        self.create_custom_styles()

        # Create tooltip style
        tooltip_style = ttk.Style()
        tooltip_style.configure('Tooltip.TLabel', 
                            background='#ffffe0', 
                            foreground='black',
                            padding=5)
        
        # Main work area
        self.work_frame = tk.Frame(self.root, bg=self.color_bg_work)
        self.work_frame.place(relx=0.05, rely=0.1, relwidth=0.9, relheight=0.75)
        
        self.canvas = tk.Canvas(self.work_frame, bg=self.color_bg_work)
        self.canvas.pack(fill=tk.BOTH, expand=True)
        
        # Top bar elements
        self.pcb_var = tk.StringVar()
        self.pcb_dropdown = ttk.Combobox(self.root, 
                                        textvariable=self.pcb_var, 
                                        state="readonly",
                                        style="TCombobox",
                                        font="Arial 16 bold")
        self.pcb_dropdown.place(relx=0.05, rely=0.03, relwidth=0.1, relheight=0.04)
        self.pcb_dropdown.bind("<<ComboboxSelected>>", self.on_pcb_selected)
        
        self.pcb_type_label = tk.Label(self.root, text="", font=("Arial", 42, "bold"), 
                                    bg=self.color_bg_main, fg=self.color_text_muted)
        self.pcb_type_label.place(relx=0.20, rely=0.05, anchor="w")
        
        self.batch_id_label = tk.Label(self.root, text=f"Batch: {self.batch_id}", font=("Arial", 42, "bold"), 
                                    bg=self.color_bg_main, fg=self.color_text_muted)
        self.batch_id_label.place(relx=0.80, rely=0.05, anchor="e")

        self.refresh_button = tk.Button(self.root, 
                                    text="Refresh Data", 
                                    command=self.refresh_production_data,
                                    bg=self.color_button_bg, 
                                    fg=self.color_button_fg,
                                    activebackground=self.color_button_active,
                                    activeforeground=self.color_button_fg,
                                    font=('Arial', 16, 'bold'))
        self.refresh_button.place(relx=0.85, rely=0.005, relwidth=0.1, relheight=0.04)
        
        # Add clear fret button
        self.clear_fret_button = tk.Button(self.root, 
                                        text="Clear Fret", 
                                        command=self.clear_fret,
                                        bg=self.color_button_bg, 
                                        fg=self.color_button_fg,
                                        activebackground=self.color_button_active,
                                        activeforeground=self.color_button_fg,
                                        font=('Arial', 16, 'bold'))
        self.clear_fret_button.place(relx=0.85, rely=0.005 + 0.04 + 0.01, relwidth=0.1, relheight=0.04)
        
        # Add tooltip
        self.create_tooltip(self.clear_fret_button,
            "Clear all module data and create empty PCB layout for custom configuration")
        
        # Center buttons with proportional scaling
        button_width = 0.1
        button_height = 0.04
        button_spacing = 0.01

        self.export_button = tk.Button(self.root, text="Export SVG", command=self.export_svg,
                                    bg=self.color_button_bg, fg=self.color_button_fg,
                                    activebackground=self.color_button_active,
                                    activeforeground=self.color_button_fg,
                                    font=('Arial', 16, 'bold'))
        self.export_button.place(relx=0.5 - button_width/2, rely=0.005, relwidth=button_width, relheight=button_height)

        self.batch_export_button = tk.Button(self.root, text="Batch Export", command=self.batch_export_svg,
                                            bg=self.color_button_bg, fg=self.color_button_fg,
                                            activebackground=self.color_button_active,
                                            activeforeground=self.color_button_fg,
                                            font=('Arial', 16, 'bold'))
        self.batch_export_button.place(relx=0.5 - button_width/2, rely=0.005 + button_height + button_spacing, 
                                    relwidth=button_width, relheight=button_height)

        # Bottom bar elements
        self.prev_button = tk.Button(self.root, text="Previous PCB", command=self.prev_pcb,
                                    bg=self.color_button_bg, fg=self.color_button_fg,
                                    activebackground=self.color_button_active,
                                    activeforeground=self.color_button_fg,
                                    font=('Arial', 16, 'bold'), 
                                    pady=10,
                                    state='disabled')
        self.prev_button.place(relx=0.15, rely=0.92, relwidth=0.15, relheight=0.05)

        self.next_button = tk.Button(self.root, text="Next PCB", command=self.next_pcb,
                                    bg=self.color_button_bg, fg=self.color_button_fg,
                                    activebackground=self.color_button_active,
                                    activeforeground=self.color_button_fg,
                                    font=('Arial', 16, 'bold'),
                                    pady=10,
                                    state='disabled')
        self.next_button.place(relx=0.7, rely=0.92, relwidth=0.15, relheight=0.05)

        self.add_module_button = tk.Button(self.root, text="Add Module", command=self.open_add_module_popup,
                                        bg=self.color_button_bg, fg=self.color_button_fg,
                                        disabledforeground='gray',
                                        activebackground=self.color_button_active,
                                        activeforeground=self.color_button_fg,
                                        font=('Arial', 16, 'bold'), pady=5)
        self.add_module_button.place(relx=0.45, rely=0.92, relwidth=0.1)
        
        self.instance_label = tk.Label(self.root, text="", font=("Arial", 24),
                                    bg=self.color_bg_main, fg=self.color_text_muted)
        self.instance_label.place(relx=0.4, rely=0.86, relwidth=0.2)
        
        self.add_module_button['state'] = 'disabled'

        # Add tooltips to dropdown
        self.create_tooltip(self.pcb_dropdown, 
            "Select a PCB type to begin. Available PCBs are loaded from production data.")

        # Add tooltips to buttons
        self.create_tooltip(self.export_button,
            "Export the current PCB layout as an SVG file for laser engraving")
        
        self.create_tooltip(self.batch_export_button,
            "Export all PCB layouts of this type as SVG files")
        
        self.create_tooltip(self.prev_button,
            "Go to previous PCB in current batch")
        
        self.create_tooltip(self.next_button,
            "Go to next PCB in current batch")
        
        self.create_tooltip(self.add_module_button,
            "Add a new module to the current PCB layout")

        self.create_tooltip(self.refresh_button, 
            "Reload production data from the CSV file and update the display")

        self.populate_pcb_list()

    # Colour scheme for UI elements
    def create_custom_styles(self):
        style = ttk.Style()
        
        # Create a custom theme
        style.theme_create("CustomTheme", parent="alt", settings={
            "TButton": {
                "configure": {
                    "background": self.color_button_bg,
                    "foreground": self.color_button_fg,
                    "font": ('Arial', 16, 'bold'),
                    "padding": 5
                },
                "map": {
                    "background": [("active", self.color_button_active)],
                    "foreground": [("active", self.color_button_fg)]
                }
            },
            "TCombobox": {
                "configure": {
                    "selectbackground": self.color_button_bg,
                    "fieldbackground": self.color_text_muted,
                    "background": self.color_button_bg,
                    "foreground": "white",
                    "padding": "5px",
                }
            }
        })
        
        # Set the custom theme
        style.theme_use("CustomTheme")

    # Create tooltips
    def create_tooltip(self, widget, text):
        tooltip = tk.Label(self.root, 
                        text=text,
                        background='#ffffe0',
                        foreground='black',
                        relief='solid',
                        borderwidth=1,
                        font=("Arial", "10", "normal"))
        tooltip.place_forget()

        def on_enter(event):
            tooltip.lift()
            
            # Get widget's absolute position relative to root window
            widget_x = widget.winfo_x()
            widget_y = widget.winfo_y()
            
            # Get dimensions
            tooltip_width = tooltip.winfo_reqwidth()
            widget_width = widget.winfo_width()
            
            # Calculate position - centered horizontally under widget
            x = widget_x + (widget_width - tooltip_width) // 2
            y = widget_y + widget.winfo_height() + 2
            
            # Ensure tooltip stays within window bounds
            window_width = self.root.winfo_width()
            if x + tooltip_width > window_width:
                x = window_width - tooltip_width
            if x < 0:
                x = 0
                
            tooltip.place(x=x, y=y)

        def on_leave(event):
            tooltip.place_forget()

        widget.bind('<Enter>', on_enter)
        widget.bind('<Leave>', on_leave)

    # Clear image cache on close
    def on_closing(self):
        self.lightburn.cleanup()
        self.cleanup_cache()
        self.root.destroy()

    # Limit the cache sizes to prevent memory issues
    def cleanup_cache(self):
        max_cache_size = 20
        while len(self.image_cache) > max_cache_size:
            self.image_cache.pop(next(iter(self.image_cache)))

    @staticmethod
    def get_monitors() -> List[Monitor]:
        """Get information about all connected monitors."""
        try:
            from screeninfo import get_monitors as get_screen_monitors
            monitors = []
            for m in get_screen_monitors():
                monitors.append(Monitor(m.x, m.y, m.width, m.height))
            return monitors
        except ImportError:
            logging.warning("screeninfo module not found. Install with: pip install screeninfo")
            # Return a single monitor based on root geometry as fallback
            return [Monitor(0, 0, tk.Tk().winfo_screenwidth(), tk.Tk().winfo_screenheight())]


# 2. Data Management
    ## 2.1 Configuration Loading
    
    # Retrieve program settings from Google Sheets
    def load_program_settings(self) -> Dict[str, Any]:

        settings = {}
        try:
            # Fetch data from Google Sheets
            response = requests.get(program_settings)
            if response.status_code != 200:
                raise Exception(f"Failed to fetch program settings: {response.status_code}")

            # Convert response content to StringIO for CSV reading
            csv_content = StringIO(response.text)
            csv_reader = csv.DictReader(csv_content)
            
            # Process each row in the settings
            for row in csv_reader:
                setting = row.get('setting', '').strip()
                value = row.get('value', '').strip()
                
                # Special handling for IR LED list (check both possible names)
                if setting in ['ir_leds', 'ir_leds_list']:
                    # Split the comma-separated string into a list, strip whitespace, and convert to uppercase
                    ir_leds = [led.strip().upper() for led in value.split(',') if led.strip()]
                    settings['ir_leds'] = ir_leds
                else:
                    settings[setting] = value
            
            logging.info(f"Successfully loaded program settings: {settings}")
            return settings
                
        except requests.RequestException as e:
            logging.error(f"Network error while fetching program settings: {str(e)}")
            messagebox.showerror("Network Error", 
                            "Failed to fetch program settings from Google Sheets. Check your internet connection.")
            return {}
        except Exception as e:
            logging.error(f"Error loading program settings: {str(e)}")
            messagebox.showerror("Error", 
                            f"An error occurred while loading program settings: {str(e)}")
            return {}

    # Load master list of PCBs
    def load_available_pcbs(self):
        try:
            # Fetch data from Google Sheets
            response = requests.get(pcb_url)
            if response.status_code != 200:
                raise Exception(f"Failed to fetch data: {response.status_code}")

            # Convert response content to StringIO for CSV reading
            csv_content = StringIO(response.text)
            pcb_data = {}
            
            # Read CSV content
            csv_reader = csv.DictReader(csv_content)
            for row in csv_reader:
                pcb_name = row['pcb'].strip()
                # Convert offset values, defaulting to 0 if empty or invalid
                try:
                    x_offset = float(row['x_offset']) if row['x_offset'] else 0
                    y_offset = float(row['y_offset']) if row['y_offset'] else 0
                except (ValueError, TypeError):
                    x_offset = 0
                    y_offset = 0
                
                pcb_data[pcb_name] = {
                    'x_offset': x_offset,
                    'y_offset': y_offset
                }
            
            logging.info(f"Successfully loaded {len(pcb_data)} PCBs from Google Sheets")
            return pcb_data
            
        except requests.RequestException as e:
            logging.error(f"Network error while fetching PCB list: {str(e)}")
            messagebox.showerror("Network Error", 
                            "Failed to fetch PCB list from Google Sheets. Check your internet connection.")
            return {}
        except Exception as e:
            logging.error(f"Error loading PCB list from Google Sheets: {str(e)}")
            messagebox.showerror("Error", 
                            f"An error occurred while loading PCB list: {str(e)}")
            return {}


    ## 2.2 Production Data Processing

    # Load and parse production data from a CSV file
    def load_production_data(self, file_path: str) -> List[ProductionData]:
        production_data = []
        try:
            with open(file_path, 'r', newline='') as csvfile:
                dialect = csv.Sniffer().sniff(csvfile.read(1024))
                csvfile.seek(0)
                csv_reader = csv.reader(csvfile, dialect)
                
                next(csv_reader, None)  # Skip header
                
                for row in csv_reader:
                    if len(row) > 6:
                        parsed_data = self.parse_production_row(row)
                        if parsed_data:
                            # Check for IR LEDs
                            has_ir = any(led_code in self.ir_leds for led_code in parsed_data.led_codes)
                            
                            # Parse product name more carefully
                            parts = parsed_data.product_name.split('-')
                            if len(parts) >= 2:
                                # Handle the second part which might have a revision letter
                                second_part = parts[1]
                                base_number = ''.join(filter(str.isdigit, second_part))  # Extract just the numbers
                                
                                # Create base PCB name without revision letter
                                base_pcb = f"{parts[0]}-{base_number}".lower()
                                ir_variant = f"{base_pcb}-ir".lower()

                                # Set PCB type based on IR presence and variant availability
                                if has_ir and ir_variant in self.available_pcbs:
                                    parsed_data.pcb_type = ir_variant
                                else:
                                    parsed_data.pcb_type = self.determine_pcb_type(parsed_data.product_name)
                            
                            # Add to unique PCB types after determining correct type
                            self.unique_pcb_types.add(parsed_data.pcb_type)
                            production_data.append(parsed_data)

                logging.info(f"Total parsed production data: {len(production_data)}")
                logging.info(f"Unique PCB types found: {len(self.unique_pcb_types)}")
                logging.info(f"PCB types with IR variants: {[pcb for pcb in self.unique_pcb_types if pcb.endswith('-ir')]}")
                
            return production_data
            
        except Exception as e:
            messagebox.showerror("Error", f"An error occurred while reading the production data: {str(e)}")
            logging.error(f"Error loading production data: {str(e)}")
        return production_data

    # Parse a single row of production data and create a ProductionData object
    def parse_production_row(self, row: List[str]) -> Optional[ProductionData]:
        try:
            product_name = row[1] if len(row) > 1 else ""  # Product name is in the second column
            pcb_type = self.determine_pcb_type(product_name)
            batch_id = row[0] if row else ""  # Batch ID is in the first column (C1)
            order_number = row[2] if len(row) > 2 else ""  # Order number is in the third column (C3)
            led_codes = []
            lens_code = None
            connector_code = None

            # Start from C7 (index 6) and look for short codes
            for cell in row[6:]:
                if len(cell) == 2 and cell[0].isalpha() and cell[1].isdigit():
                    led_codes.append(cell)
                elif len(cell) == 3:
                    if cell.startswith('L'):
                        lens_code = cell
                    elif cell.startswith('C'):
                        connector_code = cell

            # If no short codes found in C7, it might be a single LED product
            if not led_codes and len(row) > 6 and len(row[6]) == 2 and row[6][0].isalpha() and row[6][1].isdigit():
                led_codes = [row[6]]

            #print(product_name, pcb_type, batch_id, order_number, led_codes, lens_code, connector_code)
            return ProductionData(product_name, pcb_type, batch_id, order_number, led_codes, lens_code, connector_code)
        except Exception as e:
            logging.error(f"Error parsing row: {row}. Error: {str(e)}")
            return None

    # Determine the PCB type based on the product name
    def determine_pcb_type(self, product_name: str) -> str:
        parts = product_name.split('-')
        
        determined_pcb_type = None

        if parts[0] == "MR":
            # Handle MR products
            if len(parts) >= 3:
                mr_variant = parts[-1]
                
                # Map MR products to LXB bases
                if mr_variant == "20T":
                    determined_pcb_type = "LXB-RT20bb"
                elif mr_variant == "20S":
                    determined_pcb_type = "LXB-RS20ag"
                elif mr_variant == "10S":
                    determined_pcb_type = "LXB-RS10ac"
                else:
                    logging.warning(f"Unknown MR variant: {mr_variant}")
        
        # Handle other product types (including fallback for unknown MR variants)
        if not determined_pcb_type and len(parts) >= 2:
            prefix = f"{parts[0]}-{parts[1]}"
            
            # Try to find an exact match for the first two parts
            for pcb in self.available_pcbs:
                if prefix.lower() in pcb.lower():
                    determined_pcb_type = pcb
                    break
            
            # If no exact match, try a more flexible match, but still requiring both parts
            if not determined_pcb_type:
                for pcb in self.available_pcbs:
                    if parts[0].lower() in pcb.lower() and parts[1].lower() in pcb.lower():
                        # Additional check to ensure parts are adjacent or only separated by a character
                        pcb_parts = pcb.lower().split('-')
                        for i in range(len(pcb_parts) - 1):
                            if pcb_parts[i] == parts[0].lower() and pcb_parts[i+1] == parts[1].lower():
                                determined_pcb_type = pcb
                                break
                        if determined_pcb_type:
                            break

        # If no match found, use a default naming convention
        if not determined_pcb_type:
            logging.warning(f"Unable to determine PCB type for product: {product_name}")
            determined_pcb_type = f"{parts[0]}-{parts[1]}" if len(parts) >= 2 else product_name

        return determined_pcb_type
    
    # Determine if the module contains an IR led
    def has_ir_led(self, product_name: str) -> bool:
        # Find the matching production data for this product
        for prod_data in self.production_data:
            if prod_data.product_name == product_name:
                # Check if any of the LED codes are in the IR LED list
                return any(led_code in self.ir_leds for led_code in prod_data.led_codes)
        return False

    # Load production data and populate the list of PCBS
    def load_and_process_production_data(self):
        try:
            self.production_data = self.load_production_data(PRODUCTION_FILE_PATH)
            self.populate_pcb_list()
            
            # Extract the batch ID from the first row of production data
            if self.production_data:
                self.batch_id = self.production_data[0].batch_id
                self.batch_id_label.config(text=f"Batch: {self.batch_id}")
            else:
                self.batch_id = "N/A"
                self.batch_id_label.config(text="Batch: N/A")
                
        except FileNotFoundError:
            error_msg = f"Production file not found at: {PRODUCTION_FILE_PATH}"
            logging.error(error_msg)
            messagebox.showerror("Error", error_msg)
        except Exception as e:
            error_msg = f"Error loading production data: {str(e)}"
            logging.error(error_msg)
            messagebox.showerror("Error", error_msg)

    # Reload production data
    def refresh_production_data(self):
        try:
            # Clear existing data
            self.production_data = []
            self.unique_pcb_types = set()
            
            # Reload production data
            self.production_data = self.load_production_data(PRODUCTION_FILE_PATH)
            
            # Update batch ID if available
            if self.production_data:
                self.batch_id = self.production_data[0].batch_id
                self.batch_id_label.config(text=f"Batch: {self.batch_id}")
            else:
                self.batch_id = "N/A"
                self.batch_id_label.config(text="Batch: N/A")
            
            # Repopulate PCB list
            self.populate_pcb_list()
            
            # Reset dropdown to default and clear work area
            self.pcb_var.set("Select a PCB")
            self.canvas.delete("all")
            self.pcb_type_label.config(text="")
            self.pcb_instances = []
            self.current_instance_index = 0
            self.update_checkbox_states()
            self.add_module_button['state'] = 'disabled'
            self.next_button['state'] = 'disabled'
            self.prev_button['state'] = 'disabled'
            
            messagebox.showinfo("Success", "Production data refreshed successfully")
            
        except Exception as e:
            error_msg = f"Error refreshing production data: {str(e)}"
            logging.error(error_msg)
            messagebox.showerror("Error", error_msg)

    # Remove all module data for this PCB
    def clear_fret(self):
        if not self.pcb_data:
            messagebox.showerror("Error", "Please select a PCB type first")
            return
            
        if messagebox.askyesno("Confirm Clear", "This will remove all module data. Continue?"):
            # Create new empty instance
            modules_count = len(self.pcb_data['Modules'])
            rows = int(self.pcb_data['Rows'])
            columns = int(self.pcb_data['Columns'])
            
            self.pcb_instances = [PCBInstance(modules_count, rows, columns)]
            self.current_instance_index = 0
            
            # Update UI
            self.update_ui_after_changes()
            self.add_module_button['state'] = 'normal'

    ## 2.3 PCB Data Processing

    # Load PCB data from a CSV file and process it into a structured format
    def load_pcb_data(self, pcb_name: str) -> Optional[Dict[str, Any]]:
        file_path = os.path.join(WORKING_DIRECTORY, f"{pcb_name}.csv")
        try:
            df = pd.read_csv(file_path)
            
            # Check if required columns are present
            required_columns = ['Element', 'X', 'Y', 'Diameter', 'Height', 'Width', 'Columns', 'Rows']
            missing_columns = [col for col in required_columns if col not in df.columns]
            if missing_columns:
                raise ValueError(f"Missing required columns: {', '.join(missing_columns)}")

            # Extract geometry data
            geometry = df[df['Element'] == 'GEOMETRY']
            if geometry.empty:
                raise ValueError("No GEOMETRY data found in the CSV file")

            # Extract MODULE data
            module_data = df[df['Element'] == 'MODULE']
            if module_data.empty:
                raise ValueError("No MODULE data found in the CSV file")
            
            module_width = module_data['Width'].iloc[0]
            module_height = module_data['Height'].iloc[0]

            circles = df[df['Element'] == 'CIRCLE']
            text = df[df['Element'] == 'MTEXT']
            
            # Load the center point from the "Point" element
            point = df[df['Element'] == 'POINT']
            if point.empty:
                raise ValueError("No POINT data found in the CSV file")
            center_point = {'x': point['X'].iloc[0], 'y': point['Y'].iloc[0]}
            
            # Create a list of module positions
            modules = []
            for _, circle in circles.iterrows():
                module = {
                    'x': circle['X'],
                    'y': circle['Y'],
                    'diameter': circle['Diameter'],
                    'width': module_width,
                    'height': module_height,
                    'faulty': False,
                    'led_positions': [],
                    'connector_position': None,
                    'lens_position': None
                }
                
                # Calculate the rectangular bounding box
                left = circle['X'] - module_width / 2
                right = circle['X'] + module_width / 2
                top = circle['Y'] - module_height / 2
                bottom = circle['Y'] + module_height / 2

                for _, t in text.iterrows():
                    # Check if the text is within the rectangular bounding box
                    if left <= t['X'] <= right and top <= t['Y'] <= bottom:
                        position = {
                            'x': t['X'],
                            'y': t['Y'],
                            'rotation': t['Rotation'],
                            'height': t['TextHeight']
                        }
                        if t['TextString'].startswith('P'):
                            module['led_positions'].append(position)
                        elif t['TextString'].startswith('C'):
                            module['connector_position'] = position
                        elif t['TextString'].startswith('L'):
                            module['lens_position'] = position
                
                modules.append(module)
            
            # Debugging: Print information about loaded modules
            #print(f"Loaded {len(modules)} modules for {pcb_name}")
            #for i, module in enumerate(modules):
                #print(f"Module {i}: {len(module['led_positions'])} LED positions")

            return {
                'Height': geometry['Height'].iloc[0],
                'Width': geometry['Width'].iloc[0],
                'Columns': geometry['Columns'].iloc[0],
                'Rows': geometry['Rows'].iloc[0],
                'Circles': circles,
                'Text': text,
                'Modules': modules,
                'CenterPoint': center_point
            }
        except Exception as e:
            logging.error(f"Error loading PCB data for {pcb_name}: {str(e)}")
            messagebox.showerror("Error", f"An error occurred while reading PCB data: {str(e)}")
        return None

    # Create PCB instances based on the loaded production data
    def initialize_pcb_instances(self):
        self.pcb_instances = []
        matching_production_data = [data for data in self.production_data if data.pcb_type == self.current_pcb_type]
        
        modules_per_pcb = len(self.pcb_data['Modules'])
        for i in range(0, len(matching_production_data), modules_per_pcb):
            instance = PCBInstance(modules_per_pcb, int(self.pcb_data['Rows']), int(self.pcb_data['Columns']))
            instance.data = matching_production_data[i:i+modules_per_pcb]
            self.pcb_instances.append(instance)
        
        if not self.pcb_instances:
            self.pcb_instances.append(PCBInstance(modules_per_pcb, int(self.pcb_data['Rows']), int(self.pcb_data['Columns'])))

        self.current_instance_index = 0

    # Load the list of PCB types from a CSV file and populate the dropdown menu
    def populate_pcb_list(self):
        try:
            # Convert set to sorted list
            unique_pcb_types = sorted(list(self.unique_pcb_types))
            
            # Create a list of tuples: (display_value, actual_value)
            pcb_types_with_display = [("Select a PCB", "Select a PCB")] + [(pcb.upper(), pcb) for pcb in unique_pcb_types]
            
            # Update the dropdown values
            self.pcb_dropdown['values'] = [display for display, _ in pcb_types_with_display]
            
            # Store the mapping of display values to actual values
            self.pcb_dropdown_mapping = dict(pcb_types_with_display)
            
            if pcb_types_with_display:
                # Set the default selection to "Select a PCB"
                self.pcb_dropdown.set("Select a PCB")
            else:
                logging.warning("No PCB types loaded.")
            
            logging.info(f"Populated PCB list with {len(unique_pcb_types)} unique types")
        except Exception as e:
            error_msg = f"An error occurred while populating the PCB list: {str(e)}"
            logging.error(error_msg)
            messagebox.showerror("Error", error_msg)


    ## 2.4 Instance Management

    # Redistribute production data across PCB instances after marking modules as faulty
    def redistribute_data(self):
        if not self.pcb_instances:
            return

        all_data = [item for instance in self.pcb_instances for item in instance.data]
        modules_per_pcb = len(self.pcb_data['Modules'])
        rows = int(self.pcb_data['Rows'])
        columns = int(self.pcb_data['Columns'])
        
        # Preserve existing instances and their faulty statuses
        preserved_instances = self.pcb_instances
        self.pcb_instances = []
        
        data_index = 0
        for instance in preserved_instances:
            new_instance = PCBInstance(modules_per_pcb, rows, columns)
            new_instance.faulty_modules = instance.faulty_modules.copy()  # Preserve faulty status
            
            for i in range(modules_per_pcb):
                if not new_instance.faulty_modules[i] and data_index < len(all_data):
                    new_instance.data.append(all_data[data_index])
                    data_index += 1
            
            self.pcb_instances.append(new_instance)
            
            if data_index >= len(all_data):
                break
        
        # If there's still data left, create new instances as needed
        while data_index < len(all_data):
            new_instance = PCBInstance(modules_per_pcb, rows, columns)
            for i in range(modules_per_pcb):
                if data_index < len(all_data):
                    new_instance.data.append(all_data[data_index])
                    data_index += 1
                else:
                    break
            self.pcb_instances.append(new_instance)
        
        # Ensure we have at least one instance
        if not self.pcb_instances:
            self.pcb_instances.append(PCBInstance(modules_per_pcb, rows, columns))
        
        # Adjust current_instance_index if necessary
        if self.current_instance_index >= len(self.pcb_instances):
            self.current_instance_index = len(self.pcb_instances) - 1
        
        self.update_navigation_buttons()
        self.draw_pcb()
        self.cleanup_cache()

    # Insert new modules into product data
    def add_new_module(self, led_codes, lens_code, connector_code, quantity):
        product_name = f"{self.current_pcb_type}-{''.join(led_codes)}"
        current_instance = self.pcb_instances[self.current_instance_index]
        
        for _ in range(quantity):
            new_data = ProductionData(
                product_name=product_name,
                pcb_type=self.current_pcb_type,
                batch_id=self.batch_id,
                order_number="N/A",
                led_codes=led_codes,
                lens_code=lens_code,
                connector_code=connector_code
            )
            current_instance.data.append(new_data)
        
        self.update_ui_after_changes()
        self.cleanup_cache()


# 3. UI Components and State Management
    ## 3.1 Component Initialization

    # Initialize the checkboxes around a PCB array
    def initialize_checkboxes(self):
        self.row_checkboxes = []
        self.col_checkboxes = []
        
        if not self.pcb_data:
            logging.warning("No PCB data available. Checkboxes will not be created.")
            return

        for i in range(int(self.pcb_data['Rows'])):
            var = tk.BooleanVar()
            cb = tk.Checkbutton(self.root, text=f"R{i+1}", bg="white", variable=var, 
                                command=lambda row=i: self.toggle_row(row))
            self.row_checkboxes.append((cb, var))
        
        for i in range(int(self.pcb_data['Columns'])):
            var = tk.BooleanVar()
            cb = tk.Checkbutton(self.root, text=f"C{i+1}", bg="white", variable=var, 
                                command=lambda col=i: self.toggle_column(col))
            self.col_checkboxes.append((cb, var))

        self.update_checkbox_states()

    # Initilize image cache
    @lru_cache(maxsize=32)
    def load_pcb_image(self, pcb_name):
        try:
            image_path = os.path.join(self.pcb_data_dir, f"{pcb_name}.png")
            image = Image.open(image_path)
            return image
        except FileNotFoundError:
            logging.warning(f"Image not found for PCB: {pcb_name}")
            return None

    # Load image and resize before caching
    def get_resized_photo(self, pcb_name, target_width, target_height):
        cache_key = (pcb_name, target_width, target_height)
        if cache_key not in self.image_cache:
            original = self.load_pcb_image(pcb_name)
            if original:
                resized = original.copy()
                resized = resized.resize((int(target_width), int(target_height)), Image.LANCZOS)
                self.image_cache[cache_key] = ImageTk.PhotoImage(resized)
        return self.image_cache.get(cache_key)


    ## 3.2 UI State Management

    # Update row / col faulty settings based on checkboxes
    def update_checkbox_states(self):
        state = 'normal' if self.pcb_instances and self.current_instance_index < len(self.pcb_instances) else 'disabled'
        
        if state == 'normal':
            current_instance = self.pcb_instances[self.current_instance_index]
            for i, (cb, var) in enumerate(self.row_checkboxes):
                var.set(current_instance.is_row_faulty(i))
                cb.configure(state=state)
            
            for i, (cb, var) in enumerate(self.col_checkboxes):
                var.set(current_instance.is_column_faulty(i))
                cb.configure(state=state)
        else:
            for cb, var in self.row_checkboxes + self.col_checkboxes:
                var.set(False)
                cb.configure(state=state)

    # Update the state of navigation buttons based on current instance
    def update_navigation_buttons(self):
        # Enable/disable navigation buttons based on current instance
        self.prev_button['state'] = 'normal' if self.current_instance_index > 0 else 'disabled'
        self.next_button['state'] = 'normal' if self.current_instance_index < len(self.pcb_instances) - 1 else 'disabled'
        self.instance_label.config(text=f"PCB: {self.current_instance_index + 1} of {len(self.pcb_instances)}")

    # Update the UI elements to reflect changes
    def update_ui_after_changes(self):
        if self.pcb_instances:
            self.redistribute_data()
            self.draw_pcb()
            self.update_navigation_buttons()
            self.update_instance_label()
            self.update_checkbox_states()
        else:
            self.canvas.delete("all")
            self.instance_label.config(text="")
            self.update_navigation_buttons()
            self.update_checkbox_states()

    # Update the instance label
    def update_instance_label(self):
        if self.pcb_instances:
            self.instance_label.config(text=f"PCB: {self.current_instance_index + 1} of {len(self.pcb_instances)}")
        else:
            self.instance_label.config(text="")


    ## 3.3 Navigation and Control

    # Navigate to the previous PCB instance
    def prev_pcb(self):
        if self.current_instance_index > 0:
            self.current_instance_index -= 1
            self.draw_pcb()
            self.update_navigation_buttons()

    # Navigate to the next PCB instance
    def next_pcb(self):
        if self.current_instance_index < len(self.pcb_instances) - 1:
            self.current_instance_index += 1
            self.draw_pcb()
            self.update_navigation_buttons()

    # Calculate the scale for use with the UI
    def calculate_scale(self):
        if self.pcb_data:
            canvas_width = self.canvas.winfo_width()
            canvas_height = self.canvas.winfo_height()
            outline_width = self.pcb_data['Width']
            outline_height = self.pcb_data['Height']
            
            # Calculate single scale factor that fits PCB in canvas with 10% margin
            self.scale = min(canvas_width / outline_width, canvas_height / outline_height) * 0.9


# 4. Event Handling

    # Handle PCB selection event, load PCB data, and initialize instances
    def on_pcb_selected(self, event=None):
        display_value = self.pcb_var.get()
        pcb_name = self.pcb_dropdown_mapping.get(display_value)
        if pcb_name and pcb_name != "Select a PCB":
            self.current_pcb_type = pcb_name
            self.pcb_type_label.config(text=display_value)
            self.batch_id_label.config(text=f"Batch: {self.batch_id}")
            
            self.pcb_data = self.load_pcb_data(pcb_name)
            if self.pcb_data:
                # Associate offset data with the loaded PCB data
                offset_data = self.available_pcbs.get(pcb_name.lower(), {'x_offset': 0, 'y_offset': 0})
                self.pcb_data['x_offset'] = offset_data['x_offset']
                self.pcb_data['y_offset'] = offset_data['y_offset']
                
                self.initialize_pcb_instances()
                self.initialize_checkboxes()
                self.current_instance_index = 0
                self.load_pcb_image(pcb_name)
                
                self.calculate_scale()
                
                logging.info(f"Loaded PCB data for {pcb_name} with offsets: x={self.pcb_data['x_offset']}, y={self.pcb_data['y_offset']}")
                
                self.draw_pcb()
                self.add_module_button['state'] = 'normal'
            else:
                self.pcb_instances = []
                self.current_instance_index = 0
                self.update_checkbox_states()
                self.add_module_button['state'] = 'disabled'
        else:
            # Clear the display when "Select a PCB" is chosen
            self.canvas.delete("all")
            self.pcb_type_label.config(text="")
            self.batch_id_label.config(text="")
            self.pcb_instances = []
            self.current_instance_index = 0
            self.update_checkbox_states()
            self.add_module_button['state'] = 'disabled'
        
        self.update_navigation_buttons()

    # Re-draws GUI in case window size changes
    def on_window_resize(self, event):
        if hasattr(self, '_resize_job'):
            self.root.after_cancel(self._resize_job)
        self._resize_job = self.root.after(100, self.redraw_pcb)

    # Toggle the faulty status of a module when clicked
    def toggle_module_faulty(self, event):
        self.calculate_scale()  # Ensure we're using the most up-to-date scale
        canvas_width = self.canvas.winfo_width()
        canvas_height = self.canvas.winfo_height()
        outline_width = self.pcb_data['Width']
        outline_height = self.pcb_data['Height']
        
        offset_x = (canvas_width - outline_width * self.scale) / 2
        offset_y = (canvas_height - outline_height * self.scale) / 2
        
        x = (event.x - offset_x) / self.scale
        y = (event.y - offset_y) / self.scale
        
        for i, module in enumerate(self.pcb_data['Modules']):
            module_left = module['x'] - module['width'] / 2
            module_right = module['x'] + module['width'] / 2
            module_top = module['y'] - module['height'] / 2
            module_bottom = module['y'] + module['height'] / 2
            
            if module_left <= x <= module_right and module_top <= y <= module_bottom:
                current_instance = self.pcb_instances[self.current_instance_index]
                current_instance.faulty_modules[i] = not current_instance.faulty_modules[i]
                self.redistribute_data()
                break

    # Faulty control for row
    def toggle_row(self, row):
        if not self.pcb_instances or self.current_instance_index >= len(self.pcb_instances):
            logging.warning("No PCB instances available or invalid instance index.")
            return
        
        current_instance = self.pcb_instances[self.current_instance_index]
        is_faulty = self.row_checkboxes[row][1].get()
        for i in range(row * int(self.pcb_data['Columns']), (row + 1) * int(self.pcb_data['Columns'])):
            if i < len(current_instance.faulty_modules):
                current_instance.faulty_modules[i] = is_faulty
        self.redistribute_data()
        self.update_checkbox_states()

    # Faulty control for col
    def toggle_column(self, col):
        if not self.pcb_instances or self.current_instance_index >= len(self.pcb_instances):
            logging.warning("No PCB instances available or invalid instance index.")
            return
        
        current_instance = self.pcb_instances[self.current_instance_index]
        is_faulty = self.col_checkboxes[col][1].get()
        for i in range(col, len(current_instance.faulty_modules), int(self.pcb_data['Columns'])):
            current_instance.faulty_modules[i] = is_faulty
        self.redistribute_data()
        self.update_checkbox_states()

    # Open data entry popup for adding new modules
    def open_add_module_popup(self):
        if not self.pcb_data:
            return

        popup = tk.Toplevel(self.root)
        popup.title("Add Module")
        popup.resizable(False, False)

        style = ttk.Style(popup)

        # Configure styles
        style.configure('TFrame', background=self.color_bg_main)
        style.configure('TLabel', background=self.color_bg_main, foreground=self.color_bg_work, font=('Arial', 10))
        style.configure('TEntry', fieldbackground=self.color_bg_work)
        style.configure('TButton', background=self.color_button_bg, foreground=self.color_button_fg, font=('Arial', 10, 'bold'))

        # Style for disabled entries
        style.map('TEntry',
            fieldbackground=[('disabled', '#e0e0e0')],
            foreground=[('disabled', '#a0a0a0')]
        )

        main_frame = ttk.Frame(popup, padding="20")
        main_frame.pack(fill=tk.BOTH, expand=True)

        led_count = len(self.pcb_data['Modules'][0]['led_positions'])
        led_entries = []
        for i in range(led_count):
            label = ttk.Label(main_frame, text=f"LED {i+1}:")
            label.grid(row=i, column=0, sticky="e", padx=5, pady=5)
            entry = ttk.Entry(main_frame, width=15)
            entry.grid(row=i, column=1, sticky="ew", padx=5, pady=5)
            led_entries.append(entry)

        # Lens entry
        lens_row = led_count
        ttk.Label(main_frame, text="Lens:").grid(row=lens_row, column=0, sticky="e", padx=5, pady=5)
        lens_entry = ttk.Entry(main_frame, width=15)
        lens_entry.grid(row=lens_row, column=1, sticky="ew", padx=5, pady=5)
        
        # Disable lens entry if not SW-12
        if self.current_pcb_type.lower() != "sw-12":
            lens_entry.state(['disabled'])

        # Connector entry
        connector_row = lens_row + 1
        ttk.Label(main_frame, text="Connector:").grid(row=connector_row, column=0, sticky="e", padx=5, pady=5)
        connector_entry = ttk.Entry(main_frame, width=15)
        connector_entry.grid(row=connector_row, column=1, sticky="ew", padx=5, pady=5)
        
        # Enable connector entry for all SW types
        if not self.current_pcb_type.lower().startswith("sw"):
            connector_entry.state(['disabled'])

        # Quantity entry
        quantity_row = connector_row + 1
        ttk.Label(main_frame, text="Quantity:").grid(row=quantity_row, column=0, sticky="e", padx=5, pady=5)
        quantity_entry = ttk.Entry(main_frame, width=15)
        quantity_entry.insert(0, "1")
        quantity_entry.grid(row=quantity_row, column=1, sticky="ew", padx=5, pady=5)

        main_frame.grid_columnconfigure(1, weight=1)

        def add_module():
            led_codes = [entry.get() for entry in led_entries]
            if not all(led_codes):
                if not messagebox.askyesno("Warning", "Not all LED fields are filled. Do you want to continue?"):
                    return
            lens_code = lens_entry.get() if self.current_pcb_type.lower() == "sw-12" else None
            connector_code = connector_entry.get() if self.current_pcb_type.lower().startswith("sw") else None
            try:
                quantity = int(quantity_entry.get())
                if quantity < 1:
                    raise ValueError
            except ValueError:
                messagebox.showerror("Error", "Please enter a valid positive integer for quantity.")
                return
            self.add_new_module(led_codes, lens_code, connector_code, quantity)
            popup.destroy()

        button_frame = ttk.Frame(main_frame)
        button_frame.grid(row=quantity_row+1, column=0, columnspan=2, pady=20)

        add_button = ttk.Button(button_frame, text="Add", command=add_module, style='TButton')
        add_button.pack(side=tk.LEFT, padx=5)
        cancel_button = ttk.Button(button_frame, text="Cancel", command=popup.destroy, style='TButton')
        cancel_button.pack(side=tk.LEFT, padx=5)

        # Calculate the popup size
        popup_width = 300
        popup_height = (quantity_row + 3) * 40 # Adjusted for padding and button row

        # Set the calculated size
        popup.geometry(f"{popup_width}x{popup_height}")

        # Center the popup on the screen
        popup.update_idletasks()
        x = (popup.winfo_screenwidth() // 2) - (popup_width // 2)
        y = (popup.winfo_screenheight() // 2) - (popup_height // 2)
        popup.geometry(f'+{x}+{y}')

        led_entries[0].focus_set()

        popup.transient(self.root)
        popup.grab_set()
        self.root.wait_window(popup)


# 5. Drawing and Rendering

    # Draw the selected PCB on the canvas with its modules and data
    def draw_pcb(self):
        if not self.pcb_data or not self.pcb_instances:
            self.canvas.delete("all")
            return

        self.canvas.delete("all")
        
        canvas_width = self.canvas.winfo_width()
        canvas_height = self.canvas.winfo_height()
        outline_width = self.pcb_data['Width']
        outline_height = self.pcb_data['Height']
        
        # Calculate scale and offset to center the PCB
        self.scale = min(canvas_width / outline_width, canvas_height / outline_height) * 0.9
        offset_x = (canvas_width - outline_width * self.scale) / 2
        offset_y = (canvas_height - outline_height * self.scale) / 2
        
        # Load and draw the background image
        pcb_name = self.pcb_var.get()
        target_width = int(outline_width * self.scale)
        target_height = int(outline_height * self.scale)
        photo = self.get_resized_photo(pcb_name, target_width, target_height)
        if photo:
            self.canvas.create_image(offset_x, offset_y, anchor="nw", image=photo)
            self.canvas.image = photo  # Keep a reference to prevent garbage collection

        # Draw PCB outline
        self.canvas.create_rectangle(
            offset_x, offset_y,
            offset_x + target_width,
            offset_y + target_height,
            outline="black", width=2
        )
        
        # Calculate row and column positions based on module positions
        row_positions = []
        col_positions = []
        for module in self.pcb_data['Modules']:
            x = offset_x + module['x'] * self.scale
            y = offset_y + module['y'] * self.scale
            if y not in row_positions:
                row_positions.append(y)
            if x not in col_positions:
                col_positions.append(x)
        
        row_positions.sort()
        col_positions.sort()
        
        # Place row checkboxes aligned with their corresponding rows
        for i, (cb, _) in enumerate(self.row_checkboxes):
            if i < len(row_positions):
                self.canvas.create_window(offset_x - 10, row_positions[i], window=cb, anchor='e')
        
        # Place column checkboxes aligned with their corresponding columns
        for i, (cb, _) in enumerate(self.col_checkboxes):
            if i < len(col_positions):
                self.canvas.create_window(col_positions[i], offset_y - 10, window=cb, anchor='s')
        
        self.update_checkbox_states()
        
        self.draw_modules_with_data(offset_x, offset_y)

        self.canvas.bind("<Button-1>", self.toggle_module_faulty)
        self.instance_label.config(text=f"PCB: {self.current_instance_index + 1} of {len(self.pcb_instances)}")
        self.update_navigation_buttons()
        
        # Update the state of the Add Module button
        self.add_module_button['state'] = 'normal'  # Enable the Add Module button

    # Draw individual modules on the PCB with their associated data
    def draw_modules_with_data(self, offset_x, offset_y):
        current_instance = self.pcb_instances[self.current_instance_index]
        data_index = 0
        for i, module in enumerate(self.pcb_data['Modules']):
            module_x = offset_x + module['x'] * self.scale
            module_y = offset_y + module['y'] * self.scale
            module_width = module['width'] * self.scale
            module_height = module['height'] * self.scale
            
            if current_instance.faulty_modules[i]:
                # Draw X from corners of the rectangular area
                top_left_x = module_x - module_width / 2
                top_left_y = module_y - module_height / 2
                bottom_right_x = module_x + module_width / 2
                bottom_right_y = module_y + module_height / 2
                
                self.canvas.create_line(top_left_x, top_left_y, bottom_right_x, bottom_right_y, fill="red", width=2)
                self.canvas.create_line(top_left_x, bottom_right_y, bottom_right_x, top_left_y, fill="red", width=2)
            elif data_index < len(current_instance.data):
                prod_data = current_instance.data[data_index]

                for j, led_pos in enumerate(module['led_positions']):
                    if j < len(prod_data.led_codes):
                        led_code = prod_data.led_codes[j]
                        led_x = offset_x + led_pos['x'] * self.scale
                        led_y = offset_y + led_pos['y'] * self.scale
                        self._draw_text(led_code, {'x': led_x, 'y': led_y, 'rotation': led_pos['rotation'], 'height': led_pos['height'] * self.scale})
                
                if module['connector_position'] and prod_data.connector_code:
                    conn_pos = module['connector_position']
                    conn_x = offset_x + conn_pos['x'] * self.scale
                    conn_y = offset_y + conn_pos['y'] * self.scale
                    self._draw_text(prod_data.connector_code, {'x': conn_x, 'y': conn_y, 'rotation': conn_pos['rotation'], 'height': conn_pos['height'] * self.scale})
                
                if module['lens_position'] and prod_data.lens_code:
                    lens_pos = module['lens_position']
                    lens_x = offset_x + lens_pos['x'] * self.scale
                    lens_y = offset_y + lens_pos['y'] * self.scale
                    self._draw_text(prod_data.lens_code, {'x': lens_x, 'y': lens_y, 'rotation': lens_pos['rotation'], 'height': lens_pos['height'] * self.scale})
                
                data_index += 1

    # Draw text onto the modules based on production data & coordinates
    def _draw_text(self, text, position):
        text_x = position['x']
        text_y = position['y']
        
        # Adjust the rotation angle to flip the text vertically
        adjusted_rotation = (position['rotation'] + 180) % 360

        # Double the font size by multiplying the height by 2
        font_size = int(position['height'] * 2)

        self.canvas.create_text(
            text_x, text_y, 
            text=' '.join(text),  # Add spaces between mirrored characters
            font=("Roboto Thin", font_size),
            fill="black",
            anchor="center",
            angle=adjusted_rotation  # Use the negative of the adjusted rotation
        )

    # Re-draw PCB outline on UI
    def redraw_pcb(self):
        if hasattr(self, 'pcb_data') and self.pcb_data:
            self.calculate_scale()
            self.draw_pcb()


# 6. Export Operations
    ## 6.1 SVG Generation

    # Export the current PCB instance as an SVG file
    def export_svg(self, batch_mode=False):
        pcb_name = self.pcb_var.get()
        if not pcb_name or not self.pcb_data or not self.pcb_instances:
            messagebox.showerror("Error", "PCB data is not loaded properly")
            return

        default_dir = r"Q:\Shared drives\Quadica\Production\Layout App Print Files\UV Laser Engrave Files"
        if not os.path.exists(default_dir):
            os.makedirs(default_dir)

        current_instance = self.pcb_instances[self.current_instance_index]
        if not current_instance.data:
            messagebox.showerror("Error", "No data available for current instance")
            return

        first_prod_data = current_instance.data[0]
        current_date = datetime.now().strftime("%Y-%m-%d")
        file_name = f"{first_prod_data.batch_id}_{first_prod_data.pcb_type}_{self.file_number:03d}.svg"
        file_path = os.path.normpath(os.path.join(default_dir, file_name))

        # Center point from PCB data (assumed to be in mm)
        center_x, center_y = self.pcb_data['CenterPoint']['x'], self.pcb_data['CenterPoint']['y']

        # Calculate offsets to center the PCB in the 210x210 mm work area
        x_offset = 105 - center_x
        y_offset = 105 - center_y

        # Get PCB-specific offsets from the loaded PCB data
        pcb_specific_x_offset = self.pcb_data.get('x_offset', 0)
        pcb_specific_y_offset = self.pcb_data.get('y_offset', 0)
        
        print(f"Exporting SVG for PCB: {pcb_name}, X offset: {pcb_specific_x_offset}, Y offset: {pcb_specific_y_offset}")

        try:
            # Create SVG with 210x210 mm dimensions
            dwg = svgwrite.Drawing(file_path, size=('210mm', '210mm'), viewBox="0 0 210 210")

            # Add 205x205 mm square around the final output
            dwg.add(dwg.rect(insert=(2.5, 2.5), size=(205, 205), fill='none', stroke='#FF0000', stroke_width=0.5))

            # Helper function to apply transformation (all values in mm)
            def transform_coords(x, y):
                new_x = x + x_offset + pcb_specific_x_offset
                new_y = y + y_offset + pcb_specific_y_offset
                return max(0, min(210, new_x)), max(0, min(210, new_y))

            # Draw cross at the center point
            center_x_transformed, center_y_transformed = transform_coords(center_x, center_y)
            cross_size = 2  # Size of the cross in mm
            dwg.add(dwg.line(start=(center_x_transformed - cross_size, center_y_transformed),
                            end=(center_x_transformed + cross_size, center_y_transformed),
                            stroke='red', stroke_width=0.2))
            dwg.add(dwg.line(start=(center_x_transformed, center_y_transformed - cross_size),
                            end=(center_x_transformed, center_y_transformed + cross_size),
                            stroke='red', stroke_width=0.2))

            data_index = 0
            for i, module in enumerate(self.pcb_data['Modules']):
                if current_instance.faulty_modules[i]:
                    continue
                elif data_index < len(current_instance.data):
                    prod_data = current_instance.data[data_index]
                    
                    for j, led_pos in enumerate(module['led_positions']):
                        if j < len(prod_data.led_codes):
                            led_code = prod_data.led_codes[j]
                            x, y = transform_coords(led_pos['x'], led_pos['y'])
                            self.add_rotated_text(dwg, led_code, x, y, led_pos['rotation'], led_pos['height'])

                    # For connector_position
                    if module['connector_position'] and prod_data.connector_code:
                        pos = module['connector_position']
                        x, y = transform_coords(pos['x'], pos['y'])
                        self.add_rotated_text(dwg, prod_data.connector_code, x, y, pos['rotation'], pos['height'])

                    # For lens_position
                    if module['lens_position'] and prod_data.lens_code:
                        pos = module['lens_position']
                        x, y = transform_coords(pos['x'], pos['y'])
                        self.add_rotated_text(dwg, prod_data.lens_code, x, y, pos['rotation'], pos['height'])
                    
                    data_index += 1

            dwg.save()

            if not batch_mode:
                #messagebox.showinfo("Success", f"SVG exported successfully to {file_path}")
                self.cleanup_cache()

            self.file_number += 1
            if not batch_mode:
                # Remove the current instance
                self.pcb_instances.pop(self.current_instance_index)

                if not self.pcb_instances:
                    # If no instances left, create a new empty one
                    rows = int(self.pcb_data['Rows'])
                    columns = int(self.pcb_data['Columns'])
                    modules_count = len(self.pcb_data['Modules'])
                    self.pcb_instances.append(PCBInstance(modules_count, rows, columns))
                elif self.current_instance_index >= len(self.pcb_instances):
                    # Adjust the current index if it's out of range
                    self.current_instance_index = len(self.pcb_instances) - 1

                self.update_ui_after_changes()

            if not batch_mode and file_path:
                try:
                    # Open LightBurn first
                    os.startfile(r"C:\Program Files\LightBurn\LightBurn.exe")
                    
                    # Wait a moment for LightBurn to initialize
                    self.root.after(3000)  # 2 second delay
                    
                    if self.lightburn.load_file(file_path):
                        logging.info(f"Successfully loaded {file_path} into LightBurn")
                        # Show confirmation dialog and wait for user to finish engraving
                        self.show_engraving_confirmation()
                    else:
                        logging.warning(f"Failed to load {file_path} into LightBurn")
                except Exception as e:
                    logging.error(f"Error interacting with LightBurn: {e}")
                    messagebox.showerror("Error", f"Failed to interact with LightBurn: {str(e)}")

            return file_path

        except Exception as e:
            messagebox.showerror("Error", f"Failed to save SVG: {str(e)}")
            logging.error(f"Failed to save SVG: {str(e)}")
            return None

    # Add text to an SVG drawing
    def add_rotated_text(self, dwg, text, x, y, angle, height):
        # Constant adjustment factor to account for whitespace above and below characters
        CHAR_HEIGHT_RATIO = 0.7 / 0.498  # Ratio of desired character height to total font height

        # Adjust the font size to compensate for the vertical spacing
        adjusted_height = height * CHAR_HEIGHT_RATIO

        # Adjust the rotation angle to flip the text
        adjusted_angle = (angle + 180) % 360

        transform = f"rotate({-adjusted_angle} {x} {y})"

        font_family = "Roboto Thin, sans-serif"
        font_weight = "normal"
        font_style = "normal"

        # Insert half-spaces between characters for SVG export
        half_space = chr(8202)
        spaced_text = half_space.join(text)

        # Use a dictionary for attributes to handle names with hyphens
        text_attributes = {
            'insert': (x, y),
            'transform': transform,
            'font-size': adjusted_height,
            'text-anchor': "middle",
            'font-family': font_family,
            'font-weight': font_weight,
            'font-style': font_style
        }

        # Add the text element using the attributes dictionary
        dwg.add(dwg.text(spaced_text, **text_attributes))

    ## 6.2 Batch Processing

    # Export all PCB instances as SVG files in batch mode
    def batch_export_svg(self):
        if not self.pcb_var.get() or not self.pcb_data or not self.pcb_instances:
            messagebox.showerror("Error", "PCB data is not loaded properly")
            return

        default_dir = r"Q:\Shared drives\Quadica\Production\Layout App Print Files\UV Laser Engrave Files"
        if not os.path.exists(default_dir):
            os.makedirs(default_dir)

        original_instance_index = self.current_instance_index
        original_file_number = self.file_number
        exported_files = []  # List to store paths of exported files

        try:
            # First, export all files
            for i in range(len(self.pcb_instances)):
                self.current_instance_index = i
                if self.pcb_instances[i].data:  # Only export if there's data
                    file_path = self.export_svg(batch_mode=True)
                    if file_path:
                        exported_files.append(file_path)
                        # Clear the data and reset faulty modules after successful export
                        self.pcb_instances[i].clear_all()

            if exported_files:
                # Start the sequential processing of files
                self.process_batch_files(exported_files)
            else:
                messagebox.showinfo("Info", "No files were exported.")

        except Exception as e:
            messagebox.showerror("Error", f"An error occurred during batch export: {str(e)}")
            logging.error(f"Batch export error: {str(e)}")
        finally:
            self.current_instance_index = original_instance_index
            self.file_number = original_file_number
            self.update_ui_after_changes()
            self.draw_pcb()
            self.update_navigation_buttons()
            self.cleanup_cache()

        # After batch export, check if all instances are empty
        if all(not instance.data for instance in self.pcb_instances):
            # If all are empty, create a new empty instance
            self.pcb_instances = [PCBInstance(len(self.pcb_data['Modules']), 
                                            int(self.pcb_data['Rows']), 
                                            int(self.pcb_data['Columns']))]
            self.current_instance_index = 0

    # Batch export SVG's with the LightBurn process
    def process_batch_files(self, file_paths: List[str], current_index: int = 0):
        """Process batch files sequentially"""
        if current_index >= len(file_paths):
            messagebox.showinfo("Complete", "All files have been processed and engraved.")
            return

        try:
            # Open LightBurn and load the current file
            os.startfile(r"C:\Program Files\LightBurn\LightBurn.exe")
            self.root.after(3000)  # Wait for LightBurn to initialize
            
            if self.lightburn.load_file(file_paths[current_index]):
                logging.info(f"Successfully loaded {file_paths[current_index]} into LightBurn")
                self.show_batch_engraving_confirmation(file_paths, current_index)
            else:
                logging.warning(f"Failed to load {file_paths[current_index]} into LightBurn")
                
        except Exception as e:
            logging.error(f"Error processing batch file: {e}")
            messagebox.showerror("Error", f"Failed to process file: {str(e)}")


    ## 6.3 LightBurn Integration

    # Popup message to control batch flow
    def show_batch_engraving_confirmation(self, file_paths: List[str], current_index: int):
        """Show confirmation dialog for batch processing"""
        dialog = tk.Toplevel(self.root)
        dialog.title("Batch Engraving Status")
        
        # Make dialog modal
        dialog.transient(self.root)
        dialog.grab_set()
        
        # Center the dialog
        dialog.geometry("400x200")
        x = self.root.winfo_x() + (self.root.winfo_width() - 400) // 2
        y = self.root.winfo_y() + (self.root.winfo_height() - 200) // 2
        dialog.geometry(f"+{x}+{y}")
        
        # Configure dialog
        dialog.configure(bg=self.color_bg_main)
        dialog.resizable(False, False)
        
        # Add messages
        progress_text = f"Processing file {current_index + 1} of {len(file_paths)}"
        progress_label = tk.Label(dialog, 
                            text=progress_text,
                            font=("Arial", 14, "bold"),
                            bg=self.color_bg_main,
                            fg=self.color_text_muted)
        progress_label.pack(pady=10)
        
        message = tk.Label(dialog, 
                        text="File Ready For Engraving",
                        font=("Arial", 14, "bold"),
                        bg=self.color_bg_main,
                        fg=self.color_text_muted)
        message.pack(pady=10)
        
        def on_finished():
            try:
                self.lightburn.force_close()
                dialog.destroy()
                # Process next file
                self.root.after(1000, lambda: self.process_batch_files(file_paths, current_index + 1))
            except Exception as e:
                logging.error(f"Error closing LightBurn: {e}")
                messagebox.showerror("Error", f"Failed to close LightBurn: {str(e)}")
        
        # Add button
        finish_button = tk.Button(dialog,
                                text="Engraving Finished",
                                command=on_finished,
                                bg=self.color_button_bg,
                                fg=self.color_button_fg,
                                activebackground=self.color_button_active,
                                activeforeground=self.color_button_fg,
                                font=('Arial', 12, 'bold'))
        finish_button.pack(pady=20)
        
        # Wait for dialog to close
        self.root.wait_window(dialog)

    # Popup used to control LightBurn workflow
    def show_engraving_confirmation(self):
        """Show a confirmation dialog after file is loaded into LightBurn"""
        dialog = tk.Toplevel(self.root)
        dialog.title("Engraving Status")
        
        # Make dialog modal
        dialog.transient(self.root)
        dialog.grab_set()
        
        # Center the dialog
        dialog.geometry("300x150")
        x = self.root.winfo_x() + (self.root.winfo_width() - 300) // 2
        y = self.root.winfo_y() + (self.root.winfo_height() - 150) // 2
        dialog.geometry(f"+{x}+{y}")
        
        # Configure dialog
        dialog.configure(bg=self.color_bg_main)
        dialog.resizable(False, False)
        
        # Add message
        message = tk.Label(dialog, 
                        text="File Ready For Engraving",
                        font=("Arial", 14, "bold"),
                        bg=self.color_bg_main,
                        fg=self.color_text_muted)
        message.pack(pady=20)
        
        def on_finished():
            try:
                self.lightburn.force_close()
                dialog.destroy()
            except Exception as e:
                logging.error(f"Error closing LightBurn: {e}")
                messagebox.showerror("Error", f"Failed to close LightBurn: {str(e)}")
        
        # Add button
        finish_button = tk.Button(dialog,
                                text="Engraving Finished",
                                command=on_finished,
                                bg=self.color_button_bg,
                                fg=self.color_button_fg,
                                activebackground=self.color_button_active,
                                activeforeground=self.color_button_fg,
                                font=('Arial', 12, 'bold'))
        finish_button.pack(pady=20)
        
        # Wait for dialog to close
        self.root.wait_window(dialog)


# 7. Main

if __name__ == "__main__":
    root = tk.Tk()
    app = PCBViewer(root)
    root.mainloop()
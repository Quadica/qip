#!/usr/bin/env python3
"""
Generate a clean, synthetic Micro-ID image for testing model understanding.

Creates a perfect 5x5 dot matrix image with known serial number to verify
that AI models understand the encoding scheme.
"""

import argparse
import os
import sys

try:
    from PIL import Image, ImageDraw
except ImportError:
    print("Installing Pillow...")
    os.system("pip3 install --user Pillow")
    from PIL import Image, ImageDraw


def serial_to_binary(serial: int) -> str:
    """Convert serial number to 20-bit binary string."""
    if serial < 0 or serial > 0xFFFFF:  # Max 20-bit value
        raise ValueError(f"Serial must be 0-1048575, got {serial}")
    return format(serial, '020b')


def calculate_parity(binary: str) -> int:
    """Calculate parity bit (even parity)."""
    ones = sum(1 for b in binary if b == '1')
    return 0 if ones % 2 == 0 else 1


def binary_to_grid(binary: str) -> list:
    """
    Convert 20-bit binary + parity to 5x5 grid.

    Grid layout:
    Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
    Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
    Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
    Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
    Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
    """
    parity = calculate_parity(binary)

    # Create 5x5 grid
    grid = [[0] * 5 for _ in range(5)]

    # Set corner anchors (always 1)
    grid[0][0] = 1  # Top-left
    grid[0][4] = 1  # Top-right
    grid[4][0] = 1  # Bottom-left
    grid[4][4] = 1  # Bottom-right

    # Set data bits
    # Row 0: bits 19, 18, 17
    grid[0][1] = int(binary[0])   # Bit 19
    grid[0][2] = int(binary[1])   # Bit 18
    grid[0][3] = int(binary[2])   # Bit 17

    # Row 1: bits 16, 15, 14, 13, 12
    grid[1][0] = int(binary[3])   # Bit 16
    grid[1][1] = int(binary[4])   # Bit 15
    grid[1][2] = int(binary[5])   # Bit 14
    grid[1][3] = int(binary[6])   # Bit 13
    grid[1][4] = int(binary[7])   # Bit 12

    # Row 2: bits 11, 10, 9, 8, 7
    grid[2][0] = int(binary[8])   # Bit 11
    grid[2][1] = int(binary[9])   # Bit 10
    grid[2][2] = int(binary[10])  # Bit 9
    grid[2][3] = int(binary[11])  # Bit 8
    grid[2][4] = int(binary[12])  # Bit 7

    # Row 3: bits 6, 5, 4, 3, 2
    grid[3][0] = int(binary[13])  # Bit 6
    grid[3][1] = int(binary[14])  # Bit 5
    grid[3][2] = int(binary[15])  # Bit 4
    grid[3][3] = int(binary[16])  # Bit 3
    grid[3][4] = int(binary[17])  # Bit 2

    # Row 4: bits 1, 0, parity
    grid[4][1] = int(binary[18])  # Bit 1
    grid[4][2] = int(binary[19])  # Bit 0
    grid[4][3] = parity           # Parity bit

    return grid


def render_grid(grid: list, cell_size: int = 100, dot_radius: int = 35,
                bg_color: str = "white", dot_color: str = "#CD7F32",
                show_gridlines: bool = True) -> Image:
    """Render grid as a PIL Image."""

    padding = cell_size // 2
    img_size = cell_size * 5 + padding * 2

    img = Image.new('RGB', (img_size, img_size), bg_color)
    draw = ImageDraw.Draw(img)

    # Draw grid lines (optional, for clarity)
    if show_gridlines:
        line_color = "#CCCCCC"
        for i in range(6):
            x = padding + i * cell_size
            draw.line([(x, padding), (x, img_size - padding)], fill=line_color, width=1)
            y = padding + i * cell_size
            draw.line([(padding, y), (img_size - padding, y)], fill=line_color, width=1)

    # Draw dots
    for row in range(5):
        for col in range(5):
            if grid[row][col] == 1:
                cx = padding + col * cell_size + cell_size // 2
                cy = padding + row * cell_size + cell_size // 2
                draw.ellipse(
                    [cx - dot_radius, cy - dot_radius, cx + dot_radius, cy + dot_radius],
                    fill=dot_color
                )

    return img


def print_grid(grid: list, serial: int, binary: str):
    """Print grid in text format."""
    parity = calculate_parity(binary)

    print(f"\nSerial: {serial:08d}")
    print(f"Binary: {binary}")
    print(f"Parity: {parity}")
    print("\nGrid (● = dot, ○ = empty):")
    print()

    for row in range(5):
        row_str = "  "
        for col in range(5):
            row_str += "● " if grid[row][col] == 1 else "○ "
        print(f"Row {row}: {row_str}")

    # Count dots
    total_dots = sum(sum(row) for row in grid)
    print(f"\nTotal dots: {total_dots}")


def main():
    parser = argparse.ArgumentParser(description="Generate synthetic Micro-ID image")
    parser.add_argument("serial", type=int, help="Serial number (0-1048575)")
    parser.add_argument("--output", "-o", default="test-microid.png", help="Output file")
    parser.add_argument("--size", "-s", type=int, default=100, help="Cell size in pixels")
    parser.add_argument("--dot-color", "-c", default="#CD7F32", help="Dot color (hex)")
    parser.add_argument("--no-gridlines", action="store_true", help="Hide grid lines")

    args = parser.parse_args()

    # Convert serial to binary
    binary = serial_to_binary(args.serial)

    # Generate grid
    grid = binary_to_grid(binary)

    # Print text representation
    print_grid(grid, args.serial, binary)

    # Render and save image
    img = render_grid(grid, cell_size=args.size, dot_color=args.dot_color,
                     show_gridlines=not args.no_gridlines)
    img.save(args.output)

    print(f"\nSaved: {args.output}")
    print(f"Size: {img.size[0]}x{img.size[1]} pixels")

    return 0


if __name__ == "__main__":
    sys.exit(main())

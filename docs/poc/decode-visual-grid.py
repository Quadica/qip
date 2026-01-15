#!/usr/bin/env python3
"""
Micro-ID Decode: Visual Grid Reading + Code-Based Conversion

BREAKTHROUGH FINDING (Session 097):
- AI models CAN read the 5x5 grid accurately
- AI models FAIL at bit-position mapping / binary conversion
- Solution: Simple visual prompt + conversion in code

This script demonstrates the validated approach:
1. Ask model to just read dots as 1/0 for each row
2. Convert the grid string to serial number in code
"""

import argparse
import base64
import json
import os
import re
import sys

try:
    import requests
except ImportError:
    os.system("pip3 install --user requests")
    import requests


# Simple visual prompt - just read what you see
VISUAL_PROMPT = """Look at this 5x5 grid image. Some cells have dots (circles), some are empty.

Read each cell left-to-right, top-to-bottom:
- If there's a dot: write 1
- If empty: write 0

Report each row on its own line:
Row 0: X X X X X
Row 1: X X X X X
Row 2: X X X X X
Row 3: X X X X X
Row 4: X X X X X

Then give me the complete 25-character string (all rows concatenated).

Respond ONLY with this JSON:
```json
{
  "row0": "XXXXX",
  "row1": "XXXXX",
  "row2": "XXXXX",
  "row3": "XXXXX",
  "row4": "XXXXX",
  "grid": "25 chars"
}
```"""


def grid_to_serial(grid_str: str) -> tuple:
    """
    Convert 25-char grid string to serial number.

    Grid layout:
    Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
    Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
    Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
    Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
    Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
    """
    # Clean input
    grid_str = ''.join(c for c in grid_str if c in '01')

    if len(grid_str) != 25:
        return None, f"Invalid grid length: {len(grid_str)}"

    # Parse grid into 2D array
    grid = []
    for row in range(5):
        grid.append([int(grid_str[row * 5 + col]) for col in range(5)])

    # Verify anchors (all 4 corners should have dots)
    anchors_valid = (grid[0][0] == grid[0][4] == grid[4][0] == grid[4][4] == 1)

    # Extract 20 data bits
    bits = [0] * 20
    bits[19] = grid[0][1]
    bits[18] = grid[0][2]
    bits[17] = grid[0][3]
    bits[16] = grid[1][0]
    bits[15] = grid[1][1]
    bits[14] = grid[1][2]
    bits[13] = grid[1][3]
    bits[12] = grid[1][4]
    bits[11] = grid[2][0]
    bits[10] = grid[2][1]
    bits[9] = grid[2][2]
    bits[8] = grid[2][3]
    bits[7] = grid[2][4]
    bits[6] = grid[3][0]
    bits[5] = grid[3][1]
    bits[4] = grid[3][2]
    bits[3] = grid[3][3]
    bits[2] = grid[3][4]
    bits[1] = grid[4][1]
    bits[0] = grid[4][2]
    parity_bit = grid[4][3]

    # Convert to decimal (bits[19] is MSB, bits[0] is LSB)
    binary_str = ''.join(str(bits[i]) for i in range(19, -1, -1))
    serial = int(binary_str, 2)

    # Verify parity (even parity: total 1s including parity should be even)
    ones = sum(bits)
    parity_valid = (ones + parity_bit) % 2 == 0

    return serial, {
        'binary': binary_str,
        'serial_formatted': f'{serial:08d}',
        'parity_valid': parity_valid,
        'anchors_valid': anchors_valid,
        'grid': grid_str,
    }


def test_decode_api(api_key: str, model: str, image_path: str) -> dict:
    """Test the visual grid reading approach via API."""

    # Load image
    with open(image_path, 'rb') as f:
        image_base64 = base64.b64encode(f.read()).decode('utf-8')

    ext = image_path.lower().split('.')[-1]
    mime_type = {'jpg': 'image/jpeg', 'jpeg': 'image/jpeg', 'png': 'image/png'}.get(ext, 'image/jpeg')

    # Call API
    headers = {
        'Authorization': f'Bearer {api_key}',
        'Content-Type': 'application/json',
        'HTTP-Referer': 'https://quadica.com',
    }

    payload = {
        'model': model,
        'messages': [{
            'role': 'user',
            'content': [
                {'type': 'image_url', 'image_url': {'url': f'data:{mime_type};base64,{image_base64}'}},
                {'type': 'text', 'text': VISUAL_PROMPT},
            ],
        }],
        'max_tokens': 500,
        'temperature': 0.0,
    }

    response = requests.post(
        'https://openrouter.ai/api/v1/chat/completions',
        headers=headers,
        json=payload,
        timeout=60,
    )

    if response.status_code != 200:
        return {'error': f'HTTP {response.status_code}: {response.text[:200]}'}

    data = response.json()
    response_text = data.get('choices', [{}])[0].get('message', {}).get('content', '')

    # Parse JSON from response
    json_match = re.search(r'```json\s*(\{.*?\})\s*```', response_text, re.DOTALL)
    if json_match:
        try:
            result = json.loads(json_match.group(1))
        except json.JSONDecodeError:
            return {'error': 'Failed to parse JSON', 'response': response_text}
    else:
        return {'error': 'No JSON found', 'response': response_text}

    # Convert grid to serial using our code
    grid_str = result.get('grid', '')
    serial, details = grid_to_serial(grid_str)

    return {
        'model_response': result,
        'serial': serial,
        'details': details,
        'raw_response': response_text,
    }


def main():
    parser = argparse.ArgumentParser(description='Micro-ID decode via visual grid reading')
    parser.add_argument('image', help='Path to Micro-ID image')
    parser.add_argument('--api-key', '-k', help='OpenRouter API key')
    parser.add_argument('--model', '-m', default='openai/gpt-4o-2024-11-20', help='Model to use')
    parser.add_argument('--expected', '-e', type=int, help='Expected serial for validation')

    args = parser.parse_args()

    api_key = args.api_key or os.environ.get('OPENROUTER_API_KEY')
    if not api_key:
        print("ERROR: API key required (--api-key or OPENROUTER_API_KEY)")
        return 1

    if not os.path.exists(args.image):
        print(f"ERROR: Image not found: {args.image}")
        return 1

    print(f"\n{'='*60}")
    print("Micro-ID Visual Grid Decode")
    print(f"{'='*60}\n")
    print(f"Image: {args.image}")
    print(f"Model: {args.model}")
    if args.expected is not None:
        print(f"Expected: {args.expected:08d}")
    print()

    result = test_decode_api(api_key, args.model, args.image)

    if 'error' in result:
        print(f"ERROR: {result['error']}")
        if 'response' in result:
            print(f"\nResponse:\n{result['response'][:500]}")
        return 1

    print("Model read:")
    for i in range(5):
        row_key = f'row{i}'
        if row_key in result['model_response']:
            print(f"  Row {i}: {result['model_response'][row_key]}")

    print(f"\nGrid string: {result['model_response'].get('grid', 'N/A')}")

    if result['serial'] is not None:
        print(f"\n{'='*40}")
        print(f"DECODED SERIAL: {result['details']['serial_formatted']}")
        print(f"Binary: {result['details']['binary']}")
        print(f"Anchors valid: {result['details']['anchors_valid']}")
        print(f"Parity valid: {result['details']['parity_valid']}")

        if args.expected is not None:
            match = result['serial'] == args.expected
            print(f"\nExpected {args.expected:08d}: {'MATCH' if match else 'MISMATCH'}")
    else:
        print(f"\nDecode failed: {result['details']}")

    return 0


if __name__ == '__main__':
    sys.exit(main())

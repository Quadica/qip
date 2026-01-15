#!/usr/bin/env python3
"""
Test Micro-ID decoding across multiple vision models via OpenRouter.

Tests preprocessed crop images against various AI models to find
which performs best at decoding the 5x5 dot matrix.

Usage:
    python test-openrouter-decode.py <image_path> [--models MODEL1,MODEL2,...]
"""

import argparse
import base64
import json
import os
import re
import sys
import time
from pathlib import Path

try:
    import requests
except ImportError:
    print("Installing requests...")
    os.system("pip3 install --user requests")
    import requests


# OpenRouter API configuration
OPENROUTER_API_URL = "https://openrouter.ai/api/v1/chat/completions"

# Vision-capable models to test (model_id: display_name)
VISION_MODELS = {
    "openai/gpt-4o-2024-11-20": "GPT-4o (Nov 2024)",
    "openai/gpt-4o-mini-2024-07-18": "GPT-4o Mini",
    "google/gemini-2.0-flash-exp:free": "Gemini 2.0 Flash",
    "google/gemini-exp-1206:free": "Gemini Exp 1206",
    "anthropic/claude-3.5-sonnet": "Claude 3.5 Sonnet",
    "anthropic/claude-3-haiku": "Claude 3 Haiku",
    "meta-llama/llama-3.2-90b-vision-instruct": "Llama 3.2 90B Vision",
    "qwen/qwen-2-vl-72b-instruct": "Qwen2 VL 72B",
    "mistralai/pixtral-12b-2409": "Pixtral 12B",
    "x-ai/grok-2-vision-1212": "Grok 2 Vision",
}

# Decode prompt for cropped images
DECODE_PROMPT_CROP = """This image shows a cropped Micro-ID 5x5 dot matrix code from an LED module.

The dots are copper/bronze colored on a white background. You need to decode the 20-bit serial number."""

# Decode prompt for full images
DECODE_PROMPT_FULL = """This image shows a full LED module (SZ-04 STAR module).

Find the Micro-ID - a tiny 5x5 dot matrix code (about 1mm square) located in the LOWER LEFT area of the module, near the "SZ-04.net" text. The dots are copper/bronze colored, very small (0.15mm diameter).

Once you find it, decode the 20-bit serial number from the 5x5 grid.

The dots are copper/bronze colored on a white background. You need to decode the 20-bit serial number."""

# Simple visual prompt - just read the grid
DECODE_PROMPT_SIMPLE = """This image shows a 5x5 grid with dots (circles) in some cells.

Your task:
1. Look at each cell in the 5x5 grid
2. Mark each cell as 1 if there's a dot, 0 if empty
3. Read left to right, top to bottom

Write out each row like this:
Row 0: [X] [X] [X] [X] [X]
Row 1: [X] [X] [X] [X] [X]
Row 2: [X] [X] [X] [X] [X]
Row 3: [X] [X] [X] [X] [X]
Row 4: [X] [X] [X] [X] [X]

Where X is 1 (dot present) or 0 (empty).

Then convert to a single 25-character binary string reading all rows concatenated.

Respond with JSON:
```json
{
  "grid": "25-character binary string",
  "row0": "[X][X][X][X][X]",
  "row1": "[X][X][X][X][X]",
  "row2": "[X][X][X][X][X]",
  "row3": "[X][X][X][X][X]",
  "row4": "[X][X][X][X][X]"
}
```"""

# Default to crop prompt
DECODE_PROMPT = DECODE_PROMPT_CROP + """

## Grid Layout
The 5x5 grid has:
- 4 corner anchors (always present): positions (0,0), (0,4), (4,0), (4,4)
- 20 data bits in the remaining positions
- 1 parity bit at position (4,3)

Bit positions (reading left-to-right, top-to-bottom):
```
Row 0: [ANCHOR] [Bit19] [Bit18] [Bit17] [ANCHOR]
Row 1: [Bit16]  [Bit15] [Bit14] [Bit13] [Bit12]
Row 2: [Bit11]  [Bit10] [Bit9]  [Bit8]  [Bit7]
Row 3: [Bit6]   [Bit5]  [Bit4]  [Bit3]  [Bit2]
Row 4: [ANCHOR] [Bit1]  [Bit0]  [PARITY][ANCHOR]
```

## Instructions
1. Find the 4 corner anchor dots to establish grid boundaries
2. Map each of the 25 positions: dot present = 1, empty = 0
3. Extract the 20 data bits (exclude corners and parity)
4. Convert binary to decimal, zero-pad to 8 digits

## Response Format
Show your grid mapping, then respond with ONLY this JSON (no other text after):
```json
{
  "success": true,
  "serial": "00000XXX",
  "binary": "20-bit-string",
  "confidence": "high/medium/low"
}
```

If you cannot decode, use success: false with an error message."""


def load_image_base64(image_path: str) -> str:
    """Load image and convert to base64."""
    with open(image_path, "rb") as f:
        return base64.b64encode(f.read()).decode("utf-8")


def get_mime_type(image_path: str) -> str:
    """Get MIME type from file extension."""
    ext = Path(image_path).suffix.lower()
    return {
        ".jpg": "image/jpeg",
        ".jpeg": "image/jpeg",
        ".png": "image/png",
        ".webp": "image/webp",
    }.get(ext, "image/jpeg")


def test_model(api_key: str, model_id: str, image_base64: str, mime_type: str, prompt: str = None) -> dict:
    """Test a single model's decode capability."""
    if prompt is None:
        prompt = DECODE_PROMPT
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json",
        "HTTP-Referer": "https://quadica.com",
        "X-Title": "Micro-ID Decoder Test",
    }

    payload = {
        "model": model_id,
        "messages": [
            {
                "role": "user",
                "content": [
                    {
                        "type": "image_url",
                        "image_url": {
                            "url": f"data:{mime_type};base64,{image_base64}"
                        },
                    },
                    {
                        "type": "text",
                        "text": prompt,
                    },
                ],
            }
        ],
        "max_tokens": 2048,
        "temperature": 0.1,  # Low temperature for more deterministic output
    }

    start_time = time.time()

    try:
        response = requests.post(
            OPENROUTER_API_URL,
            headers=headers,
            json=payload,
            timeout=120,
        )
        elapsed_ms = int((time.time() - start_time) * 1000)

        if response.status_code != 200:
            return {
                "success": False,
                "error": f"HTTP {response.status_code}: {response.text[:200]}",
                "elapsed_ms": elapsed_ms,
            }

        data = response.json()

        # Extract response text
        response_text = ""
        if "choices" in data and len(data["choices"]) > 0:
            message = data["choices"][0].get("message", {})
            response_text = message.get("content", "")

        # Parse JSON from response
        result = None
        json_match = re.search(r'```json\s*(\{.*?\})\s*```', response_text, re.DOTALL)
        if json_match:
            try:
                result = json.loads(json_match.group(1))
            except json.JSONDecodeError:
                pass

        if not result:
            # Try to find raw JSON
            json_match = re.search(r'(\{[^{}]*"success"[^{}]*\})', response_text, re.DOTALL)
            if json_match:
                try:
                    result = json.loads(json_match.group(1))
                except json.JSONDecodeError:
                    pass

        # Get usage info
        usage = data.get("usage", {})

        return {
            "success": True,
            "result": result,
            "response_text": response_text,
            "elapsed_ms": elapsed_ms,
            "input_tokens": usage.get("prompt_tokens", "N/A"),
            "output_tokens": usage.get("completion_tokens", "N/A"),
        }

    except requests.exceptions.Timeout:
        return {
            "success": False,
            "error": "Request timeout (120s)",
            "elapsed_ms": 120000,
        }
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "elapsed_ms": int((time.time() - start_time) * 1000),
        }


def main():
    parser = argparse.ArgumentParser(description="Test Micro-ID decode across vision models")
    parser.add_argument("image", help="Path to preprocessed crop image")
    parser.add_argument("--models", "-m", help="Comma-separated list of models to test (default: all)")
    parser.add_argument("--api-key", "-k", help="OpenRouter API key (or set OPENROUTER_API_KEY env var)")
    parser.add_argument("--expected", "-e", help="Expected serial number for comparison")
    parser.add_argument("--simple", "-s", action="store_true", help="Use simple grid-reading prompt")

    args = parser.parse_args()

    # Get API key
    api_key = args.api_key or os.environ.get("OPENROUTER_API_KEY")
    if not api_key:
        print("ERROR: OpenRouter API key required. Use --api-key or set OPENROUTER_API_KEY")
        return 1

    # Check image exists
    if not os.path.exists(args.image):
        print(f"ERROR: Image not found: {args.image}")
        return 1

    # Determine which models to test
    if args.models:
        model_ids = [m.strip() for m in args.models.split(",")]
    else:
        model_ids = list(VISION_MODELS.keys())

    # Load image
    print(f"\n{'='*60}")
    print("Micro-ID Multi-Model Decode Test (via OpenRouter)")
    print(f"{'='*60}\n")
    print(f"Image: {args.image}")
    print(f"Size: {os.path.getsize(args.image) / 1024:.1f} KB")
    if args.expected:
        print(f"Expected: {args.expected}")
    print(f"Models to test: {len(model_ids)}")
    print()

    image_base64 = load_image_base64(args.image)
    mime_type = get_mime_type(args.image)

    # Select prompt
    prompt = DECODE_PROMPT_SIMPLE if args.simple else DECODE_PROMPT
    if args.simple:
        print("Using SIMPLE grid-reading prompt\n")

    # Test each model
    results = []

    for model_id in model_ids:
        model_name = VISION_MODELS.get(model_id, model_id)
        print(f"Testing {model_name}...", end=" ", flush=True)

        result = test_model(api_key, model_id, image_base64, mime_type, prompt)
        result["model_id"] = model_id
        result["model_name"] = model_name
        results.append(result)

        if result["success"]:
            decoded = result.get("result", {})
            if decoded and decoded.get("success"):
                serial = decoded.get("serial", "???")
                match = ""
                if args.expected:
                    match = " ✓" if serial == args.expected else " ✗"
                print(f"{serial}{match} ({result['elapsed_ms']}ms)")
            else:
                error = decoded.get("error", "decode failed") if decoded else "no result"
                print(f"FAILED: {error} ({result['elapsed_ms']}ms)")
        else:
            print(f"ERROR: {result['error']}")

    # Summary
    print(f"\n{'='*60}")
    print("SUMMARY")
    print(f"{'='*60}\n")

    print(f"{'Model':<25} {'Serial':<12} {'Time':<10} {'Match'}")
    print("-" * 55)

    for r in results:
        model = r["model_name"][:24]
        if r["success"] and r.get("result") and r["result"].get("success"):
            serial = r["result"].get("serial", "???")
            time_str = f"{r['elapsed_ms']}ms"
            if args.expected:
                match = "✓" if serial == args.expected else "✗"
            else:
                match = "-"
        else:
            serial = "ERROR"
            time_str = "-"
            match = "-"
        print(f"{model:<25} {serial:<12} {time_str:<10} {match}")

    # Show detailed results for successful decodes
    print(f"\n{'='*60}")
    print("DETAILED RESULTS")
    print(f"{'='*60}")

    for r in results:
        if r["success"]:
            print(f"\n--- {r['model_name']} ---")
            decoded = r.get("result")
            if decoded:
                if decoded.get("success"):
                    print(f"Serial: {decoded.get('serial')}")
                    print(f"Binary: {decoded.get('binary')}")
                    print(f"Confidence: {decoded.get('confidence')}")
                elif decoded.get("grid"):
                    print(f"Grid: {decoded.get('grid')}")
                    for i in range(5):
                        row_key = f"row{i}"
                        if row_key in decoded:
                            print(f"Row {i}: {decoded[row_key]}")
                else:
                    print(f"Error: {decoded.get('error')}")
            else:
                print("No parsed result")
            # Show raw response
            print(f"\nRaw response (first 500 chars):")
            print(r.get("response_text", "")[:500])

            # Show first part of reasoning
            response = r.get("response_text", "")
            if response and len(response) > 100:
                # Show grid mapping if present
                grid_match = re.search(r'(Row \d.*?Row \d.*?\n)', response, re.DOTALL)
                if grid_match:
                    print(f"Grid mapping:\n{grid_match.group(1)[:300]}")

    return 0


if __name__ == "__main__":
    sys.exit(main())

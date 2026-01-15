#!/usr/bin/env python3
"""
Micro-ID Preprocessor POC

Demonstrates OpenCV-based preprocessing to:
1. Detect LED module in smartphone photo
2. Correct rotation/orientation
3. Extract and enhance the Micro-ID region

Usage:
    python microid-preprocessor.py <input_image> [--output <output_dir>] [--debug]

Requirements:
    pip install opencv-python numpy
"""

import cv2
import numpy as np
import argparse
import os
from pathlib import Path


class MicroIDPreprocessor:
    """Preprocesses smartphone photos to extract Micro-ID region."""

    # SZ-04 module specifications (relative to module bounds)
    # These are approximate - would need calibration with real images
    SZ04_SPECS = {
        'module_aspect_ratio': 1.0,  # Square module
        'microid_relative_x': 0.15,  # Micro-ID position from left edge (%)
        'microid_relative_y': 0.75,  # Micro-ID position from top edge (%)
        'microid_relative_size': 0.12,  # Micro-ID size relative to module (%)
    }

    def __init__(self, debug=False):
        self.debug = debug
        self.debug_images = {}

    def process(self, image_path: str, output_dir: str = None) -> dict:
        """
        Process an image to extract the Micro-ID region.

        Returns dict with:
            - success: bool
            - cropped_image: numpy array of extracted region (if successful)
            - rotation_angle: detected rotation
            - debug_images: dict of intermediate images (if debug=True)
            - error: error message (if failed)
        """
        # Load image
        img = cv2.imread(image_path)
        if img is None:
            return {'success': False, 'error': f'Could not load image: {image_path}'}

        original = img.copy()
        self.debug_images['01_original'] = original

        # Step 1: Detect module outline
        module_contour, rotation_angle = self._detect_module(img)

        if module_contour is None:
            return {
                'success': False,
                'error': 'Could not detect module outline',
                'debug_images': self.debug_images if self.debug else {}
            }

        # Draw detected contour for debug
        if self.debug:
            contour_img = original.copy()
            cv2.drawContours(contour_img, [module_contour], -1, (0, 255, 0), 3)
            self.debug_images['03_detected_contour'] = contour_img

        # Step 2: Get rotated bounding box and normalize orientation
        normalized, transform_matrix = self._normalize_orientation(img, module_contour)
        self.debug_images['04_normalized'] = normalized

        # Step 3: Extract Micro-ID region (try all 4 orientations)
        microid_crops = self._extract_microid_regions(normalized)

        # Step 4: Enhance the crops
        enhanced_crops = []
        for i, crop in enumerate(microid_crops):
            enhanced = self._enhance_microid(crop)
            enhanced_crops.append(enhanced)
            self.debug_images[f'05_enhanced_rotation_{i*90}'] = enhanced

        # Save outputs if output_dir specified
        if output_dir:
            self._save_outputs(output_dir, image_path, enhanced_crops)

        return {
            'success': True,
            'cropped_images': enhanced_crops,  # All 4 rotations
            'rotation_angle': rotation_angle,
            'debug_images': self.debug_images if self.debug else {}
        }

    def _detect_module(self, img: np.ndarray) -> tuple:
        """
        Detect the LED module outline in the image.
        Returns (contour, rotation_angle) or (None, None) if not found.
        """
        # Convert to grayscale
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

        # Apply Gaussian blur to reduce noise
        blurred = cv2.GaussianBlur(gray, (5, 5), 0)
        self.debug_images['02a_blurred'] = blurred

        # Edge detection
        edges = cv2.Canny(blurred, 50, 150)
        self.debug_images['02b_edges'] = edges

        # Dilate edges to connect broken lines
        kernel = np.ones((3, 3), np.uint8)
        dilated = cv2.dilate(edges, kernel, iterations=2)
        self.debug_images['02c_dilated'] = dilated

        # Find contours
        contours, _ = cv2.findContours(dilated, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        if not contours:
            return None, None

        # Find the largest roughly-square contour (the module)
        best_contour = None
        best_score = 0

        img_area = img.shape[0] * img.shape[1]

        for contour in contours:
            area = cv2.contourArea(contour)

            # Filter by size (module should be significant portion of image)
            if area < img_area * 0.05 or area > img_area * 0.95:
                continue

            # Get rotated bounding box
            rect = cv2.minAreaRect(contour)
            width, height = rect[1]

            if width == 0 or height == 0:
                continue

            # Check aspect ratio (looking for square-ish module)
            aspect_ratio = min(width, height) / max(width, height)

            # Score based on area and squareness
            score = area * aspect_ratio

            if score > best_score and aspect_ratio > 0.7:  # At least 70% square
                best_score = score
                best_contour = contour

        if best_contour is None:
            return None, None

        # Get rotation angle from minAreaRect
        rect = cv2.minAreaRect(best_contour)
        angle = rect[2]

        # Normalize angle to -45 to 45 range
        if angle < -45:
            angle += 90

        return best_contour, angle

    def _normalize_orientation(self, img: np.ndarray, contour: np.ndarray) -> tuple:
        """
        Rotate and crop the image to normalize the module orientation.
        Returns (normalized_image, transformation_matrix).
        """
        # Get the rotated bounding box
        rect = cv2.minAreaRect(contour)
        center, (width, height), angle = rect

        # Ensure width >= height for consistent orientation
        if width < height:
            width, height = height, width
            angle += 90

        # Get rotation matrix
        rotation_matrix = cv2.getRotationMatrix2D(center, angle, 1.0)

        # Calculate new image bounds after rotation
        cos = np.abs(rotation_matrix[0, 0])
        sin = np.abs(rotation_matrix[0, 1])
        new_width = int(img.shape[1] * cos + img.shape[0] * sin)
        new_height = int(img.shape[1] * sin + img.shape[0] * cos)

        # Adjust rotation matrix for new bounds
        rotation_matrix[0, 2] += (new_width - img.shape[1]) / 2
        rotation_matrix[1, 2] += (new_height - img.shape[0]) / 2

        # Rotate the image
        rotated = cv2.warpAffine(img, rotation_matrix, (new_width, new_height))

        # Recalculate contour position after rotation
        new_center = (
            rotation_matrix[0, 0] * center[0] + rotation_matrix[0, 1] * center[1] + rotation_matrix[0, 2],
            rotation_matrix[1, 0] * center[0] + rotation_matrix[1, 1] * center[1] + rotation_matrix[1, 2]
        )

        # Crop to module bounds with some padding
        padding = 20
        x1 = max(0, int(new_center[0] - width/2 - padding))
        y1 = max(0, int(new_center[1] - height/2 - padding))
        x2 = min(rotated.shape[1], int(new_center[0] + width/2 + padding))
        y2 = min(rotated.shape[0], int(new_center[1] + height/2 + padding))

        cropped = rotated[y1:y2, x1:x2]

        return cropped, rotation_matrix

    def _extract_microid_regions(self, normalized: np.ndarray) -> list:
        """
        Extract potential Micro-ID regions for all 4 possible orientations.
        Returns list of 4 cropped images.
        """
        h, w = normalized.shape[:2]
        crops = []

        # For each of 4 possible orientations
        for rotation in range(4):
            # Rotate the normalized image
            if rotation > 0:
                rotated = cv2.rotate(normalized, [
                    cv2.ROTATE_90_CLOCKWISE,
                    cv2.ROTATE_180,
                    cv2.ROTATE_90_COUNTERCLOCKWISE
                ][rotation - 1])
            else:
                rotated = normalized.copy()

            rh, rw = rotated.shape[:2]

            # Extract Micro-ID region based on SZ-04 specs
            # These coordinates are estimates - would need calibration
            specs = self.SZ04_SPECS

            # Calculate crop coordinates
            x = int(rw * specs['microid_relative_x'])
            y = int(rh * specs['microid_relative_y'])
            size = int(min(rw, rh) * specs['microid_relative_size'])

            # Expand crop area to be safe
            margin = size // 2
            x1 = max(0, x - margin)
            y1 = max(0, y - margin)
            x2 = min(rw, x + size + margin)
            y2 = min(rh, y + size + margin)

            crop = rotated[y1:y2, x1:x2]

            # If crop is too small, just take center region
            if crop.shape[0] < 50 or crop.shape[1] < 50:
                center_size = min(rw, rh) // 4
                cx, cy = rw // 2, rh // 2
                crop = rotated[cy-center_size:cy+center_size, cx-center_size:cx+center_size]

            crops.append(crop)

        return crops

    def _enhance_microid(self, crop: np.ndarray) -> np.ndarray:
        """
        Enhance the Micro-ID crop for better visibility.
        """
        if crop.size == 0:
            return crop

        # Resize to standard size for consistent processing
        target_size = 400
        h, w = crop.shape[:2]
        scale = target_size / max(h, w)
        new_w, new_h = int(w * scale), int(h * scale)
        resized = cv2.resize(crop, (new_w, new_h), interpolation=cv2.INTER_CUBIC)

        # Convert to grayscale for processing
        if len(resized.shape) == 3:
            gray = cv2.cvtColor(resized, cv2.COLOR_BGR2GRAY)
        else:
            gray = resized

        # Apply CLAHE (Contrast Limited Adaptive Histogram Equalization)
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        enhanced = clahe.apply(gray)

        # Sharpen
        kernel = np.array([[-1, -1, -1],
                          [-1,  9, -1],
                          [-1, -1, -1]])
        sharpened = cv2.filter2D(enhanced, -1, kernel)

        # Convert back to BGR for consistency
        result = cv2.cvtColor(sharpened, cv2.COLOR_GRAY2BGR)

        return result

    def _save_outputs(self, output_dir: str, input_path: str, enhanced_crops: list):
        """Save all output images to the specified directory."""
        os.makedirs(output_dir, exist_ok=True)

        base_name = Path(input_path).stem

        # Save enhanced crops (all 4 rotations)
        for i, crop in enumerate(enhanced_crops):
            if crop.size > 0:
                output_path = os.path.join(output_dir, f'{base_name}_microid_rot{i*90}.jpg')
                cv2.imwrite(output_path, crop)
                print(f'  Saved: {output_path}')

        # Save debug images if enabled
        if self.debug:
            debug_dir = os.path.join(output_dir, 'debug')
            os.makedirs(debug_dir, exist_ok=True)

            for name, img in self.debug_images.items():
                if img is not None and img.size > 0:
                    output_path = os.path.join(debug_dir, f'{base_name}_{name}.jpg')
                    cv2.imwrite(output_path, img)
                    print(f'  Debug: {output_path}')


def main():
    parser = argparse.ArgumentParser(description='Micro-ID Preprocessor POC')
    parser.add_argument('input', help='Input image path or directory')
    parser.add_argument('--output', '-o', default='./output', help='Output directory')
    parser.add_argument('--debug', '-d', action='store_true', help='Save debug images')

    args = parser.parse_args()

    preprocessor = MicroIDPreprocessor(debug=args.debug)

    # Handle single file or directory
    input_path = Path(args.input)

    if input_path.is_file():
        files = [input_path]
    elif input_path.is_dir():
        files = list(input_path.glob('*.jpg')) + list(input_path.glob('*.jpeg')) + list(input_path.glob('*.png'))
    else:
        print(f'Error: {args.input} is not a valid file or directory')
        return 1

    print(f'\nMicro-ID Preprocessor POC')
    print(f'=' * 50)
    print(f'Processing {len(files)} image(s)...\n')

    for file_path in files:
        print(f'Processing: {file_path}')
        result = preprocessor.process(str(file_path), args.output)

        if result['success']:
            print(f'  Status: SUCCESS')
            print(f'  Rotation detected: {result["rotation_angle"]:.1f} degrees')
            print(f'  Generated {len(result["cropped_images"])} orientation variants')
        else:
            print(f'  Status: FAILED')
            print(f'  Error: {result["error"]}')
        print()

    print(f'Output saved to: {args.output}')
    return 0


if __name__ == '__main__':
    exit(main())

#!/usr/bin/env python3
"""
Micro-ID Preprocessor POC v6 - Coordinate-Based Extraction

Uses known Micro-ID coordinates relative to module outline.
Much more reliable than feature detection since position is fixed per module type.

Approach:
1. Detect module outline (corners) using contour detection
2. Calculate scale (pixels per mm) and orientation
3. Apply known Micro-ID coordinates for the module type
4. Extract and enhance the region

SZ-04 Module Specifications:
- Module size: 20mm x 20mm (assumed, configurable)
- Micro-ID bounding box (from bottom-left origin):
  - Bottom-left corner: (4.1281mm, 6.2953mm)
  - Size: 1.825mm x 1.825mm
  - Internal padding: 0.3mm

Usage:
    python microid-preprocessor.py <input_image> [--output <output_dir>] [--debug]
"""

import cv2
import numpy as np
import argparse
import os
from pathlib import Path


class ModuleSpec:
    """Specifications for a module type."""

    def __init__(self, name: str, width_mm: float, height_mm: float,
                 microid_x_mm: float, microid_y_mm: float,
                 microid_size_mm: float, microid_padding_mm: float = 0.3):
        self.name = name
        self.width_mm = width_mm
        self.height_mm = height_mm
        # Micro-ID position from bottom-left corner (AutoCAD UCS)
        self.microid_x_mm = microid_x_mm
        self.microid_y_mm = microid_y_mm
        self.microid_size_mm = microid_size_mm
        self.microid_padding_mm = microid_padding_mm

    def get_microid_rect_mm(self):
        """Get Micro-ID rectangle in mm (x, y, w, h) from bottom-left origin."""
        return (
            self.microid_x_mm,
            self.microid_y_mm,
            self.microid_size_mm,
            self.microid_size_mm
        )


# Pre-defined module specifications
MODULE_SPECS = {
    'SZ-04': ModuleSpec(
        name='SZ-04',
        width_mm=20.0,
        height_mm=20.0,
        microid_x_mm=4.1281,
        microid_y_mm=6.2953,
        microid_size_mm=1.825,
        microid_padding_mm=0.3
    ),
    # Add other module types here as needed
}


class MicroIDPreprocessor:
    """Preprocesses smartphone photos to extract Micro-ID region."""

    def __init__(self, module_type: str = 'SZ-04', debug: bool = False, tight_crop: bool = False, raw_color: bool = False):
        self.module_spec = MODULE_SPECS.get(module_type)
        if not self.module_spec:
            raise ValueError(f"Unknown module type: {module_type}")
        self.debug = debug
        self.tight_crop = tight_crop  # Use smaller crop focused on dot grid
        self.raw_color = raw_color  # Keep original colors without enhancement
        self.debug_images = {}

    def process(self, image_path: str, output_dir: str = None) -> dict:
        """Process an image to extract the Micro-ID region."""
        img = cv2.imread(image_path)
        if img is None:
            return {'success': False, 'error': f'Could not load image: {image_path}'}

        h, w = img.shape[:2]
        self.debug_images['01_original'] = img.copy()
        print(f'  Image size: {w}x{h}')
        print(f'  Module type: {self.module_spec.name}')

        # Step 1: Try to detect module outline
        module_corners, rotation_angle = self._detect_module_outline(img)

        if module_corners is not None:
            print(f'  Module detected, rotation: {rotation_angle:.1f}°')
            # Step 2: Calculate transformation
            transform, scale_px_per_mm = self._calculate_transform(module_corners, img.shape)
            print(f'  Scale: {scale_px_per_mm:.1f} px/mm')
            # Step 3: Extract Micro-ID region using known coordinates
            crops = self._extract_microid_region(img, module_corners, scale_px_per_mm, rotation_angle)
            detection_method = 'coordinate_based'
        else:
            # Fallback: Assume module fills most of the frame
            print(f'  Module outline not detected, using frame-based fallback')
            crops, scale_px_per_mm, rotation_angle = self._extract_assuming_full_frame(img)
            detection_method = 'frame_fallback'

        if not crops:
            return {'success': False, 'error': 'Could not extract Micro-ID region'}

        result = {
            'success': True,
            'cropped_images': crops,
            'detection_method': detection_method,
            'scale_px_per_mm': scale_px_per_mm,
            'rotation_angle': rotation_angle
        }

        result['debug_images'] = self.debug_images if self.debug else {}

        if output_dir:
            self._save_outputs(output_dir, image_path, result)

        return result

    def _detect_module_outline(self, img: np.ndarray):
        """
        Detect the module's rectangular outline.
        Returns (corners, rotation_angle) or (None, None).
        """
        gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
        h, w = gray.shape

        # Blur and edge detection
        blurred = cv2.GaussianBlur(gray, (5, 5), 0)
        edges = cv2.Canny(blurred, 50, 150)

        if self.debug:
            self.debug_images['02_edges'] = edges

        # Dilate to connect edges
        kernel = np.ones((3, 3), np.uint8)
        dilated = cv2.dilate(edges, kernel, iterations=2)

        # Find contours
        contours, _ = cv2.findContours(dilated, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        if not contours:
            return None, None

        # Find the largest roughly-square contour
        img_area = w * h
        best_contour = None
        best_score = 0

        for contour in contours:
            area = cv2.contourArea(contour)

            # Module should be significant portion of image (10-90%)
            if area < img_area * 0.10 or area > img_area * 0.95:
                continue

            # Approximate to polygon
            epsilon = 0.02 * cv2.arcLength(contour, True)
            approx = cv2.approxPolyDP(contour, epsilon, True)

            # Get rotated bounding box
            rect = cv2.minAreaRect(contour)
            box_w, box_h = rect[1]

            if box_w == 0 or box_h == 0:
                continue

            # Check aspect ratio (should be square-ish)
            aspect = min(box_w, box_h) / max(box_w, box_h)

            if aspect > 0.8:  # Close to square
                score = area * aspect
                if score > best_score:
                    best_score = score
                    best_contour = contour

        if best_contour is None:
            return None, None

        # Get the rotated bounding box
        rect = cv2.minAreaRect(best_contour)
        box = cv2.boxPoints(rect)
        box = np.int32(box)

        # Get rotation angle
        angle = rect[2]
        if rect[1][0] < rect[1][1]:
            angle = angle - 90

        if self.debug:
            debug_img = img.copy()
            cv2.drawContours(debug_img, [box], 0, (0, 255, 0), 3)
            self.debug_images['03_module_detected'] = debug_img

        return box, angle

    def _calculate_transform(self, corners: np.ndarray, img_shape: tuple):
        """
        Calculate the transformation matrix and scale.
        """
        # Get bounding box dimensions
        rect = cv2.minAreaRect(corners)
        box_w, box_h = rect[1]

        # The module should be square, so average the dimensions
        module_px = max(box_w, box_h)

        # Calculate scale
        module_mm = max(self.module_spec.width_mm, self.module_spec.height_mm)
        scale_px_per_mm = module_px / module_mm

        return rect, scale_px_per_mm

    def _extract_microid_region(self, img: np.ndarray, corners: np.ndarray,
                                 scale: float, rotation: float) -> list:
        """
        Extract the Micro-ID region using known coordinates.
        """
        h, w = img.shape[:2]
        spec = self.module_spec

        # Get the rotated bounding box center and dimensions
        rect = cv2.minAreaRect(corners)
        center = rect[0]
        box_w, box_h = rect[1]

        # Sort corners to find bottom-left
        # corners from boxPoints are in order: bottom-left, top-left, top-right, bottom-right
        # but may be rotated
        corners_sorted = self._sort_corners(corners, rotation)

        if corners_sorted is None:
            # Fallback: use center-based extraction
            return self._extract_from_center(img, center, scale, rotation)

        bottom_left = corners_sorted[0]

        # Calculate Micro-ID position in pixels
        # From bottom-left origin, going up and right
        microid_x_px = spec.microid_x_mm * scale
        microid_y_px = spec.microid_y_mm * scale
        microid_size_px = spec.microid_size_mm * scale

        # Add some padding for safety
        padding_px = microid_size_px * 0.3

        # Convert from bottom-left origin to image coordinates
        # Need to account for rotation
        angle_rad = np.radians(rotation)
        cos_a = np.cos(angle_rad)
        sin_a = np.sin(angle_rad)

        # Offset from bottom-left corner
        offset_x = microid_x_px * cos_a - microid_y_px * sin_a
        offset_y = microid_x_px * sin_a + microid_y_px * cos_a

        # In image coordinates (Y is inverted from AutoCAD)
        microid_center_x = bottom_left[0] + offset_x + microid_size_px / 2
        microid_center_y = bottom_left[1] - offset_y - microid_size_px / 2

        # Extract region with padding
        crop_size = int(microid_size_px + padding_px * 2)
        x1 = max(0, int(microid_center_x - crop_size / 2))
        y1 = max(0, int(microid_center_y - crop_size / 2))
        x2 = min(w, int(microid_center_x + crop_size / 2))
        y2 = min(h, int(microid_center_y + crop_size / 2))

        if self.debug:
            debug_img = img.copy()
            cv2.rectangle(debug_img, (x1, y1), (x2, y2), (0, 255, 0), 2)
            cv2.circle(debug_img, (int(microid_center_x), int(microid_center_y)), 5, (0, 0, 255), -1)
            cv2.circle(debug_img, (int(bottom_left[0]), int(bottom_left[1])), 8, (255, 0, 0), -1)
            self.debug_images['04_extraction_region'] = debug_img

        crop = img[y1:y2, x1:x2]

        if crop.size == 0:
            return self._extract_from_center(img, center, scale, rotation)

        # Rotate crop to normalize orientation if needed
        if abs(rotation) > 5:
            crop = self._rotate_crop(crop, -rotation)

        enhanced = self._enhance(crop)

        # Return all 4 orientations
        crops = [enhanced]
        for rot in [cv2.ROTATE_90_CLOCKWISE, cv2.ROTATE_180, cv2.ROTATE_90_COUNTERCLOCKWISE]:
            crops.append(cv2.rotate(enhanced, rot))

        return crops

    def _sort_corners(self, corners: np.ndarray, rotation: float) -> np.ndarray:
        """
        Sort corners to identify bottom-left, top-left, top-right, bottom-right.
        Returns corners in order: [BL, TL, TR, BR] or None if failed.
        """
        try:
            # Find centroid
            cx = np.mean(corners[:, 0])
            cy = np.mean(corners[:, 1])

            # Calculate angles from centroid
            angles = []
            for corner in corners:
                angle = np.arctan2(corner[1] - cy, corner[0] - cx)
                angles.append(angle)

            # Sort by angle
            sorted_indices = np.argsort(angles)
            sorted_corners = corners[sorted_indices]

            # In image coordinates (Y down), bottom-left has positive Y and negative X relative to center
            # Rearrange based on position relative to centroid
            result = np.zeros_like(sorted_corners)

            for i, corner in enumerate(corners):
                dx = corner[0] - cx
                dy = corner[1] - cy

                if dx < 0 and dy > 0:  # Bottom-left (image coords)
                    result[0] = corner
                elif dx < 0 and dy < 0:  # Top-left
                    result[1] = corner
                elif dx > 0 and dy < 0:  # Top-right
                    result[2] = corner
                else:  # Bottom-right
                    result[3] = corner

            return result
        except:
            return None

    def _extract_from_center(self, img: np.ndarray, center: tuple,
                             scale: float, rotation: float) -> list:
        """
        Fallback extraction using module center.
        """
        h, w = img.shape[:2]
        spec = self.module_spec

        # Calculate Micro-ID position relative to center
        # Module center is at (width/2, height/2) in mm
        module_center_mm = (spec.width_mm / 2, spec.height_mm / 2)

        # Micro-ID center in mm from module origin
        microid_center_mm = (
            spec.microid_x_mm + spec.microid_size_mm / 2,
            spec.microid_y_mm + spec.microid_size_mm / 2
        )

        # Offset from module center
        offset_mm = (
            microid_center_mm[0] - module_center_mm[0],
            microid_center_mm[1] - module_center_mm[1]
        )

        # Convert to pixels
        offset_px = (offset_mm[0] * scale, offset_mm[1] * scale)

        # Apply rotation
        angle_rad = np.radians(rotation)
        cos_a = np.cos(angle_rad)
        sin_a = np.sin(angle_rad)

        rotated_offset = (
            offset_px[0] * cos_a - offset_px[1] * sin_a,
            offset_px[0] * sin_a + offset_px[1] * cos_a
        )

        # Micro-ID center in image coordinates (Y inverted)
        microid_center = (
            center[0] + rotated_offset[0],
            center[1] - rotated_offset[1]
        )

        # Extract region
        crop_size = int(spec.microid_size_mm * scale * 1.6)  # Add padding
        x1 = max(0, int(microid_center[0] - crop_size / 2))
        y1 = max(0, int(microid_center[1] - crop_size / 2))
        x2 = min(w, int(microid_center[0] + crop_size / 2))
        y2 = min(h, int(microid_center[1] + crop_size / 2))

        if self.debug:
            debug_img = img.copy()
            cv2.rectangle(debug_img, (x1, y1), (x2, y2), (255, 255, 0), 2)
            cv2.circle(debug_img, (int(microid_center[0]), int(microid_center[1])), 5, (0, 0, 255), -1)
            self.debug_images['04b_fallback_region'] = debug_img

        crop = img[y1:y2, x1:x2]

        if crop.size == 0:
            return []

        enhanced = self._enhance(crop)

        crops = [enhanced]
        for rot in [cv2.ROTATE_90_CLOCKWISE, cv2.ROTATE_180, cv2.ROTATE_90_COUNTERCLOCKWISE]:
            crops.append(cv2.rotate(enhanced, rot))

        return crops

    def _extract_assuming_full_frame(self, img: np.ndarray) -> tuple:
        """
        Fallback: Assume the module fills most of the frame.
        Try all 4 possible orientations and extract Micro-ID from each.
        """
        h, w = img.shape[:2]
        spec = self.module_spec

        # Assume the module is roughly centered and fills most of the smaller dimension
        module_px = min(w, h) * 0.9
        module_mm = max(spec.width_mm, spec.height_mm)
        scale = module_px / module_mm

        print(f'  Fallback scale: {scale:.1f} px/mm')
        print(f'  Trying all 4 orientations...')

        crops = []
        debug_img = img.copy() if self.debug else None

        # Colors for debug visualization
        colors = [
            (0, 255, 255),   # Yellow - 0°
            (255, 0, 255),   # Magenta - 90°
            (255, 255, 0),   # Cyan - 180°
            (0, 255, 0),     # Green - 270°
        ]

        # Try all 4 orientations
        for rot_idx, rotation_deg in enumerate([0, 90, 180, 270]):
            # Rotate the image to test this orientation
            if rotation_deg == 0:
                rotated_img = img
            elif rotation_deg == 90:
                rotated_img = cv2.rotate(img, cv2.ROTATE_90_CLOCKWISE)
            elif rotation_deg == 180:
                rotated_img = cv2.rotate(img, cv2.ROTATE_180)
            else:  # 270
                rotated_img = cv2.rotate(img, cv2.ROTATE_90_COUNTERCLOCKWISE)

            rh, rw = rotated_img.shape[:2]

            # Recalculate scale for rotated dimensions
            rot_module_px = min(rw, rh) * 0.9
            rot_scale = rot_module_px / module_mm

            # Calculate Micro-ID position for this orientation
            img_center = (rw / 2, rh / 2)
            module_center_mm = (spec.width_mm / 2, spec.height_mm / 2)

            microid_center_mm = (
                spec.microid_x_mm + spec.microid_size_mm / 2,
                spec.microid_y_mm + spec.microid_size_mm / 2
            )

            offset_mm = (
                microid_center_mm[0] - module_center_mm[0],
                -(microid_center_mm[1] - module_center_mm[1])
            )

            offset_px = (offset_mm[0] * rot_scale, offset_mm[1] * rot_scale)

            microid_center = (
                img_center[0] + offset_px[0],
                img_center[1] + offset_px[1]
            )

            # Extract region
            # Tight crop: use actual dot grid size (bounding box minus padding)
            # Normal crop: use 2x bounding box for margin
            if self.tight_crop:
                # Actual dot grid is bounding box minus 2x padding
                dot_grid_mm = spec.microid_size_mm - (2 * spec.microid_padding_mm)
                crop_size = int(dot_grid_mm * rot_scale * 1.8)  # Moderate margin
            else:
                crop_size = int(spec.microid_size_mm * rot_scale * 2.0)
            x1 = max(0, int(microid_center[0] - crop_size / 2))
            y1 = max(0, int(microid_center[1] - crop_size / 2))
            x2 = min(rw, int(microid_center[0] + crop_size / 2))
            y2 = min(rh, int(microid_center[1] + crop_size / 2))

            crop = rotated_img[y1:y2, x1:x2]

            if crop.size > 0:
                if self.raw_color:
                    # Just resize without enhancement
                    target = 500
                    h, w = crop.shape[:2]
                    scale = target / max(h, w)
                    nw, nh = max(1, int(w * scale)), max(1, int(h * scale))
                    processed = cv2.resize(crop, (nw, nh), interpolation=cv2.INTER_CUBIC)
                else:
                    processed = self._enhance(crop)
                crops.append(processed)
                print(f'    {rotation_deg}°: extracted crop {crop.shape[1]}x{crop.shape[0]}')

            # Add to debug visualization (transform coordinates back to original image)
            if self.debug and debug_img is not None:
                # Calculate where this crop region is in the original image
                orig_points = self._transform_rect_to_original(
                    x1, y1, x2, y2, rotation_deg, w, h
                )
                if orig_points is not None:
                    pts = np.array(orig_points, np.int32).reshape((-1, 1, 2))
                    cv2.polylines(debug_img, [pts], True, colors[rot_idx], 2)
                    # Label
                    cv2.putText(debug_img, f'{rotation_deg}',
                               (orig_points[0][0] + 5, orig_points[0][1] + 15),
                               cv2.FONT_HERSHEY_SIMPLEX, 0.5, colors[rot_idx], 2)

        if self.debug and debug_img is not None:
            self.debug_images['04_all_orientations'] = debug_img

        return crops, scale, 0.0

    def _transform_rect_to_original(self, x1, y1, x2, y2, rotation_deg, orig_w, orig_h):
        """Transform rectangle coordinates from rotated image back to original."""
        if rotation_deg == 0:
            return [(x1, y1), (x2, y1), (x2, y2), (x1, y2)]
        elif rotation_deg == 90:
            # 90° CW: (x,y) in rotated -> (y, orig_w - x) in original
            return [
                (y1, orig_w - x2),
                (y1, orig_w - x1),
                (y2, orig_w - x1),
                (y2, orig_w - x2)
            ]
        elif rotation_deg == 180:
            # 180°: (x,y) -> (orig_w - x, orig_h - y)
            return [
                (orig_w - x2, orig_h - y2),
                (orig_w - x1, orig_h - y2),
                (orig_w - x1, orig_h - y1),
                (orig_w - x2, orig_h - y1)
            ]
        else:  # 270
            # 270° CW (90° CCW): (x,y) in rotated -> (orig_h - y, x) in original
            return [
                (orig_h - y2, x1),
                (orig_h - y1, x1),
                (orig_h - y1, x2),
                (orig_h - y2, x2)
            ]

    def _rotate_crop(self, crop: np.ndarray, angle: float) -> np.ndarray:
        """Rotate crop to normalize orientation."""
        h, w = crop.shape[:2]
        center = (w // 2, h // 2)
        matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
        rotated = cv2.warpAffine(crop, matrix, (w, h), borderMode=cv2.BORDER_REPLICATE)
        return rotated

    def _enhance(self, crop: np.ndarray) -> np.ndarray:
        """Enhance the Micro-ID crop."""
        if crop.size == 0:
            return crop

        # Resize to standard size
        target = 500
        h, w = crop.shape[:2]
        if h == 0 or w == 0:
            return crop

        scale = target / max(h, w)
        nw, nh = max(1, int(w * scale)), max(1, int(h * scale))
        resized = cv2.resize(crop, (nw, nh), interpolation=cv2.INTER_CUBIC)

        # Convert to grayscale
        if len(resized.shape) == 3:
            gray = cv2.cvtColor(resized, cv2.COLOR_BGR2GRAY)
        else:
            gray = resized

        # CLAHE for contrast
        clahe = cv2.createCLAHE(clipLimit=3.0, tileGridSize=(8, 8))
        enhanced = clahe.apply(gray)

        # Sharpen
        kernel = np.array([[-1, -1, -1], [-1, 9, -1], [-1, -1, -1]])
        sharp = cv2.filter2D(enhanced, -1, kernel)

        # Contrast boost
        result = cv2.convertScaleAbs(sharp, alpha=1.3, beta=10)

        return cv2.cvtColor(result, cv2.COLOR_GRAY2BGR)

    def _save_outputs(self, output_dir: str, input_path: str, result: dict):
        """Save output images."""
        os.makedirs(output_dir, exist_ok=True)
        base = Path(input_path).stem

        for i, crop in enumerate(result.get('cropped_images', [])):
            if crop is not None and crop.size > 0:
                p = os.path.join(output_dir, f'{base}_crop_{i}.jpg')
                cv2.imwrite(p, crop)
                print(f'  Saved: {p}')

        if self.debug and result.get('debug_images'):
            ddir = os.path.join(output_dir, 'debug')
            os.makedirs(ddir, exist_ok=True)
            for name, im in result['debug_images'].items():
                if im is not None and hasattr(im, 'size') and im.size > 0:
                    p = os.path.join(ddir, f'{base}_{name}.jpg')
                    cv2.imwrite(p, im)
                    print(f'  Debug: {p}')


def main():
    parser = argparse.ArgumentParser(description='Micro-ID Preprocessor POC v6')
    parser.add_argument('input', help='Input image path or directory')
    parser.add_argument('--output', '-o', default='./output', help='Output directory')
    parser.add_argument('--module', '-m', default='SZ-04', help='Module type')
    parser.add_argument('--debug', '-d', action='store_true', help='Save debug images')
    parser.add_argument('--tight', '-t', action='store_true', help='Use tight crop (dot grid only)')
    parser.add_argument('--raw', '-r', action='store_true', help='Keep raw colors (no grayscale enhancement)')

    args = parser.parse_args()

    try:
        preprocessor = MicroIDPreprocessor(module_type=args.module, debug=args.debug, tight_crop=args.tight, raw_color=args.raw)
    except ValueError as e:
        print(f'Error: {e}')
        print(f'Available modules: {", ".join(MODULE_SPECS.keys())}')
        return 1

    input_path = Path(args.input)

    if input_path.is_file():
        files = [input_path]
    elif input_path.is_dir():
        files = list(input_path.glob('*.jpg')) + list(input_path.glob('*.jpeg'))
    else:
        print(f'Error: invalid input')
        return 1

    print(f'\nMicro-ID Preprocessor POC v6 (Coordinate-Based)')
    print('=' * 50)

    for f in files:
        print(f'\nProcessing: {f}')
        result = preprocessor.process(str(f), args.output)

        if result['success']:
            print(f'  SUCCESS: {result.get("detection_method")}')
            print(f'  Scale: {result.get("scale_px_per_mm", 0):.1f} px/mm')
            print(f'  Rotation: {result.get("rotation_angle", 0):.1f}°')
        else:
            print(f'  FAILED: {result.get("error")}')

    print(f'\nOutput: {args.output}')
    return 0


if __name__ == '__main__':
    exit(main())

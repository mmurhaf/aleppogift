import os
import subprocess
import glob
from PIL import Image, ImageFilter

# FFmpeg path - try to find it or use command
FFMPEG_PATH = None
print("Initializing VideoEngine...")

# 1. Check specific path found in logs (hardcoded from valid discovery)
specific_path = r"C:\Users\DeLL\AppData\Local\Microsoft\WinGet\Packages\Gyan.FFmpeg_Microsoft.Winget.Source_8wekyb3d8bbwe\ffmpeg-8.0.1-full_build\bin\ffmpeg.exe"

possible_ffmpeg_locations = [
    "ffmpeg",  # If in PATH
    specific_path,
    r"C:\ffmpeg\bin\ffmpeg.exe",
]

# 2. Add glob search for WinGet packages if specific path doesn't exist
if not os.path.exists(specific_path):
    search_pattern = os.path.expandvars(r"$LOCALAPPDATA\Microsoft\WinGet\Packages\**\ffmpeg.exe")
    matches = glob.glob(search_pattern, recursive=True)
    if matches:
        possible_ffmpeg_locations.insert(1, matches[0])

for path in possible_ffmpeg_locations:
    try:
        if path == "ffmpeg":
            # Test command
            result = subprocess.run([path, "-version"], stdout=subprocess.PIPE, stderr=subprocess.PIPE, timeout=5)
            if result.returncode == 0:
                FFMPEG_PATH = path
                print(f"VideoEngine: Found FFmpeg in PATH")
                break
        elif os.path.exists(path):
            FFMPEG_PATH = path
            print(f"VideoEngine: Found FFmpeg at {path}")
            break
    except Exception as e:
        print(f"VideoEngine: Error checking {path}: {e}")
        continue

if not FFMPEG_PATH:
    print("VideoEngine: WARNING - FFmpeg not found during initialization.")

class VideoGenerator:
    def __init__(self, quality='medium'):
        """
        quality: 'low' (720p), 'medium' (1080p), 'high' (1080p, high bitrate)
        """
        self.quality_presets = {
            'low': {'width': 720, 'height': 1280, 'bitrate': '2M'},
            'medium': {'width': 1080, 'height': 1920, 'bitrate': '4M'},
            'high': {'width': 1080, 'height': 1920, 'bitrate': '8M'}
        }
        self.current_preset = self.quality_presets.get(quality, self.quality_presets['medium'])
    
    def create_video(self, image_path, output_path, duration=15, audio_path=None, text_overlay=None):
        """
        Create video from single or multiple images.
        Multiple images: Creates slides with 2 images side-by-side per slide.
        
        Args:
            image_path: str or list of str - path(s) to image(s)
            output_path: str - output video path
            duration: int - total video duration in seconds
            audio_path: str - optional background music path
            text_overlay: str - optional text overlay
        """
        
        # Ensure output directory exists
        os.makedirs(os.path.dirname(output_path), exist_ok=True)

        # Handle both single image and multiple images
        if isinstance(image_path, str):
            image_paths = [image_path]
        else:
            image_paths = image_path
        
        print(f"Creating video with {len(image_paths)} image(s)")
        
        # If multiple images, create side-by-side slides
        if len(image_paths) > 1:
            return self._create_multi_image_video(image_paths, output_path, duration, audio_path)
        
        # Single image - original logic
        temp_video = output_path.replace('.mp4', '_temp.mp4')
        
        # 1. & 2. Prepare Image (Smart Resize)
        try:
            processed_image = self.apply_smart_resize(image_paths[0])
            print(f"Processed image saved to: {processed_image}")
        except Exception as e:
            print(f"Error processing image: {e}")
            return None
        
        # 3. Ken Burns Effect (Zoompan)
        width = self.current_preset['width']
        height = self.current_preset['height']
        bitrate = self.current_preset['bitrate']
        
        # Construct filter chain
        # Zoompan: smooth zoom in
        filter_complex = (
            f"zoompan=z='min(zoom+0.0015,1.2)':d={duration}*25:"
            f"x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)',"
            f"scale={width}:{height}"
        )
        
        cmd = [
            FFMPEG_PATH if FFMPEG_PATH else 'ffmpeg', '-y',
            '-loop', '1',
            '-i', processed_image,
            '-vf', filter_complex,
            '-c:v', 'libx264',
            '-t', str(duration),
            '-b:v', bitrate,
            '-pix_fmt', 'yuv420p',
            temp_video
        ]
        
        print(f"Executing FFmpeg command: {cmd}")
        
        try:
            # Use text=True to get string output in stderr
            result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if result.returncode != 0:
                print(f"FFmpeg Error Output: {result.stderr}")
                return None
        except FileNotFoundError as e:
            print(f"ERROR: It seems FFmpeg executable was not found when trying to run subprocess. Path used: {cmd[0]}")
            return None
        except Exception as e:
            print(f"ERROR executing FFmpeg: {e}")
            return None
            
        # 5. Merge Audio
        if audio_path and os.path.exists(audio_path):
            print(f"Merging audio from: {audio_path}")
            final_cmd = [
                FFMPEG_PATH if FFMPEG_PATH else 'ffmpeg', '-y',
                '-i', temp_video,
                '-i', audio_path,
                '-map', '0:v',
                '-map', '1:a',
                '-c:v', 'copy', # Copy video stream without re-encoding
                '-c:a', 'aac',
                '-shortest', # Cut audio to video length
                output_path
            ]
            try:
                subprocess.run(final_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
                if os.path.exists(temp_video):
                    os.remove(temp_video)
            except subprocess.CalledProcessError as e:
                 print(f"Error merging audio: {e}")
                 # Fallback to silent video if audio merge fails
                 if os.path.exists(temp_video):
                     if os.path.exists(output_path): os.remove(output_path)
                     os.rename(temp_video, output_path)
        else:
            # If no audio, just rename temp to output
            if os.path.exists(output_path):
                os.remove(output_path)
            os.rename(temp_video, output_path)
            
        # Cleanup processed image if it was temp
        if processed_image != image_path and os.path.exists(processed_image):
             try:
                os.remove(processed_image)
             except:
                 pass

        if os.path.exists(output_path):
             print(f"Video generated successfully: {output_path}")
             return output_path
        else:
             print("Video generation finished but output file missing.")
             return None
    
    def apply_smart_resize(self, image_path):
        """
        Create proper 9:16 video frame from various image aspect ratios.
        NEVER stretches - always maintains aspect ratio.
        Uses blurred background with properly scaled foreground.
        """
        # Ensure image path is absolute
        image_path = os.path.abspath(image_path)
        
        with Image.open(image_path) as img:
            width, height = img.size
            target_width = self.current_preset['width']
            target_height = self.current_preset['height']
            
            # Calculate aspect ratios
            # 9:16 = 0.5625
            image_ratio = width / height
            target_ratio = target_width / target_height
            
            print(f"Image dimensions: {width}x{height} (ratio: {image_ratio:.3f})")
            print(f"Target dimensions: {target_width}x{target_height} (ratio: {target_ratio:.3f})")
            
            # If already close to target aspect ratio (portrait)
            if 0.5 < image_ratio < 0.65:
                print("Image aspect ratio is good, cropping to fill to avoid stretching")
                return self._create_centered_crop(img, image_path, target_width, target_height, image_ratio)
            
            # For all other aspect ratios, use blurred background with proper scaling
            print("Creating blurred background composition (no stretching)")
            return self._create_blurred_background(img, image_path, target_width, target_height, image_ratio)

    def _create_centered_crop(self, img, image_path, target_width, target_height, image_ratio):
        """
        Crop image to exact target dimensions (Zoom to Fill).
        Used for images that are close to target aspect ratio.
        """
        # Calculate size to fill target while maintaining aspect ratio
        if image_ratio > (target_width / target_height):
            # Image is wider - scale by height, crop width
            new_height = target_height
            new_width = int(new_height * image_ratio)
        else:
            # Image is taller - scale by width, crop height
            new_width = target_width
            new_height = int(new_width / image_ratio)
            
        # Resize
        resized = img.resize((new_width, new_height), Image.Resampling.LANCZOS)
        
        # Center crop
        left = (new_width - target_width) // 2
        top = (new_height - target_height) // 2
        
        cropped = resized.crop((left, top, left + target_width, top + target_height))
        
        # Save to temporary file
        out_dir = os.path.dirname(image_path)
        out_filename = f"processed_crop_{os.path.basename(image_path)}"
        out_path = os.path.join(out_dir, out_filename)
        
        cropped.save(out_path)
        print(f"Centered crop created: {target_width}x{target_height}")
        return out_path
    
    def _create_side_by_side_layout(self, img, target_width, target_height):
        """
        Create a side-by-side duplicate layout for very wide images.
        This prevents extreme stretching by filling vertical space first.
        """
        original_ratio = img.width / img.height
        
        # Create blurred background first
        canvas = img.copy()
        canvas = canvas.resize((target_width, target_height), Image.Resampling.LANCZOS)
        canvas = canvas.filter(ImageFilter.GaussianBlur(radius=25))
        
        # Calculate dimensions: fill the HEIGHT, then calculate width based on aspect ratio
        single_height = target_height  # Fill full height
        single_width = int(single_height * original_ratio)
        
        # Make sure width doesn't exceed half of target width (with some padding)
        max_single_width = (target_width // 2) - 20  # 20px padding between images
        if single_width > max_single_width:
            single_width = max_single_width
            single_height = int(single_width / original_ratio)
        
        # Resize image maintaining aspect ratio
        resized = img.resize((single_width, single_height), Image.Resampling.LANCZOS)
        
        # Calculate positions - center vertically and distribute horizontally
        y_offset = (target_height - single_height) // 2
        
        # Left image: centered in left half
        x1 = (target_width // 2 - single_width) // 2
        # Right image: centered in right half
        x2 = target_width // 2 + (target_width // 2 - single_width) // 2
        
        # Paste two copies
        canvas.paste(resized, (x1, y_offset))
        canvas.paste(resized, (x2, y_offset))
        
        # Save to temporary file
        out_dir = os.path.dirname(img.filename) if hasattr(img, 'filename') else os.getcwd()
        out_filename = f"processed_sidebyside_{os.path.basename(img.filename) if hasattr(img, 'filename') else 'image.jpg'}"
        out_path = os.path.join(out_dir, out_filename)
        
        canvas.save(out_path)
        print(f"Side-by-side layout created: {single_width}x{single_height} each at positions ({x1},{y_offset}) and ({x2},{y_offset})")
        return out_path
    
    def _create_blurred_background(self, img, image_path, target_width, target_height, image_ratio):
        """
        Create blurred background with centered foreground image.
        NEVER stretches - maintains original aspect ratio.
        Background is cropped (not stretched) to fill frame.
        """
        # Create blurred background - CROP to fill, don't stretch
        bg = img.copy()
        
        # Calculate size to fill target while maintaining aspect ratio (crop method)
        if image_ratio > (target_width / target_height):
            # Image is wider - scale by height, crop width
            bg_height = target_height
            bg_width = int(bg_height * image_ratio)
        else:
            # Image is taller - scale by width, crop height
            bg_width = target_width
            bg_height = int(bg_width / image_ratio)
        
        # Resize and crop to fill
        bg = bg.resize((bg_width, bg_height), Image.Resampling.LANCZOS)
        
        # Crop to exact target size (center crop)
        left = (bg_width - target_width) // 2
        top = (bg_height - target_height) // 2
        bg = bg.crop((left, top, left + target_width, top + target_height))
        
        # Apply blur
        bg = bg.filter(ImageFilter.GaussianBlur(radius=30))
        
        # Calculate foreground size maintaining aspect ratio
        # Always scale to fit within 80% of the frame
        
        if image_ratio > 1.0:  # Landscape or square
            # For landscape: fit by WIDTH first
            fg_width = int(target_width * 0.80)
            fg_height = int(fg_width / image_ratio)
            
            # If height exceeds limits, scale down by height instead
            if fg_height > target_height * 0.80:
                fg_height = int(target_height * 0.80)
                fg_width = int(fg_height * image_ratio)
        else:  # Portrait
            # For portrait: fit by HEIGHT first
            fg_height = int(target_height * 0.80)
            fg_width = int(fg_height * image_ratio)
            
            # If width exceeds limits, scale down by width instead
            if fg_width > target_width * 0.80:
                fg_width = int(target_width * 0.80)
                fg_height = int(fg_width / image_ratio)
        
        # Resize foreground maintaining aspect ratio - NO STRETCHING
        fg = img.resize((fg_width, fg_height), Image.Resampling.LANCZOS)
        
        # Center foreground on background
        x = (target_width - fg_width) // 2
        y = (target_height - fg_height) // 2
        
        bg.paste(fg, (x, y))
        
        # Save to temporary file
        out_dir = os.path.dirname(image_path)
        out_filename = f"processed_{os.path.basename(image_path)}"
        out_path = os.path.join(out_dir, out_filename)
        
        bg.save(out_path)
        print(f"Blurred background created: foreground {fg_width}x{fg_height} at ({x},{y}) - ratio preserved: {fg_width/fg_height:.3f} (original: {image_ratio:.3f})")
        return out_path
    
    def _create_multi_image_video(self, image_paths, output_path, duration, audio_path):
        """
        Create video with multiple images, pairing them side-by-side.
        Each pair of images creates one slide.
        
        Args:
            image_paths: list of str - paths to images
            output_path: str - output video path
            duration: int - total duration in seconds
            audio_path: str - optional background music
        """
        target_width = self.current_preset['width']
        target_height = self.current_preset['height']
        
        # Group images into pairs
        slides = []
        for i in range(0, len(image_paths), 2):
            if i + 1 < len(image_paths):
                # Pair of two images
                slides.append((image_paths[i], image_paths[i + 1]))
            else:
                # Single image (odd number of images)
                slides.append((image_paths[i],))
        
        print(f"Creating {len(slides)} slide(s) from {len(image_paths)} image(s)")
        
        # Calculate duration per slide
        duration_per_slide = duration / len(slides)
        
        # Create slide images
        slide_image_paths = []
        for idx, slide in enumerate(slides):
            try:
                if len(slide) == 2:
                    if self._should_stack_vertically(slide[0], slide[1], target_width, target_height):
                        slide_img = self._create_vertical_pair_slide(slide[0], slide[1], target_width, target_height)
                    else:
                        slide_img = self._create_side_by_side_slide(slide[0], slide[1], target_width, target_height)
                else:
                    # Single image - use normal processing
                    slide_img = self.apply_smart_resize(slide[0])
                
                slide_image_paths.append(slide_img)
                print(f"Slide {idx + 1}/{len(slides)} created: {slide_img}")
            except Exception as e:
                print(f"Error creating slide {idx + 1}: {e}")
                return None
        
        # Create video from slides using FFmpeg concat
        temp_video = output_path.replace('.mp4', '_temp.mp4')
        
        try:
            # Create video clips for each slide
            clip_paths = []
            for idx, slide_img in enumerate(slide_image_paths):
                clip_path = output_path.replace('.mp4', f'_clip_{idx}.mp4')
                
                # Ken Burns effect for each slide
                filter_complex = (
                    f"zoompan=z='min(zoom+0.0015,1.2)':d={int(duration_per_slide * 25)}:"
                    f"x='iw/2-(iw/zoom/2)':y='ih/2-(ih/zoom/2)',"
                    f"scale={target_width}:{target_height}"
                )
                
                cmd = [
                    FFMPEG_PATH if FFMPEG_PATH else 'ffmpeg', '-y',
                    '-loop', '1',
                    '-i', slide_img,
                    '-vf', filter_complex,
                    '-c:v', 'libx264',
                    '-t', str(duration_per_slide),
                    '-b:v', self.current_preset['bitrate'],
                    '-pix_fmt', 'yuv420p',
                    clip_path
                ]
                
                result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
                if result.returncode != 0:
                    print(f"FFmpeg Error for clip {idx}: {result.stderr}")
                    return None
                
                clip_paths.append(clip_path)
            
            # Concatenate all clips
            concat_file = output_path.replace('.mp4', '_concat.txt')
            with open(concat_file, 'w') as f:
                for clip in clip_paths:
                    f.write(f"file '{os.path.abspath(clip)}'\n")
            
            concat_cmd = [
                FFMPEG_PATH if FFMPEG_PATH else 'ffmpeg', '-y',
                '-f', 'concat',
                '-safe', '0',
                '-i', concat_file,
                '-c', 'copy',
                temp_video
            ]
            
            result = subprocess.run(concat_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
            if result.returncode != 0:
                print(f"FFmpeg Error concatenating: {result.stderr}")
                return None
            
            # Merge audio if provided
            if audio_path and os.path.exists(audio_path):
                print(f"Merging audio from: {audio_path}")
                final_cmd = [
                    FFMPEG_PATH if FFMPEG_PATH else 'ffmpeg', '-y',
                    '-i', temp_video,
                    '-i', audio_path,
                    '-map', '0:v',
                    '-map', '1:a',
                    '-c:v', 'copy',
                    '-c:a', 'aac',
                    '-shortest',
                    output_path
                ]
                subprocess.run(final_cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, check=True)
                if os.path.exists(temp_video):
                    os.remove(temp_video)
            else:
                if os.path.exists(output_path):
                    os.remove(output_path)
                os.rename(temp_video, output_path)
            
            # Cleanup temporary files
            for clip in clip_paths:
                if os.path.exists(clip):
                    os.remove(clip)
            if os.path.exists(concat_file):
                os.remove(concat_file)
            for slide_img in slide_image_paths:
                if os.path.exists(slide_img) and slide_img not in image_paths:
                    try:
                        os.remove(slide_img)
                    except:
                        pass
            
            if os.path.exists(output_path):
                print(f"Multi-image video generated successfully: {output_path}")
                return output_path
            else:
                print("Multi-image video generation finished but output file missing.")
                return None
                
        except Exception as e:
            print(f"Error creating multi-image video: {e}")
            return None
    
    def _create_side_by_side_slide(self, image_path1, image_path2, target_width, target_height):
        """
        Create a single slide with two images side by side.
        Each image takes half the width, maintaining aspect ratios.
        """
        with Image.open(image_path1) as img1, Image.open(image_path2) as img2:
            # Create canvas
            canvas = Image.new('RGB', (target_width, target_height), (20, 20, 20))
            
            # Calculate dimensions for each image - allow 45% width each for better height
            padding = 10
            single_width = int(target_width * 0.45)  # 45% each = 90% total usage
            
            # Process each image
            for idx, img in enumerate([img1, img2]):
                img_ratio = img.width / img.height
                
                # Calculate max dimensions for allocated space - use 90% of height
                max_width = single_width
                max_height = int(target_height * 0.90)
                
                # Scale to fit within allocated space while maintaining aspect ratio
                # Choose the limiting dimension
                if img_ratio > (max_width / max_height):
                    # Image is wider - width is the limiting factor
                    scaled_width = max_width
                    scaled_height = int(scaled_width / img_ratio)
                else:
                    # Image is taller - height is the limiting factor
                    scaled_height = max_height
                    scaled_width = int(scaled_height * img_ratio)
                
                # Resize maintaining aspect ratio
                resized = img.resize((scaled_width, scaled_height), Image.Resampling.LANCZOS)
                
                # Calculate position
                x = padding + idx * (single_width + padding) + (single_width - scaled_width) // 2
                y = (target_height - scaled_height) // 2
                
                # Paste on canvas
                canvas.paste(resized, (x, y))
            
            # Save slide
            out_dir = os.path.dirname(image_path1)
            out_filename = f"slide_{os.path.basename(image_path1)}"
            out_path = os.path.join(out_dir, out_filename)
            canvas.save(out_path)
            print(f"Side-by-side slide created: {out_path}")
            return out_path

    def _should_stack_vertically(self, image_path1, image_path2, target_width, target_height):
        """
        For portrait outputs, stack two wide images vertically to avoid ultra-wide look.
        Returns True when both are clearly landscape and vertical stacking will use space better.
        """
        if target_height <= target_width:
            return False  # Only adjust for portrait canvases

        try:
            with Image.open(image_path1) as img1, Image.open(image_path2) as img2:
                ratios = [img1.width / img1.height, img2.width / img2.height]
        except Exception as e:
            print(f"Could not inspect image aspect ratios for vertical stack decision: {e}")
            return False

        # Threshold keeps normal side-by-side for square/portrait images
        return all(r > 1.15 for r in ratios)

    def _create_vertical_pair_slide(self, image_path1, image_path2, target_width, target_height):
        """
        Stack two landscape images vertically for portrait videos.
        This reduces wide-letterboxing and keeps aspect ratios intact.
        """
        with Image.open(image_path1) as img1, Image.open(image_path2) as img2:
            canvas = Image.new('RGB', (target_width, target_height), (20, 20, 20))

            padding = 10
            available_height = target_height - padding * 3
            slot_height = available_height // 2
            # Allow images to use 95% of width for better height utilization
            max_width = int(target_width * 0.95)

            for idx, img in enumerate([img1, img2]):
                img_ratio = img.width / img.height
                max_h = slot_height
                max_w = max_width

                if img_ratio > (max_w / max_h):
                    scaled_width = max_w
                    scaled_height = int(scaled_width / img_ratio)
                else:
                    scaled_height = max_h
                    scaled_width = int(scaled_height * img_ratio)

                resized = img.resize((scaled_width, scaled_height), Image.Resampling.LANCZOS)

                x = (target_width - scaled_width) // 2
                y = padding + idx * (slot_height + padding) + (slot_height - scaled_height) // 2

                canvas.paste(resized, (x, y))

            out_dir = os.path.dirname(image_path1)
            out_filename = f"slide_vertical_{os.path.basename(image_path1)}"
            out_path = os.path.join(out_dir, out_filename)
            canvas.save(out_path)
            print(f"Vertical slide created: {out_path}")
            return out_path
    
    def add_ken_burns_effect(self, input_video, zoom_start=1.0, zoom_end=1.1):
        """FFmpeg zoompan filter (Helper, logic included in create_video)"""
        pass
    
    def add_text_overlay(self, video_path, text, position='bottom'):
        """FFmpeg drawtext filter with custom fonts"""
        pass
    
    def add_watermark(self, video_path, logo_path, position='top-right'):
        """Brand logo overlay"""
        pass
    
    def merge_audio(self, video_path, audio_path):
        """Merge background music using FFmpeg"""
        pass

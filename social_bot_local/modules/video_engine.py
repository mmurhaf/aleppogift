"""Build 9:16 shoppable reels from product photos.

Each photo becomes its own full-screen scene: a sharpened product on a
darkened blurred background (or a full-bleed frame when the photo is
already portrait), with the store name, product title, and AED price.
Scenes get a slow camera move and a short fade through black. Music, when
supplied, is looped and faded. The file always has an AAC audio track
so Instagram and TikTok accept it.
"""

import glob
import logging
import math
import os
import shutil
import subprocess
import tempfile

from PIL import Image, ImageDraw, ImageEnhance, ImageFilter, ImageFont, ImageOps

logger = logging.getLogger("social_bot.video")
logger.setLevel(logging.INFO)
if not any(isinstance(handler, logging.StreamHandler) for handler in logger.handlers):
    _stream = logging.StreamHandler()
    _stream.setFormatter(logging.Formatter("%(levelname)s %(message)s"))
    logger.addHandler(_stream)


def resolve_ffmpeg():
    """Find ffmpeg without depending on a single machine's install path."""
    env_path = os.environ.get("FFMPEG_PATH")
    if env_path and os.path.isfile(env_path):
        return env_path

    on_path = shutil.which("ffmpeg")
    if on_path:
        return on_path

    local_app = os.environ.get("LOCALAPPDATA", "")
    if local_app:
        patterns = (
            os.path.join(local_app, "Microsoft", "WinGet", "Packages", "**", "ffmpeg.exe"),
            os.path.join(local_app, "Temp", "ffmpeg_extract", "**", "ffmpeg.exe"),
        )
        for pattern in patterns:
            matches = glob.glob(pattern, recursive=True)
            if matches:
                return matches[0]

    for candidate in (
        r"C:\ffmpeg\bin\ffmpeg.exe",
        r"C:\Program Files\ffmpeg\bin\ffmpeg.exe",
    ):
        if os.path.isfile(candidate):
            return candidate
    return None


FFMPEG_PATH = resolve_ffmpeg()

# Layout is authored at 1080x1920 and scaled to the render size.
BRAND_Y = 118
PRODUCT_TOP = 250
TEXT_TOP = 1508
ORANGE = (230, 126, 34, 255)
ORANGE_DARK = (176, 86, 18, 255)
CREAM = (255, 248, 242, 255)
INK = (18, 12, 9, 255)
MUTED = (236, 224, 212, 255)


class VideoGenerator:
    FPS = 30
    XFADE = 0.45
    MIN_SCENE = 2.6
    MAX_SCENE = 10.0
    MAX_SCENES = 12
    ZOOM = 0.055

    PRESETS = {
        "low": {"width": 720, "height": 1280, "crf": 24, "preset": "veryfast", "scale": 1.35},
        "medium": {"width": 1080, "height": 1920, "crf": 19, "preset": "medium", "scale": 1.45},
        "high": {"width": 1080, "height": 1920, "crf": 16, "preset": "slow", "scale": 1.5},
    }

    def __init__(self, quality="medium", brand="Aleppo Gift"):
        preset = self.PRESETS.get(quality, self.PRESETS["medium"])
        self.quality_name = quality if quality in self.PRESETS else "medium"
        self.width = preset["width"]
        self.height = preset["height"]
        self.crf = preset["crf"]
        self.x264_preset = preset["preset"]
        self.render_scale = preset["scale"]
        self.render_w = int(self.width * self.render_scale) // 2 * 2
        self.render_h = int(self.height * self.render_scale) // 2 * 2
        self.brand = (brand or "Aleppo Gift").strip()
        self._fonts = {}

    def plan(self, scene_count, duration=None, content_format="reel"):
        """Return scene length and total runtime for a reel or story."""
        photos = max(1, min(int(scene_count or 1), self.MAX_SCENES))
        end_card = content_format == "reel" and photos >= 2
        count = photos + (1 if end_card else 0)
        fade = self.XFADE if count > 1 else 0.0
        if duration is None:
            requested = 7.5 if photos == 1 else count * 3.5
        else:
            requested = float(duration)
        per = (requested + (count - 1) * fade) / count
        per = min(self.MAX_SCENE, max(self.MIN_SCENE, per))
        frames = max(int(round(self.MIN_SCENE * self.FPS)), int(round(per * self.FPS)))
        per = frames / self.FPS
        total = count * per - (count - 1) * fade
        return {
            "scene_length": per,
            "frames": frames,
            "fade": fade,
            "total": total,
            "end_card": end_card,
            "count": count,
            "photos": photos,
        }

    def create_video(
        self,
        image_path,
        output_path,
        duration=15,
        audio_path=None,
        text_overlay=None,
        scenes=None,
        content_format="reel",
    ):
        """Render a reel and return its path, or None if rendering failed."""
        if not FFMPEG_PATH:
            logger.error("FFmpeg was not found. Add it to PATH or set FFMPEG_PATH.")
            return None

        scene_list = self._coerce_scenes(image_path, text_overlay, scenes)
        if not scene_list:
            logger.error("No usable images were provided.")
            return None

        timing = self.plan(len(scene_list), duration, content_format)
        scene_list = scene_list[: timing["photos"]]
        output_path = os.path.abspath(output_path)
        os.makedirs(os.path.dirname(output_path) or ".", exist_ok=True)

        logger.info(
            "Rendering %s scene(s) at %sx%s, about %.1fs",
            timing["count"],
            self.width,
            self.height,
            timing["total"],
        )

        work = tempfile.TemporaryDirectory(prefix="aleppo_reel_")
        try:
            frames = []
            for index, scene in enumerate(scene_list):
                photo = self._load_scene_image(scene["image"], work.name)
                frame = self._compose_product(
                    photo,
                    title=scene["title"],
                    price=scene["price"],
                    index=index,
                    total=len(scene_list),
                )
                frame_path = os.path.join(work.name, f"frame_{index:02d}.png")
                frame.save(frame_path, "PNG")
                frames.append(frame_path)

            if timing["end_card"]:
                photo = self._load_scene_image(scene_list[0]["image"], work.name)
                card = self._compose_end_card(photo)
                card_path = os.path.join(work.name, "frame_end.png")
                card.save(card_path, "PNG")
                frames.append(card_path)

            motions = [0, 2, 3, 1]
            clips = []
            for index, frame_path in enumerate(frames):
                # Closing card eases out instead of drifting.
                motion = 1 if timing["end_card"] and index == len(frames) - 1 else motions[index % len(motions)]
                clip_path = os.path.join(work.name, f"clip_{index:02d}.mp4")
                self._render_clip(frame_path, clip_path, timing["frames"], motion, final=(len(frames) == 1))
                clips.append(clip_path)

            silent_path = os.path.join(work.name, "silent.mp4")
            if len(clips) == 1:
                os.replace(clips[0], silent_path)
            else:
                self._crossfade(clips, timing["frames"], timing["fade"], silent_path)

            self._mux_audio(silent_path, audio_path, output_path, timing["total"])
        except Exception:
            logger.exception("Reel render failed")
            return None
        finally:
            try:
                work.cleanup()
            except OSError:
                logger.warning("Temporary render files could not be removed yet.")

        if os.path.isfile(output_path) and os.path.getsize(output_path) > 0:
            logger.info("Reel ready: %s", output_path)
            return output_path
        logger.error("FFmpeg finished without an output file.")
        return None

    def _coerce_scenes(self, image_path, text_overlay, scenes):
        if scenes:
            raw = list(scenes)
        elif isinstance(image_path, list) and image_path and isinstance(image_path[0], dict):
            raw = list(image_path)
        else:
            paths = [image_path] if isinstance(image_path, str) else list(image_path or [])
            raw = [{"image": path, "title": text_overlay or "", "price": None} for path in paths]

        cleaned = []
        for item in raw:
            if isinstance(item, str):
                item = {"image": item, "title": text_overlay or "", "price": None}
            image = item.get("image") or item.get("path")
            if not image:
                continue
            title = str(item.get("title") or text_overlay or "").strip()
            cleaned.append({
                "image": image,
                "title": title[:80],
                "price": item.get("price"),
            })
            if len(cleaned) >= self.MAX_SCENES:
                break
        return cleaned

    def _load_scene_image(self, source, work_dir):
        path = source
        if isinstance(source, str) and source.startswith(("http://", "https://")):
            import requests

            filename = os.path.basename(source.split("?", 1)[0]) or "remote.jpg"
            path = os.path.join(work_dir, "remote_" + filename[-80:])
            response = requests.get(source, timeout=25)
            response.raise_for_status()
            with open(path, "wb") as handle:
                handle.write(response.content)
        if not os.path.isfile(path):
            raise FileNotFoundError(f"Image not found: {source}")
        with Image.open(path) as img:
            img = ImageOps.exif_transpose(img)
            if img.mode == "P":
                img = img.convert("RGBA")
            elif img.mode not in ("RGB", "RGBA"):
                img = img.convert("RGB")
            return img.copy()

    def _compose_product(self, photo, title, price, index, total):
        ratio = photo.width / max(photo.height, 1)
        full_bleed = 0.45 <= ratio <= 0.68
        look = self._grade(photo)

        if full_bleed:
            canvas = self._cover(look.convert("RGB"), self.render_w, self.render_h)
            canvas = self._vignette(canvas, strength=150)
            canvas = canvas.convert("RGBA")
            self._scrim(canvas, top=0.20, bottom=0.48, top_alpha=165, bottom_alpha=225)
        else:
            canvas = self._blurred_background(look)
            canvas = self._vignette(canvas, strength=110)
            canvas = canvas.convert("RGBA")
            panel_top = self.px(PRODUCT_TOP)
            panel_h = self.render_h - panel_top
            hero = self._cover(look.convert("RGB"), self.render_w, panel_h)
            hero = hero.filter(ImageFilter.UnsharpMask(radius=1.2, percent=55, threshold=2))
            canvas.alpha_composite(hero.convert("RGBA"), (0, panel_top))
            self._scrim(canvas, top=0.18, bottom=0.48, top_alpha=90, bottom_alpha=235, bottom_power=0.55)

        draw = ImageDraw.Draw(canvas)
        self._draw_brand_pill(draw, canvas.size[0])
        self._draw_lower_third(
            draw,
            canvas.size[0],
            title=title,
            price=self._format_price(price),
            cta="Shop now" if total == 1 else None,
            index=index,
            total=total,
        )
        return canvas.convert("RGB")

    def _compose_end_card(self, photo):
        canvas = self._blurred_background(self._grade(photo))
        canvas = self._vignette(canvas, strength=180)
        canvas = canvas.convert("RGBA")
        veil = Image.new("RGBA", canvas.size, (12, 8, 6, 110))
        canvas.alpha_composite(veil)

        draw = ImageDraw.Draw(canvas)
        width = canvas.size[0]
        rule = self._font(15, bold=True)
        self._draw_tracked(draw, "GIFTS FROM ALEPPO", self.px(760), rule, ORANGE, width, self.px(4))

        wordmark = self._font(78, bold=True)
        title_y = self.px(860)
        self._draw_centered(draw, self.brand.upper(), title_y, wordmark, CREAM, width, shadow=True)
        underline_w = self.px(120)
        underline_y = title_y + self.px(110)
        draw.rounded_rectangle(
            ((width - underline_w) // 2, underline_y, (width + underline_w) // 2, underline_y + self.px(8)),
            radius=self.px(4),
            fill=ORANGE,
        )

        sub = self._font(34, bold=False)
        self._draw_centered(draw, "Shop the collection", underline_y + self.px(48), sub, MUTED, width, shadow=False)
        return canvas.convert("RGB")

    def _grade(self, photo):
        rgb = photo.convert("RGB")
        rgb = ImageEnhance.Contrast(rgb).enhance(1.05)
        rgb = ImageEnhance.Color(rgb).enhance(1.07)
        if photo.mode == "RGBA":
            graded = rgb.convert("RGBA")
            graded.putalpha(photo.getchannel("A"))
            return graded
        return rgb

    def _blurred_background(self, photo):
        small_w = max(2, self.render_w // 4)
        small_h = max(2, self.render_h // 4)
        cover = self._cover(photo.convert("RGB"), small_w, small_h)
        cover = cover.filter(ImageFilter.GaussianBlur(radius=14))
        cover = cover.resize((self.render_w, self.render_h), Image.Resampling.LANCZOS)
        ink = Image.new("RGB", cover.size, (16, 10, 8))
        return Image.blend(cover, ink, 0.42)

    def _scrim(self, image, top, bottom, top_alpha, bottom_alpha, bottom_power=1.15):
        width, height = image.size
        mask = Image.new("L", image.size, 0)
        top_h = int(height * top)
        bot_h = int(height * bottom)
        if top_h > 0:
            mask.paste(self._ramp(width, top_h, top_alpha, reverse=True, power=1.2), (0, 0))
        if bot_h > 0:
            mask.paste(self._ramp(width, bot_h, bottom_alpha, reverse=False, power=bottom_power), (0, height - bot_h))
        veil = Image.new("RGBA", image.size, (8, 5, 4, 255))
        veil.putalpha(mask)
        image.alpha_composite(veil)

    def _vignette(self, image, strength):
        sample_w, sample_h = 160, 284
        small = Image.new("L", (sample_w, sample_h), 0)
        pixels = small.load()
        cx = (sample_w - 1) / 2
        cy = (sample_h - 1) / 2
        max_r = math.hypot(cx, cy)
        for y in range(sample_h):
            for x in range(sample_w):
                radius = math.hypot(x - cx, y - cy) / max_r
                fade = max(0.0, (radius - 0.42) / 0.58)
                pixels[x, y] = int(min(255, (fade ** 1.35) * strength))
        mask = small.resize(image.size, Image.Resampling.BICUBIC)
        dark = Image.new("RGB", image.size, (6, 4, 3))
        return Image.composite(dark, image.convert("RGB"), mask)

    def _draw_brand_pill(self, draw, width):
        font = self._font(26, bold=True)
        label = self.brand.upper()
        text_w = self._tracked_width(draw, label, font, self.px(2.4))
        pad_x = self.px(26)
        pad_y = self.px(12)
        box_w = text_w + pad_x * 2
        box_h = self.px(58)
        left = (width - box_w) // 2
        top = self.px(BRAND_Y)
        draw.rounded_rectangle(
            (left, top, left + box_w, top + box_h),
            radius=box_h // 2,
            fill=(16, 10, 8, 186),
        )
        draw.rounded_rectangle(
            (left, top, left + self.px(8), top + box_h),
            radius=2,
            fill=ORANGE,
        )
        self._draw_tracked(draw, label, top + pad_y, font, CREAM, width, self.px(2.4))

    def _draw_lower_third(self, draw, width, title, price, cta, index, total):
        y = self.px(TEXT_TOP)
        max_w = int(width * 0.86)
        if title:
            font = self._font(52, bold=True)
            lines = self._wrap(draw, title, font, max_w, max_lines=2)
            y = self._draw_lines(draw, lines, y, font, CREAM, width, gap=self.px(6))
            y += self.px(18)
        if price:
            y += self._draw_pill(draw, price, width // 2, y, self._font(30, bold=True), ORANGE, CREAM)
            y += self.px(20)
        if cta:
            y = self._draw_centered(draw, cta, y, self._font(28, bold=True), ORANGE, width, shadow=False)
            y += self.px(28)
        if total > 1:
            self._draw_dots(draw, index, total, y, width)

    def _draw_dots(self, draw, index, total, y, width):
        radius = self.px(5)
        gap = self.px(12)
        active_w = self.px(28)
        heights = radius * 2
        widths = [active_w if i == index else heights for i in range(total)]
        row_w = sum(widths) + gap * (total - 1)
        x = (width - row_w) // 2
        for i, dot_w in enumerate(widths):
            color = ORANGE if i == index else (255, 248, 242, 120)
            draw.rounded_rectangle((x, y, x + dot_w, y + heights), radius=radius, fill=color)
            x += dot_w + gap

    def _draw_pill(self, draw, text, center_x, y, font, background, foreground):
        bbox = draw.textbbox((0, 0), text, font=font)
        text_w = bbox[2] - bbox[0]
        text_h = bbox[3] - bbox[1]
        pad_x = self.px(22)
        pad_y = self.px(10)
        box_w = text_w + pad_x * 2
        box_h = text_h + pad_y * 2
        left = center_x - box_w // 2
        draw.rounded_rectangle((left, y, left + box_w, y + box_h), radius=box_h // 2, fill=background)
        tx = left + (box_w - text_w) // 2
        ty = y + (box_h - text_h) // 2 - bbox[1]
        draw.text((tx, ty), text, font=font, fill=foreground)
        return box_h

    def _draw_lines(self, draw, lines, y, font, fill, width, gap):
        for line in lines:
            y = self._draw_centered(draw, line, y, font, fill, width, shadow=True)
            y += gap
        return y

    def _draw_centered(self, draw, text, y, font, fill, width, shadow):
        bbox = draw.textbbox((0, 0), text, font=font)
        text_w = bbox[2] - bbox[0]
        text_h = bbox[3] - bbox[1]
        x = (width - text_w) // 2
        if shadow:
            draw.text((x + self.px(2), y + self.px(3)), text, font=font, fill=(0, 0, 0, 150))
        draw.text((x, y), text, font=font, fill=fill)
        return y + text_h

    def _draw_tracked(self, draw, text, y, font, fill, width, tracking):
        total = self._tracked_width(draw, text, font, tracking)
        x = (width - total) // 2
        for char in text:
            draw.text((x, y), char, font=font, fill=fill)
            x += self._text_width(draw, char, font) + tracking

    def _tracked_width(self, draw, text, font, tracking):
        if not text:
            return 0
        widths = [self._text_width(draw, char, font) for char in text]
        return int(sum(widths) + tracking * (len(text) - 1))

    def _wrap(self, draw, text, font, max_width, max_lines):
        words = [word for word in str(text).replace("\n", " ").split(" ") if word]
        if not words:
            return []
        lines = []
        current = ""
        index = 0
        while index < len(words):
            word = words[index]
            trial = word if not current else f"{current} {word}"
            if self._text_width(draw, trial, font) <= max_width or not current:
                current = trial
                index += 1
                continue
            lines.append(current)
            current = ""
            if len(lines) == max_lines:
                break
        if current and len(lines) < max_lines:
            lines.append(current)
        if index < len(words) and lines:
            lines[-1] = self._ellipsis(draw, lines[-1], font, max_width)
        return [self._ellipsis(draw, line, font, max_width) for line in lines[:max_lines]]

    def _ellipsis(self, draw, text, font, max_width):
        if self._text_width(draw, text, font) <= max_width:
            return text
        trimmed = text
        while trimmed and self._text_width(draw, trimmed + "…", font) > max_width:
            trimmed = trimmed[:-1]
        return (trimmed.rstrip() + "…") if trimmed else "…"

    def _text_width(self, draw, text, font):
        bbox = draw.textbbox((0, 0), text, font=font)
        return bbox[2] - bbox[0]

    def _font(self, size, bold):
        px = self.px(size)
        key = (px, bold)
        if key not in self._fonts:
            self._fonts[key] = self._load_font(px, bold)
        return self._fonts[key]

    def _load_font(self, size, bold):
        windir = os.environ.get("WINDIR", r"C:\Windows")
        fonts_dir = os.path.join(windir, "Fonts")
        names = (
            ["segoeuib.ttf", "arialbd.ttf", "calibrib.ttf", "DejaVuSans-Bold.ttf"]
            if bold
            else ["segoeui.ttf", "arial.ttf", "calibri.ttf", "DejaVuSans.ttf"]
        )
        search = [fonts_dir, "/usr/share/fonts/truetype/dejavu", "/usr/share/fonts/truetype/liberation"]
        for name in names:
            for folder in search:
                path = os.path.join(folder, name)
                if os.path.isfile(path):
                    return ImageFont.truetype(path, size=size)
        return ImageFont.load_default(size=size)

    def _format_price(self, price):
        if price is None or price == "":
            return None
        try:
            value = float(price)
        except (TypeError, ValueError):
            return None
        if value <= 0:
            return None
        if abs(value - round(value)) < 0.001:
            return f"AED {int(round(value))}"
        return f"AED {value:.2f}"

    def px(self, value):
        return max(1, int(round(float(value) * self.render_w / 1080)))

    def _cover(self, img, width, height):
        img = img.convert("RGB")
        ratio = img.width / max(img.height, 1)
        target = width / height
        if ratio > target:
            resized_h = height
            resized_w = max(1, int(resized_h * ratio))
        else:
            resized_w = width
            resized_h = max(1, int(resized_w / ratio))
        img = img.resize((resized_w, resized_h), Image.Resampling.LANCZOS)
        left = (img.width - width) // 2
        top = (img.height - height) // 2
        return img.crop((left, top, left + width, top + height))

    @staticmethod
    def _ramp(width, height, alpha, reverse, power):
        column = Image.new("L", (1, 256))
        pixels = column.load()
        for i in range(256):
            t = i / 255
            if reverse:
                t = 1 - t
            pixels[0, i] = int(alpha * (t ** power))
        return column.resize((width, height), Image.Resampling.BILINEAR)

    def _motion_filter(self, index, frames):
        """Slow zoom or drift. zoompan fits the whole frame at zoom 1 and stays sharp
        because the still is rendered larger than the output."""
        last = max(int(frames) - 1, 1)
        amount = self.ZOOM
        mode = index % 4
        if mode == 0:
            z = f"1+{amount}*on/{last}"
            x = "iw/2-(iw/zoom/2)"
            y = "ih/2-(ih/zoom/2)"
        elif mode == 1:
            z = f"{1 + amount:.4f}-{amount}*on/{last}"
            x = "iw/2-(iw/zoom/2)"
            y = "ih/2-(ih/zoom/2)"
        elif mode == 2:
            z = f"{1 + amount * 0.75:.4f}"
            x = f"(iw-iw/zoom)*on/{last}"
            y = "(ih-ih/zoom)/2"
        else:
            z = f"{1 + amount * 0.75:.4f}"
            x = "(iw-iw/zoom)/2"
            y = f"(ih-ih/zoom)*(1-on/{last})"
        return (
            f"zoompan=z='{z}':x='{x}':y='{y}':d={int(frames)}:s={self.width}x{self.height}:fps={self.FPS},"
            "eq=saturation=1.04:contrast=1.02,setsar=1,format=yuv420p"
        )

    def _encode_args(self, final):
        preset = self.x264_preset if final else "veryfast"
        crf = self.crf if final else min(self.crf, 17)
        return [
            "-c:v", "libx264",
            "-preset", preset,
            "-crf", str(crf),
            "-profile:v", "high",
            "-pix_fmt", "yuv420p",
            "-r", str(self.FPS),
            "-colorspace", "bt709",
            "-color_primaries", "bt709",
            "-color_trc", "bt709",
        ]

    def _render_clip(self, image_path, dest, frames, motion, final):
        cmd = [
            FFMPEG_PATH, "-y",
            "-i", image_path,
            "-vf", self._motion_filter(motion, frames),
            "-frames:v", str(frames),
            *self._encode_args(final),
            dest,
        ]
        self._run(cmd, f"scene render ({motion})")

    def _crossfade(self, clips, frames, fade, dest):
        scene_len = frames / self.FPS
        cmd = [FFMPEG_PATH, "-y"]
        for clip in clips:
            cmd.extend(["-i", clip])
        filters = []
        previous = "[0:v]"
        last = "[0:v]"
        for index in range(1, len(clips)):
            offset = index * (scene_len - fade) - (1 / self.FPS)
            last = f"[v{index}]" if index < len(clips) - 1 else "[vout]"
            filters.append(
                f"{previous}[{index}:v]xfade=transition=fadeblack:duration={fade:.4f}:offset={max(0.0, offset):.4f}{last}"
            )
            previous = last
        cmd.extend([
            "-filter_complex", ";".join(filters),
            "-map", last,
            *self._encode_args(final=True),
            dest,
        ])
        self._run(cmd, "crossfade")

    def _mux_audio(self, video, audio, dest, duration):
        cmd = [FFMPEG_PATH, "-y", "-i", video]
        if audio and os.path.isfile(audio):
            fade_out = max(0.3, duration - 1.05)
            cmd.extend([
                "-stream_loop", "-1",
                "-i", audio,
                "-af", f"volume=0.42,afade=t=in:st=0:d=0.45,afade=t=out:st={fade_out:.3f}:d=1.0",
            ])
        else:
            cmd.extend(["-f", "lavfi", "-i", "anullsrc=channel_layout=stereo:sample_rate=44100"])
        cmd.extend([
            "-map", "0:v:0",
            "-map", "1:a:0",
            "-c:v", "copy",
            "-c:a", "aac",
            "-b:a", "192k",
            "-shortest",
            "-movflags", "+faststart",
            dest,
        ])
        self._run(cmd, "audio mux")

    def _run(self, cmd, label):
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        if result.returncode != 0:
            tail = (result.stderr or "").strip().splitlines()[-25:]
            detail = "\n".join(tail) or "ffmpeg returned a non-zero status"
            raise RuntimeError(f"{label} failed:\n{detail}")

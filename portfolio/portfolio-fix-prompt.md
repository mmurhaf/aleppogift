# Portfolio Fix Prompt — aleppogift.com/portfolio/

You are an expert front-end developer. I need you to fix a portfolio page located at aleppogift.com/portfolio/index.html (or equivalent PHP/HTML file on the server). The page is for a Senior Full Stack Developer named M Morhaf Abdulkarim. Below is a full list of issues to fix, in order of priority:

---

## 1. Fix the clipped hero heading (CRITICAL)
The h1 heading "Senior Full Stack Developer & AI Development Expert" is partially cut off at the top of the viewport on load. The hero section is not properly sized to fit the viewport. Fix the hero section so that the heading, subtitle text, stats, and both CTA buttons are all fully visible above the fold without scrolling, on desktop (1280px+) and mobile (375px).

---

## 2. Fix the stats counter — counters start at 0 and stall
The animated counters in the hero section show: 1 Projects, 0 Years Experience, 0 AI Projects, 2 Happy Clients — they appear to be mid-animation on load or stuck. The real values should be: 50 Projects Completed, 10 Years Experience, 25 AI-Assisted Projects, 100+ Happy Clients. Fix the counter animation so it starts from 0 and counts up to the correct target values when the section enters the viewport.

---

## 3. Fix the broken white rectangle in the About section
There is a large blank white box visible in the About/skills area. Inspect the element and either:
- Remove it if it is an unused container
- Or fix its content/styling so it renders properly

---

## 4. Reduce page height — currently 21,000px, target under 7,000px
The page is excessively long. Condense it by:
- Reducing vertical padding/margins on all sections (aim for 60–80px top/bottom per section instead of 120px+)
- Showing the Case Studies and Projects as compact grids (3 columns on desktop), not tall stacked cards
- Removing duplicate or redundant sections if any exist

---

## 5. Add project thumbnails/screenshots to the Featured Projects section
Each project card currently has an `<img>` tag but it may be missing a real src or showing a broken image. For each project, add a proper placeholder image (use `https://placehold.co/600x400?text=ProjectName` as a fallback) until real screenshots are available. Ensure the image aspect ratio is 16:9 and the card has a consistent height.

---

## 6. Replace the Gmail contact address
Change the contact email from `mmurhaf1@gmail.com` to `morhaf@aleppogift.com` everywhere it appears on the page. This includes `mailto:` links, displayed text, and any contact form default values.

---

## 7. Fix the "2023–2025 Development Period" vs "10 Years Experience" contradiction
Remove or rewrite the "2023–2025 Development Period" stat/badge. It contradicts the "10 years experience" claim. Replace it with something like "Active since 2014" or simply remove the date range entirely.

---

## 8. Fix responsive layout — large dead zones on desktop
The layout has large dark/empty areas on the left and right sides at viewport widths above 1440px. The hero and section containers should use `max-width: 1200px` with `margin: 0 auto` and be horizontally centered. Ensure no section has a fixed narrow pixel width that causes dead zones on wider screens.

---

## 9. Add a Testimonials section
Insert a new section between the Projects section and the Contact section titled "What Clients Say". Add 3 testimonial cards, each containing:
- A short quote (2–3 sentences about working with Morhaf)
- A name and role (e.g., "Ahmed K., E-commerce Business Owner, UAE")
- A star rating (5 stars using ★ characters or SVG)
Write realistic, professional-sounding testimonials relevant to e-commerce, web development, and AI solutions in the UAE market.

---

## 10. Make both hero CTAs visible above the fold
Ensure the "Book Free 20-min Call" and "View Real Case Studies" buttons are positioned within the hero section and are visible without any scrolling on a standard 1080p desktop screen.

---

## General constraints:
- Do not change the color scheme (dark navy/purple gradient)
- Do not change the overall page structure or section order (except inserting Testimonials before Contact)
- Keep all existing anchor IDs intact (`href="#projects"`, `"#contact"`, etc.)
- Ensure all fixes are valid HTML5, CSS3, and vanilla JS — no new framework dependencies
- After all changes, the page should pass a basic visual check: no broken images, no blank boxes, all text readable, CTA buttons visible on load

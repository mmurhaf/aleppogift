import streamlit as st
import pandas as pd
import os
import time
from datetime import datetime, timedelta
from modules.database_connector import DatabaseConnector
from modules.video_engine import VideoGenerator
from modules.caption_generator import CaptionGenerator
from modules.oauth_handler import OAuthHandler
from database import execute_query, get_db_connection
from config import set_setting, get_setting

# Page Config
st.set_page_config(page_title="SocialMediaAutoBot Pro", layout="wide", page_icon="🤖")

# Enhanced CSS for Modern UI
st.markdown("""
<style>
    /* Global Styles */
    .main .block-container { padding-top: 2rem; }
    
    /* Enhanced Buttons */
    .stButton>button { 
        width: 100%; 
        border-radius: 12px; 
        padding: 0.5rem 1rem;
        font-weight: 600;
        transition: all 0.3s ease;
        border: none;
        background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
    }
    .stButton>button:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
    }
    
    /* Product Cards */
    .product-card {
        border: 2px solid #e1e5e9;
        border-radius: 16px;
        padding: 1rem;
        margin: 0.5rem 0;
        transition: all 0.3s ease;
        background: white;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    .product-card:hover {
        border-color: #667eea;
        transform: translateY(-4px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
    }
    .product-card.selected {
        border-color: #667eea;
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }
    
    /* Enhanced Headers */
    .custom-header {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        padding: 1.5rem;
        border-radius: 12px;
        color: white;
        text-align: center;
        margin: 1rem 0;
    }
    
    /* Step Indicators */
    .step-indicator {
        display: flex;
        align-items: center;
        background: #f8f9fa;
        padding: 0.8rem 1.2rem;
        border-radius: 10px;
        margin: 1rem 0;
        border-left: 4px solid #667eea;
    }
    
    /* Status Badges */
    .status-badge { padding: 6px 12px; border-radius: 20px; font-weight: bold; font-size: 0.85em; }
    .status-queued { background: linear-gradient(45deg, #ffecd2 0%, #fcb69f 100%); color: #8b4513; }
    .status-posted { background: linear-gradient(45deg, #a8edea 0%, #fed6e3 100%); color: #155724; }
    .status-failed { background: linear-gradient(45deg, #ff9a9e 0%, #fecfef 100%); color: #721c24; }
    
    /* Search Enhancement */
    .stTextInput>div>div>input {
        border-radius: 25px;
        border: 2px solid #e1e5e9;
        padding: 0.7rem 1.2rem;
    }
    .stTextInput>div>div>input:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    /* Configuration Panel */
    .config-panel {
        background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        padding: 2rem;
        border-radius: 16px;
        margin: 1rem 0;
    }
    
    /* Selection Counter */
    .selection-counter {
        background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0.8rem 1.5rem;
        border-radius: 25px;
        text-align: center;
        font-weight: bold;
        margin: 1rem 0;
    }
</style>
""", unsafe_allow_html=True)

# Main Title with Enhanced Styling
st.markdown("""
<div class="custom-header">
    <h1 style="margin: 0; font-size: 2.5rem;">🤖 Social Bot Creator</h1>
    <p style="margin: 0.5rem 0 0 0; opacity: 0.9; font-size: 1.1rem;">Create engaging social media content in minutes</p>
</div>
""", unsafe_allow_html=True)

# Sidebar
st.sidebar.title("Navigation")
nav_selection = st.sidebar.radio("Go to", ["Create Content", "Scheduler", "Products", "Analytics", "Settings"])

# --- VIEW: CREATE CONTENT ---
if nav_selection == "Create Content":
    # Step 1 Header
    st.markdown("""
    <div class="step-indicator">
        <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">1</div>
        <h3 style="margin: 0; color: #333;">📂 Select Content Source</h3>
    </div>
    """, unsafe_allow_html=True)
    
    # Enhanced source selection
    col1, col2 = st.columns([1, 1])
    with col1:
        from_product = st.button("📦 From Product Database", use_container_width=True, type="secondary")
    with col2:
        custom_upload = st.button("📤 Custom Upload", use_container_width=True, type="secondary")
    
    # Determine source type based on button clicks
    if 'source_type' not in st.session_state:
        st.session_state.source_type = "From Product"
    
    if from_product:
        st.session_state.source_type = "From Product"
    elif custom_upload:
        st.session_state.source_type = "Custom Upload"
    
    source_type = st.session_state.source_type
    
    selected_image_path = None
    selected_product = None
    
    if source_type == "From Product":
        # Search/Select Product(s)
        products_db = execute_query("SELECT id, name, price, image_url FROM products ORDER BY id DESC")
        if not products_db:
             st.warning("No products found. Go to 'Products' tab to sync.")
        else:
            # --- Enhanced Grid Selection UI ---
            if 'selected_product_ids' not in st.session_state:
                st.session_state.selected_product_ids = set()

            # Search and Actions Row
            search_col, filter_col, action_col = st.columns([2, 1, 1])
            with search_col:
                search_query = st.text_input("🔍 Search Products", placeholder="Search by name, price, or category...", key="product_search")
            with filter_col:
                price_filter = st.selectbox("💰 Price Range", ["All Prices", "Under $50", "$50-$100", "Over $100"])
            with action_col:
                if st.button("🗑️ Clear All", type="secondary"):
                    st.session_state.selected_product_ids = set()
                    st.rerun()

            # Filter products
            filtered_products = products_db
            if search_query:
                filtered_products = [p for p in filtered_products if search_query.lower() in p['name'].lower()]
            
            # Apply price filter
            if price_filter != "All Prices":
                if price_filter == "Under $50":
                    filtered_products = [p for p in filtered_products if float(p['price']) < 50]
                elif price_filter == "$50-$100":
                    filtered_products = [p for p in filtered_products if 50 <= float(p['price']) <= 100]
                elif price_filter == "Over $100":
                    filtered_products = [p for p in filtered_products if float(p['price']) > 100]
            
            # Limit for performance
            filtered_products = filtered_products[:40]

            # Enhanced Selection Summary
            if st.session_state.selected_product_ids:
                selected_count = len(st.session_state.selected_product_ids)
                st.markdown(f"""
                <div class="selection-counter">
                    ✅ {selected_count} product{'s' if selected_count != 1 else ''} selected
                </div>
                """, unsafe_allow_html=True)

            # Enhanced Grid Display
            if not filtered_products:
                st.markdown("""
                <div style="text-align: center; padding: 3rem; background: #f8f9fa; border-radius: 12px; margin: 2rem 0;">
                    <h3>🔍 No products found</h3>
                    <p>Try adjusting your search or filters</p>
                </div>
                """, unsafe_allow_html=True)
            else:
                st.markdown(f"**Showing {len(filtered_products)} product{'s' if len(filtered_products) != 1 else ''}**")
                
                # Responsive grid (3 columns on desktop, 2 on mobile)
                cols = st.columns(3)
                for idx, product in enumerate(filtered_products):
                    with cols[idx % 3]:
                        # Enhanced Product Card
                        is_selected = product['id'] in st.session_state.selected_product_ids
                        card_class = "product-card selected" if is_selected else "product-card"
                        
                        # Resolve Image with better error handling
                        image_url = product['image_url']
                        display_image = "https://via.placeholder.com/200x200/667eea/ffffff?text=No+Image"
                        
                        if image_url:
                            if image_url.startswith('http'):
                                display_image = image_url
                            else:
                                # Normalize slashes and try multiple paths
                                clean_url = image_url.replace('/', '\\')
                                possible_paths = [
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift', clean_url),
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift\\public', clean_url),
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift', image_url),
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift\\images', clean_url)
                                ]
                                for p in possible_paths:
                                    if os.path.exists(p):
                                        display_image = p
                                        break
                        
                        # Create enhanced product card
                        with st.container():
                            st.markdown(f'<div class="{card_class}">', unsafe_allow_html=True)
                            
                            # Product Image with overlay for selection
                            try:
                                st.image(display_image, use_container_width=True, caption=None)
                            except:
                                st.markdown("""
                                <div style="height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 8px;">
                                    <span style="color: #999;">📷 Image unavailable</span>
                                </div>
                                """, unsafe_allow_html=True)
                            
                            # Product Info
                            st.markdown(f"**{product['name'][:25]}{'...' if len(product['name']) > 25 else ''}**")
                            
                            # Price with styling
                            st.markdown(f"""
                            <div style="display: flex; justify-content: space-between; align-items: center; margin: 0.5rem 0;">
                                <span style="font-size: 1.2rem; font-weight: bold; color: #667eea;">${product['price']}</span>
                                <span style="font-size: 0.9rem; color: #666;">ID: {product['id']}</span>
                            </div>
                            """, unsafe_allow_html=True)
                            
                            # Selection Toggle with enhanced styling
                            select_key = f"prod_select_{product['id']}"
                            if st.checkbox("✅ Select for Content" if is_selected else "⬜ Select for Content", 
                                         key=select_key, value=is_selected):
                                st.session_state.selected_product_ids.add(product['id'])
                            else:
                                st.session_state.selected_product_ids.discard(product['id'])
                            
                            st.markdown('</div>', unsafe_allow_html=True)

            # --- Process Selection for Downstream Logic ---
            if st.session_state.selected_product_ids:
                # Retrieve full objects for selected IDs
                selected_products = [p for p in products_db if p['id'] in st.session_state.selected_product_ids]
                
                if selected_products:
                    selected_product = selected_products[0] # Metadata from first product
                    
                    # Collect images
                    image_paths = []
                    for prod in selected_products:
                        i_url = prod['image_url']
                        if i_url:
                             if not i_url.startswith('http'):
                                 clean_url = i_url.replace('/', '\\')
                                 possible_paths = [
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift', clean_url),
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift\\public', clean_url),
                                    os.path.join('c:\\xampp\\htdocs\\aleppogift', i_url)
                                 ]
                                 for p in possible_paths:
                                     if os.path.exists(p):
                                         image_paths.append(p)
                                         break
                    
                    if image_paths:
                        selected_image_path = image_paths if len(image_paths) > 1 else image_paths[0]

    else:  # Custom Upload
        st.markdown("""
        <div style="background: #f8f9fa; padding: 2rem; border-radius: 12px; border: 2px dashed #667eea; text-align: center;">
            <h4 style="color: #667eea;">📤 Upload Your Images</h4>
            <p style="color: #666;">Drag and drop or click to select up to 10 images</p>
        </div>
        """, unsafe_allow_html=True)
        
        uploaded_files = st.file_uploader("Upload Images (up to 10, will be paired side-by-side)", 
                                         type=['jpg', 'png', 'jpeg'], 
                                         accept_multiple_files=True,
                                         label_visibility="collapsed")
        if uploaded_files:
            # Limit to 10 images
            if len(uploaded_files) > 10:
                st.warning("⚠️ Maximum 10 images allowed. Using first 10.")
                uploaded_files = uploaded_files[:10]
            
            # Enhanced upload feedback
            st.markdown(f"""
            <div class="selection-counter">
                📷 {len(uploaded_files)} image{'s' if len(uploaded_files) != 1 else ''} uploaded successfully
            </div>
            """, unsafe_allow_html=True)
            
            # Save all uploaded files
            image_paths = []
            for uploaded_file in uploaded_files:
                save_path = os.path.join("assets/images", uploaded_file.name)
                os.makedirs("assets/images", exist_ok=True)
                with open(save_path, "wb") as f:
                    f.write(uploaded_file.getbuffer())
                image_paths.append(save_path)
            
            # Enhanced thumbnail display
            st.markdown("**📷 Uploaded Images Preview:**")
            cols = st.columns(min(4, len(image_paths)))
            for idx, img_path in enumerate(image_paths):
                with cols[idx % 4]:
                    with st.container():
                        st.markdown('<div class="product-card">', unsafe_allow_html=True)
                        st.image(img_path, use_container_width=True)
                        st.markdown(f"<small>{os.path.basename(img_path)}</small>", unsafe_allow_html=True)
                        st.markdown('</div>', unsafe_allow_html=True)
            
            selected_image_path = image_paths  # Now it's a list
            
            # Create a mock product object for custom uploads
            selected_product = {
                'id': 0,
                'name': 'Multi-Image Upload',
                'price': 0,
                'category': 'Custom',
                'description': f'{len(image_paths)} images'
            }

    # Step 2: Configuration
    if selected_image_path and selected_product:
        # Step 2 Header
        st.markdown("""
        <div class="step-indicator">
            <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">2</div>
            <h3 style="margin: 0; color: #333;">⚙️ Customize Content</h3>
        </div>
        """, unsafe_allow_html=True)
        
        with st.container():
            st.markdown('<div class="config-panel">', unsafe_allow_html=True)
            
            # Main configuration
            c1, c2 = st.columns([2, 1])
            with c1:
                content_type = st.radio("Content Format", ["Reel / TikTok (9:16 Video)", "Story (9:16 Video)", "Post (Static Image)"], horizontal=True)
            with c2:
                caption_theme = st.selectbox("Marketing Hook", ["new_arrival", "product_spotlight", "limited_offer"], format_func=lambda x: x.replace('_', ' ').title())

            # Advanced settings in expander to reduce clutter
            with st.expander("🛠️ Advanced Settings (Audio & Quality)"):
                col1, col2 = st.columns(2)
                with col1:
                    quality = st.select_slider("Video Quality (Rendering Speed)", options=["low", "medium", "high"], value="medium")
                with col2:
                    add_audio = st.checkbox("Add Background Music", value=True)
            
            st.markdown('</div>', unsafe_allow_html=True)
        
        # Step 3: Generate
        st.markdown("""
        <div class="step-indicator">
            <div style="background: #667eea; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 1rem; font-weight: bold;">3</div>
            <h3 style="margin: 0; color: #333;">✨ Generate Result</h3>
        </div>
        """, unsafe_allow_html=True)
        
        # Center the generate button
        _, col_btn, _ = st.columns([1, 2, 1])
        with col_btn:
            generate_btn = st.button("🚀 Create Content", type="primary", use_container_width=True)

        if generate_btn:
            if content_type == "Post (Static Image)":
                st.info("Static Post generation is simple passthrough for this demo.")
                st.image(selected_image_path, caption="Post Preview")
                st.session_state['generated_file'] = selected_image_path
                st.session_state['generated_type'] = 'image'
            else:
                 with st.spinner("🎬 Rendering video... This might take a moment."):
                    # Check FFmpeg availability first
                    from modules.video_engine import FFMPEG_PATH
                    if not FFMPEG_PATH:
                        st.error("❌ FFmpeg not detected!")
                        st.code(r"Expected location: C:\Users\DeLL\AppData\Local\Microsoft\WinGet\Packages\...\ffmpeg.exe")
                        st.info("FFmpeg was installed but requires restarting the application. Click 'Restart' in the PHP dashboard.")
                        st.stop()
                    
                    generator = VideoGenerator(quality=quality)
                    # Use timestamp to avoid cache issues
                    output_filename = f"video_{selected_product['id']}_{int(time.time())}.mp4"
                    output_path = os.path.join("output/videos", output_filename)
                    
                    # Mock finding audio
                    audio_path = None
                    if add_audio:
                        audio_dir = os.path.join(os.path.dirname(__file__), "assets", "audio")
                        if os.path.exists(audio_dir):
                            audio_files = [f for f in os.listdir(audio_dir) if f.endswith(".mp3")]
                            if audio_files:
                                audio_path = os.path.join(audio_dir, audio_files[0])
                    
                    # Handle both single image and multiple images
                    image_input = selected_image_path if isinstance(selected_image_path, list) else [selected_image_path]
                    final_video = generator.create_video(image_input, output_path, audio_path=audio_path)
                    
                    if final_video and os.path.exists(final_video):
                        st.success("✅ Video Generated Successfully!")
                        st.video(final_video)
                        st.session_state['generated_file'] = final_video
                        st.session_state['generated_type'] = 'video'
                        st.session_state['current_product'] = selected_product
                    else:
                        st.error("❌ Video generation failed!")
                        st.warning("An error occurred during video processing.")
                        st.info("Please check the terminal logs for detailed FFmpeg error output.")
                        st.info("Troubleshooting:\n1. Ensure FFmpeg is installed correctly.\n2. Check if the input image format is supported.\n3. Verify you have write permissions in the output folder.")

        # Step 4: Publish
        if 'generated_file' in st.session_state:
            st.divider()
            st.subheader("4. Publish / Schedule")
            
            sc_col1, sc_col2 = st.columns(2)
            with sc_col1:
                platforms = st.multiselect("Select Platforms", ["tiktok", "instagram", "facebook"], default=["tiktok"])
                
                caption_gen = CaptionGenerator()
                product_for_caption = st.session_state.get('current_product', selected_product) or selected_product
                default_caption = caption_gen.generate(product_for_caption, 'tiktok')
                final_caption = st.text_area("Caption", value=default_caption, height=120)
                
            with sc_col2:
                schedule_mode = st.radio("Timing", ["Post Now", "Schedule for Later"])
                scheduled_time = datetime.now()
                if schedule_mode == "Schedule for Later":
                    d = st.date_input("Date")
                    t = st.time_input("Time")
                    scheduled_time = datetime.combine(d, t)
                else:
                    # Give it a minute buffer
                    scheduled_time = datetime.now() + timedelta(minutes=1)
            
            if st.button("🚀 Confirm & Schedule", type="primary"):
                # Save to DB
                current_product = st.session_state.get('current_product', {})
                product_id = current_product['id'] if current_product and 'id' in current_product else 0
                video_id = execute_query("INSERT INTO videos (product_id, video_path) VALUES (?, ?)", 
                                        (product_id, st.session_state['generated_file']), commit=True)
                
                for plt in platforms:
                   execute_query("""
                        INSERT INTO posts (video_id, platform, caption, status, scheduled_time)
                        VALUES (?, ?, ?, 'queued', ?)
                    """, (video_id, plt, final_caption, scheduled_time), commit=True)
                
                st.balloons()
                st.success(f"Content scheduled for {', '.join(platforms)} at {scheduled_time}")
                del st.session_state['generated_file'] # Reset

# --- VIEW: SCHEDULER/QUEUE ---
elif nav_selection == "Scheduler":
    st.header("📅 Scheduled Posts")
    
    # Tabs for status
    tab_q, tab_h, tab_f = st.tabs(["Queued", "History (Posted)", "Failed"])
    
    with tab_q:
        queued = execute_query("SELECT p.id, p.platform, p.scheduled_time, p.caption, v.video_path FROM posts p JOIN videos v ON p.video_id = v.id WHERE p.status = 'queued' ORDER BY p.scheduled_time ASC")
        if queued:
            for item in queued:
                with st.expander(f"{item['platform'].upper()} - {item['scheduled_time']}"):
                    st.write(item['caption'])
                    col_a, col_b = st.columns([1,4])
                    if item['video_path'].endswith('.mp4'):
                        col_a.video(item['video_path'])
                    else:
                        col_a.image(item['video_path'])
                    if col_b.button("Cancel", key=f"cancel_{item['id']}"):
                        execute_query("DELETE FROM posts WHERE id=?", (item['id'],), commit=True)
                        st.experimental_rerun()
        else:
            st.info("No queued posts.")

    with tab_h:
        posted = execute_query("SELECT id, platform, posted_time, caption FROM posts WHERE status = 'posted' ORDER BY posted_time DESC LIMIT 20")
        if posted:
            st.dataframe(pd.DataFrame(posted))
        else:
            st.info("No posting history.")
            
    with tab_f:
        failed = execute_query("SELECT id, platform, scheduled_time, error_message FROM posts WHERE status = 'failed' ORDER BY scheduled_time DESC")
        if failed:
             st.dataframe(pd.DataFrame(failed))
             if st.button("Retry All Failed"):
                 execute_query("UPDATE posts SET status='queued', retry_count=retry_count+1 WHERE status='failed'", commit=True)
                 st.success("Rescheduled failed posts.")
        else:
            st.success("No failed posts!")

# --- VIEW: SETTINGS ---
elif nav_selection == "Settings":
    st.header("⚙️ Settings & Keys")
    
    st.info("Enter your API keys (Client ID / Secrets) here. These are saved locally to your database/configuration.")
    
    # Help Guide Link
    with st.expander("📚 Need help getting API keys?"):
        st.markdown("""
        ### Step-by-step guide available!
        
        We've created a comprehensive guide to help you obtain all the necessary API keys:
        
        - 🎵 **TikTok API Setup** - Complete walkthrough for TikTok Business & Developer accounts
        - 📷 **Instagram API Setup** - How to get Facebook Graph API tokens for Instagram
        - 👥 **Facebook Page Setup** - Configure your Facebook Page for posting
        - 🔧 **Testing & Troubleshooting** - Common issues and how to fix them
        """)
        
        import webbrowser
        import os
        
        guide_path = os.path.join(os.path.dirname(__file__), "SOCIAL_MEDIA_API_SETUP_GUIDE.html")
        
        if st.button("📖 Open Setup Guide in Browser", key="open_guide"):
            webbrowser.open(f"file:///{guide_path}")
            st.success("Setup guide opened in your browser!")
        
        st.caption(f"Or find the guide in your project folder: `SOCIAL_MEDIA_API_SETUP_GUIDE.html`")
    
    st.markdown("---")
    
    # TikTok
    st.subheader("TikTok Configuration")
    col_t1, col_t2 = st.columns(2)
    with col_t1:
        tt_key = st.text_input("TikTok Client Key", value=get_setting("TIKTOK_CLIENT_KEY", ""), type="password")
        if st.button("Save TikTok Key"):
            set_setting("TIKTOK_CLIENT_KEY", tt_key)
            st.success("Saved")
    with col_t2:
        tt_secret = st.text_input("TikTok Client Secret", value=get_setting("TIKTOK_CLIENT_SECRET", ""), type="password")
        if st.button("Save TikTok Secret"):
            set_setting("TIKTOK_CLIENT_SECRET", tt_secret)
            st.success("Saved")
            
    st.markdown("---")
    
    # Instagram
    st.subheader("Instagram/Facebook Configuration")
    col_i1, col_i2 = st.columns(2)
    with col_i1:
        ig_user = st.text_input("Instagram User ID", value=get_setting("INSTAGRAM_USER_ID", ""))
        fb_page = st.text_input("Facebook Page ID", value=get_setting("FACEBOOK_PAGE_ID", ""))
        if st.button("Save IDs"):
            set_setting("INSTAGRAM_USER_ID", ig_user)
            set_setting("FACEBOOK_PAGE_ID", fb_page)
            st.success("Saved")
            
    with col_i2:
        fb_token = st.text_input("Meta/Facebook App ID", value=get_setting("FACEBOOK_APP_ID", ""), type="password")
        fb_secret = st.text_input("Meta/Facebook App Secret", value=get_setting("FACEBOOK_APP_SECRET", ""), type="password")
        if st.button("Save Meta Keys"):
            set_setting("FACEBOOK_APP_ID", fb_token)
            set_setting("FACEBOOK_APP_SECRET", fb_secret)
            st.success("Saved")
            
    st.markdown("---")
    
    # NGROK
    st.subheader("System Tools")
    ng_token = st.text_input("NGROK Auth Token", value=get_setting("NGROK_AUTH_TOKEN", ""), type="password")
    if st.button("Save NGROK Token"):
        set_setting("NGROK_AUTH_TOKEN", ng_token)
        st.success("Saved")
        
    st.markdown("### Database")
    if st.button("Re-Initialize Database"):
        from init_database import init_db
        init_db()
        st.success("Database schema verified.")

# --- VIEW: PRODUCTS ---
elif nav_selection == "Products":
    st.header("📦 Product Catalog")
    col_p1, col_p2 = st.columns([1,3])
    with col_p1:
        if st.button("🔄 Sync from MySQL"):
            with st.spinner("Connecting to source DB..."):
                connector = DatabaseConnector()
                products = connector.fetch_products(limit=5000)
                count = 0
                for p in products:
                    # Upsert
                    exists = execute_query("SELECT id FROM products WHERE external_id = ?", (p['id'],))
                    if not exists:
                        # Convert Decimal to float for SQLite compatibility
                        price = float(p['price']) if p['price'] is not None else 0.0
                        execute_query("""
                            INSERT INTO products (external_id, name, price, description, image_url, synced_at)
                            VALUES (?, ?, ?, ?, ?, ?)
                        """, (p['id'], p['name'], price, p['description'], p['image_url'], datetime.now()), commit=True)
                        count += 1
                st.success(f"Synced. Added {count} new products.")
                
    st.write("Current Inventory:")
    products_list = execute_query("SELECT * FROM products ORDER BY id DESC LIMIT 5000")
    if products_list:
        st.dataframe(pd.DataFrame(products_list))

# --- VIEW: ANALYTICS ---
elif nav_selection == "Analytics":
    st.header("📈 Performance Analytics")
    st.info("Connect analytics module to display real-time graphs.")
    
    # Mock Charts
    col_a1, col_a2 = st.columns(2)
    with col_a1:
        st.subheader("Engagement by Platform")
        # Mock Data
        data = pd.DataFrame({
            'Platform': ['TikTok', 'Instagram', 'Facebook'],
            'Views': [1200, 3400, 800]
        })
        st.bar_chart(data, x='Platform', y='Views')
        
    with col_a2:
        st.subheader("Recent Post Performance")
        st.line_chart([10, 25, 14, 88, 32, 45, 60])


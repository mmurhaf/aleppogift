import sqlite3
import os

# Use absolute path matching database.py
DB_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'social_bot.db')

def init_db():
    print(f"Initializing database at {DB_FILE}...")
    
    conn = sqlite3.connect(DB_FILE)
    cursor = conn.cursor()

    # -- App settings and encrypted API credentials
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        is_encrypted BOOLEAN DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
    ''')

    # -- Synced product catalog
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY,
        external_id INTEGER,
        name TEXT,
        price DECIMAL(10,2),
        category TEXT,
        description TEXT,
        image_url TEXT,
        local_image_path TEXT,
        is_blacklisted BOOLEAN DEFAULT 0,
        synced_at DATETIME
    )
    ''')

    # -- Generated videos inventory
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY,
        product_id INTEGER,
        video_path TEXT,
        thumbnail_path TEXT,
        duration INTEGER,
        quality_preset TEXT,
        created_at DATETIME,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )
    ''')

    # -- Post queue and history
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY,
        video_id INTEGER,
        platform TEXT,
        caption TEXT,
        hashtags TEXT,
        status TEXT,
        scheduled_time DATETIME,
        posted_time DATETIME,
        post_url TEXT,
        error_message TEXT,
        retry_count INTEGER DEFAULT 0,
        FOREIGN KEY (video_id) REFERENCES videos(id)
    )
    ''')
    
    # -- Analytics data
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS post_metrics (
        id INTEGER PRIMARY KEY,
        post_id INTEGER,
        views INTEGER DEFAULT 0,
        likes INTEGER DEFAULT 0,
        shares INTEGER DEFAULT 0,
        comments INTEGER DEFAULT 0,
        engagement_rate DECIMAL(5,2),
        fetched_at DATETIME,
        FOREIGN KEY (post_id) REFERENCES posts(id)
    )
    ''')

    # -- OAuth tokens (encrypted)
    cursor.execute('''
    CREATE TABLE IF NOT EXISTS oauth_tokens (
        platform TEXT PRIMARY KEY,
        access_token TEXT,
        refresh_token TEXT,
        expires_at DATETIME,
        created_at DATETIME
    )
    ''')

    conn.commit()
    conn.close()
    print("Database initialization complete.")

if __name__ == "__main__":
    init_db()

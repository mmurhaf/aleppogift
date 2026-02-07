import os
from dotenv import load_dotenv
from cryptography.fernet import Fernet
import logging

# Load environment variables
load_dotenv()

# Logger setup
def setup_logger():
    if not os.path.exists('output/logs'):
        os.makedirs('output/logs')
        
    logging.basicConfig(
        filename='output/logs/app.log',
        level=logging.INFO,
        format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
    )
    return logging.getLogger('social_bot')

logger = setup_logger()

# Security
def get_encryption_key():
    key = os.getenv('ENCRYPTION_KEY')
    if not key:
        return None
    return key.encode() if isinstance(key, str) else key

def encrypt_token(token):
    key = get_encryption_key()
    if not key:
        raise ValueError("ENCRYPTION_KEY not set in .env")
    f = Fernet(key)
    return f.encrypt(token.encode()).decode()

def decrypt_token(encrypted_token):
    key = get_encryption_key()
    if not key:
        raise ValueError("ENCRYPTION_KEY not set in .env")
    f = Fernet(key)
    return f.decrypt(encrypted_token.encode()).decode()

def get_setting(key, default=None):
    # 1. Check Environment Variable first (overrides DB)
    env_val = os.getenv(key.upper())
    if env_val:
        return env_val
        
    # 2. Check Database
    try:
        import sqlite3
        conn = sqlite3.connect('social_bot.db')
        cursor = conn.cursor()
        cursor.execute("SELECT value FROM settings WHERE key = ?", (key,))
        result = cursor.fetchone()
        conn.close()
        if result:
            return result[0]
    except Exception as e:
        print(f"Error reading setting {key}: {e}")
        
    return default

def set_setting(key, value):
    try:
        import sqlite3
        conn = sqlite3.connect('social_bot.db')
        cursor = conn.cursor()
        cursor.execute("INSERT INTO settings (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = ?", (key, value, value))
        conn.commit()
        conn.close()
        return True
    except Exception as e:
        print(f"Error saving setting {key}: {e}")
        return False

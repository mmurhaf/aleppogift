import sqlite3
import os
from datetime import datetime

# Use absolute path for DB to avoid issues with running from different directories
DB_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'social_bot.db')

def get_db_connection():
    conn = sqlite3.connect(DB_FILE)
    conn.row_factory = sqlite3.Row
    return conn

def execute_query(query, params=(), commit=False):
    conn = get_db_connection()
    cursor = conn.cursor()
    try:
        cursor.execute(query, params)
        if commit:
            conn.commit()
            return cursor.lastrowid
        else:
            return cursor.fetchall()
    finally:
        conn.close()

def log_error(source_id, message):
    # Depending on how you want to log, maybe to a table or file
    # Here we just log to file via config logger
    import config
    config.logger.error(f"Error for source {source_id}: {message}")

def get_pending_posts():
    query = """
    SELECT p.*, v.video_path, v.duration 
    FROM posts p
    JOIN videos v ON p.video_id = v.id
    WHERE p.status = 'queued' AND p.scheduled_time <= ?
    """
    # Fetch posts scheduled for now or in the past
    return execute_query(query, (datetime.now(),))

def update_post_status(post_id, status, error_message=None):
    if error_message:
        query = "UPDATE posts SET status = ?, error_message = ?, posted_time = ? WHERE id = ?"
        execute_query(query, (status, error_message, datetime.now(), post_id), commit=True)
    else:
        query = "UPDATE posts SET status = ?, posted_time = ? WHERE id = ?"
        execute_query(query, (status, datetime.now(), post_id), commit=True)

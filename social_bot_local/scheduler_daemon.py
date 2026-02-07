import schedule
import time
from modules.platforms.tiktok_api import TikTokAPI
from modules.platforms.instagram_api import InstagramAPI
from modules.platforms.facebook_api import FacebookAPI
from database import get_pending_posts, update_post_status, log_error

def process_queue():
    """Check database for posts scheduled within next 5 minutes"""
    print("Checking for pending posts...")
    try:
        pending = get_pending_posts()
        if not pending:
             return

        for post in pending:
            print(f"Processing post {post['id']} for {post['platform']}")
            update_post_status(post['id'], 'processing')
            
            try:
                # Assuming post is a dictionary or Row object
                video_path = post['video_path']
                caption = post['caption']
                hashtags = post['hashtags']
                
                if post['platform'] == 'tiktok':
                    TikTokAPI().upload_video(video_path, caption, hashtags)
                elif post['platform'] == 'instagram':
                    InstagramAPI().upload_reel(video_path, caption, hashtags)
                elif post['platform'] == 'facebook':
                    FacebookAPI().upload_reel(video_path, caption)
                
                update_post_status(post['id'], 'posted')
                print(f"Successfully posted {post['id']}")
            except Exception as e:
                print(f"Failed to post {post['id']}: {e}")
                log_error(post['id'], str(e))
                update_post_status(post['id'], 'failed', str(e))
    except Exception as e:
        print(f"Scheduler loop error: {e}")

def sync_analytics():
    """Fetch metrics for all posted content"""
    from modules.analytics import AnalyticsDashboard
    print("Syncing analytics...")
    AnalyticsDashboard().sync_metrics()

# Run every 5 minutes
schedule.every(5).minutes.do(process_queue)

# Sync analytics daily at 3 AM
schedule.every().day.at("03:00").do(sync_analytics)

if __name__ == "__main__":
    print("Scheduler daemon started...")
    while True:
        schedule.run_pending()
        time.sleep(60)

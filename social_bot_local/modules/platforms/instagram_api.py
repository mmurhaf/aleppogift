from config import get_setting, decrypt_token
from modules.video_host_server import TemporaryVideoHost
import requests
import time

class InstagramAPI:
    def __init__(self):
        self.graph_url = "https://graph.facebook.com/v18.0"
        self.ig_user_id = get_setting('INSTAGRAM_USER_ID')
        # In a real app, fetch from DB
        self.access_token = "PLACEHOLDER_TOKEN"
        self.video_host = TemporaryVideoHost()
    
    def upload_reel(self, video_path, caption, hashtags):
        """
        Step 1: Start local Flask server to serve video
        Step 2: Get public URL via NGROK
        Step 3: POST to /media with video_url
        Step 4: Wait for processing
        Step 5: POST to /media_publish
        Step 6: Shutdown temp server
        """
        try:
            # 1. Host Video
            video_url = self.video_host.start_server(video_path)
            if not video_url:
                raise Exception("Failed to start video host")
                
            full_caption = f"{caption}\n\n{hashtags}"
            
            # 2. Create Container
            creation_id = self.create_container(video_url, full_caption)
            if not creation_id:
                raise Exception("Failed to create media container")
            
            # 3. Wait/Check Status could be implemented here
            time.sleep(10) # Simple wait
            
            # 4. Publish
            result = self.publish_container(creation_id)
            return result
            
        finally:
            self.video_host.stop_server()
    
    def create_container(self, video_url, caption):
        """Step 1: Create media container"""
        url = f"{self.graph_url}/{self.ig_user_id}/media"
        params = {
            'media_type': 'REELS',
            'video_url': video_url,
            'caption': caption,
            'access_token': self.access_token
        }
        print(f"Creating IG Container for: {video_url}")
        # response = requests.post(url, params=params)
        # return response.json().get('id')
        return "12345_container_id"
    
    def publish_container(self, creation_id):
        """Step 2: Publish the container"""
        url = f"{self.graph_url}/{self.ig_user_id}/media_publish"
        params = {
            'creation_id': creation_id,
            'access_token': self.access_token
        }
        print(f"Publishing IG Container: {creation_id}")
        # response = requests.post(url, params=params)
        # return response.json().get('id')
        return "published_media_id"
    
    def get_insights(self, media_id):
        """Fetch reel performance metrics"""
        pass

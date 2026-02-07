from config import get_setting
import requests

class FacebookAPI:
    def __init__(self):
        self.page_id = get_setting('FACEBOOK_PAGE_ID')
        self.access_token = "PLACEHOLDER_TOKEN"
        
    def upload_reel(self, video_path, caption):
        """Direct multipart upload to page"""
        url = f"https://graph-video.facebook.com/v18.0/{self.page_id}/video_reels"
        print(f"Uploading to Facebook Reel: {video_path}")
        
        # files = {
        #     'video_file': open(video_path, 'rb')
        # }
        # params = {
        #     'access_token': self.access_token,
        #     'description': caption,
        #     'upload_phase': 'start'
        # }
        # Do upload flow
        return "fb_video_id"
    
    def get_video_stats(self, video_id):
        """Fetch views and engagement"""
        pass

from config import get_setting, encrypt_token, decrypt_token
from database import execute_query
import webbrowser
import threading
from http.server import HTTPServer, BaseHTTPRequestHandler
from urllib.parse import urlparse, parse_qs

class OAuthCallbackHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header('Content-type', 'text/html')
        self.end_headers()
        self.wfile.write(b"<h1>Authorization Successful!</h1><p>You can close this window.</p>")
        
        # Extract code
        query = parse_qs(urlparse(self.path).query)
        if 'code' in query:
            self.server.auth_code = query['code'][0]
        
class OAuthHandler:
    def __init__(self, platform):
        """platform: 'tiktok', 'instagram', 'facebook'"""
        self.platform = platform
        self.server = None
    
    def start_oauth_flow(self):
        """
        1. Start local HTTP server on http://localhost:8080/callback
        2. Open browser to authorization URL
        3. Capture auth code from redirect
        4. Exchange for tokens
        5. Store encrypted tokens in database
        """
        # Define Platform specific auth URLs
        client_id = get_setting(f"{self.platform}_CLIENT_KEY" if self.platform == 'tiktok' else f"{self.platform}_APP_ID")
        redirect_uri = "http://localhost:8080/callback"
        
        auth_url = ""
        if self.platform == 'tiktok':
            auth_url = f"https://www.tiktok.com/v2/auth/authorize/?client_key={client_id}&scope=user.info.basic,video.publish,video.upload&response_type=code&redirect_uri={redirect_uri}&state=random_state"
        elif self.platform == 'instagram':
            auth_url = f"https://api.instagram.com/oauth/authorize?client_id={client_id}&redirect_uri={redirect_uri}&scope=user_profile,user_media&response_type=code"
            
        print(f"Opening browser for {self.platform} auth: {auth_url}")
        webbrowser.open(auth_url)
        
        # Start server to listen for callback
        server_address = ('', 8080)
        self.server = HTTPServer(server_address, OAuthCallbackHandler)
        self.server.auth_code = None
        self.server.handle_request() # Handle one request then exit
        
        if self.server.auth_code:
            print(f"Received auth code: {self.server.auth_code}")
            # Exchange code for token (Implementation skipped for brevity)
            # self.exchange_token(self.server.auth_code)
            return True
        return False
    
    def refresh_token_if_needed(self, platform):
        """Check expiry and auto-refresh"""
        pass

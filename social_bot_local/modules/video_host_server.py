from flask import Flask, send_file
import threading
from pyngrok import ngrok
import os

app = Flask(__name__)

# Global variable to store current video path
current_video_path = None

@app.route('/video.mp4')
def serve_video():
    if current_video_path and os.path.exists(current_video_path):
        return send_file(current_video_path, mimetype='video/mp4')
    return "Video not found", 404

class TemporaryVideoHost:
    """
    Flask server to serve videos for Instagram API
    Starts on-demand, exposes via NGROK, shuts down after upload.
    Note: Managing Flask in a thread and shutting it down cleanly is tricky.
    For simplicity, we might keep it running or start adjacent process.
    Here is a simplified threading model.
    """
    def __init__(self, port=5000):
        self.port = port
        self.server_thread = None
        self.public_url = None
        self.tunnel = None

    def start_server(self, video_path):
        """Returns public URL to video"""
        global current_video_path
        current_video_path = video_path
        
        # Start NGROK
        token = os.getenv("NGROK_AUTH_TOKEN")
        if token:
           ngrok.set_auth_token(token)
           
        self.tunnel = ngrok.connect(self.port)
        self.public_url = self.tunnel.public_url + "/video.mp4"
        print(f"Server started at {self.public_url}")
        
        # Start Flask (only if not already running - flask is tricky to restart in same process)
        if not self.server_thread:
             self.server_thread = threading.Thread(target=lambda: app.run(port=self.port, use_reloader=False))
             self.server_thread.daemon = True
             self.server_thread.start()
             
        return self.public_url
    
    def stop_server(self):
        """Clean shutdown - mostly just closing tunnel"""
        if self.tunnel:
            ngrok.disconnect(self.tunnel.public_url)
            self.tunnel = None
            self.public_url = None
        # We don't stop the flask thread, just valid for one session
        print("Server stopped (tunnel closed)")

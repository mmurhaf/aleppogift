import json
import random
import os

class CaptionGenerator:
    def __init__(self):
        self.captions_file = 'templates/captions.json'
        self.hashtags_file = 'templates/hashtags.json'
        self.load_templates()
        
    def load_templates(self):
        try:
             with open(self.captions_file, 'r', encoding='utf-8') as f:
                self.caption_templates = json.load(f)
        except Exception:
            self.caption_templates = {}
            
        try:
            with open(self.hashtags_file, 'r', encoding='utf-8') as f:
                self.hashtag_pools = json.load(f)
        except Exception:
            self.hashtag_pools = {}

    def generate(self, product, platform, theme='new_arrival'):
        """
        Load template from templates/captions.json
        Replace placeholders: {product_name}, {price}, {emoji}
        Add platform-specific hashtags from hashtags.json
        """
        if theme not in self.caption_templates:
            theme = 'new_arrival' # Fallback
            
        templates = self.caption_templates.get(theme, {}).get('templates', [])
        if not templates:
            return f"Check out {product['name']}! Only ${product['price']}"
            
        template = random.choice(templates)
        
        caption = template.replace('{product_name}', product['name'])
        caption = caption.replace('{price}', str(product['price']))
        
        hashtags = self.get_hashtags(platform)
        
        return f"{caption}\n\n{hashtags}"
    
    def get_hashtags(self, platform, limit=15):
        """
        Platform-specific hashtag strategy
        """
        platform_tags = self.hashtag_pools.get(platform, {})
        all_tags = []
        for category, tags in platform_tags.items():
            all_tags.extend(tags)
            
        if not all_tags:
            return "#AleppoGift"
            
        selected_tags = random.sample(all_tags, min(len(all_tags), limit))
        return ' '.join(selected_tags)

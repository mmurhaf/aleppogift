import mysql.connector
from config import get_setting

class DatabaseConnector:
    def __init__(self):
        self.host = get_setting('MYSQL_HOST', 'localhost')
        self.user = get_setting('MYSQL_USER', 'root')
        self.password = get_setting('MYSQL_PASSWORD', '')
        self.database = get_setting('MYSQL_DATABASE', 'u933234997_aleppogift')

    def get_connection(self):
        """Establish a connection to the MySQL database."""
        try:
            conn = mysql.connector.connect(
                host=self.host,
                user=self.user,
                password=self.password,
                database=self.database
            )
            return conn
        except mysql.connector.Error as err:
            print(f"Error connecting to MySQL: {err}")
            return None

    def fetch_products(self, limit=1000):
        """Fetch products from the database."""
        conn = self.get_connection()
        if not conn:
            return []
        
        cursor = conn.cursor(dictionary=True)
        # Adjusted to match actual AlepppoGift schema
        # Products table has name_en/name_ar instead of just 'name', and images are in a separate table or just not 'main_image' column directly if related.
        # However, looking at DATABASE_STRUCTURE_REFERENCE, we have product_images table.
        # But 'products' might not have main_image column.
        # We also need to map name_en to name.
        
        query = """
            SELECT 
                p.id, 
                p.name_en as name, 
                p.price, 
                p.description_en as description, 
                (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id AND pi.is_main = 1 LIMIT 1) as image_url,
                p.category_id
            FROM products p
            WHERE p.status = 1
            LIMIT %s
        """
        cursor.execute(query, (limit,))
        results = cursor.fetchall()
        
        # If necessary, fetch category names if it's a joined query
        # For simplicity return raw results
        
        cursor.close()
        conn.close()
        return results

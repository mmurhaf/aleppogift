<?php
/**
 * Product Reviews Class
 * Handles all review-related functionality
 */

class ProductReviews {
    private $db;
    private $conn;
    
    public function __construct($database = null) {
        // Accept optional Database instance to reuse connection
        if ($database instanceof Database) {
            $this->db = $database;
        } else {
            $this->db = new Database();
        }
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Get product rating summary
     */
    public function getProductRatingSummary($product_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star,
                SUM(is_verified_purchase) as verified_count
            FROM product_reviews
            WHERE product_id = :product_id AND is_approved = 1
        ");
        $stmt->execute(['product_id' => $product_id]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_reviews' => (int)($result['total_reviews'] ?? 0),
            'average_rating' => (float)($result['average_rating'] ?? 0),
            'five_star' => (int)($result['five_star'] ?? 0),
            'four_star' => (int)($result['four_star'] ?? 0),
            'three_star' => (int)($result['three_star'] ?? 0),
            'two_star' => (int)($result['two_star'] ?? 0),
            'one_star' => (int)($result['one_star'] ?? 0),
            'verified_count' => (int)($result['verified_count'] ?? 0),
        ];
    }
    
    /**
     * Get product reviews
     */
    public function getProductReviews($product_id, $limit = 10, $offset = 0, $sort = 'recent') {
        $order_by = match($sort) {
            'helpful' => 'helpful_count DESC',
            'highest' => 'rating DESC',
            'lowest' => 'rating ASC',
            default => 'created_at DESC'
        };

        $stmt = $this->conn->prepare("
            SELECT pr.*, 
                   CASE WHEN pr.order_id IS NOT NULL THEN 1 ELSE 0 END as verified
            FROM product_reviews pr
            WHERE pr.product_id = :product_id AND pr.is_approved = 1
            ORDER BY {$order_by}
            LIMIT :limit OFFSET :offset
        ");

        $stmt->bindValue(':product_id', (int)$product_id, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Submit a new review
     */
    public function submitReview($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO product_reviews 
            (product_id, customer_name, customer_email, customer_phone, rating, review_title, review_text, order_id, is_verified_purchase)
            VALUES (:product_id, :customer_name, :customer_email, :customer_phone, :rating, :review_title, :review_text, :order_id, :is_verified_purchase)
        ");

        $is_verified = !empty($data['order_id']) ? 1 : 0;

        return $stmt->execute([
            'product_id' => (int)$data['product_id'],
            'customer_name' => $data['customer_name'],
            'customer_email' => $data['customer_email'] ?: null,
            'customer_phone' => $data['customer_phone'] ?: null,
            'rating' => (int)$data['rating'],
            'review_title' => $data['review_title'] ?: null,
            'review_text' => $data['review_text'],
            'order_id' => !empty($data['order_id']) ? (int)$data['order_id'] : null,
            'is_verified_purchase' => $is_verified,
        ]);
    }
    
    /**
     * Mark review as helpful
     */
    public function markHelpful($review_id, $ip_address) {
        $check = $this->conn->prepare("SELECT id FROM review_helpful_votes WHERE review_id = :review_id AND voter_ip = :voter_ip");
        $check->execute([
            'review_id' => (int)$review_id,
            'voter_ip' => $ip_address,
        ]);
        $exists = (bool)$check->fetch(PDO::FETCH_ASSOC);

        if ($exists) {
            return false;
        }

        $stmt = $this->conn->prepare("INSERT INTO review_helpful_votes (review_id, voter_ip) VALUES (:review_id, :voter_ip)");
        $stmt->execute([
            'review_id' => (int)$review_id,
            'voter_ip' => $ip_address,
        ]);

        $update = $this->conn->prepare("UPDATE product_reviews SET helpful_count = helpful_count + 1 WHERE id = :review_id");
        $update->execute(['review_id' => (int)$review_id]);

        return true;
    }
    
    /**
     * Get review statistics for all products
     */
    public function getAllProductsWithRatings() {
        $result = $this->conn->query("
            SELECT 
                p.id,
                p.name_en,
                COUNT(pr.id) as review_count,
                AVG(pr.rating) as avg_rating
            FROM products p
            LEFT JOIN product_reviews pr ON p.id = pr.product_id AND pr.is_approved = 1
            GROUP BY p.id, p.name_en
            HAVING review_count > 0
            ORDER BY avg_rating DESC, review_count DESC
        ");

        return $result->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if customer can review (purchased the product)
     */
    public function canCustomerReview($product_id, $email_or_phone) {
        $stmt = $this->conn->prepare(" 
            SELECT o.id as order_id
            FROM orders o
            INNER JOIN order_items oi ON o.id = oi.order_id
            WHERE oi.product_id = ? 
            AND EXISTS (
                SELECT 1
                FROM customers c
                WHERE c.id = o.customer_id
                  AND (c.email = ? OR c.phone = ?)
            )
            AND o.status IN ('completed', 'delivered')
            LIMIT 1
        ");

        $stmt->execute([(int)$product_id, $email_or_phone, $email_or_phone]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ? (int)$result['order_id'] : null;
    }
}
?>

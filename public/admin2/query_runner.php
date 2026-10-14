<?php
$root_dir = dirname(dirname(__DIR__));
require_once($root_dir . '/config/config.php');
require_once($root_dir . '/includes/session_helper.php');
require_once($root_dir . '/includes/Database.php');

require_admin_login();

$db = new Database();

// Initialize variables
$query = '';
$result = null;
$error = null;
$affectedRows = 0;
$executionTime = 0;
$queryExecuted = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['query'])) {
    $query = trim($_POST['query']);
    
    if (!empty($query)) {
        $startTime = microtime(true);
        
        try {
            // Execute the query
            $stmt = $db->query($query);
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds
            $queryExecuted = true;
            
            // Determine query type
            $queryType = strtoupper(substr(ltrim($query), 0, 6));
            
            if (in_array($queryType, ['SELECT', 'SHOW', 'DESCRI', 'EXPLAI'])) {
                // For SELECT queries, fetch results
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $affectedRows = count($result);
            } else {
                // For UPDATE, INSERT, DELETE, etc., get affected rows
                $affectedRows = $stmt->rowCount();
                $result = null;
            }
            
        } catch (PDOException $e) {
            $error = $e->getMessage();
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
        }
    }
}

?>
<?php
$page_title = 'Query Runner';
require_once __DIR__ . '/includes/header.php';
?>
<style>
        /* Query Runner specific styles */
        .query-container {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        .query-textarea {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            resize: vertical;
            margin-bottom: 15px;
        }
        
        .query-textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .execute-btn {
            background-color: var(--primary-color);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .execute-btn:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        .clear-btn {
            background-color: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-left: 10px;
        }
        
        .clear-btn:hover {
            background-color: #5a6268;
        }
        
        .result-container {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-top: 25px;
        }
        
        .result-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .result-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: #333;
        }
        
        .result-info {
            display: flex;
            gap: 20px;
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .result-info-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .result-table {
            width: 100%;
            border-collapse: collapse;
            overflow-x: auto;
            display: block;
        }
        
        .result-table thead {
            background-color: var(--primary-color);
            color: white;
        }
        
        .result-table th,
        .result-table td {
            padding: 12px 15px;
            text-align: left;
            border: 1px solid #ddd;
        }
        
        .result-table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .result-table tbody tr:hover {
            background-color: rgba(255, 127, 0, 0.05);
        }
        
        .result-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .query-examples {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
            border-left: 4px solid var(--primary-color);
        }
        
        .query-examples h4 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .query-examples code {
            display: block;
            background: white;
            padding: 8px 12px;
            margin: 5px 0;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
            font-size: 0.85rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .query-examples code:hover {
            background-color: #e9ecef;
        }
        
        .no-results {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-size: 1.1rem;
        }
        
        .warning-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .warning-box i {
            color: #856404;
            font-size: 1.2rem;
        }
        
        .warning-box-content {
            flex: 1;
        }
        
        .warning-box-title {
            font-weight: 600;
            color: #856404;
            margin-bottom: 5px;
        }
        
        .warning-box-text {
            color: #856404;
            font-size: 0.9rem;
        }
    </style>

    <div class="container-fluid py-4">

        <div class="query-container">
            <!-- Warning Box -->
            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="warning-box-content">
                    <div class="warning-box-title">⚠️ Use With Caution</div>
                    <div class="warning-box-text">
                        This tool allows direct database access. Be careful with UPDATE, DELETE, and DROP queries. 
                        Always backup your data before making destructive changes.
                    </div>
                </div>
            </div>

            <h2>Execute SQL Query</h2>
            
            <form method="POST" action="">
                <textarea 
                    name="query" 
                    class="query-textarea" 
                    placeholder="Enter your SQL query here...&#10;Example: UPDATE products SET cost_price = price * 0.80 WHERE code = 700;"
                    required><?php echo htmlspecialchars($query); ?></textarea>
                
                <div>
                    <button type="submit" class="execute-btn">
                        <i class="fas fa-play"></i>
                        Execute Query
                    </button>
                    <button type="button" class="clear-btn" onclick="clearQuery()">
                        <i class="fas fa-eraser"></i>
                        Clear
                    </button>
                </div>
            </form>

            <!-- Query Examples -->
            <div class="query-examples">
                <h4><i class="fas fa-lightbulb"></i> Example Queries (Click to use):</h4>
                <code onclick="fillQuery(this.textContent)">SELECT * FROM products LIMIT 10;</code>
                <code onclick="fillQuery(this.textContent)">UPDATE products SET cost_price = price * 0.80 WHERE code = 700;</code>
                <code onclick="fillQuery(this.textContent)">SELECT code, name, price, cost_price FROM products WHERE code = 700;</code>
                <code onclick="fillQuery(this.textContent)">SHOW TABLES;</code>
                <code onclick="fillQuery(this.textContent)">DESCRIBE products;</code>
            </div>
        </div>

        <?php if ($queryExecuted): ?>
        <div class="result-container">
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-times-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>Query Error:</strong><br>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle" style="font-size: 1.5rem;"></i>
                    <div>
                        <strong>Query executed successfully!</strong>
                    </div>
                </div>
                
                <div class="result-header">
                    <h3 class="result-title">Query Results</h3>
                    <div class="result-info">
                        <div class="result-info-item">
                            <i class="fas fa-database"></i>
                            <strong>Rows:</strong> <?php echo $affectedRows; ?>
                        </div>
                        <div class="result-info-item">
                            <i class="fas fa-clock"></i>
                            <strong>Time:</strong> <?php echo $executionTime; ?> ms
                        </div>
                    </div>
                </div>

                <?php if ($result !== null && count($result) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="result-table">
                            <thead>
                                <tr>
                                    <?php foreach (array_keys($result[0]) as $column): ?>
                                        <th><?php echo htmlspecialchars($column); ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $row): ?>
                                    <tr>
                                        <?php foreach ($row as $value): ?>
                                            <td><?php echo htmlspecialchars($value ?? 'NULL'); ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php elseif ($result !== null): ?>
                    <div class="no-results">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                        <p>Query returned no results.</p>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle" style="font-size: 1.5rem;"></i>
                        <div>
                            <strong>Query Type:</strong> UPDATE/INSERT/DELETE<br>
                            <strong>Affected Rows:</strong> <?php echo $affectedRows; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function fillQuery(query) {
            document.querySelector('.query-textarea').value = query;
        }

        function clearQuery() {
            document.querySelector('.query-textarea').value = '';
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

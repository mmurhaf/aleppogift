<?php
require_once 'includes/uae_symbol_utils.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UAE Symbol Utilities Demo</title>
    <link rel="stylesheet" href="public/assets/css/style.css">
    <link rel="stylesheet" href="public/assets/css/ui-components.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
            line-height: 1.6;
        }
        .demo-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        .demo-item {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fafafa;
        }
        .price-example {
            font-size: 1.2rem;
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            margin: 10px 0;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="demo-container">
        <h1>UAE Symbol Utilities Demo</h1>
        
        <div class="demo-item">
            <h3>1. Basic Symbol Methods</h3>
            
            <div class="price-example">
                <strong>SVG Method:</strong> <?= getUAESymbolSVG() ?>1,299
            </div>
            <div class="code-block">
                &lt;?= getUAESymbolSVG() ?&gt;1,299
            </div>
            
            <div class="price-example">
                <strong>Font Method:</strong> <?= getUAESymbolFont() ?>1,299
            </div>
            <div class="code-block">
                &lt;?= getUAESymbolFont() ?&gt;1,299
            </div>
            
            <div class="price-example">
                <strong>Text Method:</strong> <?= getUAESymbolText() ?>1,299
            </div>
            <div class="code-block">
                &lt;?= getUAESymbolText() ?&gt;1,299
            </div>
        </div>
        
        <div class="demo-item">
            <h3>2. Formatted Price Functions</h3>
            
            <div class="price-example">
                <strong>SVG Price:</strong> <?= formatPriceAED(1299.50, 'svg', 2) ?>
            </div>
            <div class="code-block">
                &lt;?= formatPriceAED(1299.50, 'svg', 2) ?&gt;
            </div>
            
            <div class="price-example">
                <strong>Font Price:</strong> <?= formatPriceAED(1299, 'font') ?>
            </div>
            <div class="code-block">
                &lt;?= formatPriceAED(1299, 'font') ?&gt;
            </div>
            
            <div class="price-example">
                <strong>Text Price:</strong> <?= formatPriceAED(1299, 'text') ?>
            </div>
            <div class="code-block">
                &lt;?= formatPriceAED(1299, 'text') ?&gt;
            </div>
        </div>
        
        <div class="demo-item">
            <h3>3. Dual Currency Display</h3>
            
            <div class="price-example">
                <div class="product-price">
                    <?= formatPriceDual(1299.50) ?>
                </div>
            </div>
            <div class="code-block">
                &lt;div class="product-price"&gt;
                    &lt;?= formatPriceDual(1299.50) ?&gt;
                &lt;/div&gt;
            </div>
        </div>
        
        <div class="demo-item">
            <h3>4. Custom Styling Examples</h3>
            
            <div class="price-example">
                <strong>Large SVG:</strong> 
                <?= getUAESymbolSVG('uae-symbol', 'width: 1.5em; height: 1.5em; filter: brightness(0.2);') ?>1,299
            </div>
            <div class="code-block">
                &lt;?= getUAESymbolSVG('uae-symbol', 'width: 1.5em; height: 1.5em; filter: brightness(0.2);') ?&gt;1,299
            </div>
            
            <div class="price-example">
                <strong>Colored Font:</strong> 
                <span style="color: #007bff; font-weight: bold;">
                    <?= getUAESymbolFont('uae-symbol-font') ?>1,299
                </span>
            </div>
            <div class="code-block">
                &lt;span style="color: #007bff; font-weight: bold;"&gt;
                    &lt;?= getUAESymbolFont('uae-symbol-font') ?&gt;1,299
                &lt;/span&gt;
            </div>
        </div>
        
        <div class="demo-item">
            <h3>5. JavaScript Integration</h3>
            
            <div class="price-example">
                <button onclick="updatePrice()">Update Price with JS</button>
                <div id="dynamic-price" style="margin-top: 10px; font-weight: bold;"></div>
            </div>
            
            <div class="code-block">
// JavaScript code:
const uaeSymbol = <?= getUAESymbolJS() ?>;
document.getElementById('dynamic-price').innerHTML = uaeSymbol + '2,499';
            </div>
        </div>
        
        <div class="demo-item">
            <h3>6. Real Product Example</h3>
            
            <div style="border: 1px solid #ddd; padding: 15px; border-radius: 8px; background: white;">
                <h4>Premium Gift Box</h4>
                <div class="product-price">
                    <span class="price-current">
                        <?= getUAESymbolSVG() ?><?= number_format(1299) ?>
                    </span>
                    <span class="price-usd">$<?= number_format(1299/3.68, 2) ?></span>
                </div>
            </div>
            
            <div class="code-block">
&lt;div class="product-price"&gt;
    &lt;span class="price-current"&gt;
        &lt;?= getUAESymbolSVG() ?&gt;&lt;?= number_format(1299) ?&gt;
    &lt;/span&gt;
    &lt;span class="price-usd"&gt;$&lt;?= number_format(1299/3.68, 2) ?&gt;&lt;/span&gt;
&lt;/div&gt;
            </div>
        </div>
    </div>
    
    <script>
        function updatePrice() {
            const uaeSymbol = <?= getUAESymbolJS() ?>;
            const randomPrice = Math.floor(Math.random() * 5000) + 500;
            document.getElementById('dynamic-price').innerHTML = uaeSymbol + randomPrice.toLocaleString();
        }
        
        // Initial load
        updatePrice();
    </script>
</body>
</html>

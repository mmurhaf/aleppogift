<div class="cart-item slide-up" data-product-id="<?= (int)$product['id'] ?>" data-key="<?= (int)$key ?>">
    <div class="item-content">
        <div class="item-image">
            <img src="<?php echo str_replace("../", "", $display_image ?: 'uploads/default-product.jpg'); ?>"
                 alt="<?php echo htmlspecialchars(loc_field($product, 'name')); ?>"
                 <?php if (!empty($variation) && !empty($variation['variant_image'])): ?>
                 class="is-variant"
                 title="<?= te('cart.variant_image_title') ?>"
                 <?php endif; ?>>
        </div>
        <div class="item-details">
            <?php if (!empty($product['brand_name'])): ?>
                <a href="products.php?brand=<?= (int)$product['brand_id'] ?>" class="brand-link">
                    <?php echo htmlspecialchars($product['brand_name']); ?>
                </a>
            <?php endif; ?>
            <h5>
                <a href="product.php?id=<?= (int)$product['id'] ?>">
                    <?php echo htmlspecialchars(loc_field($product, 'name')); ?>
                </a>
            </h5>
            <?php if ($variationText): ?>
                <p class="item-variation">
                    <i class="fas fa-tags me-1"></i><?php echo htmlspecialchars($variationText); ?>
                </p>
            <?php endif; ?>
            <?php if ($role === 'bundle'): ?>
                <p class="item-variation"><?php if ((int)($item['required_quantity'] ?? 0) > 0): ?><?= te('cart.bundle_item_required', ['count' => (int)$item['required_quantity']]) ?><?php else: ?><?= te('cart.bundle_item') ?><?php endif; ?></p>
            <?php elseif ($role === 'extra' || $role === 'replacement'): ?>
                <p class="item-variation"><?= te($role === 'replacement' ? 'cart.replacement_item' : 'cart.added_item') ?></p>
            <?php endif; ?>
            <?php
                $chargedUnit = (int)$item['quantity'] > 0 ? $lineTotal / (int)$item['quantity'] : (float)$price;
                $listUnit = isset($priced['list_unit']) ? (float)$priced['list_unit'] : (float)$price;
            ?>
            <div class="item-price" data-unit-aed="<?= htmlspecialchars((string)$chargedUnit) ?>">
                <?php if ($listUnit > $chargedUnit + 0.009): ?>
                    <s class="text-muted"><?php echo formatAedUsd($listUnit); ?></s>
                <?php endif; ?>
                <?= te('cart.price_each', ['price' => formatAedUsd($chargedUnit)]) ?>
            </div>
        </div>
        <div class="item-controls">
            <div class="quantity-control">
                <button type="button" class="update-qty" data-key="<?= (int)$key ?>" data-id="<?= (int)$key ?>" data-action="decrease" aria-label="<?= te('cart.decrease_qty') ?>">
                    <i class="fas fa-minus"></i>
                </button>
                <input type="number" class="quantity-display quantity-input" min="1" max="9999" step="1" inputmode="numeric" autocomplete="off"
                       value="<?= (int)$item['quantity'] ?>" data-qty="<?= (int)$item['quantity'] ?>" data-key="<?= (int)$key ?>"
                       aria-label="<?= te('cart.quantity') ?>">
                <button type="button" class="update-qty" data-key="<?= (int)$key ?>" data-id="<?= (int)$key ?>" data-action="increase" aria-label="<?= te('cart.increase_qty') ?>">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
            <div class="item-total">
                <?php echo formatAedUsd($lineTotal); ?>
            </div>
            <button type="button" class="remove-btn remove-item" data-key="<?= (int)$key ?>" data-id="<?= (int)$key ?>" title="<?= te('cart.remove_from_cart') ?>">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    </div>
</div>

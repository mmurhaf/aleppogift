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
            <div class="item-price" data-unit-aed="<?= htmlspecialchars((string)$price) ?>">
                <?php if ($lineTotal + 0.001 < ($price * $item['quantity'])): ?>
                    <s class="text-muted"><?php echo formatAedUsd($price); ?></s>
                <?php endif; ?>
                <?= te('cart.price_each', ['price' => formatAedUsd($item['quantity'] > 0 ? $lineTotal / $item['quantity'] : $price)]) ?>
            </div>
        </div>
        <div class="item-controls">
            <div class="quantity-control">
                <button type="button" class="update-qty" data-key="<?= (int)$key ?>" data-id="<?= (int)$key ?>" data-action="decrease" aria-label="<?= te('cart.decrease_qty') ?>">
                    <i class="fas fa-minus"></i>
                </button>
                <span class="quantity-display"><?= (int)$item['quantity'] ?></span>
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

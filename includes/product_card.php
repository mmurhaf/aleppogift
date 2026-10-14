<?php
/**
 * Storefront product tile. Expects $p (product row with main_image, brand/category names)
 * and $show_prices in scope.
 */
$cardId = (int)$p['id'];
$cardName = loc_field($p, 'name');
$cardBrand = loc_field($p, 'brand_name');
$cardUrl = 'product.php?id=' . $cardId;
$cardImage = str_replace('../', '', (string)($p['main_image'] ?? ''));
if ($cardImage === '' || (!preg_match('#^(https?:)?//#i', $cardImage) && !is_file(__DIR__ . '/../public/' . rawurldecode(ltrim($cardImage, '/'))))) {
    $cardImage = 'assets/images/no-image.png';
}
$cardShareUrl = store_url($cardUrl);
$cardWhatsapp = 'https://wa.me/971561125320?text=' . rawurlencode(
    t('catalog.whatsapp_interest', ['name' => $p['name_en'], 'url' => 'https://aleppogift.com/' . $cardUrl])
);
$cardInStock = !array_key_exists('stock', $p) || (int)$p['stock'] > 0;
?>
<article class="product-card modern-product-card tg-card">
    <div class="product-image">
        <a href="<?= $cardUrl ?>" class="product-image-link" aria-label="<?= te('catalog.view_details', ['name' => $cardName]) ?>">
            <img src="<?= htmlspecialchars($cardImage) ?>"
                 alt="<?= te('catalog.img_alt', ['name' => $p['name_en'], 'brand' => $p['brand_name_en'] ?? t('catalog.alt_brand_fallback'), 'category' => $p['category_name_en'] ?? t('catalog.alt_category_fallback')]) ?>"
                 loading="lazy"
                 decoding="async"
                 width="400"
                 height="400">
        </a>

        <?= product_card_badges($p) ?>

        <button type="button"
                class="card-save btn-wishlist"
                data-id="<?= $cardId ?>"
                title="<?= te('catalog.save_wishlist') ?>"
                aria-label="<?= te('catalog.save_item_wishlist', ['name' => $cardName]) ?>">
            <i class="far fa-heart"></i>
        </button>

        <div class="card-float-actions">
            <?php if ($cardInStock): ?>
            <form class="add-to-cart-form" method="post" action="ajax/add_to_cart.php">
                <input type="hidden" name="product_id" value="<?= $cardId ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit"
                        class="card-float-btn card-float-cart add-to-cart"
                        data-id="<?= $cardId ?>"
                        data-name="<?= htmlspecialchars($p['name_en']) ?>"
                        title="<?= te('add_cart') ?>"
                        aria-label="<?= te('catalog.add_item_cart', ['name' => $cardName]) ?>">
                    <i class="fas fa-shopping-bag" aria-hidden="true"></i>
                </button>
            </form>
            <?php endif; ?>
            <a class="card-float-btn card-float-whatsapp"
               href="<?= htmlspecialchars($cardWhatsapp) ?>"
               target="_blank"
               rel="noopener noreferrer"
               title="WhatsApp"
               aria-label="<?= te('catalog.ask_item_whatsapp', ['name' => $cardName]) ?>">
                <i class="fab fa-whatsapp" aria-hidden="true"></i>
            </a>
            <button type="button"
                    class="card-float-btn card-float-share"
                    title="<?= te('catalog.share') ?>"
                    aria-label="<?= te('catalog.share_item', ['name' => $cardName]) ?>"
                    onclick="shareProduct(<?= htmlspecialchars(json_encode($p['name_en']), ENT_QUOTES, 'UTF-8') ?>, <?= htmlspecialchars(json_encode(number_format((float)$p['price'], 0)), ENT_QUOTES, 'UTF-8') ?>, <?= $cardId ?>, <?= htmlspecialchars(json_encode($cardShareUrl), ENT_QUOTES, 'UTF-8') ?>)">
                <i class="fas fa-share-alt" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="product-card-content">
        <?php if ($cardBrand !== '' && !is_placeholder_name($p['brand_name_en'] ?? '')): ?>
            <a class="tg-card-brand" href="<?= htmlspecialchars(store_url('products.php?brand=' . (int)$p['brand_id'])) ?>"><?= htmlspecialchars($cardBrand) ?></a>
        <?php elseif (loc_field($p, 'category_name') !== '' && !is_placeholder_name($p['category_name_en'] ?? '')): ?>
            <a class="tg-card-brand" href="<?= htmlspecialchars(store_url('products.php?category=' . (int)$p['category_id'])) ?>"><?= htmlspecialchars(loc_field($p, 'category_name')) ?></a>
        <?php endif; ?>
        <h3 class="product-name">
            <a href="<?= $cardUrl ?>" class="product-link-title"><?= htmlspecialchars($cardName) ?></a>
        </h3>
        <?php if (store_lang() === 'en' && !is_placeholder_arabic_name($p['name_ar'] ?? '')): ?>
            <p class="product-name-ar" dir="rtl"><?= htmlspecialchars($p['name_ar']) ?></p>
        <?php endif; ?>
        <?php if ($show_prices): ?>
            <div class="product-price">
                <?= format_store_price_pair((float)$p['price'], 'price-current', selected_store_currency()['code'] === 'AED' ? 0 : 1) ?>
            </div>
        <?php endif; ?>
    </div>
</article>

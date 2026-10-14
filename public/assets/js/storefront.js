/**
 * Single cart, toast, and wishlist behavior for the storefront.
 * Installed after other scripts so it replaces their competing copies.
 */
(function () {
    var WISHLIST_KEY = 'aleppogift_wishlist';

    var __ = function (k, p, fallback) {
        var s = (window.AG_I18N && window.AG_I18N[k]) || fallback || k;
        if (p) { for (var n in p) { s = s.split('{' + n + '}').join(p[n]); } }
        return s;
    };

    function usdFromAed(amount) {
        var rate = typeof window.AED_TO_USD_RATE === 'number' ? window.AED_TO_USD_RATE : 0.2723;
        return (Number(amount) * rate).toFixed(2);
    }

    function readWishlist() {
        try {
            var parsed = JSON.parse(localStorage.getItem(WISHLIST_KEY) || '[]');
            return Array.isArray(parsed) ? parsed.map(String) : [];
        } catch (error) {
            return [];
        }
    }

    function writeWishlist(ids) {
        localStorage.setItem(WISHLIST_KEY, JSON.stringify(ids));
    }

    function paintWishlistButton(button, saved) {
        button.classList.toggle('active', saved);
        button.setAttribute('aria-pressed', saved ? 'true' : 'false');
        var icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('fas', saved);
            icon.classList.toggle('far', !saved);
        }
        if (button.childNodes.length && button.textContent.trim() !== '') {
            var label = saved ? __('saved_to_wishlist_label', null, 'Saved to Wishlist') : __('add_to_wishlist', null, 'Add to Wishlist');
            button.childNodes.forEach(function (node) {
                if (node.nodeType === Node.TEXT_NODE && node.textContent.trim() !== '') {
                    node.textContent = ' ' + label;
                }
            });
        }
    }

    function applyWishlistState() {
        document.querySelectorAll('.btn-wishlist.is-saved').forEach(function (button) {
            paintWishlistButton(button, true);
        });
    }

    function showToast(message, type) {
        type = type || 'info';
        var toast = document.createElement('div');
        toast.className = 'toast-notification toast-' + type;
        toast.setAttribute('role', 'status');
        toast.style.cssText = 'position:fixed;top:1rem;right:1rem;z-index:2000;background:#1f2937;color:#fff;padding:0.85rem 1rem;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.18);max-width:320px;';
        if (type === 'success') toast.style.background = '#0f7a45';
        if (type === 'error') toast.style.background = '#9b2335';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(function () {
            if (toast.parentNode) toast.parentNode.removeChild(toast);
        }, 4000);
    }

    function updateCartCount(count) {
        var countInt = parseInt(count, 10) || 0;
        ['cart-count', 'cart-count-mobile', 'cart-count-toggle'].forEach(function (id) {
            var badge = document.getElementById(id);
            if (!badge) return;
            badge.textContent = String(countInt);
            badge.classList.toggle('is-zero', countInt < 1);
            badge.style.display = '';
            badge.setAttribute('aria-label', __('items_in_cart_aria', { count: countInt }, '{count} items in cart'));
        });
    }

    function cartEndpoint(file) {
        var path = window.location.pathname || '/';
        if (path.indexOf('/ajax/') !== -1) {
            return file;
        }
        return 'ajax/' + file;
    }

    function loadCartHtml(targetSelector) {
        var target = document.querySelector(targetSelector);
        if (!target) return Promise.resolve();
        target.innerHTML = '<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">' + __('loading', null, 'Loading...') + '</span></div></div>';
        return fetch(cartEndpoint('cart_preview.php') + '?t=' + Date.now(), { credentials: 'same-origin' })
            .then(function (response) { return response.text(); })
            .then(function (html) { target.innerHTML = html; })
            .catch(function () {
                target.innerHTML = '<div class="text-center p-4"><p class="text-muted mb-0">' + __('unable_to_load_cart', null, 'Unable to load cart') + '</p></div>';
            });
    }

    function showCartPreview() {
        var preview = document.getElementById('cartPreview');
        if (!preview) return;
        if (window.cartPreviewTimeout) {
            clearTimeout(window.cartPreviewTimeout);
            window.cartPreviewTimeout = null;
        }
        preview.style.display = 'block';
        loadCartHtml('#cart-items-preview');
    }

    function hideCartPreview() {
        var preview = document.getElementById('cartPreview');
        if (preview) preview.style.display = 'none';
    }

    function openCartAfterAdd() {
        if (window.matchMedia('(max-width: 991.98px)').matches) {
            var drawer = document.getElementById('cartOffcanvas');
            if (drawer && window.bootstrap && bootstrap.Offcanvas) {
                bootstrap.Offcanvas.getOrCreateInstance(drawer).show();
                return;
            }
        }
        showCartPreview();
    }

    function removeCartItem(button) {
        if (!button || button.dataset.removing === '1') return;
        var productId = button.getAttribute('data-id') || '';
        if (!productId) return;
        button.dataset.removing = '1';
        var original = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin" aria-hidden="true"></i>';

        var body = new URLSearchParams();
        var cartKey = button.getAttribute('data-key');
        if (cartKey !== null && cartKey !== '') {
            body.set('cart_key', cartKey);
        }
        body.set('product_id', productId);
        fetch(cartEndpoint('remove_from_cart.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || __('could_not_remove_item', null, 'Could not remove item'));
                updateCartCount(data.count);
                var jobs = [];
                if (document.getElementById('cart-items-preview')) jobs.push(loadCartHtml('#cart-items-preview'));
                if (document.getElementById('cart-items-offcanvas')) jobs.push(loadCartHtml('#cart-items-offcanvas'));
                return Promise.all(jobs);
            })
            .catch(function (error) {
                button.innerHTML = original;
                button.disabled = false;
                delete button.dataset.removing;
                showToast(error.message || __('could_not_remove_item', null, 'Could not remove item'), 'error');
            });
    }

    function toggleCartPreview() {
        var preview = document.getElementById('cartPreview');
        if (!preview) return;
        if (preview.style.display === 'block') {
            hideCartPreview();
        } else {
            showCartPreview();
        }
    }

    function addToCart(productId, quantity, button) {
        var qty = 1;
        if (typeof quantity === 'number' || (typeof quantity === 'string' && quantity !== '' && !isNaN(quantity))) {
            qty = parseInt(quantity, 10) || 1;
        }
        if (!productId || parseInt(productId, 10) < 1) {
            showToast(__('invalid_product', null, 'Invalid product'), 'error');
            return;
        }
        if (button && button.jquery) button = button[0];

        var originalHtml = button ? button.innerHTML : '';
        var compact = button && button.classList && button.classList.contains('card-float-btn');
        if (button) {
            button.disabled = true;
            button.innerHTML = compact
                ? '<i class="fas fa-spinner fa-spin"></i>'
                : '<i class="fas fa-spinner fa-spin me-2"></i>' + __('adding', null, 'Adding...');
        }

        var body = new URLSearchParams();
        body.set('product_id', productId);
        body.set('quantity', String(qty));
        var form = button && button.closest ? button.closest('form') : null;
        if (form) {
            var variation = form.querySelector('[name="variation_id"], [name="variation"]');
            if (variation && variation.value) body.set('variation_id', variation.value);
        }

        fetch(cartEndpoint('add_to_cart.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            credentials: 'same-origin',
            body: body.toString()
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (!data.success) throw new Error(data.message || __('failed_to_add', null, 'Failed to add to cart'));
                updateCartCount(data.count != null ? data.count : data.cart_count);
                openCartAfterAdd();
                if (button) {
                    button.innerHTML = compact
                        ? '<i class="fas fa-check"></i>'
                        : '<i class="fas fa-check me-2"></i>' + __('added', null, 'Added');
                    setTimeout(function () {
                        button.innerHTML = originalHtml;
                        button.disabled = false;
                    }, 1600);
                }
            })
            .catch(function (error) {
                showToast(error.message || __('could_not_add', null, 'Could not add to cart'), 'error');
                if (button) {
                    button.innerHTML = originalHtml;
                    button.disabled = false;
                }
            });
    }

    function toggleWishlist(button) {
        var id = String(button.dataset.id || '');
        if (!id) return;
        var body = new URLSearchParams();
        body.set('product_id', id);
        fetch('ajax/wishlist_toggle.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
            body: body.toString()
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                var saved = !!(data && data.saved);
                document.querySelectorAll('.btn-wishlist[data-id="' + id + '"]').forEach(function (match) {
                    paintWishlistButton(match, saved);
                });
                showToast(saved ? __('saved_to_wishlist', null, 'Saved to wishlist') : __('removed_from_wishlist', null, 'Removed from wishlist'), saved ? 'success' : 'info');
            })
            .catch(function () {
                showToast(__('wishlist_update_failed', null, 'Could not update the wishlist'), 'error');
            });
    }

    function bindNewsletter() {
        var form = document.getElementById('footer-subscribe-form');
        var status = document.getElementById('footer-subscribe-status');
        if (!form || form.dataset.bound === '1') return;
        form.dataset.bound = '1';
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            var email = (form.querySelector('[name="email"]') || {}).value || '';
            var body = new URLSearchParams();
            body.set('email', email.trim());
            fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'fetch' },
                body: body.toString()
            })
                .then(function (response) { return response.json().then(function (data) { return { ok: response.ok, data: data }; }); })
                .then(function (result) {
                    if (status) {
                        status.hidden = false;
                        status.textContent = (result.data && result.data.message) || (result.ok ? __('subscribed', null, 'You are subscribed.') : __('subscribe_failed', null, 'Could not subscribe.'));
                    }
                    if (result.ok) form.reset();
                })
                .catch(function () {
                    if (status) {
                        status.hidden = false;
                        status.textContent = __('subscribe_failed_retry', null, 'Could not subscribe. Please try again.');
                    }
                });
        });
    }

    function bindHeaderChrome() {
        var header = document.getElementById('tg-header');
        if (!header || header.dataset.bound === '1') return;
        header.dataset.bound = '1';

        var onScroll = function () {
            header.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();

        var searchBar = document.getElementById('search-bar');
        if (!searchBar) return;
        var searchInput = searchBar.querySelector('input[name="search"]');
        var openSearch = function (event) {
            event.preventDefault();
            searchBar.classList.remove('d-none');
            searchBar.style.display = 'block';
            if (searchInput) searchInput.focus();
        };
        var closeSearch = function () {
            searchBar.classList.add('d-none');
            searchBar.style.display = 'none';
        };
        ['search-toggle', 'search-toggle-mobile'].forEach(function (id) {
            var button = document.getElementById(id);
            if (button) button.addEventListener('click', openSearch);
        });
        var closeButton = document.getElementById('search-close');
        if (closeButton) closeButton.addEventListener('click', function (event) {
            event.preventDefault();
            closeSearch();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeSearch();
        });
    }

    function bindCarousels() {
        document.querySelectorAll('[data-tg-carousel]').forEach(function (carousel) {
            if (carousel.dataset.bound === '1') return;
            carousel.dataset.bound = '1';
            var track = carousel.querySelector('[data-tg-track]');
            var prev = carousel.querySelector('[data-tg-prev]');
            var next = carousel.querySelector('[data-tg-next]');
            if (!track) return;
            var rtl = getComputedStyle(track).direction === 'rtl';

            var step = function () {
                var item = track.firstElementChild;
                var gap = parseFloat(getComputedStyle(track).columnGap) || 0;
                var itemWidth = item ? item.getBoundingClientRect().width + gap : track.clientWidth;
                return Math.max(itemWidth, Math.floor(track.clientWidth / itemWidth) * itemWidth);
            };
            var update = function () {
                var max = track.scrollWidth - track.clientWidth - 2;
                var pos = Math.abs(track.scrollLeft);
                if (prev) prev.disabled = pos <= 2;
                if (next) next.disabled = pos >= max;
                carousel.classList.toggle('is-static', max <= 0);
            };
            var scrollBy = function (direction) {
                track.scrollBy({ left: direction * step() * (rtl ? -1 : 1), behavior: 'smooth' });
            };
            if (prev) prev.addEventListener('click', function () { scrollBy(-1); });
            if (next) next.addEventListener('click', function () { scrollBy(1); });
            track.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        });
    }

    function bindFooterColumns() {
        var columns = document.querySelectorAll('.tg-footer-col');
        if (!columns.length || !window.matchMedia) return;
        var mobile = window.matchMedia('(max-width: 767.98px)');
        var sync = function () {
            columns.forEach(function (column) { column.open = !mobile.matches; });
        };
        if (columns[0].dataset.bound === '1') return;
        columns[0].dataset.bound = '1';
        sync();
        if (mobile.addEventListener) mobile.addEventListener('change', sync);
    }

    function installStorefront() {
        window.showToast = showToast;
        window.updateCartCount = updateCartCount;
        window.addToCart = addToCart;
        window.showCartPreview = showCartPreview;
        window.hideCartPreview = hideCartPreview;
        window.toggleCartPreview = toggleCartPreview;
        window.loadCartPreview = function () { return loadCartHtml('#cart-items-preview'); };
        window.loadCartOffcanvas = function () { return loadCartHtml('#cart-items-offcanvas'); };
        window.usdFromAed = usdFromAed;

        applyWishlistState();
        bindNewsletter();
        bindHeaderChrome();
        bindCarousels();
        bindFooterColumns();

        if (!window.__storefrontClickBound) {
            window.__storefrontClickBound = true;
            document.addEventListener('click', function (event) {
                var wish = event.target.closest ? event.target.closest('.btn-wishlist') : null;
                if (wish) {
                    event.preventDefault();
                    toggleWishlist(wish);
                    return;
                }

                var removeBtn = event.target.closest ? event.target.closest('.remove-item') : null;
                if (removeBtn && !removeBtn.closest('.cart-page')) {
                    event.preventDefault();
                    event.stopPropagation();
                    removeCartItem(removeBtn);
                    return;
                }

                var preview = document.getElementById('cartPreview');
                var desktopCart = document.getElementById('cart-toggle-desktop');
                if (preview && preview.style.display === 'block') {
                    var insidePreview = preview.contains(event.target);
                    var onCartButton = desktopCart && desktopCart.contains(event.target);
                    var adding = event.target.closest && event.target.closest('.add-to-cart, .card-float-cart');
                    if (!insidePreview && !onCartButton && !adding) hideCartPreview();
                }
            });
        }

        var desktopCart = document.getElementById('cart-toggle-desktop');
        if (desktopCart && desktopCart.dataset.bound !== '1') {
            desktopCart.dataset.bound = '1';
            desktopCart.addEventListener('click', function (event) {
                event.preventDefault();
                toggleCartPreview();
            });
        }

        var offcanvas = document.getElementById('cartOffcanvas');
        if (offcanvas && offcanvas.dataset.bound !== '1') {
            offcanvas.dataset.bound = '1';
            offcanvas.addEventListener('show.bs.offcanvas', function () {
                loadCartHtml('#cart-items-offcanvas');
            });
        }
    }

    window.__installStorefront = installStorefront;

    function bootStorefront() {
        setTimeout(installStorefront, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootStorefront);
    } else {
        bootStorefront();
    }
    window.addEventListener('load', installStorefront);
})();

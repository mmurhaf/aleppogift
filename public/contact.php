<?php
// Set proper headers and output buffering
header('Content-Type: text/html; charset=UTF-8');
ob_start();

// Use the secure bootstrap instead of direct config loading
require_once(__DIR__ . '/../includes/bootstrap.php');

$db = new Database();
?>

<!DOCTYPE html>
<html lang="<?= htmlspecialchars(store_lang_html()) ?>" dir="<?= store_lang_dir() ?>">
<head>
    <?php require_once(__DIR__ . '/../includes/google_tag.php'); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= te('pages.contact.meta_title') ?></title>
    <meta name="description" content="<?= te('pages.contact.meta_description') ?>">
    <meta name="keywords" content="contact AleppoGift, tableware UAE, cups and glasses Dubai, trays and vases UAE, home accessories contact">
    <meta name="author" content="Aleppo Gift">
    <meta name="robots" content="index, follow">
    <?= seo_lang_links('https://aleppogift.com/contact.php') ?>
    <meta property="og:title" content="<?= te('pages.contact.meta_title') ?>">
    <meta property="og:description" content="<?= te('pages.contact.og_description') ?>">
    <meta property="og:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    <meta property="og:url" content="<?= htmlspecialchars(seo_canonical_url('https://aleppogift.com/contact.php')) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Aleppo Gift">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= te('pages.contact.meta_title') ?>">
    <meta name="twitter:description" content="<?= te('pages.contact.twitter_description') ?>">
    <meta name="twitter:image" content="https://aleppogift.com/assets/images/og-image.jpg">
    <?php require __DIR__ . '/../includes/storefront_assets.php'; ?>

    <style>
        :root {
            --contact-accent: #d4840e;
            --contact-accent-deep: #a85f0a;
            --contact-ink: #2c1505;
            --contact-muted: #7a5030;
            --contact-bg-soft: #fff8ee;
            --contact-border: rgba(200, 140, 40, 0.22);
            --contact-shadow: 0 10px 28px rgba(22, 10, 2, 0.08);
        }

        .contact-page {
            margin-top: 1.5rem;
            margin-bottom: 3rem;
        }

        .contact-hero {
            text-align: center;
            padding: clamp(1.4rem, 3vw, 2.2rem);
            border-radius: 16px;
            background: linear-gradient(140deg, #fff5df 0%, #fff1d4 52%, #fbe5bc 100%);
            border: 1px solid var(--contact-border);
            box-shadow: var(--contact-shadow);
            margin-bottom: 1.5rem;
        }

        .contact-hero h1 {
            font-family: "Playfair Display", Georgia, serif;
            color: var(--contact-ink);
            margin-bottom: 0.55rem;
            font-size: clamp(1.65rem, 2.8vw, 2.35rem);
        }

        .contact-hero p {
            margin: 0;
            color: var(--contact-muted);
            font-size: clamp(0.95rem, 1.6vw, 1.05rem);
        }

        .contact-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            margin-bottom: 1.35rem;
        }

        .contact-card {
            height: 100%;
            border-radius: 14px;
            border: 1px solid var(--contact-border);
            background: #fff;
            box-shadow: 0 6px 20px rgba(12, 5, 0, 0.06);
            padding: 1.1rem 1rem;
            display: flex;
            align-items: flex-start;
            gap: 0.85rem;
        }

        .contact-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            flex: 0 0 44px;
            color: #fff;
            background: linear-gradient(140deg, var(--contact-accent) 0%, var(--contact-accent-deep) 100%);
            box-shadow: 0 8px 16px rgba(168, 95, 10, 0.3);
        }

        .contact-card h3 {
            margin: 0 0 0.3rem;
            color: var(--contact-ink);
            font-size: 1.02rem;
            font-weight: 700;
        }

        .contact-card p {
            margin: 0;
            color: var(--contact-muted);
            font-size: 0.95rem;
            line-height: 1.55;
        }

        .contact-card a {
            color: var(--contact-accent-deep);
            text-decoration: none;
            font-weight: 600;
        }

        .contact-card a:hover {
            color: var(--contact-accent);
            text-decoration: underline;
        }

        .social-section {
            margin-top: 0.5rem;
            border-radius: 16px;
            border: 1px solid var(--contact-border);
            background: #fff;
            box-shadow: var(--contact-shadow);
            padding: clamp(1rem, 2.5vw, 1.8rem);
            text-align: center;
        }

        .social-section h2 {
            color: var(--contact-ink);
            margin-bottom: 0.35rem;
            font-size: 1.35rem;
            font-family: "Playfair Display", Georgia, serif;
        }

        .social-section p {
            color: var(--contact-muted);
            margin-bottom: 1rem;
        }

        .social-icons {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .social-icons a {
            min-width: 130px;
            border-radius: 999px;
            border: 1px solid var(--contact-border);
            padding: 0.6rem 0.95rem;
            text-decoration: none;
            color: var(--contact-ink);
            background: var(--contact-bg-soft);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-weight: 600;
            transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease;
        }

        .social-icons a:hover {
            transform: translateY(-2px);
            background: #fff2d9;
            border-color: rgba(212, 132, 14, 0.35);
        }

        .back-home {
            margin-top: 1.2rem;
            text-align: center;
        }

        .back-home a {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 999px;
            background: var(--contact-accent-deep);
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            padding: 0.65rem 1.2rem;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .back-home a:hover {
            background: var(--contact-accent);
            transform: translateY(-1px);
        }

        @media (max-width: 767.98px) {
            .contact-grid {
                grid-template-columns: 1fr;
            }

            .social-icons a {
                min-width: 115px;
            }
        }
    </style>
</head>
<body>

    <?php require_once(__DIR__ . '/../includes/header.php'); ?>
    <div class="container store-main">
        <main class="contact-page" aria-label="<?= te('pages.contact.main_aria') ?>">
            <header class="page-hero">
                <p class="page-kicker"><?= te('contact') ?></p>
                <h1><?= te('pages.contact.heading') ?></h1>
                <p class="page-lead"><?= te('pages.contact.lead') ?></p>
            </header>

            <section class="contact-grid" aria-label="<?= te('pages.contact.details_aria') ?>">
                <article class="contact-card">
                    <div class="contact-card-icon" aria-hidden="true"><i class="fas fa-envelope"></i></div>
                    <div>
                        <h3><?= te('pages.contact.email') ?></h3>
                        <p><a href="mailto:info@aleppogift.com">info@aleppogift.com</a></p>
                    </div>
                </article>

                <article class="contact-card">
                    <div class="contact-card-icon" aria-hidden="true"><i class="fas fa-phone"></i></div>
                    <div>
                        <h3><?= te('pages.contact.phone') ?></h3>
                        <p><a href="tel:+971561125320">+971 56 112 5320</a></p>
                    </div>
                </article>

                <article class="contact-card">
                    <div class="contact-card-icon" aria-hidden="true"><i class="fas fa-map-marker-alt"></i></div>
                    <div>
                        <h3><?= te('pages.contact.address') ?></h3>
                        <p><?= te('pages.contact.address_value') ?></p>
                    </div>
                </article>

                <article class="contact-card">
                    <div class="contact-card-icon" aria-hidden="true"><i class="fas fa-clock"></i></div>
                    <div>
                        <h3><?= te('pages.contact.hours') ?></h3>
                        <p><?= te('pages.contact.hours_value') ?></p>
                    </div>
                </article>
            </section>

            <section class="social-section" aria-label="<?= te('pages.contact.social_aria') ?>">
                <h2><?= te('pages.contact.social_heading') ?></h2>
                <p><?= te('pages.contact.social_text') ?></p>
                <div class="social-icons">
                    <a href="https://www.facebook.com/dubai.gift.2025/" target="_blank" rel="noopener" aria-label="Facebook">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="https://www.instagram.com/best_deal_in_uae/" target="_blank" rel="noopener" aria-label="Instagram">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                        <span>Instagram</span>
                    </a>
                    <a href="https://www.tiktok.com/@aleppogift" target="_blank" rel="noopener" aria-label="TikTok">
                        <i class="fab fa-tiktok" aria-hidden="true"></i>
                        <span>TikTok</span>
                    </a>
                    <a href="https://www.pinterest.com/Krmeencom" target="_blank" rel="noopener" aria-label="Pinterest">
                        <i class="fab fa-pinterest" aria-hidden="true"></i>
                        <span>Pinterest</span>
                    </a>
                </div>
            </section>

            <section class="contact-form-card" aria-label="<?= te('footer.contact_form') ?>">
                <h2><?= te('pages.contact.form_heading') ?></h2>
                <p class="page-lead" style="margin-bottom: 1rem;"><?= te('pages.contact.form_lead') ?></p>
                <form id="contactForm">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="firstName" class="form-label"><?= te('pages.contact.first_name') ?></label>
                            <input type="text" class="form-control" id="firstName" name="firstName" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="lastName" class="form-label"><?= te('pages.contact.last_name') ?></label>
                            <input type="text" class="form-control" id="lastName" name="lastName" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label"><?= te('pages.contact.email') ?></label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="phone" class="form-label"><?= te('pages.contact.phone') ?></label>
                        <input type="tel" class="form-control" id="phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="subject" class="form-label"><?= te('pages.contact.subject') ?></label>
                        <select class="form-select" id="subject" name="subject" required>
                            <option value=""><?= te('pages.contact.subject_choose') ?></option>
                            <option value="general"><?= te('pages.contact.subject_general') ?></option>
                            <option value="order"><?= te('pages.contact.subject_order') ?></option>
                            <option value="product"><?= te('pages.contact.subject_product') ?></option>
                            <option value="shipping"><?= te('pages.contact.subject_shipping') ?></option>
                            <option value="return"><?= te('pages.contact.subject_return') ?></option>
                            <option value="complaint"><?= te('pages.contact.subject_complaint') ?></option>
                            <option value="other"><?= te('pages.contact.subject_other') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="message" class="form-label"><?= te('pages.contact.message') ?></label>
                        <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" id="contact-submit"><?= te('pages.contact.send') ?></button>
                    <p class="mt-3 mb-0" id="contact-status" role="status"></p>
                </form>
            </section>

            <div class="back-home">
                <a href="<?= htmlspecialchars(store_url()) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i> <?= te('pages.contact.back_home') ?></a>
            </div>
        </main>
    </div>

    <?php require_once(__DIR__ . '/../includes/footer.php'); ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var contactMessages = <?= json_encode([
        'sending' => t('pages.contact.sending'),
        'sent' => t('pages.contact.sent'),
        'failed' => t('pages.contact.send_failed'),
        'failedFallback' => t('pages.contact.send_failed_fallback'),
    ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    document.getElementById('contactForm').addEventListener('submit', async function (event) {
        event.preventDefault();
        var status = document.getElementById('contact-status');
        var button = document.getElementById('contact-submit');
        button.disabled = true;
        status.textContent = contactMessages.sending;
        try {
            var response = await fetch(<?= json_encode(store_url('process_contact.php')) ?>, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    firstName: document.getElementById('firstName').value.trim(),
                    lastName: document.getElementById('lastName').value.trim(),
                    email: document.getElementById('email').value.trim(),
                    phone: document.getElementById('phone').value.trim(),
                    subject: document.getElementById('subject').value,
                    message: document.getElementById('message').value.trim()
                })
            });
            var data = await response.json();
            status.textContent = data.message || (response.ok ? contactMessages.sent : contactMessages.failed);
            if (response.ok && data.success) {
                event.target.reset();
            }
        } catch (error) {
            status.textContent = contactMessages.failedFallback;
        }
        button.disabled = false;
    });
    </script>
</body>
</html>
<?php
// Flush output buffer
ob_end_flush();
?>
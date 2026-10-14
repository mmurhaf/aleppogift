(function () {
    const PAGE_W = 210;
    const PAGE_H = 297;
    const MARGIN = 12;
    const FOOTER_TOP = PAGE_H - 12;
    const INK = [44, 21, 5];
    const GOLD = [212, 132, 14];
    const GOLD_DEEP = [184, 98, 10];
    const MUTED = [94, 71, 47];
    const CREAM = [255, 248, 238];
    const LINE = [232, 214, 186];
    const MAX_IMAGE = 280;

    function money(price) {
        const amount = Number(price);
        if (!Number.isFinite(amount)) {
            return '';
        }
        if (typeof window.formatStoreMoney === 'function') {
            return window.formatStoreMoney(amount, false);
        }
        return 'AED ' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function productUrl(id) {
        return 'https://aleppogift.com/product.php?id=' + encodeURIComponent(id);
    }

    function subtitle(filters, count, title) {
        const parts = [];
        if (filters && filters.category && filters.category !== title) parts.push(filters.category);
        if (filters && filters.brand && filters.brand !== title) parts.push(filters.brand);
        if (filters && filters.search) parts.push('Search: ' + filters.search);
        parts.push(count + (count === 1 ? ' product' : ' products'));
        parts.push(new Date().toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }));
        return parts.join('   ·   ');
    }

    function catalogTitle(filters, fallback) {
        if (filters && filters.brand) return filters.brand;
        if (filters && filters.category) return filters.category;
        return fallback || 'Product Catalog';
    }

    function fileName(filters, prefix) {
        let base = prefix || 'AleppoGift_Catalog';
        const source = (filters && (filters.brand || filters.category)) || '';
        if (source) {
            const slug = String(source).replace(/[^a-zA-Z0-9]+/g, '_').replace(/^_|_$/g, '');
            if (slug) base = 'AleppoGift_' + slug;
        }
        return base + '_' + new Date().toISOString().slice(0, 10) + '.pdf';
    }

    function prepareImage(dataUrl, maxEdge) {
        const limit = maxEdge || MAX_IMAGE;
        if (!dataUrl || typeof dataUrl !== 'string' || dataUrl.indexOf('data:image') !== 0) {
            return Promise.resolve(null);
        }
        if (dataUrl.length > 12000000) {
            return Promise.resolve(null);
        }

        return new Promise(function (resolve) {
            const img = new Image();
            img.onload = function () {
                const sourceW = img.naturalWidth || img.width;
                const sourceH = img.naturalHeight || img.height;
                if (!sourceW || !sourceH) {
                    resolve(null);
                    return;
                }
                if (sourceW <= limit && sourceH <= limit && dataUrl.indexOf('data:image/jpeg') === 0) {
                    resolve({ dataUrl: dataUrl, w: sourceW, h: sourceH });
                    return;
                }
                const scale = Math.min(limit / sourceW, limit / sourceH, 1);
                const w = Math.max(1, Math.round(sourceW * scale));
                const h = Math.max(1, Math.round(sourceH * scale));
                const canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);
                resolve({ dataUrl: canvas.toDataURL('image/jpeg', limit > MAX_IMAGE ? 0.74 : 0.62), w: w, h: h });
            };
            img.onerror = function () {
                resolve(null);
            };
            img.src = dataUrl;
        });
    }

    async function prepareAll(products) {
        const cache = new Map();
        const pending = [];
        products.forEach(function (product) {
            const src = product && product.main_image;
            if (!src || cache.has(src) || pending.indexOf(src) !== -1) {
                return;
            }
            pending.push(src);
        });

        const limit = 4;
        let index = 0;
        async function worker() {
            while (index < pending.length) {
                const src = pending[index++];
                cache.set(src, await prepareImage(src));
            }
        }
        const workers = [];
        const count = Math.min(limit, pending.length);
        for (let i = 0; i < count; i++) workers.push(worker());
        await Promise.all(workers);
        return cache;
    }

    function paintImage(doc, image, boxX, boxY, boxW, boxH, alias) {
        if (!image) return false;
        const ratio = image.w / image.h;
        let drawW = boxW;
        let drawH = boxW / ratio;
        if (drawH > boxH) {
            drawH = boxH;
            drawW = boxH * ratio;
        }
        const x = boxX + (boxW - drawW) / 2;
        const y = boxY + (boxH - drawH) / 2;
        doc.addImage(image.dataUrl, 'JPEG', x, y, drawW, drawH, alias);
        return true;
    }

    function drawFooter(doc, page, total) {
        doc.setFillColor(INK[0], INK[1], INK[2]);
        doc.rect(0, PAGE_H - 8, PAGE_W, 8, 'F');
        doc.setFillColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.rect(0, PAGE_H - 8, PAGE_W, 0.8, 'F');
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(255, 248, 238);
        doc.text('Aleppo Gift   ·   www.aleppogift.com', MARGIN, PAGE_H - 3.2);
        doc.text(page + '  /  ' + total, PAGE_W - MARGIN, PAGE_H - 3.2, { align: 'right' });
    }

    function drawRunningHeader(doc, title) {
        doc.setFillColor(INK[0], INK[1], INK[2]);
        doc.rect(0, 0, PAGE_W, 10, 'F');
        doc.setFillColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.rect(0, 10, PAGE_W, 1.1, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.text('ALEPPO GIFT', MARGIN, 6.4);
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(255, 248, 238);
        const label = doc.splitTextToSize(title, 110)[0];
        doc.text(label, PAGE_W - MARGIN, 6.4, { align: 'right' });
        return 16;
    }

    function drawFeaturedCover(doc, title, detail, product, image, showContents) {
        doc.setFillColor(INK[0], INK[1], INK[2]);
        doc.rect(0, 0, PAGE_W, 32, 'F');
        doc.setFillColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.rect(0, 32, PAGE_W, 1.6, 'F');
        doc.setFillColor(CREAM[0], CREAM[1], CREAM[2]);
        doc.rect(0, 33.6, PAGE_W, PAGE_H - 33.6, 'F');

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.text('ALEPPO GIFT', MARGIN, 12);
        doc.setTextColor(255, 248, 238);
        doc.setFontSize(title.length > 32 ? 16 : 20);
        doc.text(doc.splitTextToSize(title, PAGE_W - MARGIN * 2)[0], MARGIN, 22);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(245, 220, 180);
        doc.text(doc.splitTextToSize(detail, PAGE_W - MARGIN * 2)[0], MARGIN, 29);

        const captionH = 32;
        const contentsReserve = showContents ? 28 : 0;
        const frameTop = 40;
        const frameBottom = FOOTER_TOP - captionH - contentsReserve;
        const frameH = Math.max(70, frameBottom - frameTop);
        const frameW = PAGE_W - MARGIN * 2;

        doc.setFillColor(255, 255, 255);
        doc.roundedRect(MARGIN, frameTop, frameW, frameH, 2, 2, 'F');

        let painted = false;
        if (image) {
            try {
                painted = paintImage(doc, image, MARGIN + 4, frameTop + 4, frameW - 8, frameH - 8, 'cover');
            } catch (error) {
                console.warn('Skipped cover image', error);
            }
        }
        if (!painted) {
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(11);
            doc.setTextColor(180, 160, 140);
            doc.text('No image', MARGIN + frameW / 2, frameTop + frameH / 2, { align: 'center' });
        }

        let y = frameTop + frameH + 8;
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(8);
        doc.setTextColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
        doc.text('FEATURED', MARGIN, y);

        if (product) {
            const price = money(product.price);
            if (price) {
                doc.setFontSize(12);
                doc.text(price, PAGE_W - MARGIN, y, { align: 'right' });
            }
            y += 6;
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(13);
            doc.setTextColor(INK[0], INK[1], INK[2]);
            const name = doc.splitTextToSize(product.name_en || 'Untitled', PAGE_W - MARGIN * 2).slice(0, 2);
            name.forEach(function (line) {
                doc.text(line, MARGIN, y);
                y += 5.2;
            });
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(9);
            doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
            doc.text('No. ' + product.id, MARGIN, y);
            const label = 'View product';
            doc.setTextColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
            const labelWidth = doc.getTextWidth(label);
            const linkX = PAGE_W - MARGIN - labelWidth;
            const url = productUrl(product.id);
            if (typeof doc.textWithLink === 'function') {
                doc.textWithLink(label, linkX, y, { url: url });
            } else {
                doc.text(label, linkX, y);
                doc.link(linkX, y - 3, labelWidth, 4, { url: url });
            }
            doc.setDrawColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
            doc.setLineWidth(0.15);
            doc.line(linkX, y + 0.7, linkX + labelWidth, y + 0.7);
            y += 8;
        }

        return y;
    }

    function drawCover(doc, title, detail) {
        doc.setFillColor(INK[0], INK[1], INK[2]);
        doc.rect(0, 0, PAGE_W, 46, 'F');
        doc.setFillColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.rect(0, 46, PAGE_W, 1.6, 'F');
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.text('ALEPPO GIFT', MARGIN, 16);
        doc.setTextColor(255, 248, 238);
        doc.setFontSize(title.length > 32 ? 16 : 22);
        const titleLine = doc.splitTextToSize(title, PAGE_W - MARGIN * 2)[0];
        doc.text(titleLine, MARGIN, 30);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(245, 220, 180);
        const detailLine = doc.splitTextToSize(detail, PAGE_W - MARGIN * 2)[0];
        doc.text(detailLine, MARGIN, 39);
        return 56;
    }

    function drawSection(doc, name, count, y) {
        doc.setFont('helvetica', 'bold');
        doc.setFontSize(13);
        doc.setTextColor(INK[0], INK[1], INK[2]);
        doc.text(doc.splitTextToSize(String(name), 130)[0], MARGIN, y);
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(9);
        doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
        doc.text(count + (count === 1 ? ' product' : ' products'), PAGE_W - MARGIN, y, { align: 'right' });
        doc.setDrawColor(GOLD[0], GOLD[1], GOLD[2]);
        doc.setLineWidth(0.4);
        doc.line(MARGIN, y + 2.2, PAGE_W - MARGIN, y + 2.2);
        return y + 7;
    }

    function drawCard(doc, product, image, x, y, colW, cardH, showArabic) {
        doc.setFillColor(CREAM[0], CREAM[1], CREAM[2]);
        doc.setDrawColor(LINE[0], LINE[1], LINE[2]);
        doc.setLineWidth(0.25);
        doc.roundedRect(x, y, colW, cardH, 1.6, 1.6, 'FD');

        const wellX = x + 3.5;
        const wellY = y + 3;
        const wellW = colW - 7;
        const wellH = 46;
        doc.setFillColor(255, 255, 255);
        doc.roundedRect(wellX, wellY, wellW, wellH, 1.1, 1.1, 'F');

        let painted = false;
        if (image) {
            try {
                painted = paintImage(doc, image, wellX + 1.5, wellY + 1.5, wellW - 3, wellH - 3, 'p' + product.id);
            } catch (error) {
                console.warn('Skipped product image', product.id, error);
                painted = false;
            }
        }
        if (!painted) {
            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(180, 160, 140);
            doc.text('No image', wellX + wellW / 2, wellY + wellH / 2, { align: 'center' });
        }

        const textX = x + 4;
        const textW = colW - 8;
        let textY = y + 54;

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(7.5);
        doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
        doc.text('No. ' + product.id, textX, textY);
        const meta = [product.brand_name, product.category_name].filter(function (value, index, list) {
            return value && value !== product._catalogTitle && list.indexOf(value) === index;
        }).join(' · ');
        if (meta) {
            const metaLine = doc.splitTextToSize(meta, textW - 22)[0];
            doc.text(metaLine, x + colW - 4, textY, { align: 'right' });
        }
        textY += 4.4;

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(INK[0], INK[1], INK[2]);
        const name = doc.splitTextToSize(product.name_en || 'Untitled', textW).slice(0, 2);
        name.forEach(function (line) {
            doc.text(line, textX, textY);
            textY += 3.8;
        });
        if (name.length < 2) textY += 3.8;

        if (showArabic && product.name_ar) {
            doc.setFont('AmiriRegular', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
            const shaped = typeof doc.processArabic === 'function' ? doc.processArabic(product.name_ar) : product.name_ar;
            const arabic = doc.splitTextToSize(shaped, textW)[0];
            doc.text(arabic, x + colW - 4, textY, { align: 'right' });
            doc.setFont('helvetica', 'normal');
        }
        textY += 4.2;

        const price = money(product.price);
        if (price) {
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
            doc.text(price, textX, textY);
        }

        const linkY = y + cardH - 4.2;
        const label = 'View product';
        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8);
        doc.setTextColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
        const labelWidth = doc.getTextWidth(label);
        const url = productUrl(product.id);
        if (typeof doc.textWithLink === 'function') {
            doc.textWithLink(label, textX, linkY, { url: url });
        } else {
            doc.text(label, textX, linkY);
            doc.link(textX, linkY - 3, labelWidth, 4, { url: url });
        }
        doc.setDrawColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
        doc.setLineWidth(0.15);
        doc.line(textX, linkY + 0.7, textX + labelWidth, linkY + 0.7);
    }

    function drawGrid(doc, products, images, startY, runningTitle, showArabic) {
        const gap = 5;
        const colW = (PAGE_W - MARGIN * 2 - gap) / 2;
        const cardH = 84;
        const rowGap = 4;
        let y = startY;
        let col = 0;

        products.forEach(function (product) {
            if (y + cardH > FOOTER_TOP) {
                doc.addPage();
                y = drawRunningHeader(doc, runningTitle);
                col = 0;
            }
            const x = MARGIN + col * (colW + gap);
            const image = images.get(product.main_image) || null;
            product._catalogTitle = runningTitle;
            drawCard(doc, product, image, x, y, colW, cardH, showArabic);
            col += 1;
            if (col >= 2) {
                col = 0;
                y += cardH + rowGap;
            }
        });
    }

    async function loadArabicFont(doc) {
        const response = await fetch('assets/fonts/Amiri-Regular.ttf');
        if (!response.ok) {
            throw new Error('Arabic font unavailable');
        }
        const bytes = new Uint8Array(await response.arrayBuffer());
        let binary = '';
        const chunk = 0x8000;
        for (let i = 0; i < bytes.length; i += chunk) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
        }
        const font = btoa(binary);
        doc.addFileToVFS('Amiri-Regular.ttf', font);
        doc.addFont('Amiri-Regular.ttf', 'AmiriRegular', 'normal');
    }

    async function download(options) {
        const products = options.products || [];
        const filters = options.filters || {};
        const groupingType = options.groupingType || 'no_grouping';
        const groups = options.groups || {};
        const hasGrouping = groupingType !== 'no_grouping' && groups && Object.keys(groups).length > 0;
        const title = catalogTitle(filters, options.title || 'Product Catalog');
        const detail = subtitle(filters, products.length, title);
        const runningTitle = title;
        let showArabic = !!options.includeArabic;

        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');
        doc.setProperties({
            title: title + ' — Aleppo Gift',
            creator: 'Aleppo Gift'
        });

        if (showArabic) {
            try {
                await loadArabicFont(doc);
            } catch (error) {
                console.warn('Arabic font skipped', error);
                showArabic = false;
            }
        }

        const images = await prepareAll(products);
        const featured = products.find(function (product) { return product && product.main_image; }) || products[0] || null;
        const coverImage = await prepareImage(filters.cover_image || (featured && featured.main_image), 960);
        const hero = coverImage || (featured ? images.get(featured.main_image) : null) || null;
        const tocSpots = [];

        if (hasGrouping) {
            const groupNames = Object.keys(groups);
            const showContents = groupNames.length > 1;
            let y = drawFeaturedCover(doc, title, detail, featured, hero, showContents);

            if (showContents) {
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(11);
                doc.setTextColor(INK[0], INK[1], INK[2]);
                doc.text('Contents', MARGIN, y);
                y += 6;

                groupNames.forEach(function (name) {
                    if (y > FOOTER_TOP - 8) {
                        doc.addPage();
                        y = drawRunningHeader(doc, 'Contents');
                    }
                    const count = (groups[name] || []).length;
                    doc.setFont('helvetica', 'normal');
                    doc.setFontSize(10);
                    doc.setTextColor(INK[0], INK[1], INK[2]);
                    doc.text(doc.splitTextToSize(String(name), 120)[0], MARGIN, y);
                    doc.setTextColor(MUTED[0], MUTED[1], MUTED[2]);
                    doc.setFontSize(8);
                    doc.text(count + (count === 1 ? ' item' : ' items'), PAGE_W - MARGIN - 18, y, { align: 'right' });
                    tocSpots.push({ page: doc.internal.getNumberOfPages(), y: y, name: name });
                    y += 7;
                });
            }

            groupNames.forEach(function (name) {
                const groupProducts = groups[name] || [];
                doc.addPage();
                const startPage = doc.internal.getNumberOfPages();
                const spot = tocSpots.find(function (entry) { return entry.name === name; });
                if (spot) spot.target = startPage;
                let sectionY = drawRunningHeader(doc, runningTitle);
                sectionY = drawSection(doc, name, groupProducts.length, sectionY);
                drawGrid(doc, groupProducts, images, sectionY, runningTitle, showArabic);
            });

            tocSpots.forEach(function (spot) {
                doc.setPage(spot.page);
                doc.setFont('helvetica', 'bold');
                doc.setFontSize(10);
                doc.setTextColor(GOLD_DEEP[0], GOLD_DEEP[1], GOLD_DEEP[2]);
                doc.text(String(spot.target || ''), PAGE_W - MARGIN, spot.y, { align: 'right' });
            });
        } else {
            const y = drawCover(doc, title, detail);
            drawGrid(doc, products, images, y, runningTitle, showArabic);
        }

        const total = doc.internal.getNumberOfPages();
        for (let page = 1; page <= total; page++) {
            doc.setPage(page);
            drawFooter(doc, page, total);
        }

        doc.save(fileName(filters, options.filenamePrefix));
    }

    window.AleppoCatalogPdf = { download: download };
})();

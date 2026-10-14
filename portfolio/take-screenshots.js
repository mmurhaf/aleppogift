const puppeteer = require('puppeteer');
const path = require('path');

const sites = [
  { url: 'https://aleppogift.com', file: 'screenshot_aleppogift.png' },
  { url: 'https://dubgift.com', file: 'screenshot_dubgift.png' },
  { url: 'https://krmeen.com', file: 'screenshot_krmeen.png' },
  { url: 'https://saleto.me', file: 'screenshot_saleto.png' },
];

(async () => {
  const browser = await puppeteer.launch({ headless: 'new', args: ['--no-sandbox', '--disable-setuid-sandbox'] });
  for (const site of sites) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1280, height: 800 });
    try {
      await page.goto(site.url, { waitUntil: 'networkidle2', timeout: 30000 });
      const outPath = path.join(__dirname, 'images', site.file);
      await page.screenshot({ path: outPath, type: 'png' });
      console.log('SAVED', outPath);
    } catch (e) {
      console.error('FAIL', site.url, e.message);
    }
    await page.close();
  }
  await browser.close();
})();

const { expect, test } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

// content/banner.md is gitignored and host-edited by design (see
// frontend/v1/content/banner.md.example) -- these tests manage its
// lifecycle directly on the filesystem shared with the dev server this
// suite runs against (both run in the same CI job/container), and
// always clean up afterward even on failure.
const BANNER_PATH = path.resolve(__dirname, '../../frontend/v1/content/banner.md');

function writeBanner(content) {
  fs.writeFileSync(BANNER_PATH, content);
}

function removeBanner() {
  if (fs.existsSync(BANNER_PATH)) {
    fs.unlinkSync(BANNER_PATH);
  }
}

test.describe('site banner', () => {
  test.afterEach(() => {
    removeBanner();
  });

  test('a banner link opens in a new tab without navigating the parent page', async ({ page, context }) => {
    writeBanner('Read the [announcement](https://example.com/announcement) for details.');

    await page.goto('/');

    const bannerFrame = page.frameLocator('#site-banner');
    await expect(bannerFrame.locator('a')).toBeVisible();

    const popupPromise = context.waitForEvent('page');
    await bannerFrame.locator('a').click();
    const popup = await popupPromise;
    await popup.waitForLoadState();

    expect(popup.url()).toBe('https://example.com/announcement');
    // The main page itself never navigated away.
    expect(page.url()).toMatch(/\/$/);
  });

  test('the iframe resizes to fit a multi-line banner vs. a one-line banner', async ({ page }) => {
    writeBanner('A short banner.');
    await page.goto('/');
    const shortBox = await page.locator('#site-banner').boundingBox();

    writeBanner(
      'A much longer banner announcement.\n\n'
        + 'It spans several separate paragraphs of content, '
        + 'so that it renders noticeably taller than a single short line.\n\n'
        + 'Here is a third paragraph to make sure the difference is unambiguous.\n\n'
        + 'And a fourth, for good measure.'
    );
    await page.reload();
    const longBox = await page.locator('#site-banner').boundingBox();

    expect(shortBox).not.toBeNull();
    expect(longBox).not.toBeNull();
    expect(longBox.height).toBeGreaterThan(shortBox.height);
  });
});

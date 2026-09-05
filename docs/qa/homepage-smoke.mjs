import assert from 'node:assert/strict'
import { mkdir, writeFile } from 'node:fs/promises'
import { chromium } from 'playwright'

// Run only against the isolated, seeded local QA stack.
const baseURL = process.env.HOMEPAGE_QA_URL || 'http://host.docker.internal:8904'
assert(['localhost', '127.0.0.1', 'host.docker.internal'].includes(new URL(baseURL).hostname))
const output = process.env.HOMEPAGE_QA_OUTPUT || '/artifacts'
await mkdir(output, { recursive: true })
// Production is HTTPS; this isolated HTTP origin needs secure-context APIs too.
const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--unsafely-treat-insecure-origin-as-secure=' + baseURL] })
const context = await browser.newContext({ viewport: { width: 1440, height: 2560 }, reducedMotion: 'reduce' })
const page = await context.newPage()
const failures = []
const consoleErrors = []
page.on('pageerror', error => failures.push(error.message))
page.on('console', message => {
  if (/hydration|unref|Cannot load payload/i.test(message.text())) failures.push(message.text())
  if (message.type() === 'error') consoleErrors.push(message.text())
})
const summary = { baseURL, pages: [], measurements: {}, checks: [], failures, consoleErrors }
try {
  const response = await page.goto(baseURL, { waitUntil: 'networkidle' })
  assert.equal(response.status(), 200)
  await page.locator('.home-flash-sale .product-card').first().waitFor()
  assert.equal(await page.locator('.home-side-promotion').count(), 3)
  assert.equal(await page.locator('.home-featured-category-card').count(), 9)
  assert.equal(await page.locator('.home-flash-sale .product-card').count(), 6)
  assert.equal(await page.locator('.home-testimonial-card').count(), 4)
  await page.evaluate(async () => {
    await Promise.all([...document.images].map(image => {
      image.loading = 'eager'
      return image.decode().catch(() => {})
    }))
    await document.fonts.ready
  })
  summary.measurements = await page.evaluate(() => {
    const rect = selector => {
      const element = document.querySelector(selector)
      const { x, y, width, height } = element.getBoundingClientRect()
      return { x, y, width, height }
    }
    return {
      header: rect('.site-header'), hero: rect('.home-hero-grid'),
      sidebar: rect('.home-category-sidebar'), mainHero: rect('.home-hero-slider'),
      promotions: rect('.home-side-promotions'), service: rect('.home-service-strip'),
      product: rect('.home-flash-sale .product-card'), footer: rect('.site-footer'),
      overflow: document.documentElement.scrollWidth > innerWidth,
      brokenImages: [...document.images].filter(image => !image.naturalWidth).map(image => image.currentSrc),
    }
  })
  assert.equal(summary.measurements.overflow, false)
  await page.screenshot({ path: output + '/homepage-desktop.png', fullPage: true })
  summary.checks.push('Desktop: 1440px, 9 categories, 6 flash cards, 3 promotions, 4 testimonials, no horizontal overflow')
  await page.getByRole('button', { name: 'Danh mục sản phẩm', exact: true }).click()
  assert(await page.locator('#header-category-panel').isVisible())
  await page.keyboard.press('Escape')
  assert.equal(await page.locator('#header-category-panel').isVisible(), false)
  summary.checks.push('Desktop menu opens and closes with Escape')
  const search = page.locator('.desktop-only input[type=search]')
  await search.fill('RTX')
  await page.locator('.desktop-only .search-result').first().waitFor()
  await page.locator('.desktop-only .search-result').first().focus()
  await page.keyboard.press('Enter')
  await page.waitForURL(url => url.pathname !== '/')
  summary.checks.push('Realtime search supports keyboard navigation')

  const list = await (await context.request.get(baseURL + '/api/v1/products?per_page=20')).json()
  const ids = list.data.slice(0, 15).map(product => product.id)
  assert.equal(ids.length, 15)
  await page.evaluate(ids => localStorage.setItem('pc_wishlist', JSON.stringify(ids)), ids)
  await page.goto(baseURL + '/yeu-thich', { waitUntil: 'networkidle' })
  assert.equal(await page.locator('.wishlist-product-grid .product-card').count(), 15)
  await page.locator('.wishlist-product-grid .product-card-wishlist').first().click()
  await page.waitForFunction(() => JSON.parse(localStorage.getItem('pc_wishlist')).length === 14)
  await page.reload({ waitUntil: 'networkidle' })
  assert.equal(await page.locator('.wishlist-product-grid .product-card').count(), 14)
  summary.checks.push('Wishlist loads 15 IDs, removes a product and persists on reload')

  await page.goto(baseURL, { waitUntil: 'networkidle' })
  await page.locator('#footer-newsletter-email').fill('homepage-browser-qa@example.test')
  await page.locator('.footer-newsletter-form button').click()
  await page.getByRole('status').filter({ hasText: 'Đăng ký nhận tin thành công.' }).waitFor()
  summary.checks.push('Newsletter form submits successfully to local API')

  for (const path of ['/pc-gaming', '/gio-hang', '/thanh-toan', '/cau-hinh', '/tin-tuc', '/lien-he', '/dang-nhap', '/dang-ky', '/tai-khoan', '/products?search=RTX', '/vga/card-man-hinh-gigabyte-geforce-rtx-4070-super-windforce-oc-12g']) {
    const result = await page.goto(baseURL + path, { waitUntil: 'networkidle' })
    summary.pages.push({ path, status: result.status() })
    assert(result.status() < 400, path)
    assert(await page.locator('.site-header').isVisible(), path + ' header')
    assert.equal(await page.locator('.site-footer').count(), 1, path + ' footer')
    assert(!/Server Error|Cannot load payload|unref\(.*not a function/i.test(await page.locator('body').innerText()), path)
  }
  for (const width of [1024, 768, 390]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto(baseURL, { waitUntil: 'networkidle' })
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > innerWidth), false, 'overflow at ' + width)
    await page.screenshot({ path: output + '/homepage-' + width + '.png', fullPage: true })
  }
  assert(await page.locator('.header-actions a[href="/dang-nhap"] svg').isVisible())
  await page.getByRole('button', { name: 'Mở danh mục sản phẩm' }).click()
  await page.getByRole('dialog').waitFor()
  assert.equal(await page.evaluate(() => document.body.style.overflow), 'hidden')
  await page.keyboard.press('Escape')
  assert.equal(await page.getByRole('dialog').isVisible(), false)
  assert.equal(await page.evaluate(() => document.activeElement.getAttribute('aria-label')), 'Mở danh mục sản phẩm')
  summary.checks.push('Mobile account, drawer focus restoration, Escape and scroll lock work')
  assert.equal(failures.length, 0, failures.join('\n'))
} catch (error) {
  failures.push(error.stack)
  await page.screenshot({ path: output + '/failure.png', fullPage: true })
  process.exitCode = 1
} finally {
  await writeFile(output + '/homepage-qa.json', JSON.stringify(summary, null, 2))
  console.log(JSON.stringify(summary, null, 2))
  await browser.close()
}

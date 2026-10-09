import assert from 'node:assert/strict';
import { after, before, test } from 'node:test';
import { fileURLToPath } from 'node:url';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import vue from '@vitejs/plugin-vue';
import { createServer } from 'vite';

let server;
let PagesIndex;

before(async () => {
    // Compile the real SFC. Only layout and Inertia transport are stubbed;
    // no browser, API, credentials or database is used by these render tests.
    server = await createServer({
        configFile: false,
        root: fileURLToPath(new URL('../..', import.meta.url)),
        server: { middlewareMode: true, watch: null },
        ssr: { noExternal: ['@inertiajs/vue3'] },
        plugins: [
            {
                name: 'admin-pages-test-stubs',
                enforce: 'pre',
                resolveId(id) {
                    if (id === '@inertiajs/vue3') return '\0pages-test-inertia';
                    if (id === '@/Layouts/AdminLayout.vue') return '\0pages-test-layout';
                },
                load(id) {
                    if (id === '\0pages-test-inertia') return `
                        import { h } from 'vue';
                        export const router = { delete() { throw new Error('Unexpected delete'); } };
                        export const usePage = () => ({ props: { flash: {} } });
                        export const Link = {
                            props: ['href'],
                            setup(props, { slots }) {
                                return () => h('a', { href: props.href }, slots.default?.());
                            },
                        };
                    `;
                    if (id === '\0pages-test-layout') return `
                        import { h } from 'vue';
                        export default {
                            props: ['title'],
                            setup(props, { slots }) { return () => h('main', slots.default?.()); },
                        };
                    `;
                },
            },
            vue(),
        ],
    });
    PagesIndex = (await server.ssrLoadModule('/resources/js/Pages/Admin/Pages/Index.vue')).default;
});

after(async () => { await server?.close(); });

async function render(props = {}) {
    const warnings = [];
    const app = createSSRApp(PagesIndex, props);
    app.config.warnHandler = (message) => warnings.push(message);
    const html = await renderToString(app);
    assert.deepEqual(warnings, [], 'The page should render without Vue warnings');
    return html;
}

function fixture(id, isActive = false) {
    return { id, title: `Trang thử ${id}`, slug: `trang-thu-${id}`, is_active: isActive };
}

test('omitted pages defaults to an empty array', async () => {
    assert.match(await render(), /Chưa có trang/);
});

test('an empty array renders exactly one empty-state row', async () => {
    const html = await render({ pages: [] });
    assert.match(html, /colspan="4"/);
    assert.equal(html.match(/Chưa có trang/g)?.length, 1);
    assert.doesNotMatch(html, /\/admin\/pages\/\d+\/edit/);
});

test('one published page renders all columns and the edit link for its ID', async () => {
    const html = await render({ pages: [fixture(7, true)] });
    assert.match(html, /Trang thử 7/);
    assert.match(html, /\/trang-thu-7/);
    assert.match(html, />Hiện<\/span>/);
    assert.match(html, /href="\/admin\/pages\/7\/edit"/);
    assert.doesNotMatch(html, /Chưa có trang/);
});

test('a hidden page still has a row labelled Ẩn', async () => {
    const page = fixture(42);
    const html = await render({ pages: [page] });
    assert.match(html, /Trang thử 42/);
    assert.match(html, /\/trang-thu-42/);
    assert.match(html, />Ẩn<\/span>/);
    assert.match(html, /href="\/admin\/pages\/42\/edit"/);
    assert.doesNotMatch(html, />Hiện<\/span>|Chưa có trang/);
    assert.equal(page.is_active, false);
});

test('published page slug opens the supplied storefront URL, not the admin domain', async () => {
    const page = { ...fixture(3, true), public_url: 'https://storefront.example.test/trang-thu-3' };
    const html = await render({ pages: [page] });
    assert.match(html, /href="https:\/\/storefront\.example\.test\/trang-thu-3" target="_blank" rel="noopener noreferrer"/);
});

test('hidden page slug has no public link even if a URL is supplied', async () => {
    const page = { ...fixture(4), public_url: 'https://storefront.example.test/trang-thu-4' };
    assert.doesNotMatch(await render({ pages: [page] }), /href="https:\/\/storefront\.example\.test/);
});

test('all rows and their supplied order survive a multi-page array', async () => {
    const pages = Array.from({ length: 30 }, (_, index) => fixture(100 - index, index % 2 === 0));
    const html = await render({ pages });
    assert.equal(html.match(/>Sửa<\/a>/g)?.length, pages.length);
    const ids = [...html.matchAll(/href="\/admin\/pages\/(\d+)\/edit"/g)].map((match) => Number(match[1]));
    assert.deepEqual(ids, pages.map((page) => page.id));
    for (const page of pages) {
        assert.ok(html.includes(page.title));
        assert.ok(html.includes(`/${page.slug}`));
    }
    assert.doesNotMatch(html, /Chưa có trang/);
});

test('rendering the reloaded edited data shows the new title without changing visibility', async () => {
    const page = fixture(19);
    assert.match(await render({ pages: [page] }), /Trang thử 19/);
    const html = await render({ pages: [{ ...page, title: 'Trang thử đã sửa' }] });
    assert.match(html, /Trang thử đã sửa/);
    assert.match(html, /href="\/admin\/pages\/19\/edit"/);
    assert.match(html, />Ẩn<\/span>/);
    assert.doesNotMatch(html, /Trang thử 19|Chưa có trang/);
});

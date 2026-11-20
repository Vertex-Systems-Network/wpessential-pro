// Runs from the Free repository's E2E package (WPE_E2E_SPEC_DIRS), against wp-env with Free + Pro active.
import { BASE, changed, expect, fillAll, notBuilt, openAdmin, snapshot, test, wp } from '../../../../wpessential/tests/e2e/specs/support/audit.mjs';

const RUN = Date.now().toString(36).slice(-4);
const body = (page) => page.locator('#wpbody-content');

test('Custom Fields: every field type with every setting', async ({ page, audit }) => {
  test.setTimeout(1_800_000);
  await openAdmin(page, 'wpessential-fields');
  const types = (await page.locator('#wpessential-fields-add-type option').evaluateAll((options) => options.map((o) => o.value))).filter(Boolean);
  const root = page.locator('#wpessential-fields-root');

  const options = await page.locator('#wpessential-fields-add-type option').evaluateAll((list) => list.map((o) => ({ value: o.value, off: o.disabled, label: o.textContent.trim() })));
  for (const option of options.filter((o) => o.value && o.off)) notBuilt('custom-fields', `field type: ${option.value}`, option.label);

  for (const type of types.filter((value) => !options.find((o) => o.value === value).off)) {
    const key = `e2e_${type}_${RUN}`;
    await audit('custom-fields', `field type: ${type} (every setting, save, reload)`, async () => {
      await openAdmin(page, 'wpessential-fields');
      await page.locator('#wpessential-fields-add-type').selectOption(type);
      await page.getByRole('button', { name: 'Add field' }).click();
      await fillAll(root, {
        'wpessential-fields-group-key': key,
        'wpessential-fields-group-title': `E2E ${type}`,
        'wpessential-fields-add-type': null,
        'wpessential-fields-status': 'draft',
        'wpessential-fields-storage-mode': null,
      });
      const entered = await snapshot(root);
      await page.getByRole('button', { name: 'Save field group' }).click();
      await expect(root.getByText(/Field Group (was )?(not )?(saved|created|updated)/i).first()).toBeVisible();
      expect(await root.getByText(/^blocked:/).allInnerTexts(), 'validation report').toEqual([]);
      await openAdmin(page, 'wpessential-fields');
      await page.locator('tr').filter({ hasText: key }).getByRole('button', { name: 'Edit' }).click();
      expect(changed(entered, await snapshot(root), /fields-(id|revision|add-type|group-key)$/)).toEqual([]);
    });
  }
});

test('Custom Fields: published group shows on the post editor and stores values', async ({ page, audit }) => {
  const key = `e2e_box_${RUN}`;
  const root = page.locator('#wpessential-fields-root');
  let postId = 0;

  await audit('custom-fields', 'publish a group placed on posts', async () => {
    await openAdmin(page, 'wpessential-fields');
    await page.locator('#wpessential-fields-group-key').fill(key);
    await page.locator('#wpessential-fields-group-title').fill('E2E box');
    await page.locator('#wpessential-fields-add-type').selectOption('text');
    await page.getByRole('button', { name: 'Add field' }).click();
    await root.locator('input[type=text]:not([id])').nth(0).fill('E2E subtitle');
    await root.locator('input[type=text]:not([id])').nth(1).fill(`e2e_subtitle_${RUN}`);
    await page.locator('#wpessential-fields-show-rest').check();
    await root.getByText('Field settings').first().click();
    await page.locator('#wpessential-fields-0-rest').check();
    await page.getByRole('button', { name: 'Add rule group (OR)' }).click();
    await root.getByLabel('Rule value').first().selectOption('post');
    await page.getByRole('button', { name: 'Save field group' }).click();
    await expect(root.getByText(/Field Group (saved|created|updated)/i).first()).toBeVisible();
    await openAdmin(page, 'wpessential-fields');
    await page.locator('tr').filter({ hasText: key }).getByRole('button', { name: 'Publish' }).click();
    await expect(page.locator('tr').filter({ hasText: key })).toContainText('published');
  });

  await audit('custom-fields', 'meta registered; value saved through REST', async () => {
    postId = Number(wp(`post create --post_title="E2E fields ${RUN}" --post_status=publish --porcelain`).trim().split('\n').pop());
    const meta = await page.request.post(`${BASE}/wp-json/wp/v2/posts/${postId}`, {
      headers: { 'X-WP-Nonce': await page.evaluate(() => window.wpApiSettings?.nonce ?? '') },
      data: { meta: { [`e2e_subtitle_${RUN}`]: 'hello e2e' } },
    });
    expect(meta.status(), await meta.text()).toBeLessThan(400);
    expect(wp(`post meta get ${postId} e2e_subtitle_${RUN}`).trim()).toBe('hello e2e');
  });

  await audit('custom-fields', 'meta box on the post edit screen: edit, save, reload', async () => {
    await page.goto(`${BASE}/wp-admin/post.php?post=${postId}&action=edit`);
    // The block editor shows classic meta boxes below the content.
    // Close the first-run welcome guide and open the collapsed "Meta Boxes" pane.
    const welcome = page.getByRole('dialog', { name: /Welcome to the editor/ });
    if (await welcome.isVisible({ timeout: 10_000 }).catch(() => false)) await welcome.getByRole('button', { name: 'Close' }).click();
    const openPane = async (field) => {
      // The pane's resize handle overlaps its toggle, so send the click to the toggle itself.
      if (!(await field.isVisible())) await page.getByText('Meta Boxes', { exact: true }).dispatchEvent('click');
      await expect(field).toBeVisible();
    };
    // Earlier runs leave groups on posts too: use this run's box.
    const box = page.locator(`#wpessential-fields-field-group-${key.replace(/_/g, '-')}`);
    const input = box.getByLabel('E2E subtitle');
    await expect(input).toHaveCount(1, { timeout: 60_000 });
    await openPane(input);
    await expect(input).toHaveValue('hello e2e', { timeout: 60_000 });
    await input.fill('edited in the meta box');
    await page.getByRole('button', { name: /^(Save|Update)$/ }).first().click();
    await expect.poll(() => wp(`post meta get ${postId} e2e_subtitle_${RUN}`).trim(), { timeout: 30_000 }).toBe('edited in the meta box');
    await page.reload();
    await expect(box.getByLabel('E2E subtitle')).toHaveCount(1, { timeout: 60_000 });
    await openPane(box.getByLabel('E2E subtitle'));
    await expect(box.getByLabel('E2E subtitle')).toHaveValue('edited in the meta box', { timeout: 60_000 });
  });
});

test('Relations: create, reload, edit, delete', async ({ page, audit }) => {
  const key = `e2e_rel_${RUN}`;
  const form = page.locator('form').filter({ has: page.getByRole('button', { name: 'Save relation' }) });
  let entered = {};
  await audit('relations', 'create with every option set', async () => {
    await openAdmin(page, 'wpessential-relations');
    await fillAll(form, { relation_key: key, title: 'E2E relation', from_type: 'post', to_type: 'post', from_subtype: 'post', to_subtype: 'page', from_min: '0', to_min: '0', from_max: '5', to_max: '5', status: 'draft', reciprocal: null });
    entered = await snapshot(form);
    await page.getByRole('button', { name: 'Save relation' }).click();
    await expect(body(page)).not.toContainText(/critical error|Sorry, you are not allowed/);
    await expect(page.locator('tr').filter({ hasText: key })).toBeVisible();
  });
  await audit('relations', 'every value survives a reload', async () => {
    await openAdmin(page, 'wpessential-relations');
    await page.locator('tr').filter({ hasText: key }).getByRole('link', { name: /Edit/ }).first().click();
    expect(changed(entered, await snapshot(form), /^(id|expected_revision|relation_key)$/)).toEqual([]);
  });
  await audit('relations', 'publish and manage a connection', async () => {
    await form.locator('select[name=status]').selectOption('published');
    await page.getByRole('button', { name: 'Save relation' }).click();
    await expect(page.locator('tr').filter({ hasText: key })).toContainText(/publish/i);
  });
});

test('Admin Columns: save a Column Set and reload it', async ({ page, audit }) => {
  const name = `E2E columns ${RUN}`;
  const root = page.locator('#wpessential-columns-root');
  let entered = {};
  await audit('admin-columns', 'create with every option set', async () => {
    await openAdmin(page, 'wpessential-admin-columns');
    await page.getByRole('button', { name: 'Add Column' }).click();
    await fillAll(root.locator('form').first(), {
      'wpessential-columns-view-name': name, 'wpessential-columns-target': 'post', 'wpessential-columns-saved-view': null,
      'wpessential-columns-assign-users': '1', 'wpessential-columns-assign-roles': 'administrator',
    });
    entered = await snapshot(root.locator('form').first());
    await page.getByRole('button', { name: 'Save Column Set' }).click();
    await expect(root.getByText('Saving revisioned Column Set definition…')).toBeHidden({ timeout: 30_000 });
    expect(await root.getByText(/^blocked:|could not|error/i).allInnerTexts()).toEqual([]);
  });
  await audit('admin-columns', 'reopen the saved Column Set', async () => {
    await openAdmin(page, 'wpessential-admin-columns');
    const option = page.locator('#wpessential-columns-saved-view option').filter({ hasText: name });
    await expect(option).toHaveCount(1); // The list loads after the page.
    await page.locator('#wpessential-columns-saved-view').selectOption(await option.getAttribute('value'));
    await page.getByRole('button', { name: 'Open selected' }).click();
    await expect(root.getByText(/^Opened .* at revision \d+/)).toBeVisible();
    // Column cards get new draft ids when reopened, so compare them in order rather than by id.
    const columns = (values) => Object.entries(values).filter(([key]) => /-\d+$/.test(key)).map(([key, value]) => `${key.replace(/-\d+$/, '')}=${value}`);
    const reopened = await snapshot(root.locator('form').first());
    expect(changed(entered, reopened, /saved-view|-\d+$/)).toEqual([]);
    expect(columns(reopened)).toEqual(columns(entered));
  });
  await audit('admin-columns', 'columns appear on the Posts list', async () => {
    await page.goto(`${BASE}/wp-admin/edit.php`);
    await expect(page.locator('#wpbody-content')).not.toContainText(/critical error/);
  });
});

test('Query Builder and Custom Tables: authoring screens', async ({ page, audit }) => {
  await audit('query', 'author a query definition', async () => {
    await openAdmin(page, 'wpessential-query');
    await fillAll(page.locator('#wpessential-query-root'), { 'wpessential-query-filter-value': '1', 'wpessential-query-page-size': '5', 'wpessential-query-offset': '0' });
    await expect(page.getByRole('button', { name: 'Preview execution unavailable' })).toBeDisabled();
  });
  notBuilt('query', 'save / preview execution', 'Screen states execution preview is unavailable in this tranche; no save action exists.');
  await audit('custom-tables', 'screen opens', async () => {
    await openAdmin(page, 'wpessential-custom-tables');
    await expect(body(page)).toContainText('Custom Tables');
  });
  notBuilt('custom-tables', 'create table definition', 'Screen lists definitions only ("No Custom Tables definitions exist yet"); no create form.');
});

test('Site Kit: every tab saves and reaches the front end', async ({ page, audit, anon }) => {
  // The controls write each setting as JSON into a hidden values[...] field; that payload is what must survive.
  const payload = (form) => form.locator('input[type=hidden][name^="values["], input[name^="snippets["]').evaluateAll((list) => Object.fromEntries(list.filter((el) => el.type !== 'checkbox' || el.checked).map((el) => [el.name, el.value])));
  const formOf = () => page.locator('form:visible').filter({ has: page.getByRole('button', { name: /Save (changes|snippets)/ }) }).first();
  await openAdmin(page, 'wpessential-site-kit');
  const tabs = await page.locator('[data-wpe-tab]').evaluateAll((list) => list.map((tab) => [tab.dataset.wpeTab, tab.textContent.trim()]));
  for (const [tab, label] of tabs) {
    await audit('site-kit', `tab: ${label} (save, reload)`, async () => {
      await openAdmin(page, 'wpessential-site-kit');
      await page.locator(`[data-wpe-tab="${tab}"]`).click();
      await fillAll(formOf(), tab === 'snippets' ? { 'snippets[0][code]': '/* e2e */ body{}', 'snippets[0][type]': 'css', 'snippets[0][title]': 'E2E' } : { '*text': '#336699' });
      const entered = await payload(formOf());
      await Promise.all([page.waitForResponse((r) => /admin-(post|ajax)\.php/.test(r.url()) && r.request().method() === 'POST'), formOf().getByRole('button', { name: /Save (changes|snippets)/ }).click()]);
      await page.waitForLoadState('load');
      await expect(body(page)).not.toContainText(/critical error|Sorry, you are not allowed/);
      await openAdmin(page, 'wpessential-site-kit');
      await page.locator(`[data-wpe-tab="${tab}"]`).click();
      expect(changed(entered, await payload(formOf()))).toEqual([]);
    });
  }
  await audit('site-kit', 'front end renders the saved styles', async () => {
    const html = await (await anon.get('/')).text();
    expect(html).toMatch(/--wpe-|wpessential-site-kit/);
    expect(html).toContain('#336699');
  });
});

test('Pro elements: blocks and Elementor widgets are registered', async ({ page, audit }) => {
  await audit('pro-elements', 'Gutenberg blocks registered', async () => {
    const response = await page.request.get(`${BASE}/wp-json/wp/v2/block-types`, { headers: { 'X-WP-Nonce': await (await page.goto(`${BASE}/wp-admin/`), page.evaluate(() => window.wpApiSettings?.nonce ?? '')) } });
    const names = (await response.json()).map((block) => block.name).filter((name) => name.startsWith('wpessential'));
    expect(names.length).toBeGreaterThan(0);
  });
  await audit('pro-elements', 'Form block renders a Contact Form 7 form', async () => {
    const out = wp(`eval '$f = get_posts(["post_type" => "wpcf7_contact_form", "numberposts" => 1]); $id = $f ? $f[0]->ID : 0; if (!$id) { $c = WPCF7_ContactForm::get_template(["title" => "E2E"]); $c->save(); $id = $c->id(); } echo do_blocks("<!-- wp:wpessential/forms {\\"formId\\":\\"$id\\"} /-->");'`);
    expect(out).toContain('wpcf7-form');
  });
  await audit('pro-elements', 'Elementor widgets registered', async () => {
    const out = wp(`eval 'echo implode(",", array_filter(array_keys(\\Elementor\\Plugin::instance()->widgets_manager->get_widget_types()), fn($n) => str_contains($n, "wpe")));'`);
    expect(out.trim().length).toBeGreaterThan(0);
  });
});

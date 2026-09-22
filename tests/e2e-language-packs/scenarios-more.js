/**
 * The failure, browser-side, role and email scenarios of run.js. Kept in a file of their own so that
 * run.js stays a runner. Called as require('./scenarios-more')(api) before the run starts.
 */
'use strict';

module.exports = function (api) {
  const { scenario, SITE, WS, MOCK, ID, TEXT, TITLE, CONTINUE, delayInstall, text } = api;

  /** Panel, first visit, expected to end in "installed" and an automatic reload. */
  async function panelInstalls({ page, shot, ev }, { slug = 'Emsfb', afterName = 'panel-after-reload' } = {}) {
    await delayInstall(page, 2500);
    await page.goto(`${SITE}/wp-admin/admin.php?page=${slug}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
    await page.waitForTimeout(600);
    ev.layer_title = await text(page, TITLE);
    ev.layer_text = await text(page, TEXT);
    await shot('installing');
    await page.waitForFunction((sel) => /Reloading/.test((document.querySelector(sel) || {}).textContent || ''), TEXT, { timeout: 60000 });
    await shot('installed-reloading');
    await page.waitForEvent('load', { timeout: 30000 });
    await page.waitForTimeout(3500);
    ev.layer_after_reload = await page.locator(ID).count();
    await shot(afterName);
  }

  /** Panel or Create, first visit, expected to end in a message with "Continue in English". */
  async function blockingFails({ page, shot, ev }, { slug = 'Emsfb', wait = 120000 } = {}) {
    await delayInstall(page, 2500);
    await page.goto(`${SITE}/wp-admin/admin.php?page=${slug}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
    await page.waitForTimeout(500);
    ev.layer_title_first = await text(page, TITLE);
    await shot('installing');
    await page.waitForSelector(CONTINUE, { state: 'visible', timeout: wait });
    ev.layer_title = await text(page, TITLE);
    ev.layer_text = await text(page, TEXT);
    await shot('message');
    await page.click(CONTINUE);
    await page.waitForTimeout(700);
    ev.layer_after_continue = await page.locator(ID).count();
    await shot('continued-in-english');
  }

  /* W. WhiteStudio down, wordpress.org saves the day -------------------------------------------- */

  scenario('W1', 'Dutch: WhiteStudio has no Dutch pack, wordpress.org has one (installed from wordpress.org)', {
    locale: 'nl_NL',
    config: { server: WS },
    run: (c) => panelInstalls(c, { afterName: 'panel-in-dutch' }),
  });

  scenario('W2', 'Russian: WhiteStudio is unreachable, wordpress.org has the pack (installed from wordpress.org, no error)', {
    locale: 'ru_RU',
    config: { server: 'http://127.0.0.1:9' },
    run: (c) => panelInstalls(c, { afterName: 'panel-in-russian' }),
  });

  scenario('W3', 'Russian: the server plugin is not deployed yet (rest_no_route), wordpress.org has the pack', {
    locale: 'ru_RU',
    config: { server: `${MOCK}/norest` },
    run: (c) => panelInstalls(c, { afterName: 'panel-in-russian' }),
  });

  /* B. Cannot download: WhiteStudio unreachable --------------------------------------------------- */

  scenario('B1', 'WhiteStudio unreachable (connection refused), Create page: message, Continue in English, one email, not repeated', {
    locale: 'sv_SE',
    config: { server: 'http://127.0.0.1:9' },
    async run(c) {
      await blockingFails(c, { slug: 'Emsfb_create' });
      // The next page view retries silently in the background; the same problem is not mailed twice.
      await c.page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'load', timeout: 60000 });
      await c.page.waitForTimeout(5000);
      c.ev.layer_on_next_view = await c.page.locator(ID).count();
      c.ev.mails_after_next_view = c.ctl('state').mails.length;
      await c.shot('next-view-no-layer');
    },
  });

  scenario('B2', 'WP_HTTP_BLOCK_EXTERNAL is on', {
    locale: 'sv_SE',
    config: { server: WS, constants: { WP_HTTP_BLOCK_EXTERNAL: true } },
    run: (c) => blockingFails(c),
  });

  scenario('B3', 'A firewall / bot-protection page answers instead of the language server', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/firewall` },
    run: (c) => blockingFails(c),
  });

  scenario('B4', 'The catalog loads, but every package download is intercepted by a firewall', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/dlblocked` },
    run: (c) => blockingFails(c),
  });

  scenario('B5', 'The language server does not answer within 10 seconds', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/slow` },
    run: (c) => blockingFails(c),
  });

  /* C. The language server answers, but with a problem ----------------------------------------------- */

  scenario('C1', 'The server plugin is not deployed yet (WordPress answers rest_no_route), nobody else has Swedish', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/norest` },
    run: (c) => blockingFails(c),
  });

  scenario('C2', 'The language server answers HTTP 500', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/error500` },
    run: (c) => blockingFails(c),
  });

  scenario('C3', 'The package does not match its checksum (damaged download)', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/damaged` },
    run: (c) => blockingFails(c),
  });

  scenario('C4', 'Nobody has a Swedish translation (real WhiteStudio server, wordpress.org has none either)', {
    locale: 'sv_SE',
    config: { server: WS },
    async run(c) {
      await blockingFails(c);
      await c.page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'load', timeout: 60000 });
      await c.page.waitForTimeout(2500);
      c.ev.layer_on_next_view = await c.page.locator(ID).count();
    },
  });

  /* D. This site cannot write ---------------------------------------------------------------------------- */

  scenario('D1', 'DISALLOW_FILE_MODS is on (WordPress itself takes install_languages away from everyone): nothing on screen, an email at once', {
    locale: 'sv_SE',
    config: { server: WS, constants: { DISALLOW_FILE_MODS: true } },
    async run(c) {
      c.ev.administrator_can_install_languages = c.ctl('can', 'install_languages').administrator_has_it;
      await c.page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'load', timeout: 60000 });
      await c.page.waitForTimeout(6000);
      c.ev.layer_count = await c.page.locator(ID).count();
      await c.shot('panel-no-layer');
    },
  });

  scenario('D2', 'The filesystem needs FTP credentials (FS_METHOD = ftpext)', {
    locale: 'sv_SE',
    config: { server: WS, constants: { FS_METHOD: 'ftpext' } },
    run: (c) => blockingFails(c),
  });

  scenario('D3', 'The languages folder cannot be created or written', {
    locale: 'sv_SE',
    config: { server: WS, constants: { WP_LANG_DIR: 'C:/xampp/htdocs/wp-test-ai/wp-content/wptai-blockfile/languages' } },
    run: (c) => blockingFails(c),
  });

  /* E. The browser side ------------------------------------------------------------------------------------ */

  scenario('E1', 'The install request cannot be started from the page (an error page instead of JSON)', {
    locale: 'ru_RU',
    config: { server: WS },
    async run(c) {
      await c.page.route('**/admin-ajax.php*', async (route) => {
        if ((route.request().postData() || '').includes('emsfb_language_packs_install')) {
          await new Promise((resolve) => setTimeout(resolve, 2000));
          await route.fulfill({ status: 500, contentType: 'text/html', body: '<h1>Internal Server Error</h1>' });
          return;
        }
        await route.continue();
      });
      await c.page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await c.page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
      await c.shot('installing');
      await c.page.waitForSelector(CONTINUE, { state: 'visible', timeout: 30000 });
      c.ev.layer_title = await text(c.page, TITLE);
      c.ev.layer_text = await text(c.page, TEXT);
      await c.shot('message');
    },
  });

  scenario('E2', 'The page was open too long: its security token expired', {
    locale: 'ru_RU',
    config: { server: WS },
    async run(c) {
      await c.page.route('**/admin-ajax.php*', async (route) => {
        const body = route.request().postData() || '';
        if (body.includes('emsfb_language_packs_install')) {
          await new Promise((resolve) => setTimeout(resolve, 2000));
          await route.continue({ postData: body.replace(/nonce=[^&]+/, 'nonce=expired') });
          return;
        }
        await route.continue();
      });
      await c.page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await c.page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
      await c.shot('installing');
      await c.page.waitForSelector(CONTINUE, { state: 'visible', timeout: 30000 });
      c.ev.layer_title = await text(c.page, TITLE);
      c.ev.layer_text = await text(c.page, TEXT);
      await c.shot('message');
    },
  });

  scenario('E3', 'The install takes longer than 90 seconds', {
    locale: 'ru_RU',
    config: { server: WS },
    async run(c) {
      await delayInstall(c.page, 100000);
      await c.page.goto(`${SITE}/wp-admin/admin.php?page=Emsfb`, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await c.page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
      await c.shot('installing');
      await c.page.waitForSelector(CONTINUE, { state: 'visible', timeout: 120000 });
      c.ev.layer_title = await text(c.page, TITLE);
      c.ev.layer_text = await text(c.page, TEXT);
      await c.shot('message-after-90s');
    },
  });

  scenario('E4', 'Failure on a screen that does not block (the Dashboard): the notice turns into the message', {
    locale: 'sv_SE',
    config: { server: 'http://127.0.0.1:9' },
    async run(c) {
      await delayInstall(c.page, 2500);
      await c.page.goto(`${SITE}/wp-admin/index.php`, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await c.page.waitForSelector(ID, { state: 'visible', timeout: 20000 });
      await c.page.waitForTimeout(600);
      c.ev.notice_text = await text(c.page, TEXT);
      await c.shot('notice-installing');
      await c.page.waitForFunction((sel) => /could not be installed/.test((document.querySelector(sel) || {}).textContent || ''), TEXT, { timeout: 60000 });
      c.ev.notice_failed_text = await text(c.page, TEXT);
      await c.shot('notice-failed');
    },
  });

  /* F. Visitors, roles and the outage email ------------------------------------------------------------------ */

  scenario('F1', 'A visitor opens a form page while the language server is unreachable', {
    locale: 'sv_SE',
    visitor: true,
    config: { server: 'http://127.0.0.1:9' },
    async run(c) {
      await c.page.goto(c.ctl('page').url, { waitUntil: 'load', timeout: 60000 });
      await c.page.waitForTimeout(5000);
      await c.shot('visitor-sees-the-form-in-english');
      c.ev.layer_or_notice_for_visitor = await c.page.locator(ID).count();
    },
  });

  scenario('F2', 'An editor (no permission to install language files) opens the Dashboard: nothing shown, installs silently', {
    locale: 'ru_RU',
    login: 'editor',
    config: { server: WS },
    async run(c) {
      await c.page.goto(`${SITE}/wp-admin/index.php`, { waitUntil: 'load', timeout: 60000 });
      await c.page.waitForTimeout(6000);
      c.ev.layer_or_notice_for_editor = await c.page.locator(ID).count();
      c.ev.pack_files_after = c.ctl('state').files.map((f) => f.name);
      await c.shot('editor-dashboard');
    },
  });

  scenario('F3', 'The language server has been unreachable for 4 days: the outage email (a cron-style run)', {
    locale: 'sv_SE',
    config: { server: `${MOCK}/firewall` },
    async run(c) {
      c.ctl('outage', 4);
      const first = c.ctl('sync');
      c.ev.first_run_status = first.summary && first.summary.status;
      c.ev.mails_after_first_run = c.ctl('state').mails.length;
      c.ctl('sync');
      c.ev.mails_after_second_run = c.ctl('state').mails.length;
    },
  });
};

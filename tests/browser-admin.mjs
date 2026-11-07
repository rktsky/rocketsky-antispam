/**
 * Exercises actual native settings saves, privacy locks and beforeunload recovery.
 *
 * Requires explicit disposable-site authorization, a marked loopback WordPress
 * fixture with mail/HTTP blocked, and synthetic administrator credentials in
 * WP_ANTISPAM_ADMIN_USER / WP_ANTISPAM_ADMIN_PASSWORD. Configure PHP_BINARY,
 * WP_ANTISPAM_PHP_ICONV, WP_ANTISPAM_PHP_SIMPLEXML, PLAYWRIGHT_MODULE and
 * PLAYWRIGHT_FIREFOX only when the local test runtime needs them. All browser
 * requests outside the exact fixture origin are denied. Original options stay
 * in memory and are restored through the native Options API in finally.
 *
 * @since 0.4.0
 */
import { execFileSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

if (
	process.env.WP_ANTISPAM_ALLOW_TESTS !== '1' ||
	!process.env.WP_ANTISPAM_WP_ROOT ||
	!process.env.WP_ANTISPAM_ADMIN_USER ||
	!process.env.WP_ANTISPAM_ADMIN_PASSWORD
) {
	throw new Error(
		'Explicit disposable fixture authorization and synthetic administrator credentials are required.'
	);
}
const php = process.env.PHP_BINARY || 'php';
const helper = fileURLToPath(new URL('./admin-browser-state.php', import.meta.url));
const phpArgs = [];
for (const key of ['WP_ANTISPAM_PHP_ICONV', 'WP_ANTISPAM_PHP_SIMPLEXML']) {
	if (process.env[key]) phpArgs.push('-d', 'extension=' + process.env[key]);
}

/**
 * Uses structured arguments for the marked fixture's native Options API helper.
 *
 * @since 0.4.0
 * @param {string} mode Fixed fixture operation.
 * @param {...string} values Optional original JSON snapshot, never shell-interpolated.
 * @return {Object|Array|null} Native helper result.
 */
function state(mode = 'state', ...values) {
	return JSON.parse(
		execFileSync(php, [...phpArgs, helper, mode, ...values], {
			encoding: 'utf8',
			maxBuffer: 262144
		})
	);
}

/**
 * Stops on a concrete browser or persisted-state invariant.
 *
 * @since 0.4.0
 * @param {boolean} condition Required observed behavior.
 * @param {string} message Non-personal failure context.
 * @return {void}
 */
function expect(condition, message) {
	if (!condition) throw new Error(message);
}
const identity = state('identity');
const origin = new URL(identity.origin).origin;
const parsed = new URL(origin);
if (
	!['http:', 'https:'].includes(parsed.protocol) ||
	!['127.0.0.1', '::1', '[::1]'].includes(parsed.hostname) ||
	!identity.mail_blocked ||
	!identity.http_blocked ||
	new URL(identity.admin).origin !== origin ||
	new URL(identity.login).origin !== origin
) {
	throw new Error('The exact loopback fixture and native side-effect blocks are required.');
}
const originalSettings = state();
/** Reads only the owned option through the guarded native helper. @since 0.4.0 @return {Object|null} Persisted configuration. */
const read = () => state();
/** Builds a fixed task view from Core's administrator URL. @since 0.4.0 @param {string} tab Allowlisted test view. @return {string} Same-origin administrator URL. */
const url = (tab) => {
	const target = new URL(identity.admin);
	target.searchParams.set('tab', tab);
	return target.href;
};
const results = {
	cases: [],
	errors: [],
	dialogs: [],
	blockedExternal: 0,
	wordpress: identity.wordpress,
	pass: false
};
const { firefox } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await firefox.launch({
	headless: true,
	...(process.env.PLAYWRIGHT_FIREFOX ? { executablePath: process.env.PLAYWRIGHT_FIREFOX } : {})
});
let seeded = false;
try {
	seeded = true;
	state('seed');
	const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
	await context.route('**/*', (r) => {
		if (new URL(r.request().url()).origin === origin) return r.continue();
		results.blockedExternal++;
		return r.abort();
	});
	const page = await context.newPage();
	page.setDefaultTimeout(10000);
	page.on('pageerror', (e) => results.errors.push(e.message));
	let dialogMode = 'accept';
	page.on('dialog', async (dialog) => {
		results.dialogs.push({ type: dialog.type(), mode: dialogMode });
		await (dialogMode === 'dismiss' ? dialog.dismiss() : dialog.accept());
	});
	await page.goto(identity.login);
	await page.locator('#user_login').fill(process.env.WP_ANTISPAM_ADMIN_USER);
	await page.locator('#user_pass').fill(process.env.WP_ANTISPAM_ADMIN_PASSWORD);
	await Promise.all([page.waitForURL(/wp-admin/), page.locator('#wp-submit').click()]);
	/** Saves the actual form and checks native notice/redirect behavior. @since 0.4.0 @param {string} tab Current task view. @return {Promise<void>} Completed native navigation. */
	const save = async (tab) => {
		const before = results.dialogs.length;
		await Promise.all([
			page.waitForURL(
				(u) =>
					u.searchParams.get('tab') === tab && u.searchParams.get('settings-updated') === 'true'
			),
			page.locator('[data-rk-settings-form] button[type=submit]').click()
		]);
		expect(results.dialogs.length === before, 'Saving must not trigger an unsaved-changes prompt.');
		expect(
			(await page.locator('#setting-error-settings_updated').count()) === 1,
			'Exactly one native settings notice is required.'
		);
	};
	await page.goto(url('settings'));
	let before = read();
	await page.locator('#rktsky-retention').fill('31');
	await save('settings');
	let after = read();
	expect(
		after.retention_days === 31 &&
			after.spam_keywords === before.spam_keywords &&
			after.allowed_languages.join() === before.allowed_languages.join() &&
			after.comments === before.comments,
		'Settings save lost another view.'
	);
	results.cases.push({ case: 'settings_native_save', noticeCount: 1, retainedOtherViews: true });
	await page.goto(url('integrations'));
	before = read();
	await page.locator('#rktsky-minimum').fill('2');
	await page.locator('#rktsky-comments').uncheck();
	await save('integrations');
	after = read();
	expect(
		after.minimum_seconds === 2 &&
			after.comments === false &&
			after.retention_days === 31 &&
			after.spam_keywords === before.spam_keywords,
		'Integration save lost another view.'
	);
	results.cases.push({
		case: 'integrations_native_save',
		noticeCount: 1,
		retainedOtherViews: true
	});
	await page.goto(url('rules'));
	before = read();
	await page.locator('#rktsky-keywords').fill('synthetic-admin-browser-updated');
	await page.locator('#rktsky-bbcode').check();
	await save('rules');
	after = read();
	expect(
		after.spam_keywords === 'synthetic-admin-browser-updated' &&
			after.bbcode_filter === true &&
			after.retention_days === 31 &&
			after.minimum_seconds === 2 &&
			after.comments === false,
		'Rule save lost another view.'
	);
	results.cases.push({ case: 'rules_native_save', noticeCount: 1, retainedOtherViews: true });
	for (const tab of ['integrations', 'rules', 'settings']) {
		await page.goto(url(tab));
		const field = page.locator(
			tab === 'integrations'
				? '#rktsky-minimum'
				: tab === 'rules'
					? '#rktsky-keywords'
					: '#rktsky-retention'
		);
		const original = await field.inputValue();
		const changed = tab === 'rules' ? original + ' changed' : String(Number(original) + 1);
		await field.fill(changed);
		dialogMode = 'dismiss';
		const n = results.dialogs.length;
		await page.locator('nav a[href*="tab=overview"]').click({ noWaitAfter: true });
		await page.waitForTimeout(150);
		expect(
			results.dialogs.length === n + 1 &&
				results.dialogs.at(-1).type === 'beforeunload' &&
				new URL(page.url()).searchParams.get('tab') === tab &&
				(await field.inputValue()) === changed,
			'Cancelling navigation did not retain unsaved ' + tab + ' changes.'
		);
		await field.fill(original);
		dialogMode = 'accept';
		const reverted = results.dialogs.length;
		await Promise.all([
			page.waitForURL((u) => u.searchParams.get('tab') === 'overview'),
			page.locator('nav a[href*="tab=overview"]').click()
		]);
		expect(
			results.dialogs.length === reverted,
			'Reverted native FormData still warned on ' + tab + '.'
		);
		results.cases.push({
			case: 'unsaved_cancel_and_revert_' + tab,
			promptedOnce: true,
			revertDoesNotPrompt: true
		});
	}
	const clean = results.dialogs.length;
	for (const tab of ['quarantine', 'overview']) await page.goto(url(tab));
	expect(results.dialogs.length === clean, 'Read-only navigation incorrectly warns.');
	results.cases.push({ case: 'readonly_navigation', prompts: 0 });
	await page.goto(url('rules'));
	await page.locator('#rktsky-keywords').fill('synthetic-admin-beforeunload-confirm');
	dialogMode = 'accept';
	const confirmed = results.dialogs.length;
	await Promise.all([
		page.waitForURL((u) => u.searchParams.get('tab') === 'integrations'),
		page.locator('nav a[href*="tab=integrations"]').click()
	]);
	expect(
		results.dialogs.length === confirmed + 1 &&
			read().spam_keywords === 'synthetic-admin-browser-updated',
		'Confirmed unsaved navigation persisted discarded content.'
	);
	results.cases.push({
		case: 'unsaved_confirm_leaves_without_save',
		promptedOnce: true,
		notPersisted: true
	});
	const nojs = await browser.newContext({
		javaScriptEnabled: false,
		storageState: await context.storageState(),
		viewport: { width: 390, height: 1000 }
	});
	await nojs.route('**/*', (r) =>
		new URL(r.request().url()).origin === origin ? r.continue() : r.abort()
	);
	const plain = await nojs.newPage();
	await plain.goto(url('integrations'));
	await plain.locator('#rktsky-minimum').fill('3');
	await Promise.all([
		plain.waitForURL(
			(u) =>
				u.searchParams.get('tab') === 'integrations' &&
				u.searchParams.get('settings-updated') === 'true'
		),
		plain.locator('[data-rk-settings-form] button[type=submit]').click()
	]);
	expect(
		read().minimum_seconds === 3 && read().spam_keywords === 'synthetic-admin-browser-updated',
		'No-JS integration save failed.'
	);
	await plain.goto(url('settings'));
	await plain.locator('#rktsky-quarantine-enabled').check();
	await plain.locator('#rktsky-notify').check();
	await plain.locator('#rktsky-privacy-mode').check();
	await Promise.all([
		plain.waitForURL(
			(u) =>
				u.searchParams.get('tab') === 'settings' &&
				u.searchParams.get('settings-updated') === 'true'
		),
		plain.locator('[data-rk-settings-form] button[type=submit]').click()
	]);
	after = read();
	expect(
		after.privacy_mode === true &&
			!after.quarantine_enabled &&
			!after.notify_admin &&
			after.minimum_seconds === 3,
		'No-JS server privacy enforcement failed.'
	);
	expect(
		(await plain.locator('#rktsky-quarantine-enabled').isDisabled()) &&
			(await plain.locator('#rktsky-notify').isDisabled()),
		'Server-rendered privacy locks are missing without JavaScript.'
	);
	results.cases.push({
		case: 'no_javascript_native_save',
		integrationSave: true,
		privacyEnforcedOnServer: true,
		retainedOtherViews: true
	});
	await nojs.close();
	expect(results.errors.length === 0, 'Unexpected browser script error.');
	results.browser = 'Firefox ' + browser.version();
	results.nativeSettingsPosts = 5;
	results.pass = true;
	console.log(
		JSON.stringify({
			pass: true,
			cases: results.cases.length,
			nativeSettingsPosts: results.nativeSettingsPosts,
			dialogs: results.dialogs.length,
			errors: results.errors
		})
	);
} catch (error) {
	results.failure = error.message;
	throw error;
} finally {
	try {
		await browser.close();
	} finally {
		if (seeded) results.restore = state('restore', JSON.stringify(originalSettings));
		const report =
			process.env.WP_ANTISPAM_ADMIN_REPORT ||
			join(tmpdir(), 'wp-antispam-admin-browser-results.json');
		writeFileSync(report, JSON.stringify(results, null, 2) + '\n');
		if (results.restore)
			expect(results.restore.restored, 'Original fixture settings were not restored exactly.');
		console.log(JSON.stringify({ restore: results.restore, report }));
	}
}

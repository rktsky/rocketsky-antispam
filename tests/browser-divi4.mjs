/**
 * Runs native Divi 4 theme/builder transports in a real Firefox browser.
 *
 * Only marked disposable loopback WordPress sites are accepted. The fixture MU
 * observer must intercept mail and all external HTTP. Successful native form
 * responses and local challenges are never mocked. Fault routes simulate only
 * explicit challenge outages, network loss and a cached original HTML document.
 *
 * Required: RKTSKY_ANTISPAM_ALLOW_TESTS=1 and RKTSKY_ANTISPAM_WP_ROOT. Optional:
 * RKTSKY_ANTISPAM_PLAYWRIGHT, RKTSKY_ANTISPAM_PHP, RKTSKY_ANTISPAM_PHP_ARGS (JSON array),
 * RKTSKY_ANTISPAM_DIVI4_CASES (comma-separated), RKTSKY_ANTISPAM_BROWSER_OUTPUT (JSON).
 *
 * @since 0.3.1
 */
import { readFileSync, realpathSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

if (process.env.RKTSKY_ANTISPAM_ALLOW_TESTS !== '1')
	throw Error('Explicit disposable-test authorization is required.');
const root = realpathSync(process.env.RKTSKY_ANTISPAM_WP_ROOT || '');
if (
	readFileSync(root + '/.rktsky-antispam-fixture', 'utf8').trim() !== 'rktsky-antispam-disposable'
)
	throw Error('A disposable fixture marker is required.');
const php = process.env.RKTSKY_ANTISPAM_PHP || 'php';
const phpArgs = JSON.parse(process.env.RKTSKY_ANTISPAM_PHP_ARGS || '[]');
if (!Array.isArray(phpArgs) || !phpArgs.every((arg) => typeof arg === 'string'))
	throw Error('PHP arguments must be a JSON array of strings.');
const helper = fileURLToPath(new URL('./prepare-divi4.php', import.meta.url));

/**
 * Changes only the native fixture option through its explicitly marked PHP helper.
 * @since 0.3.1
 * @param {...string} args Fixed helper mode and bounded test values.
 * @return {Object} Native helper result; failures stop the browser run.
 */
function fixture(...args) {
	return JSON.parse(
		execFileSync(php, [...phpArgs, helper, ...args], { env: process.env, encoding: 'utf8' }).trim()
	);
}
const initial = fixture('state');
const origin = new URL(initial.origin).origin;
if (!/^http:\/\/127\.0\.0\.1:(8884|8886)$/.test(origin))
	throw Error('Only the assigned Divi4 fixture origins are allowed.');
const selected = new Set((process.env.RKTSKY_ANTISPAM_DIVI4_CASES || '').split(',').filter(Boolean));
const { firefox } = await import(process.env.RKTSKY_ANTISPAM_PLAYWRIGHT || 'playwright');
const browser = await firefox.launch({ headless: true });
const results = [];
const run = 'browser-' + Date.now().toString(36);
const cases = [];

/**
 * Records a meaningful native-browser invariant without logging form contents.
 * @since 0.3.1
 * @param {boolean} value Required observable outcome.
 * @param {string} label Context on failure.
 * @return {void}
 */
function expect(value, label) {
	if (!value) throw Error(label);
}

/**
 * Registers a named independent case that can be rerun after a focused fix.
 * @since 0.3.1
 * @param {string} name Stable non-personal test label.
 * @param {Function} task Browser checks against real provider output.
 * @param {Object} options Native browser-context options.
 * @return {void}
 */
function add(name, task, options = {}) {
	cases.push({ name, task, options });
}

/**
 * Loads the real native page and waits for production local proof when requested.
 * @since 0.3.1
 * @param {Object} test Current isolated browser case.
 * @param {string} kind Synthetic page key.
 * @param {number} number Original form index on the page.
 * @param {boolean} ready Whether issuance is expected to work.
 * @return {Promise<Object>} Original form locator.
 */
async function load(test, kind = 'plain', number = 0, ready = true) {
	await test.page.goto(origin + '/?page_id=' + initial.pages[kind]);
	const form = test.page.locator('form.et_pb_contact_form').nth(number);
	await form.waitFor({ state: 'visible' });
	if (ready) {
		const input = form.locator('[name="rktsky_antispam_token"]');
		const deadline = Date.now() + 10000;
		await input.waitFor({ state: 'attached' });
		while ((await input.inputValue()).length <= 40) {
			if (Date.now() > deadline) throw Error('The genuine local challenge did not issue proof.');
			await test.page.waitForTimeout(25);
		}
	}
	return form;
}

/**
 * Fills only the synthetic original fields; own hidden controls stay untouched.
 * @since 0.3.1
 * @param {Object} form Original provider form locator.
 * @return {Promise<void>}
 */
async function fill(form) {
	await form.locator('[data-original_id="name"]').fill('Synthetic browser visitor');
	await form.locator('[data-original_id="email"]').fill('fixture@example.invalid');
	await form
		.locator('[data-original_id="message"]')
		.fill('Synthetic isolated Divi browser message.');
}

/**
 * Clicks the genuine native submitter and waits for its same-page POST response.
 * @since 0.3.1
 * @param {Object} test Current independent browser case.
 * @param {Object} form Native form locator.
 * @return {Promise<Object>} Real original-provider HTTP response.
 */
async function send(test, form) {
	const pending = test.page.waitForResponse(
		(response) => response.request().method() === 'POST' && response.url().includes('page_id=')
	);
	await form.getByRole('button', { name: 'Send synthetic' }).click();
	const response = await pending;
	await test.page.waitForTimeout(200);
	return response;
}

/**
 * Reads the native mail observer using a case-scoped loopback-only test endpoint.
 * @since 0.3.1
 * @param {Object} test Current independent browser case.
 * @return {Promise<Object>} Mail and original submit-action observations.
 */
async function state(test) {
	const response = await test.context.request.get(
		origin + '/index.php?rest_route=/rktsky-fixture/v1/state&case=' + test.tag
	);
	const value = await response.json();
	expect(
		Number.isInteger(value.mail) && Array.isArray(value.native),
		'The native side-effect observer is unavailable.'
	);
	return value;
}

/**
 * Requires an actually visible failure and a safe reload destination.
 * @since 0.3.1
 * @param {Object} test Current independent browser case.
 * @param {string} kind Expected synthetic page key.
 * @return {Promise<void>}
 */
async function rejected(test, kind = 'plain') {
	await test.page.getByText('Your submission could not be verified.').waitFor({ state: 'visible' });
	const link = test.page.getByRole('link', { name: 'Reload the form' });
	expect(await link.isVisible(), 'A rejected native submission needs a visible reload action.');
	expect(
		new URL(await link.getAttribute('href'), origin).searchParams.get('page_id') ===
			String(initial.pages[kind]),
		'Reload must preserve the actual public form page.'
	);
	const observed = await state(test);
	expect(
		observed.mail === 0 && observed.native.length === 0,
		'Rejected proof reached the original mail or submit action.'
	);
}

/**
 * Requires genuine native success and its exact intercepted mail-call count.
 * @since 0.3.1
 * @param {Object} test Current independent browser case.
 * @param {number} count Expected cumulative native mail calls for this case.
 * @return {Promise<void>}
 */
async function accepted(test, count = 1) {
	await test.page
		.getByText('Synthetic Divi accepted.', { exact: true })
		.last()
		.waitFor({ state: 'visible' });
	const observed = await state(test);
	expect(
		observed.mail === count,
		'A native accepted submission must send exactly the expected number of messages.'
	);
	expect(
		observed.native.filter((event) => event.error === false).length === count,
		'The native submit observer must agree with genuine successful delivery.'
	);
}

add('valid', async (test) => {
	const form = await load(test);
	await fill(form);
	expect((await send(test, form)).status() === 200, 'Native AJAX success needs HTTP 200.');
	await accepted(test);
});

for (const kind of ['plain', 'custom'])
	add(kind + '_trap_retry', async (test) => {
		let form = await load(test, kind);
		await fill(form);
		await form.locator('[data-rk-trap]').evaluate((input) => {
			input.value = 'synthetic-bot';
		});
		await send(test, form);
		await rejected(test, kind);
		await test.page.getByRole('link', { name: 'Reload the form' }).click();
		form = test.page.locator('form.et_pb_contact_form');
		await fill(form);
		await send(test, form);
		await accepted(test);
	});

for (const [name, value] of [
	['missing_proof', ''],
	['malformed_proof', 'malformed-synthetic-proof']
])
	add(name, async (test) => {
		const form = await load(test);
		await fill(form);
		await form.locator('[name="rktsky_antispam_token"]').evaluate((input, proof) => {
			input.value = proof;
		}, value);
		await send(test, form);
		await rejected(test);
	});

add('replay', async (test) => {
	const form = await load(test);
	await fill(form);
	const response = await send(test, form);
	await accepted(test);
	const replay = await test.context.request.post(response.url(), {
		data: response.request().postData(),
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-Requested-With': 'XMLHttpRequest'
		}
	});
	expect(
		(await replay.text()).includes('Your submission could not be verified.'),
		'A captured native POST must not be reusable.'
	);
	expect((await state(test)).mail === 1, 'A replay must not send a second message.');
});

add('native_nonce_retry', async (test) => {
	let form = await load(test);
	await fill(form);
	let invalidated = false;
	await test.page.route('**/*', (route) => {
		if (
			!invalidated &&
			route.request().method() === 'POST' &&
			route.request().url().includes('page_id=')
		) {
			invalidated = true;
			const data = new URLSearchParams(route.request().postData());
			for (const key of data.keys())
				if (key.startsWith('_wpnonce-et-pb-contact-form-submitted-'))
					data.set(key, 'invalid-native-nonce');
			return route.continue({ postData: data.toString() });
		}
		return route.fallback();
	});
	await send(test, form);
	expect((await state(test)).mail === 0, 'The unchanged provider nonce error must stop mail.');
	await test.page.locator('.et_pb_contact_error_text').first().waitFor({ state: 'visible' });
	// The original nonce error explicitly requests a page refresh. Its replaced
	// form has no native direct-submit binding until the provider initializes.
	await test.page.reload();
	form = test.page.locator('form.et_pb_contact_form');
	await fill(form);
	await send(test, form);
	await accepted(test);
});

add('client_required_retry', async (test) => {
	const form = await load(test);
	await fill(form);
	await form.locator('[data-original_id="name"]').fill('');
	await form.getByRole('button', { name: 'Send synthetic' }).click();
	await test.page.waitForTimeout(200);
	expect((await state(test)).mail === 0, 'An empty original required field must not deliver.');
	await form.locator('[data-original_id="name"]').fill('Synthetic corrected visitor');
	await send(test, form);
	await accepted(test);
});

add('native_honeypot', async (test) => {
	const form = await load(test);
	await fill(form);
	// D4 still verifies this reserved native trap although this fixture's
	// current markup does not render it. Exercise that original PHP boundary.
	await form.evaluate((element) => {
		const input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'et_pb_contact_et_number_0';
		input.value = 'synthetic-native-bot';
		element.append(input);
	});
	await send(test, form);
	const observed = await state(test);
	expect(
		observed.mail === 0 && observed.native.some((event) => event.error === true),
		'The original native honeypot must remain authoritative.'
	);
});

add('multiple_forms', async (test) => {
	await load(test, 'multiple');
	let form = test.page.locator('form.et_pb_contact_form').nth(1);
	await fill(form);
	await send(test, form);
	await accepted(test);
	form = test.page.locator('form.et_pb_contact_form').first();
	await fill(form);
	await send(test, form);
	await accepted(test, 2);
});

add('multiple_targets', async (test) => {
	const form = await load(test);
	await fill(form);
	await form.evaluate((element) => {
		const extra = document.createElement('input');
		extra.type = 'hidden';
		extra.name = 'et_pb_contactform_submit_1';
		extra.value = 'synthetic';
		element.append(extra);
	});
	await send(test, form);
	await rejected(test);
});

add('warm_cached_html', async (test) => {
	const url = origin + '/?page_id=' + initial.pages.plain;
	const html = await (await test.context.request.get(url)).text();
	await test.page.route(url, (route) =>
		route.request().method() === 'GET'
			? route.fulfill({ contentType: 'text/html', body: html })
			: route.continue()
	);
	const tokens = [];
	for (let attempt = 0; attempt < 2; attempt++) {
		const form = await load(test);
		tokens.push(await form.locator('[name="rktsky_antispam_token"]').inputValue());
		await fill(form);
		await send(test, form);
		await accepted(test, attempt + 1);
	}
	expect(
		tokens[0] !== tokens[1],
		'Repeated cached native HTML must obtain independent real challenges.'
	);
});

add('minimum_two', async (test) => {
	fixture('minimum', '2');
	try {
		const started = Date.now();
		const form = await load(test);
		await fill(form);
		await send(test, form);
		await accepted(test);
		expect(Date.now() - started >= 1900, 'The browser must honour the configured proof minimum.');
	} finally {
		fixture('minimum', '0');
	}
});

for (const failure of ['503', 'abort'])
	add('challenge_' + failure, async (test) => {
		let failing = true;
		let challenges = 0;
		await test.page.route('**/*', (route) => {
			if (
				route.request().method() === 'POST' &&
				route.request().url().includes('rktsky-antispam/v1/challenge')
			) {
				challenges++;
				if (failing)
					return failure === '503'
						? route.fulfill({ status: 503, body: 'Synthetic local outage' })
						: route.abort('failed');
			}
			return route.fallback();
		});
		let form = await load(test, 'plain', 0, false);
		await fill(form);
		await send(test, form);
		await rejected(test);
		expect(
			challenges >= 1 && challenges <= 2,
			'An unavailable challenge must not create an automatic retry loop.'
		);
		failing = false;
		await test.page.getByRole('link', { name: 'Reload the form' }).click();
		form = test.page.locator('form.et_pb_contact_form');
		await fill(form);
		await send(test, form);
		await accepted(test);
	});

add(
	'no_javascript',
	async (test) => {
		const form = await load(test, 'plain', 0, false);
		await fill(form);
		expect(
			(await send(test, form)).status() === 403,
			'JavaScript-disabled native POST must fail before native mail.'
		);
		expect((await state(test)).mail === 0, 'A no-JavaScript submission must not send mail.');
		await test.page.getByText('Your submission could not be verified.').waitFor({ state: 'visible' });
	},
	{ javaScriptEnabled: false }
);

add('double_click', async (test) => {
	const form = await load(test);
	await fill(form);
	const box = await form.getByRole('button', { name: 'Send synthetic' }).boundingBox();
	await test.page.mouse.click(box.x + box.width / 2, box.y + box.height / 2, {
		clickCount: 2,
		delay: 20
	});
	await accepted(test);
	await test.page.waitForTimeout(200);
	expect((await state(test)).mail === 1, 'Double clicking must not duplicate native delivery.');
});

add('native_ajax_network', async (test) => {
	let blocked = 0;
	await test.page.route('**/*', (route) => {
		if (route.request().method() === 'POST' && route.request().url().includes('page_id=')) {
			blocked++;
			return route.abort('failed');
		}
		return route.fallback();
	});
	const form = await load(test);
	await fill(form);
	await form.getByRole('button', { name: 'Send synthetic' }).click();
	await test.page.waitForTimeout(600);
	expect(
		blocked === 1 && (await state(test)).mail === 0,
		'A failed native AJAX transport must not deliver or loop.'
	);
	test.note =
		'Native AJAX network failure: ' +
		(await test.page.locator('.et-pb-contact-message').allTextContents())
			.map((text) => text.trim())
			.join(' | ');
	await test.page.reload();
	await test.page.unroute('**/*');
	await fill(test.page.locator('form.et_pb_contact_form'));
	await send(test, test.page.locator('form.et_pb_contact_form'));
	await accepted(test);
});

try {
	fixture('minimum', '0');
	for (const entry of cases) {
		if (selected.size && !selected.has(entry.name)) continue;
		const tag = run + '-' + entry.name;
		const context = await browser.newContext({
			viewport: { width: 1280, height: 900 },
			extraHTTPHeaders: { 'X-Rk-Fixture-Case': tag },
			...entry.options
		});
		let blockedExternal = 0;
		await context.route('**/*', (route) => {
			if (new URL(route.request().url()).origin === origin) return route.continue();
			blockedExternal++;
			return route.abort('blockedbyclient');
		});
		const page = await context.newPage();
		page.setDefaultTimeout(10000);
		const errors = [];
		page.on('pageerror', (error) => errors.push(error.message));
		const test = { tag, context, page, note: '' };
		const started = Date.now();
		try {
			await entry.task(test);
			expect(errors.length === 0, 'A native browser exception occurred: ' + errors.join('; '));
			results.push({
				case: entry.name,
				pass: true,
				milliseconds: Date.now() - started,
				blockedExternal,
				browserErrors: errors,
				note: test.note
			});
		} catch (error) {
			results.push({
				case: entry.name,
				pass: false,
				milliseconds: Date.now() - started,
				blockedExternal,
				error: error.message,
				browserErrors: errors
			});
		} finally {
			await context.close();
		}
		console.log(JSON.stringify(results.at(-1)));
	}
} finally {
	fixture('minimum', String(initial.minimum));
	await browser.close();
}
const output = {
	origin,
	browser: 'Firefox ' + browser.version(),
	viewport: '1280x900 CSS pixels, headless desktop; no device emulation',
	results
};
if (process.env.RKTSKY_ANTISPAM_BROWSER_OUTPUT)
	writeFileSync(process.env.RKTSKY_ANTISPAM_BROWSER_OUTPUT, JSON.stringify(output, null, 2) + '\n');
console.log(
	JSON.stringify({
		origin,
		passed: results.filter((result) => result.pass).length,
		total: results.length
	})
);
if (results.some((result) => !result.pass)) process.exitCode = 1;

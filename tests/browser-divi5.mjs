/**
 * Exercises original Divi 5, legacy and converted forms in a real Firefox browser.
 *
 * The unmodified theme remains outside the repository. Native mail/actions are
 * observed by the private fixture; actual mail and external HTTP remain blocked.
 *
 * @since 0.3.1
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { randomBytes } from 'node:crypto';

const root = process.env.WP_ANTISPAM_WP_ROOT;
if (
	process.env.WP_ANTISPAM_ALLOW_TESTS !== '1' ||
	!root ||
	readFileSync(root + '/.rktsky-antispam-fixture', 'utf8').trim() !== 'rktsky-antispam-disposable'
) {
	throw new Error('Explicit disposable native fixture authorization is required.');
}
const php = process.env.PHP_BINARY || 'php';
const cli = process.env.WP_ANTISPAM_WP_CLI;
const phpArgs = [];
for (const name of ['WP_ANTISPAM_PHP_ICONV', 'WP_ANTISPAM_PHP_SIMPLEXML']) {
	if (process.env[name]) {
		phpArgs.push('-d', 'extension=' + process.env[name]);
	}
}

/**
 * Uses structured native WordPress CLI arguments only on the marked local fixture.
 *
 * @since 0.3.1
 * @param {...string} args Original CLI arguments.
 * @return {string} Trimmed native standard output.
 */
function wp(...args) {
	return execFileSync(php, [...phpArgs, cli, ...args, '--path=' + root], {
		encoding: 'utf8'
	}).trim();
}
const base = wp('option', 'get', 'home');
if (base !== 'http://127.0.0.1:8885') {
	throw new Error('The Divi 5 browser fixture must use its dedicated loopback origin.');
}
const safety = JSON.parse(
	wp(
		'eval',
		'echo wp_json_encode(array("local"=>wp_get_environment_type(),"fixture"=>defined("WP_ANTISPAM_TEST_FIXTURE")&&WP_ANTISPAM_TEST_FIXTURE,"observer"=>function_exists("rktsky_divi5_event"),"mail"=>false!==has_filter("pre_wp_mail","__return_true")&&false!==has_filter("pre_wp_mail","rktsky_divi5_fixture_mail"),"http"=>false!==has_filter("pre_http_request","rktsky_divi5_fixture_http"),"log"=>defined("RKTSKY_DIVI5_FIXTURE_LOG")?RKTSKY_DIVI5_FIXTURE_LOG:null));'
	)
);
if (
	safety.local !== 'local' ||
	safety.fixture !== true ||
	!safety.observer ||
	!safety.mail ||
	!safety.http ||
	typeof safety.log !== 'string'
) {
	throw new Error('The isolated delivery/HTTP observer fixture is missing.');
}
const fixture = JSON.parse(wp('option', 'get', 'rktsky_antispam_test_divi5', '--format=json'));
const original = JSON.parse(wp('option', 'get', 'rktsky_antispam_settings', '--format=json'));
const { firefox } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await firefox.launch({
	headless: true,
	...(process.env.PLAYWRIGHT_FIREFOX ? { executablePath: process.env.PLAYWRIGHT_FIREFOX } : {})
});
const results = [];
const log = safety.log;

/**
 * Restricts every browser context to this fixture before any original asset loads.
 *
 * @since 0.3.1
 * @param {Object} options Original browser context options.
 * @return {Promise<Object>} Isolated context with external requests denied.
 */
async function localContext(options = {}) {
	const context = await browser.newContext(options);
	await context.route('**/*', (route) =>
		new URL(route.request().url()).origin === base ? route.continue() : route.abort()
	);
	return context;
}

/**
 * Reads counts of genuine native theme/mail events for one synthetic browser case.
 *
 * @since 0.3.1
 * @param {string} label Local correlation identifier, without visitor data.
 * @return {Object} Native boundary counts.
 */
function counts(label) {
	const data = { mail_attempt: 0, native_submit: 0, native_recipient: 0 };
	for (const line of readFileSync(log, 'utf8').split('\n').filter(Boolean)) {
		const event = JSON.parse(line);
		if (event.case === label && event.event in data) {
			++data[event.event];
		}
	}
	return data;
}

/**
 * Returns only this local form's native final POST, excluding challenge issuance.
 *
 * @since 0.3.1
 * @param {Object} response Original Playwright network response.
 * @param {number} id Native synthetic page ID.
 * @return {boolean} Whether this is the page's final form submission.
 */
function finalPost(response, id) {
	return (
		response.request().method() === 'POST' &&
		new URL(response.url()).searchParams.get('page_id') === String(id)
	);
}

/**
 * Fills only visible synthetic provider inputs through the browser's native controls.
 *
 * @since 0.3.1
 * @param {Object} form Original native Divi form locator.
 * @param {boolean} complete Whether the native required message is filled.
 * @return {Promise<void>}
 */
async function fill(form, complete = true) {
	await form.locator('[data-original_id="name"]').fill('Synthetic browser fixture');
	await form.locator('[data-original_id="email"]').fill('fixture@example.invalid');
	await form
		.locator('[data-original_id="message"]')
		.fill(complete ? 'Synthetic browser message.' : '');
}

/**
 * Waits for the genuine shared browser protocol to initialize the owning form.
 *
 * @since 0.3.1
 * @param {Object} page Original Playwright page.
 * @param {number} index Actual form index, defaulting to the first native form.
 * @return {Promise<void>}
 */
async function ready(page, index = 0) {
	await page.waitForFunction(
		(index) =>
			window.rktskyAntispamBridge &&
			document
				.querySelectorAll('form.et_pb_contact_form')
				[index]?.querySelector('[data-rk-antispam]'),
		index
	);
	if (
		!(await page.evaluate(
			async (index) =>
				window.rktskyAntispamBridge.ensure(
					document.querySelectorAll('form.et_pb_contact_form')[index]
				),
			index
		))
	) {
		throw new Error('The original local challenge did not become ready.');
	}
}

/**
 * Uses native provider success text and actual pre-delivery counters to verify one send.
 *
 * @since 0.3.1
 * @param {Object} page Real browser page.
 * @param {string} label Synthetic correlation label.
 * @param {number} mail Expected cumulative native mail attempts.
 * @return {Promise<void>}
 */
async function accepted(page, label, mail = 1) {
	await page.waitForFunction(() => document.body.innerText.includes('accepted.'));
	const events = counts(label);
	if (
		events.mail_attempt !== mail ||
		events.native_submit !== mail ||
		events.native_recipient !== mail
	) {
		throw new Error('Native send/actions differ: ' + JSON.stringify(events));
	}
}

try {
	for (const minimum of process.env.WP_ANTISPAM_DIVI5_EXTENDED_ONLY === '1' ? [] : [0, 2]) {
		wp(
			'option',
			'update',
			'rktsky_antispam_settings',
			JSON.stringify({ ...original, minimum_seconds: minimum }),
			'--format=json'
		);
		for (const kind of ['native', 'legacy', 'migrated']) {
			for (const failure of ['honeypot', 'required', 'challenge_503', 'challenge_network']) {
				const label =
					'd5_' + kind + '_' + failure + '_' + minimum + '_' + randomBytes(4).toString('hex');
				const context = await localContext({ extraHTTPHeaders: { 'X-Rk-Case': label } });
				try {
					const page = await context.newPage();
					const errors = [];
					let fault = '';
					let challenges = 0;
					let posts = 0;
					const proofAges = [];
					page.on('pageerror', (error) => errors.push(error.message));
					page.on('request', (request) => {
						if (
							request.method() === 'POST' &&
							new URL(request.url()).searchParams.get('page_id') === String(fixture[kind])
						) {
							++posts;
							const token = new URLSearchParams(request.postData() || '').get(
								'rktsky_antispam_token'
							);
							if (token) {
								const issued = Number(
									Buffer.from(token.split('.')[0], 'base64url').toString().split('|')[3]
								);
								proofAges.push(Math.floor(Date.now() / 1000) - issued);
							}
						}
					});
					await page.route('**/*', async (route) => {
						const url = route.request().url();
						if (new URL(url).origin !== base) {
							return route.abort();
						}
						if (url.includes('/rktsky-antispam/v1/challenge')) {
							++challenges;
							if (fault) {
								const mode = fault;
								fault = '';
								return mode === 'challenge_503'
									? route.fulfill({ status: 503, body: '{}' })
									: route.abort();
							}
						}
						return route.continue();
					});
					await page.goto(base + '/?page_id=' + fixture[kind]);
					await ready(page);
					let form = page.locator('form.et_pb_contact_form').first();
					await fill(form, failure !== 'required');
					if (failure === 'honeypot') {
						await form.locator('[data-rk-trap]').evaluate((element) => {
							element.value = 'synthetic-bot';
						});
					} else if (failure.startsWith('challenge_')) {
						await page.evaluate(() =>
							window.rktskyAntispamBridge.consume(document.querySelector('form.et_pb_contact_form'))
						);
						fault = failure;
					}
					if (failure === 'required') {
						await form.locator('.et_pb_contact_submit').click();
						await page.waitForTimeout(450);
						if (
							posts !== 0 ||
							counts(label).mail_attempt !== 0 ||
							counts(label).native_submit !== 0
						) {
							throw new Error(
								'Native client required-field rejection reached a server-side action.'
							);
						}
					} else {
						const rejected = page.waitForResponse((response) => finalPost(response, fixture[kind]));
						await form.locator('.et_pb_contact_submit').click();
						if ((await rejected).status() !== 200) {
							throw new Error(
								'Native Divi AJAX rejection must return its selectable error fragment.'
							);
						}
						await page.getByRole('link', { name: 'Reload the form', exact: true }).waitFor();
						if (counts(label).mail_attempt !== 0 || counts(label).native_submit !== 0) {
							throw new Error('A local protection rejection reached native delivery/actions.');
						}
					}
					const beforePosts = posts;
					const beforeChallenges = challenges;
					if (failure !== 'required') {
						await page.getByRole('link', { name: 'Reload the form', exact: true }).click();
					}
					form = page.locator('form.et_pb_contact_form').first();
					await fill(form);
					const started = Date.now();
					const success = page.waitForResponse((response) => finalPost(response, fixture[kind]));
					await form.locator('.et_pb_contact_submit').click();
					if ((await success).status() !== 200) {
						throw new Error('Original retry transport failed.');
					}
					await accepted(page, label);
					if (
						posts - beforePosts !== 1 ||
						challenges - beforeChallenges !== 1 ||
						errors.length ||
						proofAges.some((age) => age < minimum)
					) {
						throw new Error(
							'Retry duplicated, missed fresh proof, violated timing or raised browser errors.'
						);
					}
					results.push({
						kind,
						failure,
						minimum,
						accepted: true,
						retryPosts: posts - beforePosts,
						retryChallenges: challenges - beforeChallenges,
						retryMs: Date.now() - started,
						jsErrors: errors.length,
						mailAttempts: counts(label).mail_attempt,
						proofAges
					});
					console.log('PASS:', kind, failure, minimum);
				} finally {
					await context.close();
				}
			}
		}
	}
	wp(
		'option',
		'update',
		'rktsky_antispam_settings',
		JSON.stringify({ ...original, minimum_seconds: 0 }),
		'--format=json'
	);
	// Native container identity must survive a custom CSS ID during AJAX rejection.
	{
		const label = 'd5_custom_' + randomBytes(4).toString('hex');
		const context = await localContext({ extraHTTPHeaders: { 'X-Rk-Case': label } });
		try {
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', (error) => errors.push(error.message));
			await page.goto(base + '/?page_id=' + fixture.custom);
			await ready(page);
			const form = page.locator('#rktsky-custom-contact form');
			await fill(form);
			await form.locator('[data-rk-trap]').evaluate((element) => {
				element.value = 'synthetic-bot';
			});
			const blocked = page.waitForResponse((response) => finalPost(response, fixture.custom));
			await form.locator('.et_pb_contact_submit').click();
			await blocked;
			await page
				.locator('#rktsky-custom-contact')
				.getByRole('link', { name: 'Reload the form', exact: true })
				.waitFor();
			if (counts(label).mail_attempt || counts(label).native_submit || errors.length) {
				throw new Error('Custom native container rejection escaped or became invisible.');
			}
			results.push({
				kind: 'custom_css_id',
				blocked: true,
				mailAttempts: 0,
				nativeActions: 0,
				jsErrors: errors.length
			});
			console.log('PASS: custom CSS ID rejection selector');
		} finally {
			await context.close();
		}
	}
	// Serve an unchanged original GET response as cached HTML; final POSTs remain real.
	for (const kind of ['native', 'legacy', 'migrated']) {
		const label = 'd5_cache_' + kind + '_' + randomBytes(4).toString('hex');
		const tokens = [];
		const intents = [];
		const url = base + '/?page_id=' + fixture[kind];
		const seedContext = await localContext();
		const cached = await (await seedContext.request.get(url)).text();
		await seedContext.close();
		for (let attempt = 0; attempt < 2; ++attempt) {
			const context = await localContext({ extraHTTPHeaders: { 'X-Rk-Case': label } });
			try {
				const page = await context.newPage();
				const errors = [];
				page.on('pageerror', (error) => errors.push(error.message));
				await page.route(url, (route) =>
					route.request().method() === 'GET'
						? route.fulfill({ status: 200, contentType: 'text/html', body: cached })
						: route.continue()
				);
				await page.goto(url);
				await ready(page);
				const form = page.locator('form.et_pb_contact_form').first();
				tokens.push(await form.locator('[name=rktsky_antispam_token]').inputValue());
				intents.push(await form.getAttribute('data-rk-antispam-intent'));
				await fill(form);
				const response = page.waitForResponse((response) => finalPost(response, fixture[kind]));
				await form.locator('.et_pb_contact_submit').click();
				await response;
				await accepted(page, label, attempt + 1);
				if (errors.length) {
					throw new Error('Cached native HTML raised browser errors.');
				}
			} finally {
				await context.close();
			}
		}
		if (tokens[0] === tokens[1] || intents[0] !== intents[1]) {
			throw new Error('Identical cached HTML did not obtain independent fresh signed proof.');
		}
		results.push({
			kind: 'cached_' + kind,
			originalCachedHtml: true,
			independentFreshProof: true,
			mailAttempts: 2,
			jsErrors: 0
		});
		console.log('PASS: cached HTML', kind);
	}
	// Two native forms must remain independently usable after replacing one container.
	{
		const label = 'd5_multiple_' + randomBytes(4).toString('hex');
		const context = await localContext({ extraHTTPHeaders: { 'X-Rk-Case': label } });
		try {
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', (error) => errors.push(error.message));
			await page.goto(base + '/?page_id=' + fixture.multiple);
			await ready(page, 0);
			await ready(page, 1);
			const wrappers = await page
				.locator('form.et_pb_contact_form')
				.evaluateAll((forms) =>
					forms.map((form) => form.closest('.et_pb_contact_form_container').id)
				);
			for (let index = 0; index < 2; ++index) {
				const form = page.locator('#' + wrappers[index] + ' form');
				await fill(form);
				const response = page.waitForResponse((response) => finalPost(response, fixture.multiple));
				await form.locator('.et_pb_contact_submit').click();
				await response;
				await accepted(page, label, index + 1);
			}
			if (errors.length) {
				throw new Error('Multiple native forms raised a browser error.');
			}
			results.push({
				kind: 'multiple_independent',
				mailAttempts: 2,
				nativeActions: 2,
				jsErrors: 0
			});
			console.log('PASS: two independent native forms');
		} finally {
			await context.close();
		}
	}
	// Divi's own arithmetic validator must remain effective before a corrected retry.
	{
		const label = 'd5_captcha_' + randomBytes(4).toString('hex');
		const context = await localContext({ extraHTTPHeaders: { 'X-Rk-Case': label } });
		try {
			const page = await context.newPage();
			const errors = [];
			let posts = 0;
			page.on('pageerror', (error) => errors.push(error.message));
			page.on('request', (request) => {
				if (
					request.method() === 'POST' &&
					new URL(request.url()).searchParams.get('page_id') === String(fixture.captcha)
				) {
					++posts;
				}
			});
			await page.goto(base + '/?page_id=' + fixture.captcha);
			await ready(page);
			const form = page.locator('form.et_pb_contact_form').first();
			await fill(form);
			const input = form.locator('input[name^=et_pb_contact_captcha_]');
			await input.fill('999999');
			await form.locator('.et_pb_contact_submit').click();
			await page.waitForTimeout(400);
			if (posts || counts(label).mail_attempt || counts(label).native_submit) {
				throw new Error('The original client arithmetic captcha was bypassed.');
			}
			const question = await form.locator('.et_pb_contact_captcha_question').innerText();
			const numbers = question.match(/[0-9]+/g).map(Number);
			await input.fill(String(numbers[0] + numbers[1]));
			const response = page.waitForResponse((response) => finalPost(response, fixture.captcha));
			await form.locator('.et_pb_contact_submit').click();
			await response;
			await accepted(page, label);
			if (posts !== 1 || errors.length) {
				throw new Error('The corrected native arithmetic captcha did not retry exactly once.');
			}
			results.push({
				kind: 'native_arithmetic_captcha',
				rejectedLocally: true,
				correctedPosts: 1,
				mailAttempts: 1,
				jsErrors: errors.length
			});
			console.log('PASS: native arithmetic captcha retry');
		} finally {
			await context.close();
		}
	}
	// The browser's full-page native POST remains guarded even without Divi's AJAX.
	{
		const label = 'd5_fullpage_' + randomBytes(4).toString('hex');
		const context = await localContext({ extraHTTPHeaders: { 'X-Rk-Case': label } });
		try {
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', (error) => errors.push(error.message));
			await page.goto(base + '/?page_id=' + fixture.native);
			await ready(page);
			const form = page.locator('form.et_pb_contact_form').first();
			await fill(form);
			const response = page.waitForResponse((response) => finalPost(response, fixture.native));
			await form.evaluate((form) => HTMLFormElement.prototype.submit.call(form));
			const native = await response;
			await accepted(page, label);
			if (native.status() !== 200 || errors.length) {
				throw new Error('The native browser full-page POST did not complete safely.');
			}
			results.push({
				kind: 'native_full_page_post',
				status: 200,
				mailAttempts: 1,
				jsErrors: errors.length
			});
			console.log('PASS: native full-page POST with proof');
		} finally {
			await context.close();
		}
	}
	// Without browser JavaScript, the missing evidence fails visibly before native actions.
	{
		const label = 'd5_nojs_' + randomBytes(4).toString('hex');
		const context = await localContext({
			javaScriptEnabled: false,
			extraHTTPHeaders: { 'X-Rk-Case': label }
		});
		try {
			const page = await context.newPage();
			await page.goto(base + '/?page_id=' + fixture.native);
			const form = page.locator('form.et_pb_contact_form').first();
			await fill(form);
			const response = page.waitForResponse((response) => finalPost(response, fixture.native));
			await form.locator('.et_pb_contact_submit').click();
			const native = await response;
			if (native.status() !== 403 || counts(label).mail_attempt || counts(label).native_submit) {
				throw new Error('The no-JavaScript full-page POST escaped protection.');
			}
			await page.getByText(/spam protection could not verify/).waitFor();
			results.push({ kind: 'no_javascript', status: 403, mailAttempts: 0, nativeActions: 0 });
			console.log('PASS: no-JavaScript full-page rejection');
		} finally {
			await context.close();
		}
	}

	const report = {
		browser: 'Firefox ' + browser.version(),
		platform: process.platform + '/' + process.arch,
		theme: 'Divi 5.19.0',
		cases: results
	};
	writeFileSync(
		process.env.WP_ANTISPAM_BROWSER_REPORT ||
			(process.env.WP_ANTISPAM_DIVI5_EXTENDED_ONLY === '1'
				? '/tmp/rktsky-antispam-divi/browser-divi5-extended-results.json'
				: '/tmp/rktsky-antispam-divi/browser-divi5-results.json'),
		JSON.stringify(report, null, 2) + '\n'
	);
	console.log(
		'PASS:',
		results.length,
		'original Divi browser retry cases; no actual mail delivery.'
	);
} finally {
	await browser.close();
	wp('option', 'update', 'rktsky_antispam_settings', JSON.stringify(original), '--format=json');
}

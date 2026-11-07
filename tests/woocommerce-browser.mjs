/**
 * Exercises native classic/block checkout retries in an explicitly disposable site.
 *
 * Run after prepare-woocommerce.php with WP_ANTISPAM_ALLOW_TESTS=1, the fixture root,
 * WP_ANTISPAM_WP_CLI and optional PHP_BINARY/PLAYWRIGHT_MODULE/PLAYWRIGHT_FIREFOX set.
 * Browser delivery is restricted to the fixture origin and native offline BACS.
 *
 * @since 0.3.0
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';

const root = process.env.WP_ANTISPAM_WP_ROOT;
if (
	process.env.WP_ANTISPAM_ALLOW_TESTS !== '1' ||
	!root ||
	readFileSync(root + '/.rktsky-antispam-fixture', 'utf8').trim() !== 'rktsky-antispam-disposable'
) {
	throw new Error('An explicitly authorized disposable WordPress fixture is required.');
}
const php = process.env.PHP_BINARY || 'php';
const cli = process.env.WP_ANTISPAM_WP_CLI;
if (!cli) {
	throw new Error('Set WP_ANTISPAM_WP_CLI to the original WP-CLI phar.');
}
const phpArgs = process.env.WP_ANTISPAM_PHP_ICONV
	? ['-d', 'extension=' + process.env.WP_ANTISPAM_PHP_ICONV]
	: [];

/**
 * Uses structured native CLI arguments against this marked test site only.
 *
 * @since 0.3.0
 * @param {...string} args Original WP-CLI arguments.
 * @return {string} Trimmed standard output.
 */
function wp(...args) {
	return execFileSync(php, [...phpArgs, cli, ...args, '--path=' + root], {
		encoding: 'utf8'
	}).trim();
}
const safety = JSON.parse(
	wp(
		'eval',
		'echo wp_json_encode(array("environment"=>wp_get_environment_type(),"fixture"=>defined("WP_ANTISPAM_TEST_FIXTURE")&&WP_ANTISPAM_TEST_FIXTURE,"mail_blocked"=>false===apply_filters("pre_wp_mail",null,array()),"http_blocked"=>defined("WP_HTTP_BLOCK_EXTERNAL")&&WP_HTTP_BLOCK_EXTERNAL));'
	)
);
if (
	safety.environment !== 'local' ||
	safety.fixture !== true ||
	safety.mail_blocked !== true ||
	safety.http_blocked !== true
) {
	throw new Error('The local fixture configuration must block mail and external WordPress HTTP.');
}
const original = JSON.parse(wp('option', 'get', 'rktsky_antispam_settings', '--format=json'));
const originalPage = wp('option', 'get', 'woocommerce_checkout_page_id');
const fixture = JSON.parse(
	wp('option', 'get', 'rktsky_antispam_test_woocommerce', '--format=json')
);
const base = wp('option', 'get', 'home');
if (!['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname)) {
	throw new Error('The browser fixture must use a loopback HTTP origin.');
}
const { firefox } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright');
const browser = await firefox.launch({
	headless: true,
	...(process.env.PLAYWRIGHT_FIREFOX ? { executablePath: process.env.PLAYWRIGHT_FIREFOX } : {})
});
const results = [];
const orders = [];

/**
 * Recognizes only the final native checkout request for the active frontend.
 *
 * @since 0.3.0
 * @param {string} url Observed same-site request URL.
 * @return {boolean} Whether it is final classic or Store API checkout.
 */
function finalCheckout(url) {
	const parsed = new URL(url);
	return (
		parsed.searchParams.get('wc-ajax') === 'checkout' ||
		/^\/wc\/store(?:\/v1)?\/checkout$/i.test(
			parsed.searchParams.get('rest_route') || parsed.pathname.replace(/^.*\/wp-json/, '')
		)
	);
}

try {
	for (const minimum of [0, 2]) {
		wp(
			'option',
			'update',
			'rktsky_antispam_settings',
			JSON.stringify({ ...original, minimum_seconds: minimum }),
			'--format=json'
		);
		for (const transport of ['classic', 'block']) {
			wp('option', 'update', 'woocommerce_checkout_page_id', String(fixture[transport]));
			for (const failure of ['honeypot', 'removed_trap', 'challenge_503', 'challenge_network']) {
				const context = await browser.newContext();
				try {
					const page = await context.newPage();
					const errors = [];
					let fault = '';
					let challenges = 0;
					let posts = 0;
					page.on('pageerror', (error) => errors.push(error.message));
					page.on('request', (request) => {
						if (request.method() === 'POST' && finalCheckout(request.url())) {
							++posts;
						}
					});
					await page.route('**/*', async (route) => {
						const url = route.request().url();
						if (new URL(url).origin !== new URL(base).origin) {
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
					await page.goto(base + '/?add-to-cart=' + fixture.product);
					await page.goto(base + '/?page_id=' + fixture[transport]);
					const values = {
						first_name: 'Synthetic',
						last_name: 'Browser',
						address_1: 'Synthetic 1',
						postcode: '3000',
						city: 'Bern',
						email: 'synthetic-' + minimum + '-' + transport + '-' + failure + '@example.invalid'
					};
					for (const [field, value] of Object.entries(values)) {
						await page
							.locator(
								transport === 'classic'
									? '[name=billing_' + field + ']'
									: field === 'email'
										? '#email'
										: '#billing-' + field
							)
							.fill(value);
					}
					// Native address updates can replace the entire classic payment section.
					await page.waitForTimeout(1800);
					await page.evaluate(async () =>
						window.rktskyAntispamBridge.ensure(
							document.querySelector('[data-rk-woocommerce-checkout] [data-rk-antispam]')
						)
					);
					if (failure === 'honeypot') {
						await page.locator('[data-rk-trap]').evaluate((element) => {
							element.value = 'synthetic-bot';
						});
					} else if (failure === 'removed_trap') {
						await page.locator('[data-rk-trap]').evaluate((element) => element.remove());
					} else {
						await page.evaluate(() =>
							window.rktskyAntispamBridge.consume(
								document.querySelector('[data-rk-woocommerce-checkout] [data-rk-antispam]')
							)
						);
						fault = failure;
					}
					const button =
						transport === 'classic'
							? page.locator('#place_order')
							: page.getByRole('button', { name: 'Place Order', exact: true });
					const rejected = page.waitForResponse(
						(response) => response.request().method() === 'POST' && finalCheckout(response.url())
					);
					await button.click();
					const rejectedResponse = await rejected;
					const rejection = await rejectedResponse.json();
					if (
						transport === 'classic'
							? rejection.result !== 'failure' || !rejection.messages.includes('spam protection')
							: rejection.code !== 'rktsky_antispam_rejected' || rejectedResponse.status() !== 403
					) {
						throw new Error(
							'Native checkout did not display the local rejection: ' + JSON.stringify(rejection)
						);
					}
					await page.waitForTimeout(100);
					const beforeChallenges = challenges;
					const beforePosts = posts;
					const started = Date.now();
					const accepted = page.waitForResponse(
						(response) => response.request().method() === 'POST' && finalCheckout(response.url())
					);
					await button.click();
					const response = await accepted;
					const result = await response.json();
					if (
						transport === 'classic'
							? result.result !== 'success'
							: !result.order_id || result.payment_result?.payment_status !== 'success'
					) {
						throw new Error('Native checkout retry did not succeed: ' + JSON.stringify(result));
					}
					orders.push(Number(result.order_id));
					const elapsed = Date.now() - started;
					if (
						posts - beforePosts !== 1 ||
						challenges - beforeChallenges !== 1 ||
						errors.length ||
						elapsed < minimum * 1000
					) {
						throw new Error(
							'Retry duplicated, skipped fresh proof, violated timing or raised a browser error.'
						);
					}
					results.push({
						minimum,
						transport,
						failure,
						accepted: true,
						retryPosts: posts - beforePosts,
						retryChallenges: challenges - beforeChallenges,
						elapsed,
						jsErrors: errors.length
					});
					console.log('PASS:', minimum, transport, failure, elapsed + 'ms');
				} finally {
					await context.close();
				}
			}
		}
	}
	const report = {
		browser: 'Firefox ' + browser.version(),
		platform: process.platform + '/' + process.arch,
		cases: results
	};
	if (process.env.WP_ANTISPAM_BROWSER_REPORT) {
		writeFileSync(process.env.WP_ANTISPAM_BROWSER_REPORT, JSON.stringify(report, null, 2) + '\n');
	}
	console.log('PASS:', results.length, 'native WooCommerce browser retry cases.');
} finally {
	await browser.close();
	wp('option', 'update', 'rktsky_antispam_settings', JSON.stringify(original), '--format=json');
	wp('option', 'update', 'woocommerce_checkout_page_id', originalPage);
	if (orders.length) {
		wp(
			'eval',
			'foreach (array(' +
				orders.join(',') +
				') as $id) { $order=wc_get_order($id); if($order){$order->delete(true);} }'
		);
	}
}

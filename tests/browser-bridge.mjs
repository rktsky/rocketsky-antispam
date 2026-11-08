/**
 * Exercises actual browser submission, retry and DOM replacement behavior.
 *
 * Every request is intercepted on a synthetic loopback origin. No WordPress,
 * message delivery, visitor payload or external network service is involved.
 * Install Playwright in a temporary development directory and set
 * RKTSKY_ANTISPAM_PLAYWRIGHT to its index.mjs when it is not locally resolvable.
 *
 * @since 0.3.0
 */
const { firefox } = await import(process.env.RKTSKY_ANTISPAM_PLAYWRIGHT || 'playwright');
import { readFileSync } from 'node:fs';
const asset = readFileSync(new URL('../assets/protection.js', import.meta.url), 'utf8');
const browser = await firefox.launch({ headless: true });
const page = await browser.newPage();
page.setDefaultTimeout(5000);
const assertions = [];
let issued = 0;
let failureRequests = 0;
let failing = true;
const errors = [];
const calls = [];
/** Records observable browser behavior; artificial proofs are verified separately by native PHP tests.
 * @since 0.3.0
 * @param {boolean} value Required outcome.
 * @param {string} label Failure context without form contents.
 * @return {void}
 */
function expect(value, label) {
	if (!value) throw Error(label);
	assertions.push(label);
	console.log(label);
}
page.on('pageerror', (e) => errors.push(e.message));
await page.route('http://127.0.0.1:8891/**', async (route) => {
	const url = new URL(route.request().url());
	if (url.pathname === '/protection.js')
		return route.fulfill({ contentType: 'application/javascript', body: asset });
	if (url.pathname === '/challenge' || url.pathname === '/failure') {
		if (url.pathname === '/failure') {
			failureRequests++;
			if (failing) return route.fulfill({ status: 503, body: 'Synthetic unavailable challenge' });
		}
		calls.push(JSON.parse(route.request().postData()));
		issued++;
		await new Promise((done) => setTimeout(done, 30));
		return route.fulfill({
			contentType: 'application/json',
			body: JSON.stringify({
				token: 'synthetic-' + issued,
				trap: 'rktsky_as_' + String(issued).padStart(20, '0'),
				minimum: 0
			})
		});
	}
	return route.fulfill({
		contentType: 'text/html',
		body: `<!doctype html><html><body>
 <form id="ordinary"><input name="message" required value="Synthetic message"><button id="send" name="action" value="deliver">Send</button><span hidden data-rk-antispam data-provider="cf7" data-form="42" data-intent="${'a'.repeat(64)}" data-endpoint="/challenge"><input type="hidden" name="rktsky_antispam_token"></span></form>
 <form id="host" data-rk-antispam-provider="wsform" data-rk-antispam-form="1" data-rk-antispam-target="rk-fixture-sales" data-rk-antispam-page="22" data-rk-antispam-intent="${'a'.repeat(64)}" data-rk-antispam-endpoint="/challenge"><button id="ws-send">Send native</button></form>
 <form id="failure"><span hidden data-rk-antispam data-provider="cf7" data-form="44" data-intent="${'a'.repeat(64)}" data-endpoint="/failure"><input type="hidden" name="rktsky_antispam_token"></span><button>Send temporary failure</button></form>
 <form id="external"><span hidden data-rk-antispam data-provider="cf7" data-form="43" data-intent="${'a'.repeat(64)}" data-endpoint="https://example.invalid/challenge"><input type="hidden" name="rktsky_antispam_token"></span><button>Send external config</button></form>
 <script>window.delivered=[];document.addEventListener('submit',e=>{e.preventDefault();window.delivered.push({id:e.target.id,token:new FormData(e.target).get('rktsky_antispam_token'),trap:[...new FormData(e.target)].find(([k])=>k.startsWith('rktsky_as_')),submitter:e.submitter&&e.submitter.id});});</script><script src="/protection.js"></script></body></html>`
	});
});
try {
	await page.goto('http://127.0.0.1:8891/');
	await page.locator('#send').click();
	await page.waitForFunction(() => window.delivered.length === 1);
	const first = await page.evaluate(() => window.delivered[0]);
	expect(
		first.token && first.trap[1] === '' && first.submitter === 'send',
		'A cold first attempt retains native submitter and supplies fresh proof'
	);
	await page.locator('#send').click();
	await page.waitForFunction(() => window.delivered.length === 2);
	const second = await page.evaluate(() => window.delivered[1]);
	expect(second.token !== first.token, 'A retry obtains unused proof before native submission');
	await page.evaluate(() =>
		window.rktskyAntispamBridge.ensure(document.querySelector('#ordinary'))
	);
	await page.locator('#ordinary [data-rk-trap]').evaluate((e) => (e.value = 'filled'));
	const trap = await page.evaluate(() =>
		window.rktskyAntispamBridge.serialized(document.querySelector('#ordinary'))
	);
	expect(
		Object.values(trap).includes('filled'),
		'The bridge preserves a filled trap for server rejection'
	);
	const before = issued;
	await page.evaluate(
		() =>
			(document.querySelector('#host').innerHTML =
				'<input name="native" value="new"><button id="ws-send">Send native</button>')
	);
	await page.waitForFunction(() => Boolean(document.querySelector('#host [data-rk-trap]')));
	await page.locator('#ws-send').click();
	await page.waitForFunction(() => window.delivered.length === 3);
	const host = await page.evaluate(() => window.delivered[2]);
	expect(
		host.token && host.trap[1] === '' && issued === before + 1,
		'Native form child replacement restores protection with one challenge'
	);
	const metadata = await page.evaluate(() => {
		const form = document.querySelector('#host');
		const values = new FormData(form);
		return {
			target: values.get('rktsky_antispam_target'),
			page: values.get('rktsky_antispam_page'),
			proof: window.rktskyAntispamBridge.serialized(form)
		};
	});
	expect(
		metadata.target === 'rk-fixture-sales' && metadata.page === '22',
		'Native child replacement restores display-only container and public-page metadata'
	);
	expect(
		!Object.keys(metadata.proof).some(
			(key) => key === 'rktsky_antispam_target' || key === 'rktsky_antispam_page'
		),
		'Display metadata cannot become token authority in the structured proof transport'
	);
	await page.locator('#external button').click();
	await page.waitForFunction(() => window.delivered.length === 4, {}, { timeout: 5000 });
	expect(
		(await page.evaluate(() => window.delivered[3])).token === '',
		'An unsafe challenge origin yields empty evidence and leaves the native error path available'
	);
	expect(
		calls.every((c) => Object.keys(c).sort().join(',') === 'form,intent,provider'),
		'Challenge requests contain no form content'
	);
	await page.locator('#failure button').click();
	await page.waitForFunction(() => window.delivered.length === 5);
	expect(
		(await page.evaluate(() => window.delivered[4])).token === '',
		'Unavailable challenges reach the ordinary native error path'
	);
	const failedBefore = failureRequests;
	await page.evaluate(() => {
		for (let i = 0; i < 8; i++) {
			const p = document.createElement('p');
			p.textContent = 'Native feedback changed';
			document.body.append(p);
		}
	});
	await page.evaluate(
		() => new Promise((done) => requestAnimationFrame(() => requestAnimationFrame(done)))
	);
	expect(
		failureRequests === failedBefore,
		'Unrelated DOM updates do not automatically retry failed issuance'
	);
	failing = false;
	await page.locator('#failure button').click();
	await page.waitForFunction(() => window.delivered.length === 6);
	expect(
		Boolean((await page.evaluate(() => window.delivered[5])).token) &&
			failureRequests === failedBefore + 1,
		'A deliberate retry after service recovery issues exactly one new proof'
	);
	expect(errors.length === 0, 'No browser exceptions during native submissions or replacement');
	console.log(JSON.stringify({ passed: assertions.length, challengeRequests: issued, errors }));
} catch (e) {
	console.log(
		JSON.stringify({ errors, deliveredCount: await page.evaluate(() => window.delivered.length) })
	);
	throw e;
} finally {
	await browser.close();
}

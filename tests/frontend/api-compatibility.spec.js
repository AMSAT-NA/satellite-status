const { expect, test } = require('@playwright/test');

const SATELLITE = 'AO-91';

function recentReportDate() {
  return new Date(Date.now() - 60 * 60 * 1000);
}

function legacyReportTime(date = new Date(Date.now() - 60 * 60 * 1000)) {
  const minute = date.getUTCMinutes();

  return {
    month: String(date.getUTCMonth() + 1).padStart(2, '0'),
    day: String(date.getUTCDate()).padStart(2, '0'),
    year: String(date.getUTCFullYear()),
    hour: String(date.getUTCHours()).padStart(2, '0'),
    period: String(minute <= 15 ? 0 : minute <= 30 ? 1 : minute <= 45 ? 2 : 3),
  };
}

function futureLegacyReportTime() {
  const date = new Date();
  date.setUTCHours(date.getUTCHours() + 1);

  return legacyReportTime(date);
}

function uniqueLegacyCallsign() {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  const value = Date.now() + Math.floor(Math.random() * alphabet.length ** 4);

  return [
    'W5',
    alphabet[value % alphabet.length],
    alphabet[Math.floor(value / alphabet.length) % alphabet.length],
    alphabet[Math.floor(value / alphabet.length ** 2) % alphabet.length],
    alphabet[Math.floor(value / alphabet.length ** 3) % alphabet.length],
  ].join('');
}

function uniqueToken(prefix) {
  return `${prefix}${Date.now().toString(36)}${Math.floor(Math.random() * 1000)}`;
}

async function fillLegacyReportForm(page, { callsign, gridSquare = 'EM25', report, reportTime }) {
  await page.goto('/');

  await page.selectOption('select[name="SatName"]', SATELLITE);
  await page.check(`input[name="SatReport"][value="${report}"]`);
  await page.selectOption('select[name="SatMonth"]', reportTime.month);
  await page.selectOption('select[name="SatDay"]', reportTime.day);
  await page.selectOption('select[name="SatYear"]', reportTime.year);
  await page.selectOption('select[name="SatHour"]', reportTime.hour);
  await page.selectOption('select[name="SatPeriod"]', reportTime.period);
  await page.fill('input[name="SatCall"]', callsign);
  await page.fill('input[name="SatGridSquare"]', gridSquare);
}

async function submitLegacyReport(page, { callsign, gridSquare = 'EM25', report, reportTime }) {
  await fillLegacyReportForm(page, { callsign, gridSquare, report, reportTime });
  await page.locator('input[name="SatSubmit"]').click();

  await expect(page.locator('body')).toContainText('You Entered');
  await expect(page.locator('body')).toContainText(callsign);
  await page.getByRole('link', { name: 'Yes' }).click();

  await expect(page.locator('body')).toContainText('Thank you for your submission');
}

async function legacyReportsForCallsign(page, callsign) {
  const payload = await page.evaluate(async () => {
    const response = await fetch('/api/v1/sat_info.php?name=AO-91&hours=72');
    return {
      status: response.status,
      body: await response.json(),
    };
  });

  expect(payload.status).toBe(200);
  return payload.body.filter((report) => report.callsign === callsign);
}

async function loginAsAdmin(page) {
  await page.goto('/admin/index.php');

  await page.fill('input[name="username"]', 'admin');
  await page.fill('input[name="password"]', 'password');
  await page.getByRole('button', { name: 'Login' }).click();

  await expect(page.locator('h1')).toContainText('Amsat Status Dashboard');
}

test.describe('public frontend/API compatibility', () => {
  test('status page renders seeded satellite data', async ({ page }) => {
    await page.goto('/');

    await expect(page.getByText('AMSAT Live OSCAR Satellite Status Page')).toBeVisible();
    await expect(page.locator('body')).toContainText('AO-91');
    await expect(page.locator('select[name="SatName"]')).toContainText('AO-91');
  });

  test('browser clients can read the new reports API envelope', async ({ page }) => {
    await page.goto('/');

    const payload = await page.evaluate(async () => {
      const response = await fetch('/api/v1/reports.php?name=AO-91&hours=72');
      return {
        status: response.status,
        contentType: response.headers.get('content-type'),
        body: await response.json(),
      };
    });

    expect(payload.status).toBe(200);
    expect(payload.contentType).toContain('application/json');
    expect(Array.isArray(payload.body.data)).toBe(true);
    expect(payload.body.data.length).toBeGreaterThan(0);
    expect(payload.body.data[0]).toEqual(
      expect.objectContaining({
        name: 'AO-91',
        callsign: expect.any(String),
        report: expect.any(String),
      })
    );
  });

  test('browser clients can submit reports through the new JSON API', async ({ page }) => {
    const callsign = uniqueLegacyCallsign();
    const reportedAt = recentReportDate().toISOString();

    await page.goto('/');

    const createPayload = await page.evaluate(
      async ({ callsign, reportedAt }) => {
        const response = await fetch('/api/v1/reports.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify({
            name: 'AO-91',
            report: 'Not Heard',
            callsign,
            grid_square: 'EM25',
            reported_at: reportedAt,
          }),
        });

        return {
          status: response.status,
          location: response.headers.get('location'),
          body: await response.json(),
        };
      },
      { callsign, reportedAt }
    );

    expect(createPayload.status).toBe(201);
    expect(createPayload.location).toContain('/api/v1/reports.php?name=AO-91');
    expect(createPayload.body.data).toEqual(
      expect.objectContaining({
        name: SATELLITE,
        callsign,
        report: 'Not Heard',
        grid_square: 'EM25',
        replaced_count: 0,
      })
    );

    const newApiPayload = await page.evaluate(async (callsign) => {
      const response = await fetch(
        `/api/v1/reports.php?name=AO-91&hours=72&callsign=${callsign}`
      );
      return {
        status: response.status,
        body: await response.json(),
      };
    }, callsign);

    expect(newApiPayload.status).toBe(200);
    expect(newApiPayload.body.data).toContainEqual(
      expect.objectContaining({
        name: SATELLITE,
        callsign,
        report: 'Not Heard',
        grid_square: 'EM25',
      })
    );
    expect(await legacyReportsForCallsign(page, callsign)).toContainEqual(
      expect.objectContaining({
        name: SATELLITE,
        callsign,
        report: 'Not Heard',
        grid_square: 'EM25',
      })
    );
  });

  test('browser clients can still read the legacy sat_info array', async ({ page }) => {
    await page.goto('/');

    const payload = await page.evaluate(async () => {
      const response = await fetch('/api/v1/sat_info.php?name=AO-91&hours=72');
      return {
        status: response.status,
        contentType: response.headers.get('content-type'),
        body: await response.json(),
      };
    });

    expect(payload.status).toBe(200);
    expect(payload.contentType).toContain('application/json');
    expect(Array.isArray(payload.body)).toBe(true);
    expect(payload.body.length).toBeGreaterThan(0);
    expect(Object.keys(payload.body[0])).toEqual([
      'name',
      'reported_time',
      'callsign',
      'report',
      'grid_square',
    ]);
  });

  test('legacy submit form writes a report visible through the legacy API', async ({ page }) => {
    const callsign = uniqueLegacyCallsign();
    const reportTime = legacyReportTime();

    await submitLegacyReport(page, { callsign, report: 'Heard', reportTime });

    expect(await legacyReportsForCallsign(page, callsign)).toContainEqual(
      expect.objectContaining({
        name: SATELLITE,
        callsign,
        report: 'Heard',
        grid_square: 'EM25',
      })
    );
  });

  test('legacy submit form corrections replace an existing report', async ({ page }) => {
    const callsign = uniqueLegacyCallsign();
    const reportTime = legacyReportTime();

    await submitLegacyReport(page, { callsign, report: 'Heard', reportTime });
    await submitLegacyReport(page, { callsign, report: 'Telemetry Only', reportTime });

    await expect(page.locator('body')).toContainText(
      'This report will replace the previous one.'
    );

    const reports = await legacyReportsForCallsign(page, callsign);

    expect(reports).toHaveLength(1);
    expect(reports[0]).toEqual(
      expect.objectContaining({
        name: SATELLITE,
        callsign,
        report: 'Telemetry Only',
        grid_square: 'EM25',
      })
    );
  });

  test('legacy submit form rejects validation errors before confirmation', async ({ page }) => {
    const reportTime = legacyReportTime();

    await fillLegacyReportForm(page, {
      callsign: '',
      report: 'Heard',
      reportTime,
    });
    await page.locator('input[name="SatSubmit"]').click();
    await expect(page.locator('body')).toContainText('You must enter a callsign.');

    await fillLegacyReportForm(page, {
      callsign: 'NOT_A_CALL',
      report: 'Heard',
      reportTime,
    });
    await page.locator('input[name="SatSubmit"]').click();
    await expect(page.locator('body')).toContainText(
      'The callsign you entered does not appear to be valid'
    );

    await fillLegacyReportForm(page, {
      callsign: uniqueLegacyCallsign(),
      gridSquare: 'ZZ99',
      report: 'Heard',
      reportTime,
    });
    await page.locator('input[name="SatSubmit"]').click();
    await expect(page.locator('body')).toContainText(
      'The grid square you entered does not appear to be valid'
    );

    await fillLegacyReportForm(page, {
      callsign: uniqueLegacyCallsign(),
      report: 'Heard',
      reportTime: futureLegacyReportTime(),
    });
    await page.locator('input[name="SatSubmit"]').click();
    await expect(page.locator('body')).toContainText(
      'The time heard you entered does not appear to be valid'
    );
  });

  test('admin can add and delete a satellite catalog entry', async ({ page }) => {
    const satelliteName = uniqueToken('Playwright Test Satellite ');
    const htmlSatelliteName = uniqueToken('PW-TEST-');

    await loginAsAdmin(page);
    await page.getByRole('link', { name: 'Add Satellite' }).click();

    await page.fill('input[name="satellite_name"]', satelliteName);
    await page.fill('input[name="html_satellite_name"]', htmlSatelliteName);
    await page.fill('input[name="website"]', 'https://www.amsat.org/status/');
    await page.getByRole('button', { name: 'Add Satellite' }).click();

    await expect(page.locator('body')).toContainText('New record created successfully');

    await page.goto('/admin/manage_satellites.php');
    const row = page.locator('tr').filter({ hasText: satelliteName });

    await expect(row).toContainText(htmlSatelliteName);
    page.once('dialog', (dialog) => dialog.accept());
    await row.getByRole('link', { name: 'Delete' }).click();

    await expect(page.locator('body')).toContainText('Satellite deleted successfully');
    await page.goto('/admin/manage_satellites.php');
    await expect(page.locator('tr').filter({ hasText: satelliteName })).toHaveCount(0);
  });

  test('OpenAPI document is valid and describes core endpoints', async ({ page }) => {
    await page.goto('/');

    const payload = await page.evaluate(async () => {
      const response = await fetch('/api/v1/openapi.php');
      return {
        status: response.status,
        contentType: response.headers.get('content-type'),
        body: await response.json(),
      };
    });

    expect(payload.status).toBe(200);
    expect(payload.contentType).toContain('application/json');
    expect(payload.body.openapi).toMatch(/^3\./);
    expect(payload.body.info).toEqual(
      expect.objectContaining({
        title: 'AMSAT Satellite Status API',
        version: expect.any(String),
      })
    );
    expect(Object.keys(payload.body.paths)).toEqual(
      expect.arrayContaining([
        '/reports.php',
        '/catalog.php',
        '/summary.php',
        '/statuses.php',
        '/health.php',
      ])
    );
    expect(payload.body.paths['/reports.php']).toEqual(
      expect.objectContaining({
        get: expect.any(Object),
        post: expect.any(Object),
      })
    );
    expect(payload.body.components.schemas).toEqual(
      expect.objectContaining({
        Report: expect.any(Object),
        ReportCreate: expect.any(Object),
        ErrorResponse: expect.any(Object),
      })
    );
  });

  test('API acknowledgements page is public', async ({ page }) => {
    const response = await page.goto('/api/v1/acknowledgements.php');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-type']).toContain('text/plain');
    await expect(page.locator('body')).toContainText(
      'AMSAT Satellite Status API Acknowledgements'
    );
    await expect(page.locator('body')).toContainText('Ben Welsh, KFØIA');
    await expect(page.locator('body')).toContainText('Dominic Hord, AD8AK');
    await expect(page.locator('body')).toContainText('API overview:');
    await expect(page.locator('body')).toContainText('Swagger documentation:');
  });

  test('Swagger docs are public and load the OpenAPI document', async ({ page }) => {
    const openApiResponses = [];
    const failedApiResponses = [];

    page.on('response', (response) => {
      if (response.url().endsWith('/api/v1/openapi.php')) {
        openApiResponses.push(response);
      }

      if (response.url().includes('/api/') && !response.ok()) {
        failedApiResponses.push(`${response.status()} ${response.url()}`);
      }
    });

    await page.goto('/api/v1/docs.php');

    await expect(page).toHaveTitle(/AMSAT Satellite Status API Docs/);
    await expect(page.getByRole('link', { name: 'Acknowledgements' })).toBeVisible();
    await expect(page.locator('#swagger-ui')).toBeVisible();
    await expect(page.getByText('AMSAT Satellite Status API')).toBeVisible();
    await expect(page.getByRole('button', { name: 'GET /reports.php Search' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'POST /reports.php Submit' })).toBeVisible();
    expect(openApiResponses.some((response) => response.ok())).toBe(true);
    expect(failedApiResponses).toEqual([]);
  });
});

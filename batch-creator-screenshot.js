#!/usr/bin/env node
/**
 * Batch Creator Screenshot with Interactions
 */

const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

const AUTH_STATE_FILE = path.join(__dirname, '.playwright-auth.json');
const CONFIG_PATH = path.join(__dirname, 'CONFIG.md');

const DEFAULT_SSH_CONFIG = {
    host: '34.71.83.227',
    port: '21264',
    user: 'luxeonstarleds',
    keyName: 'rlux',
    wpPath: '/www/luxeonstarleds_546/public'
};

function getTestingConfigSection(configText) {
    const sectionHeader = '## Testing Environment';
    const startIndex = configText.indexOf(sectionHeader);
    if (startIndex === -1) return null;
    const remaining = configText.slice(startIndex + sectionHeader.length);
    const nextHeaderIndex = remaining.indexOf('\n## ');
    return nextHeaderIndex === -1 ? remaining : remaining.slice(0, nextHeaderIndex);
}

function getConfigValue(sectionText, label) {
    const pattern = new RegExp(`\\*\\*${label}:\\*\\*\\s*\\\`([^\\\`]+)\\\``, 'i');
    const match = sectionText.match(pattern);
    return match ? match[1].trim() : null;
}

function loadTestingConfig() {
    if (!fs.existsSync(CONFIG_PATH)) return null;
    try {
        const configText = fs.readFileSync(CONFIG_PATH, 'utf8');
        const testingSection = getTestingConfigSection(configText);
        if (!testingSection) return null;
        return {
            host: getConfigValue(testingSection, 'HOST'),
            port: getConfigValue(testingSection, 'PORT'),
            user: getConfigValue(testingSection, 'USER'),
            keyName: getConfigValue(testingSection, 'KEY'),
            wpPath: getConfigValue(testingSection, 'PATH')
        };
    } catch (error) {
        return null;
    }
}

function getSshConfig() {
    const fileConfig = loadTestingConfig() || {};
    const keyName = process.env.SCREENSHOT_SSH_KEY || fileConfig.keyName || DEFAULT_SSH_CONFIG.keyName;
    return {
        host: process.env.SCREENSHOT_SSH_HOST || fileConfig.host || DEFAULT_SSH_CONFIG.host,
        port: process.env.SCREENSHOT_SSH_PORT || fileConfig.port || DEFAULT_SSH_CONFIG.port,
        user: process.env.SCREENSHOT_SSH_USER || fileConfig.user || DEFAULT_SSH_CONFIG.user,
        keyPath: process.env.SCREENSHOT_SSH_KEY_PATH || path.join(os.homedir(), '.ssh', keyName),
        wpPath: process.env.SCREENSHOT_SSH_PATH || fileConfig.wpPath || DEFAULT_SSH_CONFIG.wpPath
    };
}

function findDefineValue(configText, keys) {
    for (const key of keys) {
        const pattern = new RegExp(`define\\s*\\(\\s*['"]${key}['"]\\s*,\\s*['"]([^'"]+)['"]\\s*\\)`);
        const match = configText.match(pattern);
        if (match) return match[1];
    }
    return null;
}

function getCredentialsFromWpConfig() {
    const { execFileSync } = require('child_process');
    const envUsername = process.env.SCREENSHOT_USER || process.env.SCREENSHOT_ADMIN_USER;
    const envPassword = process.env.SCREENSHOT_PASS || process.env.SCREENSHOT_ADMIN_PASS;
    if (envUsername && envPassword) {
        return { username: envUsername, password: envPassword };
    }

    try {
        const sshConfig = getSshConfig();
        const output = execFileSync(
            'ssh',
            [
                '-i', sshConfig.keyPath,
                '-o', 'BatchMode=yes',
                '-o', 'IdentitiesOnly=yes',
                '-o', 'StrictHostKeyChecking=accept-new',
                '-p', sshConfig.port,
                `${sshConfig.user}@${sshConfig.host}`,
                `grep -E 'SCREENSHOT_(ADMIN_)?(USER|PASS)' ${sshConfig.wpPath}/wp-config.php`
            ],
            { encoding: 'utf8', timeout: 10000 }
        );
        const username = findDefineValue(output, ['SCREENSHOT_USER', 'SCREENSHOT_ADMIN_USER']);
        const password = findDefineValue(output, ['SCREENSHOT_PASS', 'SCREENSHOT_ADMIN_PASS']);
        if (username && password) return { username, password };
    } catch (error) {}
    return { username: null, password: null };
}

async function authenticate(browser, siteUrl) {
    console.log('Authenticating with WordPress...');
    const context = await browser.newContext();
    const page = await context.newPage();

    const wpConfigCreds = getCredentialsFromWpConfig();
    const username = wpConfigCreds.username;
    const password = wpConfigCreds.password;

    if (!username || !password) {
        throw new Error('No credentials provided.');
    }

    await page.goto(`${siteUrl}/wp-login.php`);
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);
    await page.click('#wp-submit');

    try {
        await page.waitForURL((url) => !url.toString().includes('wp-login.php'), { timeout: 30000 });
    } catch (error) {
        throw new Error('Authentication failed');
    }

    console.log('Authentication successful');
    await context.storageState({ path: AUTH_STATE_FILE });
    await context.close();
}

async function main() {
    const url = 'https://env-luxeonstarleds-rlux.kinsta.cloud/wp-admin/admin.php?page=qsa-engraving-batch-creator';
    const outputPath = '/home/warrisr/qip/wp-content/plugins/qsa-engraving/docs/screenshots/dev/batch-creator-no-hash-prefix-2026-01-02.png';
    const siteUrl = 'https://env-luxeonstarleds-rlux.kinsta.cloud';

    const browser = await chromium.launch({ headless: true });

    try {
        if (!fs.existsSync(AUTH_STATE_FILE)) {
            await authenticate(browser, siteUrl);
        }

        const context = await browser.newContext({
            storageState: AUTH_STATE_FILE,
            viewport: { width: 1400, height: 1000 }
        });

        const page = await context.newPage();

        console.log(`Navigating to ${url}...`);
        await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 });

        // Wait for content - look for text "STAR" which we know is on the page
        console.log('Waiting for STAR text...');
        await page.waitForSelector('text=STAR', { timeout: 30000 });
        console.log('STAR text found');

        // Wait a moment for animations
        await page.waitForTimeout(1500);

        // Click on the STAR row
        console.log('Clicking STAR row...');
        await page.click('text=STAR');
        await page.waitForTimeout(1500);

        // Debug: get HTML of the visible area to understand structure
        const pageContent = await page.content();
        console.log('Page has', pageContent.length, 'chars of HTML');

        // Take debug screenshot
        await page.screenshot({
            path: '/home/warrisr/qip/wp-content/plugins/qsa-engraving/docs/screenshots/dev/batch-creator-after-star-click.png',
            fullPage: true
        });
        console.log('Took debug screenshot after STAR click');

        // Look for order number patterns (6 digits starting with 28)
        console.log('Looking for order rows...');
        const orderLocator = page.locator('text=/28\\d{4}/').first();
        const orderCount = await orderLocator.count();
        console.log(`Order locator count: ${orderCount}`);

        if (orderCount > 0) {
            console.log('Clicking first order...');
            await orderLocator.click();
            await page.waitForTimeout(1500);
        }

        // Ensure output directory exists
        const outputDir = path.dirname(outputPath);
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }

        console.log(`Capturing final screenshot...`);
        await page.screenshot({
            path: outputPath,
            fullPage: true
        });

        console.log('Screenshot captured successfully');
        await context.close();
    } catch (error) {
        console.error('Error:', error.message);
        if (error.message.includes('wp-login') && fs.existsSync(AUTH_STATE_FILE)) {
            fs.unlinkSync(AUTH_STATE_FILE);
        }
        throw error;
    } finally {
        await browser.close();
    }
}

main()
    .then(() => process.exit(0))
    .catch((error) => {
        console.error('Fatal error:', error);
        process.exit(1);
    });

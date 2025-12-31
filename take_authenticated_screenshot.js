#!/usr/bin/env node
/**
 * Authenticated WordPress Screenshot Tool
 *
 * Takes screenshots of WordPress admin pages with authentication.
 * Usage: node take_authenticated_screenshot.js <url> <output-file>
 *
 * Test Site URL
 * Add the full testing site url to the const 'SITE_URL =' line below
 * 
 * Credentials:
 * Create an admin account with the username 'screenshots' and a generated
 * password on the testing server. Add the following definitions to the
 * wp-config.php file on the testing server:
 *
 *  define('SCREENSHOT_USER', 'screenshots');
 *  define('SCREENSHOT_PASS', 'PASSWORD');
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
    if (startIndex === -1) {
        return null;
    }

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
    if (!fs.existsSync(CONFIG_PATH)) {
        return null;
    }

    try {
        const configText = fs.readFileSync(CONFIG_PATH, 'utf8');
        const testingSection = getTestingConfigSection(configText);
        if (!testingSection) {
            return null;
        }

        return {
            host: getConfigValue(testingSection, 'HOST'),
            port: getConfigValue(testingSection, 'PORT'),
            user: getConfigValue(testingSection, 'USER'),
            keyName: getConfigValue(testingSection, 'KEY'),
            wpPath: getConfigValue(testingSection, 'PATH')
        };
    } catch (error) {
        console.error('Warning: Could not read CONFIG.md:', error.message);
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
        if (match) {
            return match[1];
        }
    }

    return null;
}

/**
 * Read credentials from environment variables or wp-config.php
 */
function getCredentialsFromWpConfig() {
    const { execFileSync } = require('child_process');
    const envUsername = process.env.SCREENSHOT_USER || process.env.SCREENSHOT_ADMIN_USER;
    const envPassword = process.env.SCREENSHOT_PASS || process.env.SCREENSHOT_ADMIN_PASS;

    if (envUsername && envPassword) {
        return { username: envUsername, password: envPassword };
    }

    // Try local wp-config.php first (for local development)
    const localWpConfigPath = path.join(__dirname, 'wp-config.php');
    if (fs.existsSync(localWpConfigPath)) {
        try {
            const wpConfig = fs.readFileSync(localWpConfigPath, 'utf8');
            const username = findDefineValue(wpConfig, ['SCREENSHOT_USER', 'SCREENSHOT_ADMIN_USER']);
            const password = findDefineValue(wpConfig, ['SCREENSHOT_PASS', 'SCREENSHOT_ADMIN_PASS']);

            if (username && password) {
                return { username, password };
            }
        } catch (error) {
            console.error('Warning: Could not read local wp-config.php:', error.message);
        }
    }

    // Try remote wp-config.php on staging server
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

        if (username && password) {
            return { username, password };
        }
    } catch (error) {
        console.error('Warning: Could not fetch credentials from remote wp-config.php:', error.message);
    }

    return { username: null, password: null };
}

async function authenticate(browser, siteUrl) {
    console.log('Authenticating with WordPress...');
    const context = await browser.newContext();
    const page = await context.newPage();

    // Get credentials from environment or wp-config.php
    const wpConfigCreds = getCredentialsFromWpConfig();
    const username = wpConfigCreds.username;
    const password = wpConfigCreds.password;

    if (!username || !password) {
        throw new Error('No credentials provided. Add SCREENSHOT_USER and SCREENSHOT_PASS to wp-config.php');
    }

    console.log(`Using username from wp-config.php: ${username}`);

    // Navigate to login page
    await page.goto(`${siteUrl}/wp-login.php`);

    // Fill in credentials
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);

    // Click login button
    await page.click('#wp-submit');

    // Wait for redirect away from login page (increased timeout for slow connections)
    try {
        await page.waitForURL((url) => !url.toString().includes('wp-login.php'), { timeout: 30000 });
    } catch (error) {
        const loginError = await page.locator('#login_error').textContent().catch(() => null);
        if (loginError) {
            throw new Error(`Authentication failed: ${loginError.trim()}`);
        }

        throw new Error('Authentication failed: login did not redirect away from wp-login.php');
    }

    console.log('✓ Authentication successful');

    // Save authentication state
    await context.storageState({ path: AUTH_STATE_FILE });
    await context.close();
}

function resolveSiteUrl(targetUrl) {
    try {
        return new URL(targetUrl).origin;
    } catch (error) {
        throw new Error('Invalid URL. Provide a full URL including https://');
    }
}

async function takeScreenshot(url, outputPath) {
    const siteUrl = resolveSiteUrl(url);
    const browser = await chromium.launch();

    try {
        // Check if we need to authenticate
        if (!fs.existsSync(AUTH_STATE_FILE)) {
            await authenticate(browser, siteUrl);
        }

        // Create context with saved authentication
        const context = await browser.newContext({
            storageState: AUTH_STATE_FILE
        });

        const page = await context.newPage();

        console.log(`Navigating to ${url}...`);
        await page.goto(url, { waitUntil: 'networkidle' });

        // Ensure output directory exists
        const outputDir = path.dirname(outputPath);
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }

        console.log(`Capturing screenshot to ${outputPath}...`);
        await page.screenshot({
            path: outputPath,
            fullPage: true
        });

        console.log('✓ Screenshot captured successfully');

        await context.close();
    } catch (error) {
        console.error('Error:', error.message);

        // If authentication failed, remove the state file and try again
        if (error.message.includes('wp-login') && fs.existsSync(AUTH_STATE_FILE)) {
            console.log('Authentication state expired, re-authenticating...');
            fs.unlinkSync(AUTH_STATE_FILE);
            await authenticate(browser, siteUrl);

            // Retry screenshot
            const context = await browser.newContext({
                storageState: AUTH_STATE_FILE
            });
            const page = await context.newPage();
            await page.goto(url, { waitUntil: 'networkidle' });
            await page.screenshot({
                path: outputPath,
                fullPage: true
            });
            console.log('✓ Screenshot captured successfully');
            await context.close();
        } else {
            throw error;
        }
    } finally {
        await browser.close();
    }
}

// Main execution
const args = process.argv.slice(2);

if (args.length < 2) {
    console.error('Usage: node take_authenticated_screenshot.js <url> <output-file>');
    console.error('');
    console.error('Credentials:');
    console.error('  Add to wp-config.php:');
    console.error("    define('SCREENSHOT_USER', 'screenshots');");
    console.error("    define('SCREENSHOT_PASS', 'your-password');");
    process.exit(1);
}

const [url, outputPath] = args;

// Check if credentials are available from either source
const wpConfigCreds = getCredentialsFromWpConfig();
if (!wpConfigCreds.username || !wpConfigCreds.password) {
    console.error('ERROR: No credentials provided in wp-config.php');
    console.error('');
    console.error('Add the following to wp-config.php:');
    console.error("  define('SCREENSHOT_USER', 'screenshots');");
    console.error("  define('SCREENSHOT_PASS', 'your-password');");
    process.exit(1);
}

takeScreenshot(url, outputPath)
    .then(() => process.exit(0))
    .catch((error) => {
        console.error('Fatal error:', error);
        process.exit(1);
    });

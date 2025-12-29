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
 * password to the testing server. Add the following definitions to the
 * wp-config.php file on the testing server:
 * 
 *  define('SCREENSHOT_ADMIN_USER', 'screenshots');
 *  define('SCREENSHOT_ADMIN_PASS', 'PASSWORD');
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SITE_URL = 'https://env-luxeonstarleds-lmb.kinsta.cloud';
const AUTH_STATE_FILE = path.join(__dirname, '.playwright-auth.json');

/**
 * Read credentials from wp-config.php if environment variables not set
 */
function getCredentialsFromWpConfig() {
    const { execSync } = require('child_process');

    // Try local wp-config.php first (for local development)
    const localWpConfigPath = path.join(__dirname, 'wp-config.php');
    if (fs.existsSync(localWpConfigPath)) {
        try {
            const wpConfig = fs.readFileSync(localWpConfigPath, 'utf8');
            const userMatch = wpConfig.match(/define\s*\(\s*['"]SCREENSHOT_ADMIN_USER['"]\s*,\s*['"](.*?)['"]\s*\)/);
            const passMatch = wpConfig.match(/define\s*\(\s*['"]SCREENSHOT_ADMIN_PASS['"]\s*,\s*['"](.*?)['"]\s*\)/);

            if (userMatch && passMatch) {
                return { username: userMatch[1], password: passMatch[1] };
            }
        } catch (error) {
            console.error('Warning: Could not read local wp-config.php:', error.message);
        }
    }

    // Try remote wp-config.php on staging server
    try {
        const sshCmd = 'ssh -i ~/.ssh/kinsta-lmb -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -p 40107 luxeonstarleds@34.71.83.227 "grep -E \'SCREENSHOT_ADMIN_(USER|PASS)\' /www/luxeonstarleds_546/public/wp-config.php"';
        const output = execSync(sshCmd, { encoding: 'utf8', timeout: 10000 });

        const userMatch = output.match(/define\s*\(\s*['"]SCREENSHOT_ADMIN_USER['"]\s*,\s*['"](.*?)['"]\s*\)/);
        const passMatch = output.match(/define\s*\(\s*['"]SCREENSHOT_ADMIN_PASS['"]\s*,\s*['"](.*?)['"]\s*\)/);

        if (userMatch && passMatch) {
            return { username: userMatch[1], password: passMatch[1] };
        }
    } catch (error) {
        console.error('Warning: Could not fetch credentials from remote wp-config.php:', error.message);
    }

    return { username: null, password: null };
}

async function authenticate(browser) {
    console.log('Authenticating with WordPress...');
    const context = await browser.newContext();
    const page = await context.newPage();

    // Get credentials from environment or wp-config.php
    const wpConfigCreds = getCredentialsFromWpConfig();
    const username = wpConfigCreds.username;
    const password = wpConfigCreds.password;

    if (!username || !password) {
        throw new Error('No credentials provided. Add SCREENSHOT_ADMIN_USER and SCREENSHOT_ADMIN_PASS to wp-config.php');
    }

    console.log(`Using username from wp-config.php: ${username}`);

    // Navigate to login page
    await page.goto(`${SITE_URL}/wp-login.php`);

    // Fill in credentials
    await page.fill('#user_login', username);
    await page.fill('#user_pass', password);

    // Click login button
    await page.click('#wp-submit');

    // Wait for redirect to admin dashboard (increased timeout for slow connections)
    await page.waitForURL('**/wp-admin/**', { timeout: 30000 });

    console.log('✓ Authentication successful');

    // Save authentication state
    await context.storageState({ path: AUTH_STATE_FILE });
    await context.close();
}

async function takeScreenshot(url, outputPath) {
    const browser = await chromium.launch();

    try {
        // Check if we need to authenticate
        if (!fs.existsSync(AUTH_STATE_FILE)) {
            await authenticate(browser);
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
            await authenticate(browser);

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
    console.error("    define('SCREENSHOT_ADMIN_USER', 'admin');");
    console.error("    define('SCREENSHOT_ADMIN_PASS', 'your-password');");
    process.exit(1);
}

const [url, outputPath] = args;

// Check if credentials are available from either source
const wpConfigCreds = getCredentialsFromWpConfig();
if (!wpConfigCreds.username || !wpConfigCreds.password) {
    console.error('ERROR: No credentials provided in wp-config.php');
    console.error('');
    console.error('Add the following to wp-config.php:');
    console.error("  define('SCREENSHOT_ADMIN_USER', 'admin');");
    console.error("  define('SCREENSHOT_ADMIN_PASS', 'your-password');");
    process.exit(1);
}

takeScreenshot(url, outputPath)
    .then(() => process.exit(0))
    .catch((error) => {
        console.error('Fatal error:', error);
        process.exit(1);
    });

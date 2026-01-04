#!/usr/bin/env node
/**
 * Screenshot with button click - clicks Configure button and captures expanded panel
 */

const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

const AUTH_STATE_FILE = path.join(__dirname, '.playwright-auth.json');

async function takeScreenshotWithClick(url, outputPath) {
    const browser = await chromium.launch();

    try {
        // Create context with saved authentication
        const context = await browser.newContext({
            storageState: AUTH_STATE_FILE
        });

        const page = await context.newPage();

        console.log(`Navigating to ${url}...`);
        await page.goto(url, { waitUntil: 'networkidle' });

        // Wait a moment for page to fully load
        await page.waitForTimeout(1000);

        // Look for the Configure button and click it
        console.log('Looking for Configure button...');
        
        // Try various selectors for the Configure button
        const configureSelectors = [
            'button:has-text("Configure")',
            'a:has-text("Configure")',
            '.configure-button',
            '#configure-button',
            'button.button:has-text("Configure")',
            '[data-action="configure"]'
        ];

        let clicked = false;
        for (const selector of configureSelectors) {
            try {
                const btn = page.locator(selector).first();
                if (await btn.isVisible({ timeout: 1000 })) {
                    console.log(`Found Configure button with selector: ${selector}`);
                    await btn.click();
                    clicked = true;
                    break;
                }
            } catch (e) {
                // Try next selector
            }
        }

        if (!clicked) {
            // Try finding by text content more broadly
            const allButtons = page.locator('button, a.button, input[type="button"]');
            const count = await allButtons.count();
            console.log(`Found ${count} buttons, checking for Configure...`);
            
            for (let i = 0; i < count; i++) {
                const btn = allButtons.nth(i);
                const text = await btn.textContent().catch(() => '');
                if (text && text.toLowerCase().includes('configure')) {
                    console.log(`Found Configure button at index ${i}: "${text}"`);
                    await btn.click();
                    clicked = true;
                    break;
                }
            }
        }

        if (!clicked) {
            console.log('WARNING: Could not find Configure button, taking screenshot anyway');
        } else {
            console.log('Clicked Configure button, waiting for animation...');
            // Wait for animation to complete
            await page.waitForTimeout(1500);
        }

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

        console.log('Screenshot captured successfully');

        await context.close();
    } finally {
        await browser.close();
    }
}

const args = process.argv.slice(2);
if (args.length < 2) {
    console.error('Usage: node take_screenshot_with_click.js <url> <output-file>');
    process.exit(1);
}

const [url, outputPath] = args;

takeScreenshotWithClick(url, outputPath)
    .then(() => process.exit(0))
    .catch((error) => {
        console.error('Fatal error:', error);
        process.exit(1);
    });

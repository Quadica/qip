#!/usr/bin/env node
/**
 * LightBurn SFTP Watcher
 *
 * Monitors a remote SFTP directory for new SVG files,
 * downloads them locally, and sends LOADFILE command to LightBurn.
 *
 * Usage: node lightburn-watcher.js [--once]
 *   --once: Process files once and exit (don't poll)
 */

const SFTPClient = require('ssh2-sftp-client');
const dgram = require('dgram');
const fs = require('fs');
const path = require('path');
const os = require('os');

// Configuration
const CONFIG = {
    // SFTP Settings
    sftp: {
        host: '34.71.83.227',
        port: 21264,
        username: 'luxeonstarleds',
        privateKey: fs.readFileSync(path.join(os.homedir(), '.ssh', 'rlux')),
        readyTimeout: 10000,
        retries: 3,
        retry_minTimeout: 2000,
    },

    // Remote directory to watch
    remoteDir: '/www/luxeonstarleds_546/public/wp-content/uploads/qsa-engraving/svg',

    // Local directory to download files to
    localDir: path.join(os.homedir(), 'LightBurn', 'Incoming'),

    // LightBurn UDP settings
    lightburn: {
        host: '127.0.0.1',
        port: 19840,
    },

    // Polling interval in milliseconds
    pollInterval: 3000,

    // State file to track processed files
    stateFile: path.join(os.homedir(), '.lightburn-watcher-state.json'),
};

// State tracking
let processedFiles = new Set();
let isRunning = true;

/**
 * Load processed files state from disk
 */
function loadState() {
    try {
        if (fs.existsSync(CONFIG.stateFile)) {
            const data = JSON.parse(fs.readFileSync(CONFIG.stateFile, 'utf8'));
            processedFiles = new Set(data.processedFiles || []);
            console.log(`Loaded state: ${processedFiles.size} previously processed files`);
        }
    } catch (err) {
        console.warn('Could not load state file, starting fresh:', err.message);
        processedFiles = new Set();
    }
}

/**
 * Save processed files state to disk
 */
function saveState() {
    try {
        const data = {
            processedFiles: Array.from(processedFiles),
            lastUpdate: new Date().toISOString()
        };
        fs.writeFileSync(CONFIG.stateFile, JSON.stringify(data, null, 2));
    } catch (err) {
        console.error('Could not save state file:', err.message);
    }
}

/**
 * Ensure local directory exists
 */
function ensureLocalDir() {
    if (!fs.existsSync(CONFIG.localDir)) {
        fs.mkdirSync(CONFIG.localDir, { recursive: true });
        console.log(`Created local directory: ${CONFIG.localDir}`);
    }
}

/**
 * Send a command to LightBurn via UDP
 */
function sendCommand(command) {
    return new Promise((resolve, reject) => {
        const socket = dgram.createSocket('udp4');

        socket.send(command, CONFIG.lightburn.port, CONFIG.lightburn.host, (err) => {
            socket.close();
            if (err) {
                reject(err);
            } else {
                resolve();
            }
        });
    });
}

/**
 * Check if LightBurn is running
 */
function isLightBurnRunning() {
    return new Promise((resolve) => {
        const { exec } = require('child_process');
        exec('tasklist /FI "IMAGENAME eq LightBurn.exe" /NH', (err, stdout) => {
            if (err) {
                resolve(false);
                return;
            }
            resolve(stdout.toLowerCase().includes('lightburn.exe'));
        });
    });
}

/**
 * Force kill LightBurn process
 */
function killLightBurn() {
    return new Promise((resolve) => {
        const { exec } = require('child_process');
        console.log('    Force killing LightBurn...');
        exec('taskkill /F /IM LightBurn.exe', (err) => {
            // Resolve regardless of error (process might not exist)
            resolve();
        });
    });
}

/**
 * Start LightBurn application
 */
function startLightBurn() {
    const { spawn } = require('child_process');
    console.log('    Starting LightBurn...');

    // Use spawn with detached option to avoid blocking
    const child = spawn('cmd', ['/c', 'start', '', 'C:\\Program Files\\LightBurn\\LightBurn.exe'], {
        detached: true,
        stdio: 'ignore'
    });
    child.unref();
}

/**
 * Wait for specified milliseconds
 */
function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Send LOADFILE command to LightBurn via UDP
 * Kills LightBurn, restarts it, then loads file
 */
async function sendToLightBurn(filePath) {
    // Kill LightBurn directly (more reliable than FORCECLOSE which can be blocked by dialogs)
    await killLightBurn();
    await wait(500);

    // Start LightBurn
    startLightBurn();

    // Wait for LightBurn to initialize and be ready for UDP commands
    await wait(4000);

    // Load the new file
    console.log('    Loading file...');
    await sendCommand(`LOADFILE:${filePath}`);
}

/**
 * Process a single file: download and send to LightBurn
 */
async function processFile(sftp, remoteFile) {
    const fileName = path.basename(remoteFile);
    const localFile = path.join(CONFIG.localDir, fileName);

    console.log(`\n>>> New file detected: ${fileName}`);

    try {
        // Download file
        console.log(`    Downloading to: ${localFile}`);
        await sftp.fastGet(remoteFile, localFile);

        // Verify download
        const stats = fs.statSync(localFile);
        console.log(`    Downloaded: ${stats.size} bytes`);

        // Send to LightBurn
        console.log(`    Sending to LightBurn...`);
        await sendToLightBurn(localFile);
        console.log(`    SUCCESS: File loaded in LightBurn`);

        // Mark as processed
        processedFiles.add(fileName);
        saveState();

        return true;
    } catch (err) {
        console.error(`    ERROR: ${err.message}`);
        return false;
    }
}

/**
 * Check for new files and process them
 */
async function checkForNewFiles(sftp) {
    try {
        // List remote directory
        const files = await sftp.list(CONFIG.remoteDir);

        // Filter for SVG files that haven't been processed
        const svgFiles = files
            .filter(f => f.type === '-' && f.name.endsWith('.svg'))
            .filter(f => !processedFiles.has(f.name))
            .sort((a, b) => a.modifyTime - b.modifyTime); // Process oldest first

        if (svgFiles.length > 0) {
            console.log(`\nFound ${svgFiles.length} new file(s) to process`);

            for (const file of svgFiles) {
                const remotePath = `${CONFIG.remoteDir}/${file.name}`;
                await processFile(sftp, remotePath);
            }
        }

        return svgFiles.length;
    } catch (err) {
        console.error('Error checking for files:', err.message);
        return 0;
    }
}

/**
 * Main polling loop with auto-reconnect
 */
async function startWatcher() {
    console.log('========================================');
    console.log('LightBurn SFTP Watcher');
    console.log('========================================');
    console.log(`Remote: ${CONFIG.sftp.host}:${CONFIG.sftp.port}`);
    console.log(`Watch:  ${CONFIG.remoteDir}`);
    console.log(`Local:  ${CONFIG.localDir}`);
    console.log(`LightBurn: ${CONFIG.lightburn.host}:${CONFIG.lightburn.port}`);
    console.log('========================================\n');

    // Load previous state
    loadState();

    // Ensure local directory exists
    ensureLocalDir();

    // Handle graceful shutdown
    process.on('SIGINT', () => {
        console.log('\n\nShutting down...');
        isRunning = false;
        process.exit(0);
    });

    process.on('SIGTERM', () => {
        isRunning = false;
        process.exit(0);
    });

    // Check if running in one-shot mode
    const oneShot = process.argv.includes('--once');

    // Main loop with auto-reconnect
    while (isRunning) {
        const sftp = new SFTPClient();

        try {
            // Connect to SFTP
            console.log('Connecting to SFTP server...');
            await sftp.connect(CONFIG.sftp);
            console.log('Connected!\n');

            if (oneShot) {
                console.log('Running in one-shot mode (--once)');
                await checkForNewFiles(sftp);
                await sftp.end();
                console.log('\nDone.');
                return;
            }

            // Polling loop
            console.log(`Watching for new files (polling every ${CONFIG.pollInterval/1000}s)...`);
            console.log('Press Ctrl+C to stop\n');

            let consecutiveErrors = 0;

            while (isRunning && consecutiveErrors < 3) {
                try {
                    await checkForNewFiles(sftp);
                    consecutiveErrors = 0; // Reset on success
                } catch (err) {
                    consecutiveErrors++;
                    console.error(`Error (${consecutiveErrors}/3):`, err.message);
                }
                await new Promise(resolve => setTimeout(resolve, CONFIG.pollInterval));
            }

            // If we exit the inner loop due to errors, close and reconnect
            if (consecutiveErrors >= 3) {
                console.log('\nToo many errors, reconnecting...');
            }

            try {
                await sftp.end();
            } catch (e) {
                // Ignore close errors
            }

        } catch (err) {
            console.error('Connection error:', err.message);
        }

        // Wait before reconnecting
        if (isRunning) {
            console.log('Waiting 5 seconds before reconnecting...\n');
            await new Promise(resolve => setTimeout(resolve, 5000));
        }
    }
}

/**
 * Clear state command
 */
function clearState() {
    if (fs.existsSync(CONFIG.stateFile)) {
        fs.unlinkSync(CONFIG.stateFile);
        console.log('State cleared. All files will be reprocessed on next run.');
    } else {
        console.log('No state file found.');
    }
}

// Main entry point
if (process.argv.includes('--clear')) {
    clearState();
} else {
    startWatcher();
}

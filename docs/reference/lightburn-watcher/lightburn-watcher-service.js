#!/usr/bin/env node
/**
 * LightBurn SFTP Watcher - Windows Service Version
 *
 * Monitors a remote SFTP directory for new SVG files,
 * downloads them locally, and sends LOADFILE command to LightBurn.
 *
 * This version is designed to run as a Windows Service with:
 * - File-based logging (no console dependency)
 * - Graceful shutdown handling
 * - Better error recovery
 *
 * Usage:
 *   node lightburn-watcher-service.js [--once] [--clear]
 *   --once:  Process files once and exit (don't poll)
 *   --clear: Clear state file and exit
 */

const SFTPClient = require('ssh2-sftp-client');
const dgram = require('dgram');
const fs = require('fs');
const path = require('path');
const os = require('os');

// ============================================================================
// CONFIGURATION
// ============================================================================

const CONFIG = {
    // SFTP Settings (Kinsta staging server)
    sftp: {
        host: '34.71.83.227',
        port: 19039,
        username: 'luxeonstarleds',
        privateKey: fs.readFileSync(path.join(__dirname, 'rlux')),
        readyTimeout: 10000,
        retries: 3,
        retry_minTimeout: 2000,
    },

    // Remote directory to watch
    remoteDir: '/www/luxeonstarleds_546/public/wp-content/uploads/qsa-engraving/svg',

    // Local directory to download files to
    localDir: 'C:\\Users\\Production\\LightBurn\\Incoming',

    // LightBurn UDP settings
    lightburn: {
        host: '127.0.0.1',
        port: 19840,
    },

    // Polling interval in milliseconds
    pollInterval: 3000,

    // State file to track processed files
    stateFile: 'C:\\Users\\Production\\.lightburn-watcher-state.json',

    // Log file location
    logFile: 'C:\\Users\\Production\\LightBurn\\lightburn-watcher.log',

    // Maximum log file size in bytes (5MB)
    maxLogSize: 5 * 1024 * 1024,
};

// ============================================================================
// LOGGING
// ============================================================================

/**
 * Simple file logger for service mode
 */
class Logger {
    constructor(logPath, maxSize) {
        this.logPath = logPath;
        this.maxSize = maxSize;
    }

    _formatMessage(level, message) {
        const timestamp = new Date().toISOString();
        return `${timestamp} [${level}] ${message}\n`;
    }

    _rotateIfNeeded() {
        try {
            if (fs.existsSync(this.logPath)) {
                const stats = fs.statSync(this.logPath);
                if (stats.size > this.maxSize) {
                    const backupPath = this.logPath + '.old';
                    if (fs.existsSync(backupPath)) {
                        fs.unlinkSync(backupPath);
                    }
                    fs.renameSync(this.logPath, backupPath);
                }
            }
        } catch (err) {
            // Ignore rotation errors
        }
    }

    _write(level, message) {
        this._rotateIfNeeded();
        const formatted = this._formatMessage(level, message);

        // Write to file
        try {
            fs.appendFileSync(this.logPath, formatted);
        } catch (err) {
            // Fall back to console if file write fails
            process.stdout.write(formatted);
        }

        // Also output to console (useful for debugging, will go to service logs)
        process.stdout.write(formatted);
    }

    info(message) {
        this._write('INFO', message);
    }

    warn(message) {
        this._write('WARN', message);
    }

    error(message) {
        this._write('ERROR', message);
    }

    debug(message) {
        this._write('DEBUG', message);
    }
}

const log = new Logger(CONFIG.logFile, CONFIG.maxLogSize);

// ============================================================================
// STATE MANAGEMENT
// ============================================================================

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
            log.info(`Loaded state: ${processedFiles.size} previously processed files`);
        }
    } catch (err) {
        log.warn(`Could not load state file, starting fresh: ${err.message}`);
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
        log.error(`Could not save state file: ${err.message}`);
    }
}

/**
 * Ensure local directory exists
 */
function ensureLocalDir() {
    if (!fs.existsSync(CONFIG.localDir)) {
        fs.mkdirSync(CONFIG.localDir, { recursive: true });
        log.info(`Created local directory: ${CONFIG.localDir}`);
    }
}

// ============================================================================
// LIGHTBURN COMMUNICATION
// ============================================================================

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
        log.debug('Force killing LightBurn...');
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
    log.debug('Starting LightBurn...');

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
      // Kill LightBurn directly
      await killLightBurn();
      await wait(1000);

      // Start LightBurn with the file as argument (more reliable than UDP)
      const { spawn } = require('child_process');
      log.debug('Starting LightBurn with file...');

      const child = spawn('C:\\Program Files\\LightBurn\\LightBurn.exe', [filePath], {
          detached: true,
          stdio: 'ignore'
      });
      child.unref();
  }

// ============================================================================
// FILE PROCESSING
// ============================================================================

/**
 * Process a single file: download and send to LightBurn
 */
async function processFile(sftp, remoteFile) {
    const fileName = path.basename(remoteFile);
    const localFile = path.join(CONFIG.localDir, fileName);

    log.info(`>>> New file detected: ${fileName}`);

    try {
        // Download file
        log.debug(`Downloading to: ${localFile}`);
        await sftp.fastGet(remoteFile, localFile);

        // Verify download
        const stats = fs.statSync(localFile);
        log.debug(`Downloaded: ${stats.size} bytes`);

        // Send to LightBurn
        log.debug('Sending to LightBurn...');
        await sendToLightBurn(localFile);
        log.info(`SUCCESS: ${fileName} loaded in LightBurn`);

        // Mark as processed
        processedFiles.add(fileName);
        saveState();

        return true;
    } catch (err) {
        log.error(`Failed to process ${fileName}: ${err.message}`);
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
            log.info(`Found ${svgFiles.length} new file(s) to process`);

            for (const file of svgFiles) {
                if (!isRunning) break; // Check if we should stop

                const remotePath = `${CONFIG.remoteDir}/${file.name}`;
                await processFile(sftp, remotePath);
            }
        }

        return svgFiles.length;
    } catch (err) {
        log.error(`Error checking for files: ${err.message}`);
        return 0;
    }
}

// ============================================================================
// MAIN SERVICE LOOP
// ============================================================================

/**
 * Main polling loop with auto-reconnect
 */
async function startWatcher() {
    log.info('========================================');
    log.info('LightBurn SFTP Watcher Service Starting');
    log.info('========================================');
    log.info(`Remote: ${CONFIG.sftp.host}:${CONFIG.sftp.port}`);
    log.info(`Watch:  ${CONFIG.remoteDir}`);
    log.info(`Local:  ${CONFIG.localDir}`);
    log.info(`LightBurn: ${CONFIG.lightburn.host}:${CONFIG.lightburn.port}`);
    log.info(`Log file: ${CONFIG.logFile}`);
    log.info('========================================');

    // Load previous state
    loadState();

    // Ensure local directory exists
    ensureLocalDir();

    // Handle graceful shutdown signals
    const shutdown = (signal) => {
        log.info(`Received ${signal}, shutting down gracefully...`);
        isRunning = false;
    };

    process.on('SIGINT', () => shutdown('SIGINT'));
    process.on('SIGTERM', () => shutdown('SIGTERM'));
    process.on('SIGHUP', () => shutdown('SIGHUP'));

    // Handle Windows service stop
    process.on('message', (msg) => {
        if (msg === 'shutdown') {
            shutdown('service-shutdown');
        }
    });

    // Check if running in one-shot mode
    const oneShot = process.argv.includes('--once');

    // Main loop with auto-reconnect
    while (isRunning) {
        const sftp = new SFTPClient();

        try {
            // Connect to SFTP
            log.info('Connecting to SFTP server...');
            await sftp.connect(CONFIG.sftp);
            log.info('Connected to SFTP server');

            if (oneShot) {
                log.info('Running in one-shot mode (--once)');
                await checkForNewFiles(sftp);
                await sftp.end();
                log.info('One-shot complete, exiting');
                return;
            }

            // Polling loop
            log.info(`Watching for new files (polling every ${CONFIG.pollInterval/1000}s)...`);

            let consecutiveErrors = 0;
            let lastStatusLog = Date.now();
            const STATUS_LOG_INTERVAL = 300000; // Log status every 5 minutes

            while (isRunning && consecutiveErrors < 3) {
                try {
                    await checkForNewFiles(sftp);
                    consecutiveErrors = 0; // Reset on success

                    // Periodic status log (every 5 minutes)
                    if (Date.now() - lastStatusLog > STATUS_LOG_INTERVAL) {
                        log.info(`Service running - ${processedFiles.size} files processed total`);
                        lastStatusLog = Date.now();
                    }
                } catch (err) {
                    consecutiveErrors++;
                    log.error(`Polling error (${consecutiveErrors}/3): ${err.message}`);
                }

                if (isRunning) {
                    await wait(CONFIG.pollInterval);
                }
            }

            // If we exit the inner loop due to errors, close and reconnect
            if (consecutiveErrors >= 3) {
                log.warn('Too many consecutive errors, will reconnect...');
            }

            try {
                await sftp.end();
            } catch (e) {
                // Ignore close errors
            }

        } catch (err) {
            log.error(`SFTP connection error: ${err.message}`);
        }

        // Wait before reconnecting
        if (isRunning) {
            log.info('Waiting 5 seconds before reconnecting...');
            await wait(5000);
        }
    }

    log.info('Service stopped');
}

/**
 * Clear state command
 */
function clearState() {
    if (fs.existsSync(CONFIG.stateFile)) {
        fs.unlinkSync(CONFIG.stateFile);
        log.info('State cleared. All files will be reprocessed on next run.');
    } else {
        log.info('No state file found.');
    }
}

// ============================================================================
// ENTRY POINT
// ============================================================================

if (process.argv.includes('--clear')) {
    clearState();
} else {
    startWatcher().catch(err => {
        log.error(`Fatal error: ${err.message}`);
        process.exit(1);
    });
}

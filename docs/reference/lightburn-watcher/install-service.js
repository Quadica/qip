#!/usr/bin/env node
/**
 * LightBurn Watcher - Windows Service Installer
 *
 * Installs the LightBurn SFTP Watcher as a Windows Service using node-windows.
 *
 * Prerequisites:
 *   npm install node-windows
 *
 * Usage (run as Administrator):
 *   node install-service.js
 *
 * The service will:
 *   - Start automatically when Windows starts
 *   - Restart automatically if it crashes
 *   - Run under the LocalSystem account
 */

const path = require('path');
const Service = require('node-windows').Service;

// Path to the main script
const scriptPath = path.join(__dirname, 'lightburn-watcher-service.js');

// Create a new service object
const svc = new Service({
    name: 'LightBurn SFTP Watcher',
    description: 'Monitors SFTP for new SVG files and loads them into LightBurn for laser engraving.',
    script: scriptPath,

    // Node.js options
    nodeOptions: [
        '--max-old-space-size=256' // Limit memory usage
    ],

    // Environment variables (if needed)
    env: [
        {
            name: 'NODE_ENV',
            value: 'production'
        }
    ],

    // Auto-restart configuration
    // Wait 1 second before restarting after a failure
    wait: 1,

    // Grow the restart wait time by 0.5 seconds each restart (max 60 seconds)
    grow: 0.5,
    maxRestarts: 10
});

// Listen for install event
svc.on('install', function() {
    console.log('\n========================================');
    console.log('Service installed successfully!');
    console.log('========================================');
    console.log('\nStarting the service...');
    svc.start();
});

svc.on('start', function() {
    console.log('Service started!');
    console.log('\nThe service is now running in the background.');
    console.log('\nTo manage the service:');
    console.log('  - Open Services (services.msc)');
    console.log('  - Find "LightBurn SFTP Watcher"');
    console.log('  - Right-click to Start/Stop/Restart');
    console.log('\nLog file location:');
    console.log('  %USERPROFILE%\\lightburn-watcher.log');
    console.log('\nTo uninstall:');
    console.log('  node uninstall-service.js');
});

svc.on('alreadyinstalled', function() {
    console.log('\nService is already installed.');
    console.log('To reinstall, first run: node uninstall-service.js');
});

svc.on('error', function(err) {
    console.error('\nError:', err.message || err);
});

// Install the service
console.log('========================================');
console.log('LightBurn SFTP Watcher - Service Installer');
console.log('========================================');
console.log('\nScript path:', scriptPath);
console.log('\nInstalling Windows service...');
console.log('(You may see a UAC prompt - click Yes)\n');

svc.install();

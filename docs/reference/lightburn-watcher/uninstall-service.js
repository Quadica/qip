#!/usr/bin/env node
/**
 * LightBurn Watcher - Windows Service Uninstaller
 *
 * Removes the LightBurn SFTP Watcher Windows Service.
 *
 * Usage (run as Administrator):
 *   node uninstall-service.js
 */

const path = require('path');
const Service = require('node-windows').Service;

// Path to the main script (must match install-service.js)
const scriptPath = path.join(__dirname, 'lightburn-watcher-service.js');

// Create service object with same config
const svc = new Service({
    name: 'LightBurn SFTP Watcher',
    script: scriptPath,
});

// Listen for uninstall event
svc.on('uninstall', function() {
    console.log('\n========================================');
    console.log('Service uninstalled successfully!');
    console.log('========================================');
    console.log('\nThe service has been removed from Windows.');
    console.log('\nNote: Log files and state files are preserved:');
    console.log('  - %USERPROFILE%\\lightburn-watcher.log');
    console.log('  - %USERPROFILE%\\.lightburn-watcher-state.json');
    console.log('\nTo reinstall, run: node install-service.js');
});

svc.on('stop', function() {
    console.log('Service stopped.');
});

svc.on('error', function(err) {
    console.error('\nError:', err.message || err);
});

// Uninstall the service
console.log('========================================');
console.log('LightBurn SFTP Watcher - Service Uninstaller');
console.log('========================================');
console.log('\nStopping and removing Windows service...');
console.log('(You may see a UAC prompt - click Yes)\n');

svc.uninstall();

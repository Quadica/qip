# Lightburn Integration

## UDP Command Interface

Lightburn can be controlled remotely via UDP commands. This enables automated file loading and job control.

### Connection Parameters
| Parameter | Value |
|-----------|-------|
| IP Address | `127.0.0.1` (localhost) |
| Output Port | `19840` (send commands) |
| Input Port | `19841` (receive responses) |
| Timeout | 1-2 seconds recommended |

### Available Commands

| Command | Description | Notes |
|---------|-------------|-------|
| `PING` | Check if Lightburn is running and responsive | Reliable |
| `LOADFILE:{filepath}` | Load an SVG or project file | Reliable |
| `CLOSE` | Close current file | Unreliable* |
| `FORCECLOSE` | Force close without save prompt | Unreliable* |

**\*Note on CLOSE/FORCECLOSE:** In production, these UDP commands have proven unreliable. The current production system uses Win32 API process termination instead (see "Process Termination" section below).

### LightBurn Startup Requirement

**IMPORTANT:** LightBurn must be started as a process BEFORE UDP commands will work. The UDP listener only activates when LightBurn is running.

```php
// Start LightBurn first - UDP commands won't work until it's running
// On Windows, use shell_exec or similar
shell_exec('start "" "C:\\Program Files\\LightBurn\\LightBurn.exe"');

// Wait for initialization (3 seconds recommended)
sleep(3);

// Now UDP commands will work
$lb = new LightburnController();
if ($lb->ping()) {
    $lb->load_file($svg_path);
}
```

### Process Termination (Production Pattern)

Since CLOSE/FORCECLOSE UDP commands are unreliable, production uses process management:

```php
// On Windows, use taskkill or similar process termination
// This is more reliable than UDP CLOSE command
function force_close_lightburn(): void {
    // Try graceful shutdown first
    shell_exec('taskkill /IM LightBurn.exe');
    sleep(1);

    // Force kill if still running
    shell_exec('taskkill /F /IM LightBurn.exe');
}
```

### PHP UDP Client
```php
class LightburnController {
    private string $ip = '127.0.0.1';
    private int $out_port = 19840;
    private int $in_port = 19841;
    private $out_socket;
    private $in_socket;
    
    public function __construct() {
        $this->out_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        $this->in_socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        
        socket_bind($this->in_socket, $this->ip, $this->in_port);
        socket_set_option($this->in_socket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec' => 1,
            'usec' => 0
        ]);
    }
    
    public function send_command(string $command): array {
        $sent = socket_sendto(
            $this->out_socket,
            $command,
            strlen($command),
            0,
            $this->ip,
            $this->out_port
        );
        
        if ($sent === false) {
            return ['success' => false, 'response' => 'Failed to send'];
        }
        
        $response = '';
        $result = @socket_recvfrom($this->in_socket, $response, 1024, 0, $from, $port);
        
        if ($result === false) {
            return ['success' => false, 'response' => 'Timeout'];
        }
        
        return ['success' => true, 'response' => $response];
    }
    
    public function ping(): bool {
        $result = $this->send_command('PING');
        return $result['success'];
    }
    
    public function load_file(string $filepath): bool {
        // Use forward slashes for consistency
        $filepath = str_replace('/', '\\', $filepath);
        $result = $this->send_command("LOADFILE:{$filepath}");
        return $result['success'];
    }
    
    public function close(): void {
        socket_close($this->in_socket);
        socket_close($this->out_socket);
    }
}
```

### WordPress Integration
```php
// SVG generation is USER-INITIATED from admin interface
// NOT triggered by WooCommerce order status changes
// Called from AJAX handler when operator clicks "Generate SVG" in engraving queue

function handle_generate_engraving_job() {
    check_ajax_referer('qom_generate_svg', 'nonce');

    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Permission denied');
    }

    $job_id = absint($_POST['job_id']);

    // Generate SVG for the engraving job (contains multiple modules)
    $svg_path = generate_job_svg($job_id);

    // Optionally auto-load in Lightburn (if running on same machine)
    if (get_option('auto_load_lightburn')) {
        $lb = new LightburnController();
        if ($lb->ping()) {
            $lb->load_file($svg_path);
        }
        $lb->close();
    }

    wp_send_json_success(['path' => $svg_path]);
}
add_action('wp_ajax_qom_generate_svg', 'handle_generate_engraving_job');
```

## File Handling Workflow

### Batch Processing Pattern
1. Generate all SVG files first
2. Store file paths in queue
3. Process sequentially:
   - Open Lightburn (if not running)
   - Load file via UDP
   - Wait for operator confirmation
   - Close file
   - Process next

### File Naming Convention
Current format: `{batch_id}_{pcb_type}_{sequence:03d}.svg`
Example: `5807_sp-12a_029.svg`

Recommended format for QOM engraving jobs:
`{job_id}-{array_sequence}-{batch_id}.svg`
Example: `42-003-1234.svg` (job 42, array 3, batch 1234)

### Output Directory
Production path:
```
Q:\Shared drives\Quadica\Production\Layout App Print Files\UV Laser Engrave Files
```

In WordPress, configure via options:
```php
$output_dir = get_option('lightburn_svg_output_dir', 
    'Q:/Shared drives/Quadica/Production/Layout App Print Files/UV Laser Engrave Files');
```

## Lightburn Settings Notes

### Import Behavior
- SVG colors map to layers
- Shapes import at specified coordinates (no auto-centering)
- Text may require font availability on the machine

### Recommended Layer Configuration
Configure in Lightburn after first import, save as device defaults:

| Layer | Color | Use | Typical Settings |
|-------|-------|-----|------------------|
| 00 | Black | Primary marking | Power 80%, Speed 800mm/s |
| 01 | Red | Alignment (Tool) | Output OFF |

### Galvo-Specific Settings
The Cloudray UV-5 is a galvo laser. Relevant settings:
- Marking area: 110×110mm (with F=160mm lens)
- Common speed: up to 10,000mm/s
- Frequency: 40-200 kHz (adjust for material)

## Error Handling

### Common Issues

**UDP Timeout:**
- Lightburn not running
- Wrong port configuration
- Firewall blocking localhost UDP

**File Load Failure:**
- Path not accessible (network drive not mounted)
- File doesn't exist
- Invalid SVG format

### Retry Pattern
```php
function load_with_retry(LightburnController $lb, string $path, int $max_attempts = 3): bool {
    for ($i = 0; $i < $max_attempts; $i++) {
        if ($lb->load_file($path)) {
            return true;
        }
        sleep(1);
    }
    return false;
}
```

## Manual Workflow (No UDP)

If UDP automation isn't available:
1. Generate SVG to known directory
2. Operator opens Lightburn manually
3. File > Import or drag-drop SVG
4. Verify layer settings
5. Frame to check alignment
6. Run job

Store pending files in a queue table for operator to process.

<?php
/**
 * RFID Service Manager
 * Automatically starts and manages Python RFID API server and related services
 */

class RFIDServiceManager {
    private $python_api_port = 5001;
    private $python_api_host = 'localhost';
    private $service_log_file = 'rfid_services.log';
    private $pid_file = 'rfid_api.pid';
    
    public function __construct() {
        // Create logs directory if it doesn't exist
        $logs_dir = dirname(__FILE__) . '/logs';
        if (!is_dir($logs_dir)) {
            mkdir($logs_dir, 0755, true);
        }
        
        $this->service_log_file = $logs_dir . '/' . $this->service_log_file;
        $this->pid_file = $logs_dir . '/' . $this->pid_file;
    }
    
    /**
     * Check if Python RFID API is running
     */
    public function isPythonAPIRunning() {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "http://{$this->python_api_host}:{$this->python_api_port}/health");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return ($http_code === 200 && $response !== false);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Start Python RFID API server
     */
    public function startPythonAPI() {
        if ($this->isPythonAPIRunning()) {
            $this->log("Python API is already running on port {$this->python_api_port}");
            return true;
        }
        
        $this->log("Starting Python RFID API server...");
        
        // Check if Python 3 is available
        $python_path = $this->getPythonPath();
        if (!$python_path) {
            $this->log("ERROR: Python 3 not found. Please install Python 3.7+");
            return false;
        }
        
        // Check if required Python packages are installed
        if (!$this->checkPythonDependencies($python_path)) {
            $this->log("ERROR: Required Python packages not found. Installing...");
            $this->installPythonDependencies($python_path);
        }
        
        // Get the RFID writer directory path
        $rfid_dir = dirname(__FILE__) . '/rfid-writer';
        if (!is_dir($rfid_dir)) {
            $this->log("ERROR: RFID writer directory not found: {$rfid_dir}");
            return false;
        }
        
        // Start the Python API server
        $command = "cd {$rfid_dir} && RFID_API_PORT={$this->python_api_port} {$python_path} rfid_api.py > {$this->service_log_file} 2>&1 & echo \$!";
        
        $pid = shell_exec($command);
        $pid = trim($pid);
        
        if (is_numeric($pid) && $pid > 0) {
            // Save PID to file
            file_put_contents($this->pid_file, $pid);
            
            // Wait a moment for server to start
            sleep(3);
            
            if ($this->isPythonAPIRunning()) {
                $this->log("Python RFID API started successfully with PID: {$pid}");
                return true;
            } else {
                $this->log("ERROR: Python API failed to start properly");
                return false;
            }
        } else {
            $this->log("ERROR: Failed to start Python API server");
            return false;
        }
    }
    
    /**
     * Stop Python RFID API server
     */
    public function stopPythonAPI() {
        if (file_exists($this->pid_file)) {
            $pid = file_get_contents($this->pid_file);
            if (is_numeric($pid)) {
                // Kill the process
                exec("kill {$pid} 2>/dev/null");
                unlink($this->pid_file);
                $this->log("Python RFID API stopped (PID: {$pid})");
                return true;
            }
        }
        
        // Try to find and kill by port
        $command = "lsof -ti:{$this->python_api_port} 2>/dev/null";
        $pids = shell_exec($command);
        if ($pids) {
            $pids = explode("\n", trim($pids));
            foreach ($pids as $pid) {
                if (is_numeric($pid)) {
                    exec("kill {$pid} 2>/dev/null");
                    $this->log("Killed Python API process: {$pid}");
                }
            }
        }
        
        return true;
    }
    
    /**
     * Restart Python RFID API server
     */
    public function restartPythonAPI() {
        $this->log("Restarting Python RFID API server...");
        $this->stopPythonAPI();
        sleep(2);
        return $this->startPythonAPI();
    }
    
    /**
     * Get Python executable path
     */
    private function getPythonPath() {
        // First try portable Python
        $rfid_dir = dirname(__FILE__) . '/rfid-writer';
        $system = strtolower(PHP_OS_FAMILY);
        
        if ($system === 'windows') {
            $portable_python = $rfid_dir . '\\portable_python\\Scripts\\python.exe';
        } else {
            $portable_python = $rfid_dir . '/portable_python/bin/python3';
        }
        
        if (file_exists($portable_python)) {
            return $portable_python;
        }
        
        // Fallback to system Python
        $python_paths = [
            'python3',
            'python',
            '/usr/bin/python3',
            '/usr/local/bin/python3',
            '/opt/homebrew/bin/python3'
        ];
        
        foreach ($python_paths as $path) {
            $output = shell_exec("which {$path} 2>/dev/null");
            if ($output) {
                return trim($output);
            }
        }
        
        return false;
    }
    
    /**
     * Check if required Python packages are installed
     */
    private function checkPythonDependencies($python_path) {
        $required_packages = ['flask', 'flask_cors', 'serial'];
        
        foreach ($required_packages as $package) {
            $command = "{$python_path} -c \"import {$package}\" 2>/dev/null";
            $result = shell_exec($command);
            if ($result === null) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Install required Python packages
     */
    private function installPythonDependencies($python_path) {
        $rfid_dir = dirname(__FILE__) . '/rfid-writer';
        $requirements_file = $rfid_dir . '/requirements.txt';
        
        if (file_exists($requirements_file)) {
            $command = "cd {$rfid_dir} && {$python_path} -m pip install -r requirements.txt 2>&1";
            $output = shell_exec($command);
            $this->log("Installing Python packages: " . $output);
            return true;
        } else {
            // Install packages individually
            $packages = ['Flask==2.3.3', 'Flask-CORS==4.0.0', 'pyserial==3.5'];
            foreach ($packages as $package) {
                $command = "{$python_path} -m pip install {$package} 2>&1";
                $output = shell_exec($command);
                $this->log("Installing {$package}: " . $output);
            }
            return true;
        }
    }
    
    /**
     * Get service status
     */
    public function getServiceStatus() {
        $status = [
            'python_api' => [
                'running' => $this->isPythonAPIRunning(),
                'port' => $this->python_api_port,
                'host' => $this->python_api_host
            ],
            'arduino_connection' => false,
            'last_check' => date('Y-m-d H:i:s')
        ];
        
        // Check Arduino connection if Python API is running
        if ($status['python_api']['running']) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, "http://{$this->python_api_host}:{$this->python_api_port}/api/status");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if ($response) {
                    $data = json_decode($response, true);
                    $status['arduino_connection'] = isset($data['connected']) ? $data['connected'] : false;
                }
            } catch (Exception $e) {
                // Ignore errors
            }
        }
        
        return $status;
    }
    
    /**
     * Auto-start all services
     */
    public function autoStartServices() {
        $this->log("Auto-starting RFID services...");
        
        // Start Python API if not running
        if (!$this->isPythonAPIRunning()) {
            $this->startPythonAPI();
        }
        
        // Wait for services to be ready
        sleep(2);
        
        $status = $this->getServiceStatus();
        $this->log("Service status: " . json_encode($status));
        
        return $status;
    }
    
    /**
     * Log messages
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] {$message}" . PHP_EOL;
        file_put_contents($this->service_log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get service logs
     */
    public function getServiceLogs($lines = 50) {
        if (file_exists($this->service_log_file)) {
            $logs = file($this->service_log_file);
            return array_slice($logs, -$lines);
        }
        return [];
    }
}

// Auto-start services when this file is included
$rfid_service_manager = new RFIDServiceManager();
$rfid_service_manager->autoStartServices();

// Handle API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_status':
            $status = $rfid_service_manager->getServiceStatus();
            echo json_encode($status);
            break;
            
        case 'start_api':
            $result = $rfid_service_manager->startPythonAPI();
            echo json_encode(['success' => $result]);
            break;
            
        case 'stop_api':
            $result = $rfid_service_manager->stopPythonAPI();
            echo json_encode(['success' => $result]);
            break;
            
        case 'restart_api':
            $result = $rfid_service_manager->restartPythonAPI();
            echo json_encode(['success' => $result]);
            break;
            
        case 'get_logs':
            $lines = isset($_POST['lines']) ? (int)$_POST['lines'] : 50;
            $logs = $rfid_service_manager->getServiceLogs($lines);
            echo json_encode(['logs' => $logs]);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
            break;
    }
    exit;
}
?>

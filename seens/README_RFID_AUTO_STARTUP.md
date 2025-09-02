# RFID Services Auto-Startup System

## Overview
The RFID services (Python API server and related components) now automatically start when you access the main SEENS dashboard (`seens/index.php`). This ensures that RFID functionality is always available without manual intervention.

## How It Works

### 1. Automatic Startup
- **File**: `seens/rfid_service_manager.php`
- **Trigger**: Automatically runs when `seens/index.php` is loaded
- **Action**: Checks if Python RFID API is running, starts it if needed
- **Dependencies**: Automatically installs required Python packages if missing

### 2. Service Management
The service manager provides:
- **Auto-startup**: Starts Python API server automatically
- **Health monitoring**: Continuously checks service status
- **Dependency management**: Installs required Python packages
- **Process management**: Tracks and manages Python API processes
- **Logging**: Maintains detailed service logs

### 3. Dashboard Integration
- **Status Display**: Shows real-time RFID system status on main dashboard
- **Auto-refresh**: Updates status every 30 seconds
- **Quick Access**: Direct link to RFID Writer interface
- **Visual Indicators**: Color-coded status indicators (green=good, red=bad)

## Files Created/Modified

### New Files:
- `seens/rfid_service_manager.php` - Main service manager class
- `seens/start_rfid_services.php` - Manual service control interface
- `seens/README_RFID_AUTO_STARTUP.md` - This documentation

### Modified Files:
- `seens/index.php` - Added RFID status display and auto-startup integration

## Features

### Automatic Features:
✅ **Auto-startup**: Services start automatically when dashboard loads  
✅ **Dependency checking**: Python packages installed automatically  
✅ **Health monitoring**: Continuous status checking  
✅ **Process management**: Automatic process cleanup  
✅ **Error handling**: Graceful fallbacks and logging  

### Manual Control:
✅ **Start services**: Manual start if needed  
✅ **Stop services**: Manual stop if needed  
✅ **Restart services**: Quick restart functionality  
✅ **View logs**: Access to service logs  
✅ **Status monitoring**: Real-time status display  

## Service Status Display

The main dashboard now shows:

1. **Python API Status**
   - Running/Stopped indicator
   - Port number
   - Host information

2. **Arduino Connection Status**
   - Connected/Disconnected indicator
   - RFID module readiness

3. **Last Check Time**
   - When status was last verified
   - Auto-refresh indicator

## Troubleshooting

### If Services Don't Start:
1. **Check logs**: View `seens/logs/rfid_services.log`
2. **Manual start**: Visit `seens/start_rfid_services.php`
3. **Python check**: Ensure Python 3.7+ is installed
4. **Port conflicts**: Check if port 5001 is available

### Common Issues:
- **Python not found**: Install Python 3.7 or higher
- **Port in use**: Kill conflicting processes or change port
- **Permission denied**: Ensure proper file permissions
- **Dependencies missing**: Service manager will auto-install

## Manual Control

### Direct Access:
- **Service Control**: `seens/start_rfid_services.php`
- **Service Manager**: `seens/rfid_service_manager.php`
- **API Endpoints**: POST to service manager with actions

### Available Actions:
- `get_status` - Get current service status
- `start_api` - Start Python API server
- `stop_api` - Stop Python API server
- `restart_api` - Restart Python API server
- `get_logs` - Retrieve service logs

## Logs

### Log Locations:
- **Service logs**: `seens/logs/rfid_services.log`
- **Python API logs**: `seens/logs/rfid_api.log`
- **Process IDs**: `seens/logs/rfid_api.pid`

### Log Content:
- Service startup/shutdown events
- Connection attempts and results
- Error messages and debugging info
- Process management events

## Security Notes

- Services run on localhost only (127.0.0.1)
- No external network access
- Process isolation with PID tracking
- Automatic cleanup of orphaned processes

## Performance

- **Startup time**: ~3-5 seconds for Python API
- **Memory usage**: Minimal (Python Flask server)
- **CPU usage**: Low (only when processing requests)
- **Auto-refresh**: Every 30 seconds (configurable)

## Future Enhancements

- **Service recovery**: Automatic restart on failure
- **Load balancing**: Multiple Python API instances
- **Monitoring**: Advanced health checks and alerts
- **Configuration**: Web-based service configuration

---

**Note**: This system ensures RFID services are always available when needed, eliminating the need for manual startup procedures.

#!/usr/bin/env python3
"""
RFID API Server
A Flask-based API server that communicates with Arduino for RFID operations
"""

import serial
import time
import json
import logging
from flask import Flask, request, jsonify
from flask_cors import CORS
import threading
import os

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('rfid_api.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)  # Enable CORS for all routes

class RFIDController:
    def __init__(self):
        self.serial_connection = None
        self.port = None
        self.baudrate = 9600
        self.timeout = 5
        self.is_connected = False
        
    def connect(self, port):
        """Connect to Arduino on specified port"""
        try:
            if self.serial_connection and self.serial_connection.is_open:
                self.disconnect()
                
            self.port = port
            self.serial_connection = serial.Serial(
                port=port,
                baudrate=self.baudrate,
                timeout=self.timeout
            )
            
            # Set connected flag temporarily for testing
            self.is_connected = True
            
            # Wait for Arduino to initialize
            time.sleep(2)
            
            # Test connection
            if self.test_connection():
                logger.info(f"Successfully connected to {port}")
                return True
            else:
                self.disconnect()
                return False
                
        except Exception as e:
            logger.error(f"Connection error: {str(e)}")
            self.is_connected = False
            return False
    
    def disconnect(self):
        """Disconnect from Arduino"""
        try:
            if self.serial_connection and self.serial_connection.is_open:
                self.serial_connection.close()
            self.is_connected = False
            logger.info("Disconnected from Arduino")
        except Exception as e:
            logger.error(f"Disconnect error: {str(e)}")
    
    def test_connection(self):
        """Test if Arduino is responding"""
        try:
            if not self.serial_connection or not self.serial_connection.is_open:
                return False
                
            # Send PING command
            self.send_command("PING")
            response = self.read_response()
            
            if response and "READY" in response:
                return True
            return False
            
        except Exception as e:
            logger.error(f"Connection test error: {str(e)}")
            return False
    
    def send_command(self, command):
        """Send command to Arduino"""
        try:
            if not self.is_connected:
                raise Exception("Not connected to Arduino")
                
            # Clear input buffer
            self.serial_connection.reset_input_buffer()
            
            # Send command with newline
            full_command = f"{command}\n"
            self.serial_connection.write(full_command.encode())
            self.serial_connection.flush()
            
            logger.info(f"Sent command: {command}")
            time.sleep(0.1)  # Small delay for Arduino processing
            
        except Exception as e:
            logger.error(f"Send command error: {str(e)}")
            raise
    
    def read_response(self, timeout=10):
        """Read response from Arduino"""
        try:
            if not self.is_connected:
                raise Exception("Not connected to Arduino")
                
            start_time = time.time()
            response = ""
            
            while time.time() - start_time < timeout:
                if self.serial_connection.in_waiting > 0:
                    line = self.serial_connection.readline().decode('utf-8').strip()
                    if line:
                        response += line + "\n"
                        logger.info(f"Received: {line}")
                        
                        # Check for end markers
                        if "READY" in line or "ERROR" in line or "SUCCESS" in line:
                            break
                            
                time.sleep(0.1)
                
            return response.strip() if response else None
            
        except Exception as e:
            logger.error(f"Read response error: {str(e)}")
            return None
    
    def read_card_uid(self):
        """Read UID from RFID card"""
        try:
            if not self.is_connected:
                raise Exception("Not connected to Arduino")
                
            self.send_command("READ_UID")
            response = self.read_response(timeout=15)
            
            if response:
                if "UID:" in response:
                    # Extract UID from response
                    lines = response.split('\n')
                    for line in lines:
                        if line.startswith("UID:"):
                            uid = line.replace("UID:", "").strip()
                            return {"success": True, "uid": uid, "raw_response": response}
                
                if "ERROR" in response:
                    return {"success": False, "error": "Card reading failed", "raw_response": response}
                    
            return {"success": False, "error": "No response from Arduino", "raw_response": response}
            
        except Exception as e:
            logger.error(f"Read UID error: {str(e)}")
            return {"success": False, "error": str(e)}
    
    def read_from_card(self, block_number):
        """Read data from specific block on RFID card"""
        try:
            if not self.is_connected:
                raise Exception("Not connected to Arduino")
                
            command = f"READ:{block_number}"
            self.send_command(command)
            response = self.read_response(timeout=15)
            
            if response:
                if "DATA_TEXT:" in response:
                    # Extract readable text data from response
                    lines = response.split('\n')
                    for line in lines:
                        if line.startswith("DATA_TEXT:"):
                            data = line.replace("DATA_TEXT:", "").strip()
                            return {"success": True, "data": data, "block": block_number, "raw_response": response}
                elif "DATA_HEX:" in response:
                    # Extract hex data from response
                    lines = response.split('\n')
                    for line in lines:
                        if line.startswith("DATA_HEX:"):
                            data = line.replace("DATA_HEX:", "").strip()
                            return {"success": True, "data": data, "block": block_number, "raw_response": response}
                
                if "ERROR" in response:
                    return {"success": False, "error": "Card reading failed", "raw_response": response}
                    
            return {"success": False, "error": "No response from Arduino", "raw_response": response}
            
        except Exception as e:
            logger.error(f"Read from card error: {str(e)}")
            return {"success": False, "error": str(e)}
    
    def write_to_card(self, block_number, data):
        """Write data to specific block on RFID card"""
        try:
            if not self.is_connected:
                raise Exception("Not connected to Arduino")
                
            command = f"WRITE:{block_number}:{data}"
            self.send_command(command)
            response = self.read_response(timeout=15)
            
            if response:
                if "SUCCESS" in response:
                    return {"success": True, "message": "Data written successfully", "block": block_number, "raw_response": response}
                
                if "ERROR" in response:
                    return {"success": False, "error": "Card writing failed", "raw_response": response}
                    
            return {"success": False, "error": "No response from Arduino", "raw_response": response}
            
        except Exception as e:
            logger.error(f"Write to card error: {str(e)}")
            return {"success": False, "error": str(e)}
    
    def get_status(self):
        """Get Arduino status"""
        try:
            if not self.is_connected:
                return {"connected": False, "port": None}
                
            self.send_command("STATUS")
            response = self.read_response(timeout=5)
            
            return {
                "connected": True,
                "port": self.port,
                "status": response if response else "No response"
            }
            
        except Exception as e:
            logger.error(f"Status error: {str(e)}")
            return {"connected": False, "error": str(e)}

# Global RFID controller instance
rfid_controller = RFIDController()

@app.route('/api/connect', methods=['POST'])
def connect():
    """Connect to Arduino on specified port"""
    try:
        data = request.get_json()
        port = data.get('port')
        
        if not port:
            return jsonify({"success": False, "error": "Port is required"}), 400
        
        if rfid_controller.connect(port):
            return jsonify({
                "success": True,
                "message": f"Connected to {port}",
                "port": port
            })
        else:
            return jsonify({
                "success": False,
                "error": f"Failed to connect to {port}"
            }), 500
            
    except Exception as e:
        logger.error(f"Connect API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/disconnect', methods=['POST'])
def disconnect():
    """Disconnect from Arduino"""
    try:
        rfid_controller.disconnect()
        return jsonify({
            "success": True,
            "message": "Disconnected from Arduino"
        })
    except Exception as e:
        logger.error(f"Disconnect API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/status', methods=['GET'])
def get_status():
    """Get current connection status"""
    try:
        status = rfid_controller.get_status()
        return jsonify(status)
    except Exception as e:
        logger.error(f"Status API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/read_uid', methods=['POST'])
def read_uid():
    """Read UID from RFID card"""
    try:
        if not rfid_controller.is_connected:
            return jsonify({"success": False, "error": "Not connected to Arduino"}), 400
        
        result = rfid_controller.read_card_uid()
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Read UID API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/read', methods=['POST'])
def read_from_card():
    """Read data from specific block on RFID card"""
    try:
        if not rfid_controller.is_connected:
            return jsonify({"success": False, "error": "Not connected to Arduino"}), 400
        
        data = request.get_json()
        block_number = data.get('block_number')
        
        if block_number is None:
            return jsonify({"success": False, "error": "Block number is required"}), 400
        
        result = rfid_controller.read_from_card(block_number)
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Read from card API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/write', methods=['POST'])
def write_to_card():
    """Write data to specific block on RFID card"""
    try:
        if not rfid_controller.is_connected:
            return jsonify({"success": False, "error": "Not connected to Arduino"}), 400
        
        data = request.get_json()
        block_number = data.get('block_number')
        card_data = data.get('data')
        
        if block_number is None or card_data is None:
            return jsonify({"success": False, "error": "Block number and data are required"}), 400
        
        result = rfid_controller.write_to_card(block_number, card_data)
        return jsonify(result)
        
    except Exception as e:
        logger.error(f"Write to card API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/test', methods=['POST'])
def test_connection():
    """Test Arduino connection"""
    try:
        if not rfid_controller.is_connected:
            return jsonify({"success": False, "error": "Not connected to Arduino"}), 400
        
        if rfid_controller.test_connection():
            return jsonify({
                "success": True,
                "message": "Arduino is responding"
            })
        else:
            return jsonify({
                "success": False,
                "error": "Arduino is not responding"
            }), 500
            
    except Exception as e:
        logger.error(f"Test connection API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/api/ports', methods=['GET'])
def get_available_ports():
    """Get list of available serial ports"""
    try:
        import serial.tools.list_ports
        
        ports = []
        for port in serial.tools.list_ports.comports():
            ports.append({
                "device": port.device,
                "description": port.description,
                "manufacturer": port.manufacturer if port.manufacturer else "Unknown"
            })
        
        return jsonify({
            "success": True,
            "ports": ports
        })
        
    except Exception as e:
        logger.error(f"Get ports API error: {str(e)}")
        return jsonify({"success": False, "error": str(e)}), 500

@app.route('/health', methods=['GET'])
def health_check():
    """Health check endpoint"""
    return jsonify({
        "status": "healthy",
        "timestamp": time.time(),
        "connected": rfid_controller.is_connected
    })

if __name__ == '__main__':
    logger.info("Starting RFID API Server...")
    
    # Get port from environment variable or use default
    port = int(os.environ.get('RFID_API_PORT', 5000))
    host = os.environ.get('RFID_API_HOST', '0.0.0.0')
    
    logger.info(f"Server will run on {host}:{port}")
    logger.info("Available endpoints:")
    logger.info("  POST /api/connect - Connect to Arduino")
    logger.info("  POST /api/disconnect - Disconnect from Arduino")
    logger.info("  GET  /api/status - Get connection status")
    logger.info("  POST /api/read_uid - Read card UID")
    logger.info("  POST /api/read - Read from card block")
    logger.info("  POST /api/write - Write to card block")
    logger.info("  POST /api/test - Test Arduino connection")
    logger.info("  GET  /api/ports - Get available serial ports")
    logger.info("  GET  /health - Health check")
    
    try:
        app.run(host=host, port=port, debug=False, threaded=True)
    except KeyboardInterrupt:
        logger.info("Shutting down RFID API Server...")
        rfid_controller.disconnect()
    except Exception as e:
        logger.error(f"Server error: {str(e)}")
        rfid_controller.disconnect()

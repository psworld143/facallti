/*
 * Arduino RFID Simple Sketch
 * Compatible with RC522 RFID module
 * Designed to work with Python API
 */

#include <SPI.h>
#include <MFRC522.h>

#define SS_PIN 10
#define RST_PIN 9

MFRC522 mfrc522(SS_PIN, RST_PIN);

// Default key for MiFare Classic cards
MFRC522::MIFARE_Key key;

void setup() {
  Serial.begin(9600);
  SPI.begin();
  mfrc522.PCD_Init();
  
  // Initialize the key
  for (byte i = 0; i < 6; i++) {
    key.keyByte[i] = 0xFF;
  }
  
  // Print ready message
  Serial.println("RFID_READY");
}

void loop() {
  // Wait for commands from Python API
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    
    if (command.length() > 0) {
      processCommand(command);
    }
  }
}

void processCommand(String command) {
  Serial.println("Command received: " + command);
  
  if (command == "PING") {
    Serial.println("PONG");
    Serial.println("READY");
  }
  else if (command == "STATUS") {
    Serial.println("Arduino Status: OK");
    Serial.println("RFID Module: Ready");
    Serial.println("READY");
  }
  else if (command == "READ_UID") {
    readCardUID();
  }
  else if (command.startsWith("READ:")) {
    int blockNumber = command.substring(5).toInt();
    readFromCard(blockNumber);
  }
  else if (command.startsWith("WRITE:")) {
    // Format: WRITE:block:data
    int firstColon = command.indexOf(':', 6);
    if (firstColon != -1) {
      int blockNumber = command.substring(6, firstColon).toInt();
      String data = command.substring(firstColon + 1);
      writeToCard(blockNumber, data);
    } else {
      Serial.println("ERROR: Invalid WRITE command format");
    }
  }
  else {
    Serial.println("ERROR: Unknown command");
  }
}

void readCardUID() {
  Serial.println("Reading card UID...");
  
  // Look for new cards
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("ERROR: No card present");
    return;
  }
  
  // Select one of the cards
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("ERROR: Failed to read card");
    return;
  }
  
  // Get card type
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  Serial.println("Card Type: " + String(mfrc522.PICC_GetTypeName(piccType)));
  
  // Print UID
  Serial.print("UID:");
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    Serial.print(mfrc522.uid.uidByte[i] < 0x10 ? "0" : "");
    Serial.print(mfrc522.uid.uidByte[i], HEX);
  }
  Serial.println();
  
  Serial.println("UID_READY");
  
  // Halt PICC
  mfrc522.PICC_HaltA();
}

void readFromCard(int blockNumber) {
  Serial.println("Reading from block " + String(blockNumber));
  
  // Look for new cards
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("ERROR: No card present");
    return;
  }
  
  // Select one of the cards
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("ERROR: Failed to read card");
    return;
  }
  
  // Get card type
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  Serial.println("Card Type: " + String(mfrc522.PICC_GetTypeName(piccType)));
  
  // Authenticate
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(MFRC522::PICC_CMD_MF_AUTH_KEY_A, blockNumber, &key, &(mfrc522.uid));
  if (status != MFRC522::STATUS_OK) {
    Serial.println("ERROR: Authentication failed");
    mfrc522.PICC_HaltA();
    return;
  }
  
  // Read block
  byte buffer[18];
  byte size = sizeof(buffer);
  status = mfrc522.MIFARE_Read(blockNumber, buffer, &size);
  if (status != MFRC522::STATUS_OK) {
    Serial.println("ERROR: Read failed");
    mfrc522.PICC_HaltA();
    return;
  }
  
      // Print data in hex
    Serial.print("DATA_HEX:");
    for (byte i = 0; i < 16; i++) {
      if (buffer[i] < 0x10) Serial.print("0");
      Serial.print(buffer[i], HEX);
    }
    Serial.println();
    
    // Print data as readable text
    Serial.print("DATA_TEXT:");
    for (byte i = 0; i < 16; i++) {
      if (buffer[i] >= 32 && buffer[i] <= 126) { // Printable ASCII
        Serial.print((char)buffer[i]);
      } else if (buffer[i] == 0) {
        break; // Stop at null terminator
      }
    }
    Serial.println();
    
    Serial.println("READY");
  
  // Halt PICC and stop crypto
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

void writeToCard(int blockNumber, String data) {
  Serial.println("Writing to block " + String(blockNumber) + ": " + data);
  
  // Look for new cards
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("ERROR: No card present");
    return;
  }
  
  // Select one of the cards
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("ERROR: Failed to read card");
    return;
  }
  
  // Get card type
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  Serial.println("Card Type: " + String(mfrc522.PICC_GetTypeName(piccType)));
  
  // Authenticate
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(MFRC522::PICC_CMD_MF_AUTH_KEY_A, blockNumber, &key, &(mfrc522.uid));
  if (status != MFRC522::STATUS_OK) {
    Serial.println("ERROR: Authentication failed");
    mfrc522.PICC_HaltA();
    return;
  }
  
  // Prepare data buffer (16 bytes)
  byte buffer[16];
  // Clear buffer
  for (byte i = 0; i < 16; i++) {
    buffer[i] = 0x00;
  }
  
  // Copy data to buffer
  int dataLength = min(data.length(), 16);
  for (int i = 0; i < dataLength; i++) {
    buffer[i] = data.charAt(i);
  }
  
  // Write block
  status = mfrc522.MIFARE_Write(blockNumber, buffer, 16);
  if (status != MFRC522::STATUS_OK) {
    Serial.println("ERROR: Write failed");
    mfrc522.PICC_HaltA();
    return;
  }
  
  Serial.println("SUCCESS: Data written to block " + String(blockNumber));
  Serial.println("READY");
  
  // Halt PICC and stop crypto
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
}

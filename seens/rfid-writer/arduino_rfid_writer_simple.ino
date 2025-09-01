/*
 * Simple RFID Writer for SEENS System - Debug Version
 * Arduino sketch for RC522 RFID module with better feedback
 * 
 * Hardware Connections:
 * RC522    Arduino
 * SDA  ->  Pin 10
 * SCK  ->  Pin 13
 * MOSI ->  Pin 11
 * MISO ->  Pin 12
 * RST  ->  Pin 9
 * 3.3V ->  3.3V
 * GND  ->  GND
 */

#include <SPI.h>
#include <MFRC522.h>

#define RST_PIN         9
#define SS_PIN          10

MFRC522 mfrc522(SS_PIN, RST_PIN);

// Default key for MiFare Classic
MFRC522::MIFARE_Key key = {
  {0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF}
};

void setup() {
  Serial.begin(9600);
  while (!Serial) {
    ; // Wait for serial port to connect
  }
  
  SPI.begin();
  mfrc522.PCD_Init();
  
  // Wait for RFID module to initialize
  delay(4);
  
  Serial.println("RFID_WRITER_READY");
  Serial.println("Waiting for commands...");
}

void loop() {
  // Check for serial commands
  if (Serial.available()) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    
    processCommand(command);
  }
  
  // Check for RFID card (with feedback)
  if (mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("CARD_DETECTED");
    
    if (mfrc522.PICC_ReadCardSerial()) {
      Serial.print("CARD_UID: ");
      for (byte i = 0; i < mfrc522.uid.size; i++) {
        Serial.print(mfrc522.uid.uidByte[i] < 0x10 ? " 0" : " ");
        Serial.print(mfrc522.uid.uidByte[i], HEX);
      }
      Serial.println();
      
      // Get card type
      MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
      Serial.print("CARD_TYPE: ");
      Serial.println(mfrc522.PICC_GetTypeName(piccType));
      
      mfrc522.PICC_HaltA();
    } else {
      Serial.println("CARD_READ_ERROR");
    }
  }
  
  delay(100);
}

void processCommand(String command) {
  Serial.print("COMMAND_RECEIVED: ");
  Serial.println(command);
  
  if (command == "STATUS") {
    Serial.println("RFID_WRITER_READY");
  }
  else if (command == "PING") {
    Serial.println("PONG");
  }
  else if (command.startsWith("WRITE:")) {
    // Format: WRITE:block:data
    int firstColon = command.indexOf(':');
    int secondColon = command.indexOf(':', firstColon + 1);
    
    if (firstColon != -1 && secondColon != -1) {
      int block = command.substring(firstColon + 1, secondColon).toInt();
      String data = command.substring(secondColon + 1);
      
      Serial.print("WRITE_REQUEST: Block ");
      Serial.print(block);
      Serial.print(", Data: ");
      Serial.println(data);
      
      if (writeToCard(data, block)) {
        Serial.println("WRITE_OK");
      } else {
        Serial.println("WRITE_ERROR");
      }
    } else {
      Serial.println("WRITE_ERROR: Invalid format");
    }
  }
  else if (command.startsWith("READ:")) {
    // Format: READ:block
    int colon = command.indexOf(':');
    
    if (colon != -1) {
      int block = command.substring(colon + 1).toInt();
      
      Serial.print("READ_REQUEST: Block ");
      Serial.println(block);
      
      String data = readFromCard(block);
      
      if (data != "") {
        Serial.print("READ_OK:");
        Serial.print(block);
        Serial.print(":");
        Serial.println(data);
      } else {
        Serial.println("READ_ERROR");
      }
    } else {
      Serial.println("READ_ERROR: Invalid format");
    }
  }
  else {
    Serial.println("UNKNOWN_COMMAND");
  }
}

bool writeToCard(String data, int block) {
  Serial.println("WRITE_PROCESS: Starting...");
  
  // Check if card is present
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("WRITE_ERROR: No card present");
    return false;
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("WRITE_ERROR: Cannot read card serial");
    return false;
  }
  
  Serial.println("WRITE_PROCESS: Card detected");
  
  // Check if it's a MiFare Classic card
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  if (piccType != MFRC522::PICC_TYPE_MIFARE_MINI &&
      piccType != MFRC522::PICC_TYPE_MIFARE_1K &&
      piccType != MFRC522::PICC_TYPE_MIFARE_4K) {
    Serial.println("WRITE_ERROR: Not a MiFare Classic card");
    mfrc522.PICC_HaltA();
    return false;
  }
  
  Serial.println("WRITE_PROCESS: Card type OK");
  
  // Authenticate the card
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(
    MFRC522::PICC_CMD_MF_AUTH_KEY_A, block, &key, &(mfrc522.uid)
  );
  
  if (status != MFRC522::STATUS_OK) {
    Serial.println("WRITE_ERROR: Authentication failed");
    mfrc522.PICC_HaltA();
    return false;
  }
  
  Serial.println("WRITE_PROCESS: Authentication OK");
  
  // Prepare data for writing (16 bytes)
  byte writeData[16];
  memset(writeData, 0, 16); // Fill with zeros
  
  // Copy data to buffer
  int dataLength = min(data.length(), 16);
  for (int i = 0; i < dataLength; i++) {
    writeData[i] = data.charAt(i);
  }
  
  // Write to card
  status = mfrc522.MIFARE_Write(block, writeData, 16);
  
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  
  if (status == MFRC522::STATUS_OK) {
    Serial.println("WRITE_PROCESS: Write successful");
    return true;
  } else {
    Serial.println("WRITE_ERROR: Write operation failed");
    return false;
  }
}

String readFromCard(int block) {
  Serial.println("READ_PROCESS: Starting...");
  
  // Check if card is present
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("READ_ERROR: No card present");
    return "";
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("READ_ERROR: Cannot read card serial");
    return "";
  }
  
  Serial.println("READ_PROCESS: Card detected");
  
  // Check if it's a MiFare Classic card
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  if (piccType != MFRC522::PICC_TYPE_MIFARE_MINI &&
      piccType != MFRC522::PICC_TYPE_MIFARE_1K &&
      piccType != MFRC522::PICC_TYPE_MIFARE_4K) {
    Serial.println("READ_ERROR: Not a MiFare Classic card");
    mfrc522.PICC_HaltA();
    return "";
  }
  
  Serial.println("READ_PROCESS: Card type OK");
  
  // Authenticate the card
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(
    MFRC522::PICC_CMD_MF_AUTH_KEY_A, block, &key, &(mfrc522.uid)
  );
  
  if (status != MFRC522::STATUS_OK) {
    Serial.println("READ_ERROR: Authentication failed");
    mfrc522.PICC_HaltA();
    return "";
  }
  
  Serial.println("READ_PROCESS: Authentication OK");
  
  // Read from card
  byte readData[18];
  byte bufferSize = sizeof(readData);
  
  status = mfrc522.MIFARE_Read(block, readData, &bufferSize);
  
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  
  if (status == MFRC522::STATUS_OK) {
    Serial.println("READ_PROCESS: Read successful");
    // Convert bytes to string
    String result = "";
    for (int i = 0; i < 16; i++) {
      if (readData[i] != 0) {
        result += (char)readData[i];
      }
    }
    return result;
  } else {
    Serial.println("READ_ERROR: Read operation failed");
    return "";
  }
}

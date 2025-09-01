/*
 * RFID Writer for SEENS System
 * Arduino Uno with RC522 RFID Module
 * Accepts commands from PHP via Serial communication
 * 
 * Hardware Connections:
 * RC522 -> Arduino Uno
 * SDA   -> Pin 10
 * SCK   -> Pin 13
 * MOSI  -> Pin 11
 * MISO  -> Pin 12
 * RST   -> Pin 9
 * 3.3V  -> 3.3V
 * GND   -> GND
 */

#include <SPI.h>
#include <MFRC522.h>

#define RST_PIN         9
#define SS_PIN          10

MFRC522 mfrc522(SS_PIN, RST_PIN);

// Command structure from PHP
struct RFIDCommand {
  char action[10];      // "WRITE" or "READ"
  char data[64];        // Data to write (e.g., "2021-0001")
  char block[5];        // Block number (0-63 for MiFare Classic)
};

RFIDCommand currentCommand;
bool commandReceived = false;

void setup() {
  Serial.begin(9600);
  SPI.begin();
  mfrc522.PCD_Init();
  
  Serial.println("RFID_WRITER_READY");
  Serial.println("Waiting for commands...");
}

void loop() {
  // Check for incoming commands from PHP
  if (Serial.available() > 0) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    
    if (command.startsWith("WRITE:")) {
      parseWriteCommand(command);
      executeWriteCommand();
    } else if (command.startsWith("READ:")) {
      parseReadCommand(command);
      executeReadCommand();
    } else if (command == "STATUS") {
      Serial.println("RFID_WRITER_READY");
    }
  }
  
  delay(100);
}

void parseWriteCommand(String command) {
  // Format: WRITE:data:block
  // Example: WRITE:2021-0001:4
  int firstColon = command.indexOf(':');
  int secondColon = command.indexOf(':', firstColon + 1);
  
  if (firstColon != -1 && secondColon != -1) {
    strcpy(currentCommand.action, "WRITE");
    String data = command.substring(firstColon + 1, secondColon);
    String block = command.substring(secondColon + 1);
    
    data.toCharArray(currentCommand.data, sizeof(currentCommand.data));
    block.toCharArray(currentCommand.block, sizeof(currentCommand.block));
    
    commandReceived = true;
    Serial.println("COMMAND_PARSED:WRITE");
  }
}

void parseReadCommand(String command) {
  // Format: READ:block
  // Example: READ:4
  int colon = command.indexOf(':');
  
  if (colon != -1) {
    strcpy(currentCommand.action, "READ");
    String block = command.substring(colon + 1);
    block.toCharArray(currentCommand.block, sizeof(currentCommand.block));
    
    commandReceived = true;
    Serial.println("COMMAND_PARSED:READ");
  }
}

void executeWriteCommand() {
  Serial.println("PLACE_CARD_ON_READER");
  
  // Wait for card to be placed
  while (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    delay(100);
  }
  
  // Check if card is MiFare Classic
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  if (piccType != MFRC522::PICC_TYPE_MIFARE_MINI &&
      piccType != MFRC522::PICC_TYPE_MIFARE_1K &&
      piccType != MFRC522::PICC_TYPE_MIFARE_4K) {
    Serial.println("ERROR:NOT_MIFARE_CARD");
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    commandReceived = false;
    return;
  }
  
  int blockNum = atoi(currentCommand.block);
  
  // Authenticate block
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(MFRC522::PICC_CMD_MF_AUTH_KEY_A, blockNum, &key, &(mfrc522.uid));
  
  if (status != MFRC522::STATUS_OK) {
    Serial.println("ERROR:AUTHENTICATION_FAILED");
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    commandReceived = false;
    return;
  }
  
  // Prepare data block (16 bytes)
  byte dataBlock[16];
  memset(dataBlock, 0, 16);
  strcpy((char*)dataBlock, currentCommand.data);
  
  // Write data to block
  status = mfrc522.MIFARE_Write(blockNum, dataBlock, 16);
  
  if (status == MFRC522::STATUS_OK) {
    Serial.println("SUCCESS:DATA_WRITTEN");
    Serial.print("BLOCK:");
    Serial.println(blockNum);
    Serial.print("DATA:");
    Serial.println(currentCommand.data);
  } else {
    Serial.println("ERROR:WRITE_FAILED");
  }
  
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  commandReceived = false;
}

void executeReadCommand() {
  Serial.println("PLACE_CARD_ON_READER");
  
  // Wait for card to be placed
  while (!mfrc522.PICC_IsNewCardPresent() || !mfrc522.PICC_ReadCardSerial()) {
    delay(100);
  }
  
  int blockNum = atoi(currentCommand.block);
  
  // Authenticate block
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(MFRC522::PICC_CMD_MF_AUTH_KEY_A, blockNum, &key, &(mfrc522.uid));
  
  if (status != MFRC522::STATUS_OK) {
    Serial.println("ERROR:AUTHENTICATION_FAILED");
    mfrc522.PICC_HaltA();
    mfrc522.PCD_StopCrypto1();
    commandReceived = false;
    return;
  }
  
  // Read data from block
  byte dataBlock[18];
  byte size = sizeof(dataBlock);
  
  status = mfrc522.MIFARE_Read(blockNum, dataBlock, &size);
  
  if (status == MFRC522::STATUS_OK) {
    Serial.println("SUCCESS:DATA_READ");
    Serial.print("BLOCK:");
    Serial.println(blockNum);
    Serial.print("DATA:");
    for (int i = 0; i < 16; i++) {
      if (dataBlock[i] != 0) {
        Serial.print((char)dataBlock[i]);
      }
    }
    Serial.println();
  } else {
    Serial.println("ERROR:READ_FAILED");
  }
  
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  commandReceived = false;
}

// Default key for MiFare Classic (you can change this)
MFRC522::MIFARE_Key key = {
  {0xFF, 0xFF, 0xFF, 0xFF, 0xFF, 0xFF}
};

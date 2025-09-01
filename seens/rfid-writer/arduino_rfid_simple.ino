/*
 * Simple RFID Reader for SEENS System
 * Basic UID reading and card operations
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
  mfrc522.PCD_DumpVersionToSerial();
  
  Serial.println("RFID_READY");
  Serial.println("Waiting for commands...");
}

void loop() {
  // Check for serial commands
  if (Serial.available()) {
    String command = Serial.readStringUntil('\n');
    command.trim();
    
    processCommand(command);
  }
  
  delay(100);
}

void processCommand(String command) {
  Serial.print("CMD: ");
  Serial.println(command);
  
  if (command == "STATUS") {
    Serial.println("RFID_READY");
  }
  else if (command == "PING") {
    Serial.println("PONG");
  }
  else if (command == "READ_UID") {
    readCardUID();
  }
  else if (command.startsWith("READ:")) {
    // Format: READ:block
    int colon = command.indexOf(':');
    if (colon != -1) {
      int block = command.substring(colon + 1).toInt();
      readFromCard(block);
    } else {
      Serial.println("READ_ERROR: Invalid format");
    }
  }
  else if (command.startsWith("WRITE:")) {
    // Format: WRITE:block:data
    int firstColon = command.indexOf(':');
    int secondColon = command.indexOf(':', firstColon + 1);
    
    if (firstColon != -1 && secondColon != -1) {
      int block = command.substring(firstColon + 1, secondColon).toInt();
      String data = command.substring(secondColon + 1);
      writeToCard(data, block);
    } else {
      Serial.println("WRITE_ERROR: Invalid format");
    }
  }
  else {
    Serial.println("UNKNOWN_COMMAND");
  }
}

void readCardUID() {
  Serial.println("READING_UID...");
  
  // Check if card is present
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("UID_ERROR: No card present");
    return;
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("UID_ERROR: Cannot read card");
    return;
  }
  
  Serial.print("UID: ");
  for (byte i = 0; i < mfrc522.uid.size; i++) {
    Serial.print(mfrc522.uid.uidByte[i] < 0x10 ? " 0" : " ");
    Serial.print(mfrc522.uid.uidByte[i], HEX);
  }
  Serial.println();
  
  // Get card type
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  Serial.print("TYPE: ");
  Serial.println(mfrc522.PICC_GetTypeName(piccType));
  
  mfrc522.PICC_HaltA();
  Serial.println("UID_READY");
}

void readFromCard(int block) {
  Serial.print("READING_BLOCK: ");
  Serial.println(block);
  
  // Check if card is present
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("READ_ERROR: No card present");
    return;
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("READ_ERROR: Cannot read card");
    return;
  }
  
  Serial.println("CARD_DETECTED");
  
  // Check if it's a MiFare Classic card
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  if (piccType != MFRC522::PICC_TYPE_MIFARE_MINI &&
      piccType != MFRC522::PICC_TYPE_MIFARE_1K &&
      piccType != MFRC522::PICC_TYPE_MIFARE_4K) {
    Serial.println("READ_ERROR: Not MiFare Classic");
    mfrc522.PICC_HaltA();
    return;
  }
  
  Serial.println("CARD_TYPE_OK");
  
  // Authenticate the card
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(
    MFRC522::PICC_CMD_MF_AUTH_KEY_A, block, &key, &(mfrc522.uid)
  );
  
  if (status != MFRC522::STATUS_OK) {
    Serial.println("READ_ERROR: Auth failed");
    mfrc522.PICC_HaltA();
    return;
  }
  
  Serial.println("AUTH_OK");
  
  // Read from card
  byte readData[18];
  byte bufferSize = sizeof(readData);
  
  status = mfrc522.MIFARE_Read(block, readData, &bufferSize);
  
  mfrc522.PICC_HaltA();
  mfrc522.PCD_StopCrypto1();
  
  if (status == MFRC522::STATUS_OK) {
    Serial.print("READ_OK:");
    Serial.print(block);
    Serial.print(":");
    
    // Convert bytes to string
    String result = "";
    for (int i = 0; i < 16; i++) {
      if (readData[i] != 0) {
        result += (char)readData[i];
      }
    }
    Serial.println(result);
  } else {
    Serial.println("READ_ERROR: Read failed");
  }
}

void writeToCard(String data, int block) {
  Serial.print("WRITING_BLOCK: ");
  Serial.print(block);
  Serial.print(" DATA: ");
  Serial.println(data);
  
  // Check if card is present
  if (!mfrc522.PICC_IsNewCardPresent()) {
    Serial.println("WRITE_ERROR: No card present");
    return;
  }
  
  if (!mfrc522.PICC_ReadCardSerial()) {
    Serial.println("WRITE_ERROR: Cannot read card");
    return;
  }
  
  Serial.println("CARD_DETECTED");
  
  // Check if it's a MiFare Classic card
  MFRC522::PICC_Type piccType = mfrc522.PICC_GetType(mfrc522.uid.sak);
  if (piccType != MFRC522::PICC_TYPE_MIFARE_MINI &&
      piccType != MFRC522::PICC_TYPE_MIFARE_1K &&
      piccType != MFRC522::PICC_TYPE_MIFARE_4K) {
    Serial.println("WRITE_ERROR: Not MiFare Classic");
    mfrc522.PICC_HaltA();
    return;
  }
  
  Serial.println("CARD_TYPE_OK");
  
  // Authenticate the card
  MFRC522::StatusCode status = mfrc522.PCD_Authenticate(
    MFRC522::PICC_CMD_MF_AUTH_KEY_A, block, &key, &(mfrc522.uid)
  );
  
  if (status != MFRC522::STATUS_OK) {
    Serial.println("WRITE_ERROR: Auth failed");
    mfrc522.PICC_HaltA();
    return;
  }
  
  Serial.println("AUTH_OK");
  
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
    Serial.println("WRITE_OK");
  } else {
    Serial.println("WRITE_ERROR: Write failed");
  }
}

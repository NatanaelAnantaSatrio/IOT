#include <ESP8266WiFi.h>
#include <PubSubClient.h>
#include <DHT.h>
#include <Wire.h>
#include <LiquidCrystal_I2C.h>

// WiFi
const char* ssid = "Tupperware";
const char* password = "mela3006";

// MQTT
const char* mqtt_server = "mqtt.revolusi-it.com";
const char* mqtt_user = "usm";
const char* mqtt_pass = "usmjaya1";
const char* clientID = "G231220167";
const char* mqtt_topic_pub = "iot/G231220167";
const char* mqtt_topic_cmd = "iot/G231220167/cmd";

// Pin
#define DHTPIN D4        // PIN DHT DIGANTI KE D4
#define DHTTYPE DHT11
#define LED1 D6
#define LED2 D7
#define LED3 D8
#define LM35PIN A0

// Object
DHT dht(DHTPIN, DHTTYPE);
WiFiClient espClient;
PubSubClient client(espClient);
LiquidCrystal_I2C lcd(0x27, 16, 2);

// State
unsigned long lastMsg = 0;

void setup_wifi() {
  delay(10);
  Serial.println();
  Serial.print("Menghubungkan ke ");
  Serial.println(ssid);

  WiFi.begin(ssid, password);
  int retry = 0;
  while (WiFi.status() != WL_CONNECTED) {
    delay(500);
    Serial.print(".");
    retry++;
    if (retry > 40) {
      Serial.println(" Gagal koneksi WiFi!");
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("WiFi Error!");
      while (1);
    }
  }
  Serial.println("");
  Serial.println("WiFi terhubung");
  Serial.print("IP: ");
  Serial.println(WiFi.localIP());
}

void callback(char* topic, byte* payload, unsigned int length) {
  String msg;
  for (unsigned int i = 0; i < length; i++) {
    msg += (char)payload[i];
  }
  Serial.print("Pesan masuk [");
  Serial.print(topic);
  Serial.print("]: ");
  Serial.println(msg);

  if (String(topic) == mqtt_topic_cmd) {
    int ledStatus = msg.toInt();
    digitalWrite(LED1, LOW);
    digitalWrite(LED2, LOW);
    digitalWrite(LED3, LOW);
    if (ledStatus >= 1) digitalWrite(LED1, HIGH);
    if (ledStatus >= 2) digitalWrite(LED2, HIGH);
    if (ledStatus >= 3) digitalWrite(LED3, HIGH);
  }
}

void reconnect() {
  while (!client.connected()) {
    Serial.print("Menghubungkan ke MQTT...");
    if (client.connect(clientID, mqtt_user, mqtt_pass)) {
      Serial.println("MQTT terhubung");
      client.subscribe(mqtt_topic_cmd);
    } else {
      Serial.print("Gagal, rc=");
      Serial.print(client.state());
      Serial.println(" coba lagi dalam 5 detik...");
      delay(5000);
    }
  }
}

void setup() {
  Serial.begin(115200);
  pinMode(LED1, OUTPUT);
  pinMode(LED2, OUTPUT);
  pinMode(LED3, OUTPUT);
  digitalWrite(LED1, LOW);
  digitalWrite(LED2, LOW);
  digitalWrite(LED3, LOW);

  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("Init...");

  setup_wifi();
  dht.begin();
  client.setServer(mqtt_server, 1883);
  client.setCallback(callback);

  lcd.clear();
}

void loop() {
  if (!client.connected()) {
    reconnect();
  }
  client.loop();

  unsigned long now = millis();
  if (now - lastMsg > 2000) {
    lastMsg = now;

    float h = dht.readHumidity();
    float t = dht.readTemperature();
    int adcValue = analogRead(LM35PIN);
    float voltage = adcValue * (3.3 / 1023.0);
    float tempLM35 = voltage * 100.0;

    if (isnan(t) || isnan(h)) {
      Serial.println("DHT gagal baca data!");
      lcd.clear();
      lcd.setCursor(0, 0);
      lcd.print("DHT ERROR");
      lcd.setCursor(0, 1);
      lcd.print("Cek kabel/sensor");
      return;
    }

    // Tampilan LCD
    lcd.clear();
    lcd.setCursor(0, 0);
    lcd.print("Suhu: ");
    lcd.print((int)t);
    lcd.print((char)223);
    lcd.print("C    "); // bersih-bersih karakter

    lcd.setCursor(0, 1);
    lcd.print("Level: ");
    if (h < 60) {
      lcd.print("Kering ");
    } else if (h < 70) {
      lcd.print("Normal");
    } else {
      lcd.print("Lembab");
    }

    // Status
    String status = "Aman";
    int blinkCount = 0;
    int humLed = 0;

    if (t > 29 && t < 30) {
      status = "Waspada";
      blinkCount = 1;
    } else if (t >= 30 && t <= 31) {
      status = "Waspada";
      blinkCount = 2;
    } else if (t > 31) {
      status = "Bahaya";
      blinkCount = 3;
    }

    if (h >= 60 && h < 70) {
      humLed = 1;
    } else if (h >= 70) {
      humLed = 3;
      if (status == "Aman") status = "Lembab";
    }

    // Serial log
    Serial.print("Suhu: "); Serial.print(t, 1);
    Serial.print(" C, Kelembaban: "); Serial.print(h, 1);
    Serial.print(" %, LM35: "); Serial.print(tempLM35, 1);
    Serial.print(" C, Status: "); Serial.println(status);

    // MQTT
    String payload = "{\"suhu\":";
    payload += String(t, 1);
    payload += ",\"kelembaban\":";
    payload += String(h, 1);
    payload += ",\"lm35\":";
    payload += String(tempLM35, 1);
    payload += ",\"info\":\"" + status + "\"}";
    client.publish(mqtt_topic_pub, payload.c_str());

    // LED Blink
    int ledBlink = max(blinkCount, humLed);
    if (ledBlink > 0) {
      for (int i = 0; i < ledBlink; i++) {
        digitalWrite(LED1, HIGH);
        digitalWrite(LED2, HIGH);
        digitalWrite(LED3, HIGH);
        delay(200);
        digitalWrite(LED1, LOW);
        digitalWrite(LED2, LOW);
        digitalWrite(LED3, LOW);
        delay(300);
      }
    }
  }
}

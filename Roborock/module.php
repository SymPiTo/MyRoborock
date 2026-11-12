<?php

class RoborockQrevoCurv extends IPSModule {

    public function Create() {
        parent::Create();
        
        // hi
        // 1. Konfigurationseigenschaften registrieren
        $this->RegisterPropertyString("MQTTTopic", "roborock/status");
        $this->RegisterPropertyString("MQTTCommandTopic", "roborock/command/control");

        // 2. Modul-Variablen anlegen (inkl. automatischer Profile----)
        $this->MaintainVariable("Battery", "Batterie", 1, "~Battery.100", 10, true);
        $this->MaintainVariable("RobotState", "Roboter Status", 1, "", 20, true);
        $this->MaintainVariable("ErrorCode", "Fehlercode", 1, "", 30, true);
        
        $this->MaintainVariable("FanSpeed", "Saugstufe", 1, "", 40, true);
        $this->MaintainVariable("WaterFlow", "Wassermenge", 1, "", 50, true);
        $this->MaintainVariable("MopMode", "Wischmodus", 1, "", 60, true);
        $this->MaintainVariable("Repeat", "Wiederholungen", 1, "", 70, true);
    }

    public function ApplyChanges() {
        parent::ApplyChanges();
        
        // Verbindung zum ausgewählten Parent (MQTT-Server oder MQTT-Client) herstellen
        // Über das Formular ("Parent") wird die ID dynamisch zugewiesen
        $this->ConnectParent("{33DA7CCF-AC8A-488E-87A7-D24EAE63B9B3}"); // Fallback/Basis-Verbindung

        // Topic-Filter setzen, damit nur relevante MQTT-Pakete im Modul landen
        $topic = $this->ReadPropertyString("MQTTTopic");
        $this->SetReceiveDataFilter(".*Topic.*" . preg_quote($topic) . ".*");
    }

    // --- 1. SENDEN: Wird aufgerufen, wenn im WebFront oder per Skript ein Wert geändert wird ---
    public function RequestAction($Ident, $Value) {
        switch ($Ident) {
            case "FanSpeed":
                $this->SendCommand("set_fan_speed", intval($Value));
                $this->SetValue($Ident, intval($Value));
                break;
                
            case "MopMode":
                $this->SendCommand("set_mop_mode", intval($Value));
                $this->SetValue($Ident, intval($Value));
                break;
                
            case "WaterFlow":
                $this->SendCommand("set_water_flow", intval($Value));
                $this->SetValue($Ident, intval($Value));
                break;
                
            case "Repeat":
                $this->SendCommand("set_repeat", intval($Value));
                $this->SetValue($Ident, intval($Value));
                break;
                
            default:
                throw new Exception("Unbekannter Ident: " . $Ident);
        }
    }

    // --- 2. EMPFANGEN: Verarbeitet das Status-JSON vom MQTT-Parent ---
    public function ReceiveData($JSONString) {
        $data = json_decode($JSONString, true);
        
        if (isset($data['Payload'])) {
            $payload = is_string($data['Payload']) ? json_decode($data['Payload'], true) : $data['Payload'];
            
            if (is_array($payload)) {
                
                // Basis-Werte mappen
                if (isset($payload['battery'])) {
                    $this->SetValue("Battery", intval($payload['battery']));
                }
                if (isset($payload['state'])) {
                    $this->SetValue("RobotState", intval($payload['state']));
                }
                if (isset($payload['error_code'])) {
                    $this->SetValue("ErrorCode", intval($payload['error_code']));
                }
                
                // Steuerungs- und Statuswerte mappen
                if (isset($payload['fan_power'])) {
                    $this->SetValue("FanSpeed", intval($payload['fan_power']));
                }
                if (isset($payload['water_box_mode'])) {
                    $this->SetValue("WaterFlow", intval($payload['water_box_mode']));
                }
                if (isset($payload['mop_mode'])) {
                    $this->SetValue("MopMode", intval($payload['mop_mode']));
                }
                if (isset($payload['repeat'])) {
                    $this->SetValue("Repeat", intval($payload['repeat']));
                }
            }
        }
    }

    // --- HILFSFUNKTION: Befehl als JSON über das MQTT-Parent senden ---
    private function SendCommand(string $command, int $value) {
        $payload = json_encode([
            "command" => $command,
            "value" => $value
        ]);

        $topic = $this->ReadPropertyString("MQTTCommandTopic");
        
        // Standardisierter MQTT TX Datenfluss in IP-Symcon
        $this->SendDataToParent(json_encode([
            "DataID" => "{0EF86B8D-C7CC-4F68-9A24-E1E5E4E38C46}",
            "Topic" => $topic,
            "Payload" => $payload
        ]));
    }
}
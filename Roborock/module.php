<?php
declare(strict_types=1);

class RoborockQrevoCurv extends IPSModule
{
    // Vollständiges Variablen-Array inklusive aller neuen Status-Signale
    public static $Variables = [
        // Bestehende Variablen
        ['Roborock_State', 'Status', VARIABLETYPE_STRING, '', [], '', true, true, false],
        ['Roborock_Battery', 'Battery', VARIABLETYPE_INTEGER, '~Intensity.100', [], '', false, true, false],
        ['Roborock_Cleaning', 'Cleaning', VARIABLETYPE_BOOLEAN, '~Switch', [], '', true, true, false],
        ['Roborock_Error', 'Error', VARIABLETYPE_STRING, '', [], '', false, true, false],

        // Neue Status-Signale & Dock-Werte
        ['Roborock_WaterBoxStatus', 'Water Box Status', VARIABLETYPE_STRING, '', [], '', false, true, false],
        ['Roborock_WashPhase', 'Wash Phase', VARIABLETYPE_STRING, '', [], '', false, true, false],
        ['Roborock_WashReady', 'Wash Ready', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true, false],
        ['Roborock_FanPower', 'Fan Power', VARIABLETYPE_INTEGER, '', [], '', false, true, false],
        ['Roborock_WaterBoxMode', 'Water Box Mode', VARIABLETYPE_INTEGER, '', [], '', false, true, false],
        ['Roborock_WaterShortageStatus', 'Water Shortage', VARIABLETYPE_BOOLEAN, '~Alert', [], '', false, true, false],
        ['Roborock_DustCollectionStatus', 'Dust Collection Status', VARIABLETYPE_STRING, '', [], '', false, true, false],
        ['Roborock_MopMode', 'Mop Mode', VARIABLETYPE_INTEGER, '', [], '', false, true, false],
        ['Roborock_DockErrorStatus', 'Dock Error Status', VARIABLETYPE_STRING, '', [], '', false, true, false],
        ['Roborock_ChargeStatus', 'Charge Status', VARIABLETYPE_INTEGER, '', [], '', false, true, false],
        ['Roborock_WashStatus', 'Wash Status', VARIABLETYPE_STRING, '', [], '', false, true, false],
        ['Roborock_InWarmup', 'In Warmup', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true, false],
        ['Roborock_DryStatus', 'Dry Status', VARIABLETYPE_STRING, '', [], '', false, true, false],
        ['Roborock_CleanPercent', 'Clean Percent', VARIABLETYPE_INTEGER, '~Intensity.100', [], '', false, true, false],
        ['Roborock_CornerCleanMode', 'Corner Clean Mode', VARIABLETYPE_BOOLEAN, '~Switch', [], '', false, true, false],
        ['Roborock_Repeat', 'Repeat', VARIABLETYPE_INTEGER, '', [], '', false, true, false],
        ['Roborock_ReplenishMode', 'Replenish Mode', VARIABLETYPE_INTEGER, '', [], '', false, true, false]
    ];

    public function Create()
    {
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}');
        $this->RegisterPropertyString('MQTTTopicReceive', '');
        $this->RegisterPropertyString('MQTTTopicSend', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $Topic = $this->ReadPropertyString('MQTTTopicReceive');
        if ($Topic !== '') {
            $this->SetReceiveDataFilter('.*\"Topic\":\"' . $Topic . '.*');
        } else {
            $this->SetReceiveDataFilter('.*');
        }

        // Variablen über SDK-Methoden registrieren
        foreach (self::$Variables as $Var) {
            $ident = $Var[0];
            $name = $Var[1];
            $type = $Var[2];
            $profile = $Var[3];

            switch ($type) {
                case VARIABLETYPE_BOOLEAN:
                    $this->RegisterVariableBoolean($ident, $name, $profile, 0);
                    break;
                case VARIABLETYPE_INTEGER:
                    $this->RegisterVariableInteger($ident, $name, $profile, 0);
                    break;
                case VARIABLETYPE_FLOAT:
                    $this->RegisterVariableFloat($ident, $name, $profile, 0);
                    break;
                case VARIABLETYPE_STRING:
                    $this->RegisterVariableString($ident, $name, $profile, 0);
                    break;
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Roborock_Cleaning':
                $Command = $Value ? 'start' : 'pause';
                $this->SendCommand($Command);
                SetValue($this->GetIDForIdent($Ident), $Value);
                break;
                
            default:
                throw new Exception('Invalid Ident');
        }
    }

    public function ReceiveData($JSONString)
    {
        $Data = json_decode($JSONString);
        $this->SendDebug('MQTT RX', $JSONString, 0);

        if (property_exists($Data, 'Topic') && property_exists($Data, 'Payload')) {
            $ReceiveTopic = $this->ReadPropertyString('MQTTTopicReceive');
            
            if ($ReceiveTopic !== '' && $Data->Topic === $ReceiveTopic) {
                $Payload = json_decode($Data->Payload);
                if ($Payload === null) {
                    return;
                }

                // Zuordnung der neuen und alten Payload-Felder zu den Variablen
                $mapping = [
                    'battery'                => 'Roborock_Battery',
                    'state'                  => 'Roborock_State',
                    'cleaning'               => 'Roborock_Cleaning',
                    'error'                  => 'Roborock_Error',
                    'water_box_status'       => 'Roborock_WaterBoxStatus',
                    'wash_phase'             => 'Roborock_WashPhase',
                    'wash_ready'             => 'Roborock_WashReady',
                    'fan_power'              => 'Roborock_FanPower',
                    'water_box_mode'         => 'Roborock_WaterBoxMode',
                    'water_shortage_status'  => 'Roborock_WaterShortageStatus',
                    'dust_collection_status' => 'Roborock_DustCollectionStatus',
                    'mop_mode'               => 'Roborock_MopMode',
                    'dock_error_status'      => 'Roborock_DockErrorStatus',
                    'charge_status'          => 'Roborock_ChargeStatus',
                    'wash_status'            => 'Roborock_WashStatus',
                    'in_warmup'              => 'Roborock_InWarmup',
                    'dry_status'             => 'Roborock_DryStatus',
                    'clean_percent'          => 'Roborock_CleanPercent',
                    'corner_clean_mode'      => 'Roborock_CornerCleanMode',
                    'repeat'                 => 'Roborock_Repeat',
                    'replenish_mode'         => 'Roborock_ReplenishMode'
                ];

                foreach ($mapping as $key => $ident) {
                    if (isset($Payload->$key)) {
                        $VarID = @$this->GetIDForIdent($ident);
                        if ($VarID !== false) {
                            $val = $Payload->$key;
                            $curType = IPS_GetVariable($VarID)['VariableType'];
                            
                            // Typgerecht setzen
                            switch ($curType) {
                                case VARIABLETYPE_BOOLEAN:
                                    SetValue($VarID, (bool)$val);
                                    break;
                                case VARIABLETYPE_INTEGER:
                                    SetValue($VarID, (int)$val);
                                    break;
                                case VARIABLETYPE_FLOAT:
                                    SetValue($VarID, (float)$val);
                                    break;
                                case VARIABLETYPE_STRING:
                                    SetValue($VarID, (string)$val);
                                    break;
                            }
                        }
                    }
                }
            }
        }
    }

    public function SendCommand(string $Command)
    {
        $Topic = $this->ReadPropertyString('MQTTTopicSend');
        
        if ($Topic === '') {
            $this->SendDebug('MQTT TX', 'Sende-Topic ist nicht konfiguriert!', 0);
            return;
        }

        $Data = [
            'DataID'           => '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}',
            'PacketType'       => 3,
            'Payload'          => $Command,
            'Topic'            => $Topic,
            'Retain'           => false,
            'QualityOfService' => 0
        ];
        
        $JSONString = json_encode($Data);
        $this->SendDebug('MQTT TX', $JSONString, 0);
        $this->SendDataToParent($JSONString);
    }

// --- Native IP-Symcon 9 HTML-SDK Visualisierung ---

    public function GetVisualizationTile()
    {
        // Initiales Laden mit gefüllten Daten aus GetFullUpdateMessage
        $initialHandling = '<script>handleMessage(' . json_encode($this->GetFullUpdateMessage()) . ');</script>';
        
        // Statisches HTML aus der separaten module.html laden
        $module = file_get_contents(__DIR__ . '/module.html');

        // Erst das HTML (definiert handleMessage), dann das Script ausführen
        return $module . $initialHandling;
    }

    private function GetFullUpdateMessage()
    {
        $batteryID = @$this->GetIDForIdent('Roborock_Battery');
        $stateID = @$this->GetIDForIdent('Roborock_State');
        $percentID = @$this->GetIDForIdent('Roborock_CleanPercent');

        $result = [
            'battery'       => $batteryID !== false ? GetValue($batteryID) : 0,
            'state'         => $stateID !== false ? GetValue($stateID) : 'Bereit',
            'clean_percent' => $percentID !== false ? GetValue($percentID) : 0
        ];

        return $result;
    }
}
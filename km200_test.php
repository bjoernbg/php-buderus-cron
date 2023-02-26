<?php

// IP Adresse oder DNS-Hostname des KM200 
define( "km200_gateway_host", '192.168.178.135', true ); 
// Port des KM200, nur bei Zugriff über Internet mit Portweiterleitung am Router ändern! 
define( "km200_gateway_port", '80', true ); 

/* 

Für die Einbindung des Schlüssels zur Kommunikation mit dem KM200 gibt es mehrere Möglichkeiten. 

1. PHP only 
  Online unter https://ssl-account.com/km200.andreashahn.info/ 
  mit Geräte- und Benutzer-Passwort den AES-Key ausrechnen lassen und folgende Zeile in das Coding einbinden: 

define( "km200_crypt_key_private", hex2bin( '21D3CF8751ED6BB163BE8C7CDE20210E0C2F9378C0DA3108F0673987B0D63E37' ), true ); 

  Hier den Parameter von hex2bin durch den eigenen AES-Key ersetzen 

2. IP Symcon-Modul, Passwörter in den Moduleigenschaften 
  Online unter https://ssl-account.com/km200.andreashahn.info/ das IP Symcon-Modul herunter laden, entpacken und 
  im IP Symcon Verzeichnis im Unterordner "modules" installieren. Falls der Ordner nicht vorhanden ist diesen 
  einfach vorab erstellen. Den IP Symcon-Dienst stoppen und wieder starten. 

  Nun über das Kontextmenü "Objekt hinzufügen->Instanz hinzufügen" unter "(Sonstige)" die Instanz 
  "AES-Key-Generator for KM200 Web Gateway" auswählen. 

  In den Moduleigenschaften Geräte- und Benutzer-Passwort setzen und folgende Zeile in das Coding einbinden: 

define( "km200_crypt_key_private", hex2bin( KM200_GetAESKey( Modul-ID ) ), true ); 

  Hier den Parameter "Modul-ID" durch die Objekt-ID des Moduls ersetzen 

3. IP Symcon-Modul, Passwörter über den Funktionsaufruf 
  Das IP Symcon-Modul wie unter 2. beschrieben herunterladen, installieren und eine Instanz erzeugen. 

  Unabhängig von den Moduleigenschaften kann der Schlüssel über eine Funktion mit Geräte- und Benutzer- 
  Passwort als Parameter erzeugt werden. Dies ist z.B. wünschenswert, wenn das Benutzer-Passwort nicht 
  gespeichert werden sondern jedes mal interaktiv abgefragt werden soll. 
   
  Hierfür folgende Zeile in das Coding einbinden: 

define( "km200_crypt_key_private", hex2bin( KM200_GetAESKeyWithPasswords( Modul-ID, BenutzerPasswort, GerätePassword ) ), true ); 

  Hier den Parameter "Modul-ID" durch die Objekt-ID des Moduls ersetzen sowie entsprechend Benutzer- 
  und Geräte-Passwort. 

*/ 

// Hier nochmals alle drei Varianten, bitte nur GENAU EINE auskommentieren und anpassen! 
define( "km200_crypt_key_private", hex2bin( '023121783b7d3558053428a2302ea72129585a99d40da36b7d2361f3209c437c' ), true ); 
// define( "km200_crypt_key_private", hex2bin( KM200_GetAESKey( Modul-ID ) ), true ); 
// define( "km200_crypt_key_private", hex2bin( KM200_GetAESKeyWithPasswords( Modul-ID, BenutzerPasswort, GerätePassword ) ), true ); 

function km200_Encrypt( $encryptData ) 
{ 
    // add PKCS #7 padding 
    $blocksize = mcrypt_get_block_size( 
        MCRYPT_RIJNDAEL_128, 
        MCRYPT_MODE_ECB 
    ); 
    $encrypt_padchar = $blocksize - ( strlen( $encryptData ) % $blocksize ); 
    $encryptData .= str_repeat( chr( $encrypt_padchar ), $encrypt_padchar ); 
    // encrypt 
    return base64_encode( 
        mcrypt_encrypt( 
            MCRYPT_RIJNDAEL_128, 
            km200_crypt_key_private, 
            $encryptData, 
            MCRYPT_MODE_ECB, 
            '' 
        ) 
    ); 
} 

function km200_Decrypt( $decryptData ) 
{ 
    $decrypt = (
        mcrypt_decrypt( 
            MCRYPT_RIJNDAEL_128, 
            km200_crypt_key_private, 
            base64_decode( $decryptData ), 
            MCRYPT_MODE_ECB, 
            '' 
        ) 
    ); 
    // remove zero padding 
    $decrypt = rtrim( $decrypt, "\x00" ); 
    // remove PKCS #7 padding 
    $decrypt_len = strlen( $decrypt ); 
    $decrypt_padchar = ord( $decrypt[ $decrypt_len - 1 ] ); 
    for ( $i = 0; $i < $decrypt_padchar ; $i++ ) 
    { 
        if ( $decrypt_padchar != ord( $decrypt[$decrypt_len - $i - 1] ) ) 
        break; 
    } 
    if ( $i != $decrypt_padchar ) 
        return $decrypt; 
    else 
        return substr( 
            $decrypt, 
            0, 
            $decrypt_len - $decrypt_padchar 
        ); 
} 

function km200_GetData( $REST_URL ) 
{ 
    $options = array( 
        'http' => array( 
           'method' => "GET", 
           'header' => "Accept: application/json\r\n" . 
                        "User-Agent: TeleHeater/2.2.3\r\n" 
        ) 
    ); 
    $context = stream_context_create( $options ); 
    $content = @file_get_contents( 
        'http://' . km200_gateway_host . ':' . km200_gateway_port . $REST_URL, 
        false, 
        $context 
    ); 
    if ( false === $content ) 
        return false; 
    return json_decode( 
        km200_Decrypt( 
            $content 
        ) 
    ); 
} 

function km200_SetData( $REST_URL, $Value ) 
{ 
    $content = json_encode( 
        array( 
            "value" => $Value 
        ) 
    ); 
    $options = array( 
        'http' => array( 
           'method' => "PUT", 
            'header' => "Content-type: application/json\r\n" . 
                        "User-Agent: TeleHeater/2.2.3\r\n", 
            'content' => km200_Encrypt( $content ) 
        ) 
    ); 
    $context = stream_context_create( $options ); 
    @file_get_contents( 
        'http://' . km200_gateway_host . ':' . km200_gateway_port . $REST_URL, 
        false, 
        $context 
    ); 
} 

$data = km200_GetData("/heatSources/numberOfStarts");
print_r($data);



?>
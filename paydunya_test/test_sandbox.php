<?php
/**
 * Test complet DMP PayDunya Sandbox
 * 
 * Instructions :
 * 1. Remplace les valeurs des clés sandbox avec celles de ton compte sandbox PayDunya.
 * 2. Exécute : php test_sandbox.php
 */

$master_key  = 'vYyfgUkv-5dcP-cPEN-s7Ho-S4fvQDnLZzx1';
$private_key = 'test_private_BWvEmBz05TlkshyaPTaKRzqoR7w';
$token       = '5wzd5E33yUERAWUBWbeb';

// URL API DMP (sandbox et live utilisent la même URL)
$api_url = 'https://app.paydunya.com/api/v1/dmp-api';

// Données pour la demande de paiement
$data = [
    'recipient_email'   => 'mgandega@gmail.com',
    'amount'            => 1250,
    'support_fees'      => 1,
    'send_notification' => 1
];

// Configuration cURL
$options = [
    CURLOPT_URL            => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "PAYDUNYA-MASTER-KEY: $master_key",
        "PAYDUNYA-PRIVATE-KEY: $private_key",
        "PAYDUNYA-TOKEN: $token"
    ],
    CURLOPT_POSTFIELDS     => json_encode($data)
];

// Création de la demande de paiement
$ch = curl_init();
curl_setopt_array($ch, $options);
$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo 'Erreur cURL : ' . curl_error($ch);
    curl_close($ch);
    exit;
}

curl_close($ch);

// Affiche la réponse JSON
echo "Création DMP :\n";
echo $response . "\n";

// Récupération de reference_number pour vérifier le statut
$response_json = json_decode($response, true);

if (isset($response_json['reference_number'])) {
    $reference_number = $response_json['reference_number'];

    // Vérification du statut
    $status_url = $api_url . '/status';
    $status_data = ['reference_number' => $reference_number];

    $ch2 = curl_init();
    curl_setopt_array($ch2, [
        CURLOPT_URL => $status_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            "PAYDUNYA-MASTER-KEY: $master_key",
            "PAYDUNYA-PRIVATE-KEY: $private_key",
            "PAYDUNYA-TOKEN: $token"
        ],
        CURLOPT_POSTFIELDS => json_encode($status_data)
    ]);

    $status_response = curl_exec($ch2);

    if (curl_errno($ch2)) {
        echo 'Erreur cURL statut : ' . curl_error($ch2);
    } else {
        echo "\nStatut DMP :\n";
        echo $status_response . "\n";
    }

    curl_close($ch2);
} else {
    echo "\nImpossible de récupérer le reference_number.\n";
}


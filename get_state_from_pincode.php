<?php
/**
 * get_state_from_pincode.php
 * Looks up an Indian PIN code and returns the state name.
 * Uses India Post's public API with a fallback prefix map.
 */
header('Content-Type: application/json');

$pincode = preg_replace('/\D/', '', $_GET['pincode'] ?? $_POST['pincode'] ?? '');

if (strlen($pincode) !== 6) {
    echo json_encode(['success' => false, 'message' => 'Invalid pincode']);
    exit;
}

// ── 1. Try India Post Open API ───────────────────────────────────────────────
$api_url = "https://api.postalpincode.in/pincode/{$pincode}";
$ctx = stream_context_create([
    'http' => [
        'timeout'        => 5,
        'ignore_errors'  => true,
        'user_agent'     => 'SilkyStore/1.0',
    ]
]);
$raw = @file_get_contents($api_url, false, $ctx);

if ($raw !== false) {
    $data = json_decode($raw, true);
    if (
        is_array($data) &&
        isset($data[0]['Status']) &&
        $data[0]['Status'] === 'Success' &&
        !empty($data[0]['PostOffice'])
    ) {
        $po    = $data[0]['PostOffice'][0];
        $state = $po['State']   ?? '';
        $city  = $po['District'] ?? $po['Region'] ?? '';

        // Map India Post state names to our exact state list
        $state_map = [
            'Andhra Pradesh'                    => 'Andhra Pradesh',
            'Arunachal Pradesh'                 => 'Arunachal Pradesh',
            'Assam'                             => 'Assam',
            'Bihar'                             => 'Bihar',
            'Chhattisgarh'                      => 'Chhattisgarh',
            'Goa'                               => 'Goa',
            'Gujarat'                           => 'Gujarat',
            'Haryana'                           => 'Haryana',
            'Himachal Pradesh'                  => 'Himachal Pradesh',
            'Jharkhand'                         => 'Jharkhand',
            'Karnataka'                         => 'Karnataka',
            'Kerala'                            => 'Kerala',
            'Madhya Pradesh'                    => 'Madhya Pradesh',
            'Maharashtra'                       => 'Maharashtra',
            'Manipur'                           => 'Manipur',
            'Meghalaya'                         => 'Meghalaya',
            'Mizoram'                           => 'Mizoram',
            'Nagaland'                          => 'Nagaland',
            'Odisha'                            => 'Odisha',
            'Punjab'                            => 'Punjab',
            'Rajasthan'                         => 'Rajasthan',
            'Sikkim'                            => 'Sikkim',
            'Tamil Nadu'                        => 'Tamil Nadu',
            'Telangana'                         => 'Telangana',
            'Tripura'                           => 'Tripura',
            'Uttar Pradesh'                     => 'Uttar Pradesh',
            'Uttarakhand'                       => 'Uttarakhand',
            'West Bengal'                       => 'West Bengal',
            'Andaman and Nicobar Islands'        => 'Andaman and Nicobar Islands',
            'Andaman & Nicobar'                 => 'Andaman and Nicobar Islands',
            'Chandigarh'                        => 'Chandigarh',
            'Dadra And Nagar Haveli'            => 'Dadra and Nagar Haveli and Daman and Diu',
            'Dadra and Nagar Haveli'            => 'Dadra and Nagar Haveli and Daman and Diu',
            'Daman and Diu'                     => 'Dadra and Nagar Haveli and Daman and Diu',
            'Delhi'                             => 'Delhi',
            'Jammu & Kashmir'                   => 'Jammu and Kashmir',
            'Jammu and Kashmir'                 => 'Jammu and Kashmir',
            'Ladakh'                            => 'Ladakh',
            'Lakshadweep'                       => 'Lakshadweep',
            'Puducherry'                        => 'Puducherry',
            'Pondicherry'                       => 'Puducherry',
        ];

        $normalised_state = $state_map[$state] ?? $state;

        echo json_encode([
            'success' => true,
            'state'   => $normalised_state,
            'city'    => $city,
            'source'  => 'api',
        ]);
        exit;
    }
}

// ── 2. Fallback: first-digit prefix map ─────────────────────────────────────
$prefix_map = [
    '11' => 'Delhi',
    '12' => 'Haryana',
    '13' => 'Punjab',
    '14' => 'Punjab',
    '15' => 'Punjab',
    '16' => 'Punjab',
    '17' => 'Himachal Pradesh',
    '18' => 'Jammu and Kashmir',
    '19' => 'Jammu and Kashmir',
    '20' => 'Uttar Pradesh',
    '21' => 'Uttar Pradesh',
    '22' => 'Uttar Pradesh',
    '23' => 'Uttar Pradesh',
    '24' => 'Uttar Pradesh',
    '25' => 'Uttar Pradesh',
    '26' => 'Uttar Pradesh',
    '27' => 'Uttar Pradesh',
    '28' => 'Uttar Pradesh',
    '30' => 'Rajasthan',
    '31' => 'Rajasthan',
    '32' => 'Rajasthan',
    '33' => 'Rajasthan',
    '34' => 'Rajasthan',
    '36' => 'Gujarat',
    '37' => 'Gujarat',
    '38' => 'Gujarat',
    '39' => 'Gujarat',
    '40' => 'Maharashtra',
    '41' => 'Maharashtra',
    '42' => 'Maharashtra',
    '43' => 'Maharashtra',
    '44' => 'Maharashtra',
    '45' => 'Madhya Pradesh',
    '46' => 'Madhya Pradesh',
    '47' => 'Madhya Pradesh',
    '48' => 'Madhya Pradesh',
    '49' => 'Chhattisgarh',
    '50' => 'Telangana',
    '51' => 'Andhra Pradesh',
    '52' => 'Andhra Pradesh',
    '53' => 'Andhra Pradesh',
    '56' => 'Karnataka',
    '57' => 'Karnataka',
    '58' => 'Karnataka',
    '59' => 'Karnataka',
    '60' => 'Tamil Nadu',
    '61' => 'Tamil Nadu',
    '62' => 'Tamil Nadu',
    '63' => 'Tamil Nadu',
    '64' => 'Tamil Nadu',
    '67' => 'Kerala',
    '68' => 'Kerala',
    '69' => 'Kerala',
    '70' => 'West Bengal',
    '71' => 'West Bengal',
    '72' => 'West Bengal',
    '73' => 'West Bengal',
    '74' => 'West Bengal',
    '75' => 'Odisha',
    '76' => 'Odisha',
    '77' => 'Odisha',
    '78' => 'Assam',
    '79' => 'Arunachal Pradesh',
    '80' => 'Bihar',
    '81' => 'Bihar',
    '82' => 'Bihar',
    '83' => 'Bihar',
    '84' => 'Bihar',
    '85' => 'Jharkhand',
    '90' => 'Army Post Office',
];

$prefix2 = substr($pincode, 0, 2);
if (isset($prefix_map[$prefix2])) {
    echo json_encode([
        'success' => true,
        'state'   => $prefix_map[$prefix2],
        'city'    => '',
        'source'  => 'prefix',
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Could not determine state for this pincode']);

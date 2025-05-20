<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ETAController extends Controller
{
    public static function hashedSerializedData($documentStructure)
    {
        $documentStructure = json_decode($documentStructure, true);
        $serializedData = self::serialize($documentStructure);
        $dd = hash('sha256', $serializedData);
        return $dd;
    }

    public static function serialize($documentStructure)
    {
        if (!is_array($documentStructure)) {
            // Scalar value (string, number, etc.) - wrap in quotes
            return '"' . $documentStructure . '"';
        }

        $serializedString = '';

        foreach ($documentStructure as $key => $value) {
            $upperKey = '"' . strtoupper($key) . '"';

            if (is_array($value)) {
                // Check if it's a sequential array (list of objects/values)
                $isList = array_keys($value) === range(0, count($value) - 1);

                if ($isList) {
                    // Add the array property name once before the array (per ETA rules)
                    $serializedString .= $upperKey;

                    foreach ($value as $item) {
                        // Repeat the property name before each array item
                        $serializedString .= $upperKey;
                        $serializedString .= self::serialize($item);
                    }
                } else {
                    // Object-like associative array (nested object)
                    $serializedString .= $upperKey;
                    $serializedString .= self::serialize($value);
                }
            } else {
                // Scalar field
                $serializedString .= $upperKey;
                $serializedString .= self::serialize($value);
            }
        }

        return $serializedString;
    }

    public function sendReceiptToETA($amount = 0)
    {
        // required from the client side
        if ($amount <= 0) {
            Log::error("Invalid amount provided for ETA receipt: " . $amount);
            return response()->json(['error' => 'Invalid amount'], 400);
        }
        // Step 1: Retrieve Access Token
        $accessToken = $this->getETAToken();
        if (!$accessToken) {
            Log::error("Failed to retrieve access token from ETA.");
            return response()->json(['error' => 'Failed to authenticate with ETA'], 401);
        }
        $currentTime = time() - 5; // Subtract 5 seconds to ensure it's in the past
        $dateTimeIssued = gmdate("Y-m-d\TH:i:s\Z", $currentTime);
        // Step 2: Prepare the receipt data
        $receiptData = '
                    {
                        "header": {
                            "dateTimeIssued": "' . $dateTimeIssued . '",
                            "receiptNumber": "E-' . rand(1000, 9999) . '",
                            "uuid": "",
                            "previousUUID": "",
                            "referenceOldUUID": "",
                            "currency": "EGP"
                        },
                        "documentType": {
                            "receiptType": "S",
                            "typeVersion": "1.2"
                        },
                        "seller": {
                            "rin": "698984712",
                            "companyTradeName": "Company Name",
                            "branchCode": "0",
                            "branchAddress": {
                                "country": "EG",
                                "governate": "Cairo Governorate",
                                "regionCity": "city center",
                                "street": "16 street",
                                "buildingNumber": "14BN",
                                "postalCode": "74299"
                            },
                            "deviceSerialNumber": "54545888",
                            "syndicateLicenseNumber": "102258",
                            "activityCode": "9319"
                        },
                        "buyer": {
                            "type": "F"
                        },
                        "itemData": [
                            {
                                "internalCode": "880609",
                                "description": "Samsung A02 32GB_LTE_BLACK_DS_SM-A022FZKDMEB_A022 _ A022_SM-",
                                "itemType": "EGS",
                                "itemCode": "EG-697604748-222Ele",
                                "unitType": "EA",
                                "quantity": 1,
                                "unitPrice": ' . $amount . ',
                                "netSale": ' . $amount . ',
                                "totalSale": ' . $amount . ',
                                "total": ' . $amount . '
                            }
                        ],
                        "totalSales": ' . $amount . ',
                        "netAmount": ' . $amount . ',
                        "totalAmount": ' . $amount . ',
                        "paymentMethod": "V"
            }';

        // Step 3: Send the receipt data to ETA API;
        $uuid = self::hashedSerializedData($receiptData);

        $receiptData = json_decode($receiptData, true);
        $receiptData['header']['uuid'] = $uuid;
        $finalReceiptData['receipts'][] = $receiptData;
        $finalReceiptData = json_encode($finalReceiptData);
        // Setup cURL
        $ch = curl_init();

        // Set cURL options
        curl_setopt($ch, CURLOPT_URL, "https://api.preprod.invoicing.eta.gov.eg/api/v1/receiptsubmissions");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $finalReceiptData); // JSON data

        // Set headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            "Authorization: Bearer $accessToken", // Make sure the access token is valid
        ]);

        // Execute the request and get the response
        $response = curl_exec($ch);

        // Check for cURL errors
        if (curl_errno($ch)) {
            echo 'Error:' . curl_error($ch);
        }

        // Close cURL
        curl_close($ch);

        // Decode the response
        // $responseData = json_decode($response, true);
        // Log the response
        Log::info("ETA Response: " . print_r($response, true));
    }
    private function getETAToken()
    {
        $client_id = env('ETA_CLIENT_ID');
        $client_secret = env('ETA_CLIENT_SECRET');
        $pos_serial = env('ETA_POS_SERIAL');

        // Send POST request using form-data (x-www-form-urlencoded)
        $response = Http::asForm()->withHeaders([
            'posserial'     => $pos_serial,
            'pososversion'  => 'android',
            'presharedkey'  => '',
        ])->post("https://id.preprod.eta.gov.eg/connect/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
        ]);

        // Check if the request was successful
        if ($response->successful()) {
            return $response->json()['access_token'] ?? null;
        } else {
            // Handle error response
            $error = $response->json()['error'] ?? 'Unknown error';
            Log::error("Failed to obtain access token: " . $error);
            throw new \Exception("Failed to obtain access token: {$error}");
        }
    }
}

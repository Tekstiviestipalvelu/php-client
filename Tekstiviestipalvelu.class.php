<?php

/**
 * A class for sending SMS messages via the Tekstiviestipalvelu.fi API.
 *
 * This class provides a simple interface to send SMS messages to one or multiple
 * recipients using the Tekstiviestipalvelu.fi API. It handles input validation,
 * API communication, and error handling. Compatible with PHP 7.0+.
 *
 * @package Tekstiviestipalvelu
 * @license MIT
 * @link https://tekstiviestipalvelu.fi/
 */
class Tekstiviestipalvelu
{
    /**
     * @var string API URL for sending SMS messages
     */
    private $apiUrl;

    /**
     * @var string API token for authentication
     */
    private $apiToken;

    /**
     * @var bool Whether to verify SSL certificates
     */
    private $verifySsl;

    /**
     * Constructor for Tekstiviestipalvelu.
     *
     * @param string $apiToken API token for authentication
     * @param string $apiUrl API endpoint URL
     * @param bool $verifySsl Whether to verify SSL certificates (default: true)
     * @throws InvalidArgumentException If API token or URL is empty
     */
    public function __construct($apiToken, $apiUrl, $verifySsl = true)
    {
        if (empty($apiToken) || empty($apiUrl)) {
            throw new InvalidArgumentException('API token and URL must not be empty');
        }
        $this->apiToken = $apiToken;
        $this->apiUrl = $apiUrl;
        $this->verifySsl = (bool) $verifySsl;
    }

    /**
     * Sends an SMS message to one or multiple recipients.
     *
     * @param string|array $recipients Single phone number or array of phone numbers
     * @param string $from Sender name or number
     * @param string $text Message content
     * @return array Associative array with 'http_code' (int) and 'response' (string)
     * @throws InvalidArgumentException If input parameters are invalid
     * @throws RuntimeException If cURL request fails
     */
    public function sendSms($recipients, $from, $text)
    {
        // Validate input parameters
        if (empty($recipients)) {
            throw new InvalidArgumentException('At least one recipient must be provided');
        }
        if (empty($from)) {
            throw new InvalidArgumentException('Sender cannot be empty');
        }
        if (empty($text)) {
            throw new InvalidArgumentException('Message text cannot be empty');
        }

        // Convert single recipient to array if string is provided
        $recipients = is_array($recipients) ? $recipients : array($recipients);

        // Validate phone numbers (more permissive regex for international formats)
        foreach ($recipients as $recipient) {
            if (!preg_match('/^\+?[0-9\s\-()]{7,15}$/', $recipient)) {
                throw new InvalidArgumentException("Invalid phone number format: $recipient");
            }
        }

        // Prepare destinations array
        $destinations = array();
        foreach ($recipients as $recipient) {
            $destinations[] = array('to' => $recipient);
        }

        // Prepare API payload
        $data = array(
            'messages' => array(
                array(
                    'from' => $from,
                    'destinations' => $destinations,
                    'text' => $text
                )
            )
        );

        // Initialize cURL session
        $ch = curl_init($this->apiUrl);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL session');
        }

        // Set cURL options
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json, text/plain',
                'Authorization: Bearer ' . $this->apiToken,
                'Content-Type: application/json'
            ),
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_SSL_VERIFYPEER => $this->verifySsl,
            CURLOPT_SSL_VERIFYHOST => $this->verifySsl ? 2 : 0
        ));

        // Execute request
        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('cURL request failed: ' . $error);
        }

        // Get HTTP code
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array(
            'http_code' => (int) $httpCode,
            'response' => (string) $response
        );
    }
}

/**
 * Example usage of the Tekstiviestipalvelu class.
 */
try {
    $apiToken = 'your-api-token-here'; // Replace with your API token
    $apiUrl = 'https://tekstiviestipalvelu-api-url-here'; // Replace with actual API URL
    $smsService = new Tekstiviestipalvelu($apiToken, $apiUrl);
    
    // Single recipient
    $recipients = '+35850123456';
    // Multiple recipients example:
    // $recipients = array('+35850123456', '+35850234567');
    
    $sender = 'Valvonta';
    $message = 'Monitoroinnin raja-arvo on ylittynyt.';
    
    $result = $smsService->sendSms($recipients, $sender, $message);
    
    // Handle response
    echo json_encode($result, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>

<?php
/**
 * Simple client for MoySklad API using Basic Auth.
 */
class MoySkladClient
{
    private string $login;
    private string $password;
    private string $baseUrl;

    public function __construct(string $login, string $password, string $baseUrl = 'https://api.moysklad.ru/api/remap/1.2')
    {
        $this->login = $login;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Fetches the assortment of products from MoySklad.
     *
     * @return array List of items from the assortment.
     * @throws RuntimeException When the API request fails.
     */
    public function getAssortment(): array
    {
        $url = $this->baseUrl . '/entity/assortment';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $this->login . ':' . $this->password);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Curl error: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200) {
            throw new RuntimeException('MoySklad API error: HTTP ' . $status . ' response: ' . $response);
        }

        $data = json_decode($response, true);
        return $data['rows'] ?? [];
    }
}

<?php
/**
 * Simple client for MoySklad API using Basic Auth.
 */
class MoySkladClient
{
    private string $login;
    private string $password;
    private string $baseUrl;
    private string $logFile;
    private string $userAgent = 'AssortmentDemo/1.0 (+Ваш email/телефон)';

    public function __construct(
        string $login,
        string $password,
        string $baseUrl = 'https://api.moysklad.ru/api/remap/1.2',
        ?string $logFile = null
    ) {
        $this->login = $login;
        $this->password = $password;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->logFile = $logFile ?? dirname(__DIR__) . '/moysklad.log';
    }

    private function log(string $message): void
    {
        $entry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        error_log($entry, 3, $this->logFile);
    }

    private function request(string $method, string $path, array $query = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        if ($query) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
        }

        $this->log('REQ ' . $method . ' ' . $url . ' login=' . $this->login);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json;charset=utf-8',
            'Accept-Encoding: gzip',
            'Content-Type: application/json; charset=utf-8',
            'User-Agent: AssortmentDemo/1.0'
    ]);

        $response = curl_exec($ch);
        if ($response === false) {
            $error = curl_error($ch);
            $code = curl_errno($ch);
            curl_close($ch);
            $this->log('Curl error: ' . $error . ' (code ' . $code . ')');
            throw new RuntimeException('Curl error: ' . $error);
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE) ?: 0;
        curl_close($ch);

        if ($status !== 200) {
            $this->log('API error HTTP ' . $status . ' response: ' . mb_substr($response, 0, 2000));
            throw new RuntimeException('MoySklad API error: HTTP ' . $status . ' response: ' . $response);
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            $this->log('JSON decode error, raw: ' . mb_substr((string)$response, 0, 1000));
            throw new RuntimeException('Не удалось разобрать ответ API');
        }
        return $data;
    }

    /**
     * Получение ассортимента.
     * $params например: ['limit'=>100, 'expand'=>'product,attributes']
     */
    public function getAssortment(array $params = []): array
    {
        // разумные дефолты
        $params = array_replace(['limit' => 100, 'expand' => 'product,attributes'], $params);
        $data = $this->request('GET', '/entity/assortment', $params);
        return $data['rows'] ?? [];
    }
}

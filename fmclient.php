<?php

class fmclient
{
    private string $host;
    private string $db;
    private string $version;

    public function __construct(array $cfg)
    {
        $this->host    = rtrim($cfg['host'], '/');
        $this->db      = $cfg['db'];
        $this->version = $cfg['version'] ?? 'vLatest';
    }

    /** LOGIN → token ophalen */
    public function login(string $user, string $pass): string
    {
        $url = $this->host. "/fmi/data/{$this->version}/databases/". rawurlencode($this->db). "/sessions";

        $headers = ['Content-Type: application/json','Authorization: Basic ' . base64_encode($user . ':' . $pass),];

        $resp = $this->request('POST', $url, $headers, '{}');

        if (!isset($resp['response']['token'])) 
        {
            throw new Exception('Login failed');
        }

        return $resp['response']['token'];
    }

    public function find(string $token, string $layout, array $query, array $sort = [], int $limit = 5000): array
    {
         $url = $this->host. "/fmi/data/{$this->version}/databases/". rawurlencode($this->db). "/layouts/". rawurlencode($layout). "/_find";

        $headers = [ 'Content-Type: application/json', 'Authorization: Bearer ' . $token,];

        // IMPORTANT: FileMaker expects query as an array of objects.
        // - If you pass a single query object: wrap it in an array.
        // - If you pass an OR list (array of objects): keep it as is.
        $isOrQuery = isset($query[0]) && is_array($query[0]);
        $queryPayload = $isOrQuery ? $query : [$query];

        $payload = [ 'query' => $queryPayload, 'limit' => $limit ];

        if (!empty($sort)) { $payload['sort'] = $sort;}

        $resp = $this->request('POST', $url, $headers, json_encode($payload));

        return $resp['response']['data'] ?? [];
    }

    /** Interne HTTP helper */
    private function request(string $method, string $url, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // DEV ONLY – SSL verificatie uit
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($body !== null) 
        {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);

        if ($raw === false) 
        {
            $err = curl_error($ch);
            curl_close($ch);
            throw new Exception("cURL error: $err");
        }

        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $json = json_decode($raw, true);

        if (!is_array($json)) {
            throw new Exception("Non-JSON response (HTTP $status): $raw");
        }

        if ($status < 200 || $status >= 300) {
            throw new Exception(json_encode($json, JSON_PRETTY_PRINT));
        }

        return $json;
    }
}

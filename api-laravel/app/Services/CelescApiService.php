<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CelescApiService
{
    private string $graphQlEndpoint;
    private string $authEndpoint;
    private ?string $accessToken;
    private ?string $refreshToken;
    private string $defaultChannel;
    private ?string $username;
    private ?string $password;
    private ?string $cookiesHeader;

    public function __construct()
    {
        $this->graphQlEndpoint = rtrim(config('services.celesc.base_url', 'https://conecte.celesc.com.br/graphql'), '/');
        $this->authEndpoint = rtrim(config('services.celesc.auth_url', 'https://conecte.celesc.com.br/auth/login'), '/');
        $this->accessToken = config('services.celesc.token');
        $this->refreshToken = config('services.celesc.refresh_token');
        $this->defaultChannel = config('services.celesc.channel', 'ZAW');
        $this->username = config('services.celesc.username');
        $this->password = config('services.celesc.password');
        $this->cookiesHeader = config('services.celesc.cookies');
    }

    /**
     * Faz login, lista contratos disponíveis e solicita a 2ª via da fatura.
     *
     * @param  array<string, string|null>  $payload
     * @return array<string, mixed>
     */
    public function executarFluxoFatura(array $payload): array
    {
        $payload['channel'] = $payload['channel'] ?? $this->defaultChannel;
        $payload['profile_type'] = $payload['profile_type'] ?? 'GRPA';

        $auth = $this->login([
            'username' => $payload['username'] ?? null,
            'password' => $payload['password'] ?? null,
            'channel' => $payload['channel'] ?? $this->defaultChannel,
        ]);

        $contracts = $this->listarContratos($auth, [
            'channel' => $payload['channel'],
            'profile_type' => $payload['profile_type'],
            'installation' => $payload['installation'] ?? null,
            'owner' => $payload['owner'] ?? null,
            'zip_code' => $payload['zip_code'] ?? null,
        ]);

        $invoice = $this->emitirFatura($auth, $contracts, $payload);

        return [
            'auth' => $auth,
            'contracts' => $contracts,
            'invoice' => $invoice,
        ];
    }

    /**
     * @param  array<string, string|null>  $payload
     * @return array<string, mixed>
     */
    public function login(array $payload): array
    {
        $this->accessToken = $this->accessToken ?? config('services.celesc.token');
        $this->refreshToken = $this->refreshToken ?? config('services.celesc.refresh_token');

        if ($this->accessToken && !($payload['username'] ?? null) && !($payload['password'] ?? null)) {
            return [
                'token' => $this->accessToken,
                'refresh_token' => $this->refreshToken,
                'sap_access' => null,
                'profile' => null,
            ];
        }

        $username = $payload['username'] ?? config('services.celesc.username');
        $password = $payload['password'] ?? config('services.celesc.password');
        $channel = $payload['channel'] ?? config('services.celesc.channel', $this->defaultChannel);

        if (!$username || !$password) {
            throw new RuntimeException('Credenciais de login da Celesc não configuradas.');
        }

        $body = [
            'username' => $username,
            'password' => $password,
            'socialCode' => '',
            'socialRedirectUri' => '',
            'channel' => $channel,
            'accessIp' => $payload['access_ip'] ?? '',
            'deviceId' => $payload['device_id'] ?? 'Windows Chrome Unknown',
            'firebaseToken' => $payload['firebase_token'] ?? '',
        ];

        $request = $this->baseRequest('https://conecte.celesc.com.br/autenticacao/login')
            ->withHeaders([
                'Referer' => 'https://conecte.celesc.com.br/autenticacao/login',
            ]);

        // Ajuste para ambiente local (ex: Windows com CA/SSL desconfigurado)
        // - services.celesc.ssl_verify = false  -> desativa verificação SSL
        // - services.celesc.ca_bundle = "C:\path\cacert.pem" -> usa CA bundle específico
        $sslVerify = config('services.celesc.ssl_verify');
        $caBundle = config('services.celesc.ca_bundle');

        if ($sslVerify === false || ($sslVerify === null && app()->environment('local'))) {
            $request = $request->withoutVerifying();
        } elseif ($caBundle) {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        $response = $request->post($this->authEndpoint, $body);

        if ($response->failed()) {
            throw new RuntimeException('Falha ao autenticar na Celesc: ' . $response->reason());
        }

        $token = $response->json('data.authenticate.login.accessToken')
            ?? $response->json('token')
            ?? $response->json('accessToken')
            ?? $response->json('access_token');

        if (!$token) {
            throw new RuntimeException('Token de acesso não encontrado na resposta de login.');
        }

        $refreshToken = $response->json('data.authenticate.login.refreshToken')
            ?? $response->json('refreshToken')
            ?? $response->json('refresh_token')
            ?? $this->refreshToken;

        $sapAccess = $response->json('data.authenticate.sapAccess');
        $profile = $response->json('data.authenticate.profile');

        $this->accessToken = $token;
        $this->refreshToken = $refreshToken;

        return [
            'token' => $token,
            'refresh_token' => $refreshToken,
            'sap_access' => is_array($sapAccess) ? $sapAccess : null,
            'profile' => is_array($profile) ? $profile : null,
        ];
    }

    /**
     * Lista contratos disponíveis antes de solicitar a fatura.
     *
     * @param  array<string, string|null>  $payload
     * @return array<string, mixed>
     */
    public function listarContratos(array $auth, array $payload): array
    {
        $token = $auth['token'] ?? null;

        if (!$token) {
            throw new RuntimeException('Token de acesso da Celesc não informado para listar contratos.');
        }

        $partner = $payload['partner'] ?? $auth['sap_access']['partner'] ?? null;

        if (!$partner) {
            throw new RuntimeException('Parceiro (partner) não encontrado na resposta de login.');
        }

        $body = [
            'variables' => [
                'channelCode' => $payload['channel'] ?? $auth['sap_access']['channel'] ?? $this->defaultChannel,
                'target' => 'sap',
                'partner' => $partner,
                'profileType' => $payload['profile_type'] ?? $auth['sap_access']['profileType'] ?? 'GRPA',
            ],
            'query' => <<<'GQL'
query ($partner: String!, $profileType: String, $installation: String, $owner: String, $zipCode: String) {
  allContracts(
    partner: $partner
    profileType: $profileType
    installation: $installation
    owner: $owner
    zipCode: $zipCode
  ) {
    contracts {
      partner
      installation
      category
      office
      contract
      contractAccount
      home
      name
      street
      houseNum
      postCode
      city1
      city2
      region
      country
      alertCode
      alert
      status
      tarifType
      favorite
      denomination
      messageHome
      messageCard
      messageType
      complement
      referencePoint
      generation
      __typename
    }
    message
    error
    __typename
  }
}
GQL,
        ];

        $response = $this->performGraphQlRequest($token, $body, 'https://conecte.celesc.com.br/contrato/selecao');

        if ($response->failed()) {
            $message = $response->json('errors.0.message') ?? $response->reason();

            throw new RuntimeException('Falha ao listar contratos da Celesc: ' . $message);
        }

        $contracts = $response->json('data.allContracts.contracts');

        if (!is_array($contracts)) {
            throw new RuntimeException('Resposta inesperada ao listar contratos da Celesc.');
        }

        return [
            'contracts' => $contracts,
            'message' => $response->json('data.allContracts.message'),
            'error' => $response->json('data.allContracts.error'),
        ];
    }

    /**
     * Solicita a fatura em base64 após a listagem de contratos.
     *
     * @param  array<string, string|null>  $payload
     * @return array<string, mixed>
     */
    private function emitirFatura(array $auth, array $contractsResult, array $payload): array
    {
        $token = $auth['token'] ?? null;

        if (!$token) {
            throw new RuntimeException('Token de acesso da Celesc não informado para emitir fatura.');
        }

        $contracts = $contractsResult['contracts'] ?? [];

        if (!is_array($contracts) || empty($contracts)) {
            throw new RuntimeException('Nenhuma UC encontrada para emissão de fatura.');
        }

        $selectedContract = $this->buscarContrato($contracts, $payload['contract_account'] ?? null);

        $payload['channel'] = $payload['channel']
            ?? $selectedContract['channel']
            ?? $auth['sap_access']['channel']
            ?? $this->defaultChannel;

        $contractAccount = $selectedContract['contractAccount'] ?? null;
        $partner = $selectedContract['partner']
            ?? $auth['sap_access']['partner']
            ?? null;
        $accessId = $selectedContract['accessId']
            ?? $payload['access_id']
            ?? $auth['sap_access']['accessId']
            ?? null;

        if (!$contractAccount || !$partner || !$accessId) {
            throw new RuntimeException('Dados obrigatórios para emitir fatura não encontrados na listagem de UCs.');
        }

        $body = [
            'variables' => [
                'duplicateBillInput' => [
                    'contractAccount' => $contractAccount,
                    'accessId' => $accessId,
                    'partner' => $partner,
                    'invoiceId' => $this->resolverInvoiceId($payload, $selectedContract),
                    'bill' => $this->resolverBill($payload, $selectedContract),
                    'channel' => $payload['channel'] ?? $this->defaultChannel,
                ],
                'target' => 'sap',
            ],
            'query' => <<<'GQL'
mutation ($duplicateBillInput: DuplicateBillInput!) {
  duplicateBill(duplicateBillInput: $duplicateBillInput) {
    channel
    partner
    contractAccount
    accessId
    invoiceId
    invoiceBase64
    __typename
  }
}
GQL,
        ];

        $response = $this->performGraphQlRequest($token, $body, 'https://conecte.celesc.com.br/fatura/historico');

        if ($response->failed()) {
            $message = $response->json('errors.0.message')
                ?? $response->reason();

            throw new RuntimeException(
                sprintf('Falha ao solicitar fatura na Celesc: %s.', $message)
            );
        }

        $data = $response->json('data.duplicateBill');

        if (!$data || !is_array($data)) {
            throw new RuntimeException('Resposta inesperada da Celesc ao emitir fatura.');
        }

        return $data;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<string, mixed>
     */
    private function buscarContrato(array $contracts, ?string $contractAccount): array
    {
        if ($contractAccount) {
            foreach ($contracts as $contract) {
                if (($contract['contractAccount'] ?? null) === $contractAccount) {
                    return $contract;
                }
            }
        }

        return $contracts[0];
    }

    /**
     * Tenta obter o invoiceId do payload ou do contrato retornado pela Celesc.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $contract
     */
    private function resolverInvoiceId(array $payload, array $contract): string
    {
        $invoiceId = $payload['invoice_id']
            ?? $contract['invoiceId']
            ?? $contract['lastInvoiceId']
            ?? $contract['invoice_id']
            ?? null;

        if (!$invoiceId) {
            throw new RuntimeException('invoice_id não encontrado. Informe no payload ou garanta que a listagem de UCs o retorne.');
        }

        return (string) $invoiceId;
    }

    /**
     * Retorna o bill informado ou tenta reutilizar algum valor retornado no contrato.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $contract
     */
    private function resolverBill(array $payload, array $contract): string
    {
        return (string) ($payload['bill']
            ?? $contract['bill']
            ?? '');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function performGraphQlRequest(string $token, array $body, string $referer): Response
    {
        return $this->baseRequest($referer)
            ->withToken($token)
            ->acceptJson()
            ->post($this->graphQlEndpoint, $body);
    }

    private function baseRequest(string $referer)
    {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Language' => 'pt-BR,pt;q=0.9',
            'Origin' => 'https://conecte.celesc.com.br',
            'execution-requester' => 'GRPA',
            'User-Agent' => 'energia-assinatura-backend',
            'Referer' => $referer,
        ];

        if ($this->cookiesHeader) {
            $headers['Cookie'] = $this->cookiesHeader;
        }

        return Http::withHeaders($headers);
    }
}
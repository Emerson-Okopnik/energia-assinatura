<?php

namespace App\Services;

use App\Exceptions\BillNotFoundException;
use App\Exceptions\CelescApiException;
use App\Exceptions\ContractNotFoundException;
use App\Exceptions\LoginFailedException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function gerarSegundaVia(array $payload): array
    {
        $installation = $payload['installation'] ?? null;
        $contractAccount = $payload['contract_account'] ?? null;
        $invoiceId = $payload['invoiceId'] ?? $payload['invoice_id'] ?? null;
        $billingPeriod = $payload['billingPeriod'] ?? $payload['billing_period'] ?? null;
        $channel = $payload['channelCode'] ?? $payload['channel'] ?? $this->defaultChannel;
        $target = $payload['target'] ?? 'sap';

        Log::info('Celesc - início do fluxo de segunda via', [
            'installation' => $installation,
            'contract_account' => $contractAccount,
            'invoice_id' => $invoiceId,
            'billing_period' => $billingPeriod,
            'channel' => $channel,
            'target' => $target,
        ]);

        $auth = $this->login(['username' => $payload['username'] ?? null, 'password' => $payload['password'] ?? null, 'channel' => $channel]);

        $sapAccess = $auth['sap_access'] ?? [];
        $partner = $this->normalizarPartner($sapAccess['partner'] ?? null);
        $sapChannel = $sapAccess['channel'] ?? $channel;

        if (!$partner) {
            throw new LoginFailedException('Parceiro não retornado no login.');
        }

        $contracts = $this->listarContratos($auth['token'], $sapChannel, $partner);

        $selectedContract = $this->selecionarContrato($contracts, $installation, $contractAccount);

        $selectedContractAccount = $selectedContract['contractAccount'] ?? null;
        $accessId = $sapAccess['accessId'] ?? null;

        if (!$selectedContractAccount || !$accessId) {
            throw new CelescApiException('Dados obrigatórios (contractAccount ou accessId) ausentes no retorno da Celesc.');
        }

        $bills = $this->listarFaturas($auth['token'], $sapChannel, $target, $partner, $installation, $selectedContractAccount);

        $selectedBill = $this->selecionarFatura($bills, $invoiceId, $billingPeriod);

        $duplicate = $this->duplicarFatura([
            'contractAccount' => $selectedContract['contractAccount'] ?? null,
            'accessId' => $accessId,
            'partner' => $partner,
            'invoiceId' => $selectedBill['code'],
            'bill' => '',
            'channel' => $sapChannel,
            'target' => $target,
            'token' => $auth['token'],
        ]);

        Log::info('Celesc - fluxo concluído com sucesso', [
            'installation' => $installation,
            'contract_account' => $selectedContract['contractAccount'] ?? null,
            'invoice_id' => $duplicate['invoiceId'] ?? null,
        ]);

        return $duplicate;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function login(array $payload): array
    {
        $this->accessToken = $this->accessToken ?? config('services.celesc.token');
        $this->refreshToken = $this->refreshToken ?? config('services.celesc.refresh_token');

        $username = $payload['username'] ?? $this->username;
        $password = $payload['password'] ?? $this->password;
        $channel = $payload['channel'] ?? $this->defaultChannel;

        if (!$username || !$password) {
            throw new LoginFailedException('Credenciais de login da Celesc não configuradas.');
        }

        $body = [
            'username' => $username,
            'password' => $password,
            'socialCode' => '',
            'socialRedirectUri' => '',
            'channel' => $channel,
            'accessIp' => $payload['access_ip'] ?? '',
            'deviceId' => $payload['device_id'] ?? 'energia-assinatura',
            'firebaseToken' => $payload['firebase_token'] ?? '',
        ];

        $response = $this->baseRequest('https://conecte.celesc.com.br/autenticacao/login')
            ->withHeaders([
                'Referer' => 'https://conecte.celesc.com.br/autenticacao/login',
            ])
            ->post($this->authEndpoint, $body);

        if ($response->failed()) {
            $message = $response->json('errors.0.message') ?? $response->reason();
            throw new LoginFailedException('Falha ao autenticar na Celesc: ' . $message);
        }

        $token = $response->json('data.authenticate.login.accessToken')
            ?? $response->json('token')
            ?? $response->json('accessToken')
            ?? $response->json('access_token');

        if (!$token) {
            throw new LoginFailedException('Token de acesso não encontrado na resposta de login.');
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
            'sap_access' => is_array($sapAccess) ? $sapAccess : [],
            'profile' => is_array($profile) ? $profile : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listarContratos(string $token, string $channel, string $partner): array {

    $body = [
        'variables' => [
            'channelCode' => $channel,
            'target' => 'sap',
            'partner' => $partner,
            'profileType' => 'GRPA'
        ],
        'query' => "query (\$partner: String!, \$profileType: String ) {\n  allContracts(\n    partner: \$partner\n    profileType: \$profileType\n ) {\n    contracts {\n      partner\n      installation\n      category\n      office\n      contract\n      contractAccount\n      home\n      name\n      street\n      houseNum\n      postCode\n      city1\n      city2\n      region\n      country\n      alertCode\n      alert\n      status\n      tarifType\n      favorite\n      denomination\n      messageHome\n      messageCard\n      messageType\n      complement\n      referencePoint\n      generation\n      __typename\n    }\n    message\n    error\n    __typename\n  }\n}",
    ];

        $response = $this->performGraphQlRequest($token, $body, 'https://conecte.celesc.com.br/contrato/selecao');

        if ($response->failed()) {
            $message = $response->json('errors.0.message') ?? $response->reason();
            throw new CelescApiException('Falha ao listar contratos da Celesc: ' . $message);
        }

        $contracts = $response->json('data.allContracts.contracts');

        if (!is_array($contracts) || empty($contracts)) {
            throw new ContractNotFoundException('Nenhuma UC encontrada para a instalação informada.');
        }

        return $contracts;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listarFaturas(string $token, string $channel, string $target, string $partner, string $installation, ?string $contractAccount): array {

      $body = [
        'variables' => [
          'channelCode' => $channel,
          'target' => $target,
          'partner' => $partner,
          'installation' => $installation,
          'contractAccount' => $contractAccount,
        ],
          'query' => "query (\$partner: String!, \$installation: String!, \$contractAccount: String) {\n  getAllBills(\n    partner: \$partner\n    installation: \$installation\n    contractAccount: \$contractAccount\n  ) {\n    bills {\n      protocol\n      installation\n      code\n      dueDate\n      totalAmount\n      currency\n      usage\n      previousUsage\n      consumption\n      compensation\n      compensationDate\n      compensationBloqued\n      launchBloqued\n      hasActiveInstallment\n      channel\n      serviceCode\n      accessId\n      serviceId\n      partner\n      billingPeriod\n      totalDays\n      flag\n      readType\n      availability\n      demandaContr\n      demandaNp\n      demandaFp\n      consumoFatNp\n      consumoFatFp\n      mediaConsFatNp\n      mediaConsFatFp\n      consumoRegNp\n      consumoRegFp\n      mediaConsRegNp\n      mediaConsRegFp\n      mediaValor\n      flagId\n      status\n      readTypeId\n      avalabilityId\n      codigoDeBarras\n      qrCode\n      positionRead\n      averageConsumption\n      intermediateConsumptionBilled\n      intermediateConsumptionReg\n      intermediateAverageConsBilled\n      intermediateAverageConsReg\n      reservedConsumption\n      averageReservedConsumption\n      intermediateGeneratedConsumption\n      generatedConsumptionNP\n      generatedConsumption\n      reservedGeneratedConsumption\n      generatedConsumptionFP\n      averageGeneratedCons\n      averageGeneratedConsNP\n      averageGeneratedConsFP\n      averageIntermediateGeneratedCons\n      averageReservedGeneratedCons\n      __typename\n    }\n    message\n    error\n    retained\n    retainedMessage\n    retainedMainMessage\n    __typename\n  }\n}",
        ];

        $response = $this->performGraphQlRequest($token, $body, 'https://conecte.celesc.com.br/fatura/historico');

        if ($response->failed()) {
            $message = $response->json('errors.0.message') ?? $response->reason();
            throw new CelescApiException('Falha ao listar faturas na Celesc: ' . $message);

            throw new RuntimeException(
                sprintf('Falha ao solicitar fatura na Celesc: %s.', $message)
            );
        }

        $bills = $response->json('data.getAllBills.bills');

        if (!is_array($bills) || empty($bills)) {
            throw new BillNotFoundException('Nenhuma fatura encontrada para a instalação informada.');
        }

        return $bills;
    }

    /**
     * @param  array<int, array<string, mixed>>  $contracts
     * @return array<string, mixed>
     */
    private function selecionarContrato(array $contracts, string $installation, ?string $contractAccount): array {

        $filtrados = array_values(array_filter(
            $contracts,
            fn ($contract) => ($contract['installation'] ?? null) === $installation
        ));

        if ($contractAccount) {
            $filtrados = array_values(array_filter(
                $filtrados,
                fn ($contract) => ($contract['contractAccount'] ?? null) === $contractAccount
            ));
        }

        if (empty($filtrados)) {
            throw new ContractNotFoundException('Contrato/UC não encontrado para a instalação informada.');
        }

        $selecionado = $filtrados[0];

        if ($contractAccount && ($selecionado['contractAccount'] ?? null) !== $contractAccount) {
            throw new ContractNotFoundException('Contract account não pertence à instalação informada.');
        }

        return $selecionado;
    }

    /**
     * @param  array<int, array<string, mixed>>  $bills
     * @return array<string, mixed>
     */
    private function selecionarFatura(array $bills, ?string $invoiceId, ?string $billingPeriod): array
    {
        if ($invoiceId) {
            foreach ($bills as $bill) {
                if (($bill['code'] ?? null) === $invoiceId) {
                    return $bill;
                }
            }

            throw new BillNotFoundException('Fatura não encontrada para o invoiceId informado.');
        }

        if ($billingPeriod) {
            foreach ($bills as $bill) {
                if (($bill['billingPeriod'] ?? null) === $billingPeriod) {
                    return $bill;
                }
            }

            throw new BillNotFoundException('Fatura não encontrada para o período de faturamento informado.');
        }

        return $bills[0];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function duplicarFatura(array $payload): array
    {
        $token = $payload['token'] ?? $this->accessToken;

        if (!$token) {
          throw new CelescApiException('Token de acesso da Celesc não informado para duplicar fatura.');
        }

        $body = [
            'variables' => [
                'duplicateBillInput' => [
                    'contractAccount' => $payload['contractAccount'],
                    'accessId' => $payload['accessId'],
                    'partner' => $payload['partner'],
                    'invoiceId' => $payload['invoiceId'],
                    'bill' => $payload['bill'],
                    'channel' => $payload['channel'],
                ],
                'target' => $payload['target'],
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
            $message = $response->json('errors.0.message') ?? $response->reason();
            throw new CelescApiException('Falha ao solicitar 2ª via na Celesc: ' . $message);
        }
        
        $data = $response->json('data.duplicateBill');

        if (!$data || !is_array($data)) {
            throw new CelescApiException('Resposta inesperada da Celesc ao emitir fatura.');
        }

        return $data;
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

        $request = Http::withHeaders($headers);

        // Ajuste para ambiente local (ex: Windows com CA/SSL desconfigurado)
        // - services.celesc.ssl_verify = false  -> desativa verificação SSL
        // - services.celesc.ca_bundle = "C:\\path\
        // \cacert.pem" -> usa CA bundle específico
        $sslVerify = config('services.celesc.ssl_verify');
        $caBundle = config('services.celesc.ca_bundle');

        if ($sslVerify === false || ($sslVerify === null && app()->environment('local'))) {
            $request = $request->withoutVerifying();
        } elseif ($caBundle) {
            $request = $request->withOptions(['verify' => $caBundle]);
        }

        return $request;
    }

    private function normalizarPartner(?string $partner): ?string
    {
        if (!$partner) {
            return null;
        }

        $partner = preg_replace('/\\D/', '', (string) $partner) ?? '';

        if ($partner === '') {
            return null;
        }

        return str_pad($partner, 10, '0', STR_PAD_LEFT);
    }
}
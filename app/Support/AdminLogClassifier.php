<?php



namespace App\Support;

class AdminLogClassifier
{
    public static function explain(string $line, ?string $level = null): array
    {
        $lower = mb_strtolower($line);
        $level = strtoupper((string) ($level ?: ''));
        $isProblem = in_array($level, ['EMERGENCY', 'ALERT', 'CRITICAL', 'ERROR', 'WARNING'], true)
            || str_contains($lower, 'exception')
            || str_contains($lower, 'failed')
            || str_contains($lower, 'falha')
            || str_contains($lower, 'erro')
            || str_contains($lower, 'invalid')
            || str_contains($lower, 'inválido')
            || str_contains($lower, 'invalido')
            || str_contains($lower, 'unauthorized')
            || str_contains($lower, 'não autorizado')
            || str_contains($lower, 'nao autorizado')
            || str_contains($lower, 'denied')
            || str_contains($lower, 'recusado')
            || str_contains($lower, 'timeout');

        if (! $isProblem) {
            return [
                'label' => 'Log informativo',
                'hint' => 'Evento normal da operação. Não indica erro.',
            ];
        }

        $rules = [
            [
                'patterns' => ['ip não permitido pela playfiver', 'ip não permitido. código: 403', 'ip nao permitido pela playfiver'],
                'label' => 'PlayFiver: IP não permitido',
                'hint' => 'A PlayFiver recusou por whitelist. Cadastre o IPv4 do servidor no painel/atendimento da PlayFiver.',
            ],
            [
                'patterns' => ['agente playfiver não existe', 'agenttoken.exists', 'esse agente não existe', 'agenttoken não existe'],
                'label' => 'PlayFiver: agente inválido',
                'hint' => 'O agentToken configurado não existe ou pertence a outro ambiente. Confira Agregador de Jogos.',
            ],
            [
                'patterns' => ['secretkey playfiver não existe', 'secretkey.exists', 'secretkey não existe'],
                'label' => 'PlayFiver: secretKey inválida',
                'hint' => 'A secretKey configurada não confere com a PlayFiver. Confira token/secret/code e ambiente.',
            ],
            [
                'patterns' => ['jogo em manutenção na playfiver', 'jogo está em manutenção', 'jogo esta em manutencao'],
                'label' => 'PlayFiver: jogo em manutenção',
                'hint' => 'O jogo específico está em manutenção. Desative o jogo no painel ou aguarde o provedor liberar.',
            ],
            [
                'patterns' => ['provedor em manutenção na playfiver', 'provedor em manutenção', 'provedor em manutencao'],
                'label' => 'PlayFiver: provedor em manutenção',
                'hint' => 'Não é erro do usuário. O provedor do jogo está indisponível na PlayFiver.',
            ],
            [
                'patterns' => ['playfiver em manutenção', 'estamos em manutenção', 'estamos em manutencao'],
                'label' => 'PlayFiver: plataforma em manutenção',
                'hint' => 'A PlayFiver informou manutenção geral. Aguarde normalizar antes de liberar jogos.',
            ],
            [
                'patterns' => ['usuário banido na playfiver', 'usuario banido', 'usuário banido', 'user banned'],
                'label' => 'PlayFiver: usuário banido',
                'hint' => 'A PlayFiver recusou esse usuário. Verifique conta, risco e regra de bloqueio antes de liberar.',
            ],
            [
                'patterns' => ['saldo insuficiente na carteira playfiver', 'saldo insuficiente na carteira', 'insufficient balance'],
                'label' => 'PlayFiver: saldo operacional insuficiente',
                'hint' => 'A carteira/agregador PlayFiver não tem saldo suficiente. Recarregue ou mantenha bloqueio de jogos.',
            ],
            [
                'patterns' => ['modo influencer ativo', 'modo influencer ativado', 'influencer.boolean'],
                'label' => 'PlayFiver: modo influencer',
                'hint' => 'A PlayFiver recusou por modo influencer ou parâmetro inválido. Confira a flag do usuário.',
            ],
            [
                'patterns' => ['agenttoken não enviado', 'agenttoken.required', 'secretkey não enviada', 'secretkey.required', 'user_code não enviado', 'user_code.required', 'game_code não enviado', 'game_code.required', 'user_balance não enviado', 'user_balance.required'],
                'label' => 'PlayFiver: campo obrigatório ausente',
                'hint' => 'A requisição para a PlayFiver saiu sem campo obrigatório. Confira montagem do payload.',
            ],
            [
                'patterns' => ['user_balance inválido', 'user_balance.numeric', 'game_original inválido', 'game_original.boolean', 'valor inválido no parametro "original"'],
                'label' => 'PlayFiver: parâmetro inválido',
                'hint' => 'A PlayFiver recusou formato de parâmetro. user_balance deve ser numérico e game_original boolean true/false.',
            ],
            [
                'patterns' => ['cpf inválido, inexistente ou recusado', 'object reference not set'],
                'label' => 'CPF inválido ou inexistente',
                'hint' => 'O gateway recusou o documento. Peça ao usuário para conferir CPF/documento e tente novamente.',
            ],
            [
                'patterns' => ['valor fora do limite permitido', 'valor solicitado fora do intervalo permitido'],
                'label' => 'Valor fora do limite do gateway',
                'hint' => 'Ajuste mínimo/máximo no painel ou use um valor aceito pelo gateway.',
            ],
            [
                'patterns' => ['saldo insuficiente na conta do gateway', 'saldo insuficiente para realizar'],
                'label' => 'Saldo insuficiente no gateway',
                'hint' => 'O gateway não tem saldo disponível para o saque. Adicione saldo na conta do gateway.',
            ],
            [
                'patterns' => ['chave pix inválida', 'erro ao processar saque, tente outra chave pix'],
                'label' => 'Chave PIX inválida',
                'hint' => 'Revise chave PIX, tipo da chave, CPF/titularidade e tente outra chave.',
            ],
            [
                'patterns' => ['unauthorized', 'não autorizado', 'nao autorizado', 'invalid token', 'token inválido', 'token invalido', 'agent_secret', 'bearer token'],
                'label' => 'Token/credencial inválida',
                'hint' => 'Confira token webhook, agent_code, agent_secret, bearer token ou client secret configurado no painel.',
            ],
            [
                'patterns' => ['ip do servidor não permitido', 'ip não permitido', 'ip nao permitido', 'não autorizado, ip', 'nao autorizado, ip', 'forbidden ip', 'whitelist', 'allowed ip'],
                'label' => 'IP não permitido',
                'hint' => 'O gateway recusou a requisição por whitelist. Cadastre o IPv4 do servidor no painel do gateway.',
            ],
            [
                'patterns' => ['invalid cpf', 'cpf inválido', 'cpf invalido', 'documento inválido', 'documento invalido', 'invalid document', 'taxid inválido', 'taxid invalido', 'tax_id inválido', 'tax_id invalido'],
                'label' => 'CPF/documento inválido',
                'hint' => 'Revise CPF enviado, máscara, dígitos e se o gateway exige CPF real do titular.',
            ],
            [
                'patterns' => ['invalid email', 'email inválido', 'email invalido', 'e-mail inválido', 'e-mail invalido'],
                'label' => 'E-mail inválido',
                'hint' => 'Revise formato do e-mail e se o gateway aceita esse domínio/cliente.',
            ],
            [
                'patterns' => ['timeout', 'timed out', 'connection refused', 'could not resolve', 'dns', 'ssl', 'curl error', 'network', 'conexão', 'conexao'],
                'label' => 'Conexão ruim/API fora',
                'hint' => 'Falha de rede, DNS, SSL ou timeout. Teste conectividade do servidor e force IPv4 quando o gateway tiver problema com IPv6.',
            ],
            [
                'patterns' => ['credenciais do gateway inválidas', 'credenciais inválidas', 'credenciais invalidas', 'api key inválida', 'api key invalida', 'client secret inválido', 'client secret invalido', 'secret inválido', 'secret invalido'],
                'label' => 'Credenciais erradas',
                'hint' => 'Confira client_id, client_secret, API key, senha ou ambiente sandbox/produção.',
            ],
            [
                'patterns' => ['422', 'validation', 'payload inválido', 'payload invalido', 'invalid_payload', 'required field', 'campo obrigatório', 'campo obrigatorio'],
                'label' => 'Payload inválido',
                'hint' => 'O gateway recusou algum campo obrigatório ou formato do corpo da requisição.',
            ],
            [
                'patterns' => ['429', 'rate limit', 'too many requests'],
                'label' => 'Rate limit',
                'hint' => 'Muitas requisições em pouco tempo. Aguarde ou reduza polling/retry.',
            ],
            [
                'patterns' => ['playfiver', 'play_fiver'],
                'label' => 'PlayFiver',
                'hint' => 'Verifique token, secret, agent_code, payload do webhook e saldo do agregador.',
            ],
            [
                'patterns' => ['gerapix', 'gateway'],
                'label' => 'Gateway de pagamento',
                'hint' => 'Confira credenciais, URL base, webhook token, status no painel do gateway e IP liberado.',
            ],
        ];

        foreach ($rules as $rule) {
            foreach ($rule['patterns'] as $pattern) {
                if (str_contains($lower, $pattern)) {
                    return ['label' => $rule['label'], 'hint' => $rule['hint']];
                }
            }
        }

        return ['label' => 'Erro geral', 'hint' => 'Erro sem causa automática identificada. Leia a mensagem completa e o contexto anterior/posterior.'];
    }
}
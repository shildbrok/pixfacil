<?php



namespace App\Services;

use App\Models\GamesKey;
use App\Models\LogsRoundsFree;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlayFiverService
{
    protected static $secretPlayFiver;
    protected static $codePlayFiver;
    protected static $tokenPlayFiver;

    private const FREE_BONUS_ENDPOINT = 'https://api.playfivers.com/api/v2/free_bonus';
    private const GAME_LAUNCH_ENDPOINT = 'https://api.playfivers.com/api/v2/game_launch';

    private static function credencialFiverPlay(): void
    {
        $setting = GamesKey::first();
        self::$secretPlayFiver = $setting?->playfiver_secret;
        self::$codePlayFiver   = $setting?->playfiver_code;
        self::$tokenPlayFiver  = $setting?->playfiver_token;
    }


    public static function RoundsFree($dados): array
    {
        return self::grantFreeBonus([
            'username' => $dados['username'] ?? $dados['user_code'] ?? null,
            'game_code' => $dados['game_code'] ?? null,
            'rounds' => $dados['rounds'] ?? null,
        ]);
    }

    public static function grantFreeBonus(array $dados): array
    {
        self::credencialFiverPlay();

        $username = trim((string) ($dados['username'] ?? $dados['user_code'] ?? ''));
        $gameCode = trim((string) ($dados['game_code'] ?? ''));
        $rounds = (int) ($dados['rounds'] ?? 0);

        if ($username === '' || $gameCode === '' || $rounds < 1 || $rounds > 1000) {
            $message = 'Dados inválidos para agendar rodadas grátis. Informe usuário, jogo e rodadas entre 1 e 1000.';
            self::logFreeBonus($gameCode, $username, false, $message);

            return [
                'status' => false,
                'message' => $message,
                'reason' => 'validation',
            ];
        }

        if (! self::hasCredentials()) {
            $message = 'Credenciais PlayFiver ausentes. Configure agent token e secret key.';
            self::logFreeBonus($gameCode, $username, false, $message);

            return [
                'status' => false,
                'message' => $message,
                'reason' => 'credentials',
            ];
        }

        $launch = self::gameLaunchFalse([
            'username' => $username,
            'game_code' => $gameCode,
        ]);

        if (! $launch['status']) {
            $message = $launch['message'] ?: 'Não foi possível validar o jogo antes de agendar rodadas grátis.';
            self::logFreeBonus($gameCode, $username, false, $message);

            return [
                'status' => false,
                'message' => $message,
                'reason' => $launch['reason'] ?? 'launch_failed',
                'http_status' => $launch['http_status'] ?? null,
            ];
        }

        $postArray = [
            'agent_token' => self::$tokenPlayFiver,
            'secret_key' => self::$secretPlayFiver,
            'user_code' => $username,
            'game_code' => $gameCode,
            'rounds' => $rounds,
        ];

        try {
            $response = self::playFiverHttp()->post(self::FREE_BONUS_ENDPOINT, $postArray);
            $data = $response->json() ?: [];
            $status = (bool) ($data['status'] ?? false);
            $message = self::freeBonusMessage((int) $response->status(), (string) ($data['msg'] ?? ''), $data);

            self::logFreeBonus($gameCode, $username, $status, $message);

            if ($response->successful() && $status) {
                return [
                    'status' => true,
                    'message' => $message ?: 'Rodadas grátis agendadas com sucesso.',
                    'data' => $data,
                ];
            }

            Log::warning('[PLAYFIVER FREEBONUS] falha ao agendar rodadas grátis', [
                'http_status' => $response->status(),
                'reason' => $message,
                'game_code' => $gameCode,
                'rounds' => $rounds,
            ]);

            return [
                'status' => false,
                'message' => $message ?: 'Não foi possível agendar rodadas grátis.',
                'http_status' => $response->status(),
                'data' => $data,
            ];
        } catch (ConnectionException $e) {
            $message = 'Falha de conexão com a PlayFiver ao agendar rodadas grátis.';
            self::logFreeBonus($gameCode, $username, false, $message);
            Log::warning('[PLAYFIVER FREEBONUS] falha de conexão ao agendar', [
                'message' => $e->getMessage(),
                'game_code' => $gameCode,
            ]);

            return ['status' => false, 'message' => $message, 'reason' => 'connection'];
        } catch (\Throwable $e) {
            $message = 'Erro interno ao agendar rodadas grátis.';
            self::logFreeBonus($gameCode, $username, false, $message);
            Log::error('[PLAYFIVER FREEBONUS] exceção ao agendar', [
                'message' => $e->getMessage(),
                'game_code' => $gameCode,
            ]);

            return ['status' => false, 'message' => $message, 'reason' => 'exception'];
        }
    }

    public static function listFreeBonus(): array
    {
        self::credencialFiverPlay();

        if (! self::hasCredentials()) {
            return [
                'status' => false,
                'message' => 'Credenciais PlayFiver ausentes. Configure agent token e secret key.',
                'data' => [],
            ];
        }

        try {
            $response = self::playFiverHttp()->send('GET', self::FREE_BONUS_ENDPOINT, [
                'json' => [
                    'agent_token' => self::$tokenPlayFiver,
                    'secret_key' => self::$secretPlayFiver,
                ],
            ]);

            $data = $response->json() ?: [];
            $status = (bool) ($data['status'] ?? $response->successful());

            if (! $response->successful() || ! $status) {
                $message = self::freeBonusMessage((int) $response->status(), (string) ($data['msg'] ?? ''), $data);
                Log::warning('[PLAYFIVER FREEBONUS] falha ao listar rodadas grátis', [
                    'http_status' => $response->status(),
                    'reason' => $message,
                ]);

                return [
                    'status' => false,
                    'message' => $message ?: 'Não foi possível listar as rodadas grátis.',
                    'data' => [],
                    'http_status' => $response->status(),
                ];
            }

            return [
                'status' => true,
                'message' => (string) ($data['msg'] ?? ''),
                'data' => array_values((array) ($data['data'] ?? [])),
                'http_status' => $response->status(),
            ];
        } catch (ConnectionException $e) {
            Log::warning('[PLAYFIVER FREEBONUS] falha de conexão ao listar', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'Falha de conexão com a PlayFiver ao listar rodadas grátis.',
                'data' => [],
                'reason' => 'connection',
            ];
        } catch (\Throwable $e) {
            Log::error('[PLAYFIVER FREEBONUS] exceção ao listar', [
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'message' => 'Erro interno ao listar rodadas grátis.',
                'data' => [],
                'reason' => 'exception',
            ];
        }
    }

    public static function deleteFreeBonus(int $id): array
    {
        self::credencialFiverPlay();

        if ($id < 1) {
            return ['status' => false, 'message' => 'ID da rodada grátis inválido.'];
        }

        if (! self::hasCredentials()) {
            return ['status' => false, 'message' => 'Credenciais PlayFiver ausentes. Configure agent token e secret key.'];
        }

        try {
            $response = self::playFiverHttp()->send('DELETE', self::FREE_BONUS_ENDPOINT, [
                'json' => [
                    'id' => $id,
                    'agent_token' => self::$tokenPlayFiver,
                    'secret_key' => self::$secretPlayFiver,
                ],
            ]);

            $data = $response->json() ?: [];
            $status = (bool) ($data['status'] ?? false);
            $message = self::deleteFreeBonusMessage((int) $response->status(), (string) ($data['msg'] ?? ''), $data);

            if ($response->successful() && $status) {
                Log::notice('[PLAYFIVER FREEBONUS] rodada grátis deletada', [
                    'free_bonus_id' => $id,
                    'message' => $message,
                ]);

                return [
                    'status' => true,
                    'message' => $message ?: 'Rodada grátis deletada com sucesso.',
                    'data' => $data,
                ];
            }

            Log::warning('[PLAYFIVER FREEBONUS] falha ao deletar rodada grátis', [
                'free_bonus_id' => $id,
                'http_status' => $response->status(),
                'reason' => $message,
            ]);

            return [
                'status' => false,
                'message' => $message ?: 'Não foi possível deletar a rodada grátis.',
                'http_status' => $response->status(),
                'data' => $data,
            ];
        } catch (ConnectionException $e) {
            Log::warning('[PLAYFIVER FREEBONUS] falha de conexão ao deletar', [
                'free_bonus_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return ['status' => false, 'message' => 'Falha de conexão com a PlayFiver ao deletar rodada grátis.'];
        } catch (\Throwable $e) {
            Log::error('[PLAYFIVER FREEBONUS] exceção ao deletar', [
                'free_bonus_id' => $id,
                'message' => $e->getMessage(),
            ]);

            return ['status' => false, 'message' => 'Erro interno ao deletar rodada grátis.'];
        }
    }

    private static function gameLaunchFalse(array $dados): array
    {
        $postArray = [
            'agentToken' => self::$tokenPlayFiver,
            'secretKey' => self::$secretPlayFiver,
            'user_code' => $dados['username'],
            'game_code' => $dados['game_code'],
            'game_original' => false,
            'user_balance' => 100,
        ];

        try {
            $response = self::playFiverHttp()->post(self::GAME_LAUNCH_ENDPOINT, $postArray);

            if ($response->successful()) {
                return ['status' => true, 'message' => ''];
            }

            $data = $response->json() ?: [];

            return [
                'status' => false,
                'message' => self::launchMessage((int) $response->status(), (string) Arr::get($data, 'msg', ''), $data),
                'http_status' => $response->status(),
                'reason' => 'launch_failed',
            ];
        } catch (ConnectionException $e) {
            return [
                'status' => false,
                'message' => 'Falha de conexão com a PlayFiver ao validar o jogo.',
                'reason' => 'connection',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => false,
                'message' => 'Erro interno ao validar o jogo na PlayFiver.',
                'reason' => 'exception',
            ];
        }
    }

    private static function hasCredentials(): bool
    {
        return filled(self::$tokenPlayFiver) && filled(self::$secretPlayFiver);
    }

    private static function playFiverHttp()
    {
        return Http::timeout(25)
            ->acceptJson()
            ->asJson()
            ->withOptions([
                'force_ip_resolve' => 'v4',
                'curl' => [
                    CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                ],
            ]);
    }

    private static function logFreeBonus(string $gameCode, string $username, bool $status, string $message): void
    {
        LogsRoundsFree::create([
            'game_code' => $gameCode,
            'username' => $username,
            'status' => $status ? 1 : 0,
            'message' => $message,
        ]);
    }

    private static function freeBonusMessage(int $httpStatus, string $message, array $data = []): string
    {
        $text = mb_strtolower($message . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        if ($httpStatus === 404) {
            return 'Este jogo não possui suporte para rodadas grátis na PlayFiver.';
        }

        if ($httpStatus === 409) {
            return 'Já existe uma rodada grátis pendente para esse usuário.';
        }

        if ($httpStatus === 422) {
            if (str_contains($text, 'limite')) {
                return 'Ultrapassou o limite de rodadas grátis disponível na PlayFiver.';
            }

            if (str_contains($text, 'round') || str_contains($text, 'rodada')) {
                return 'Quantidade de rodadas inválida. A PlayFiver aceita entre 1 e 1000 rodadas. Contas padrão podem ter limite comercial menor, normalmente até 50 rodadas por até 200 clientes.';
            }

            return 'Dados recusados pela PlayFiver. Confira usuário, jogo e quantidade de rodadas.';
        }

        if ($httpStatus === 500) {
            return 'Erro interno da PlayFiver ao processar rodadas grátis.';
        }

        return $message !== '' ? $message : 'Falha ao processar rodadas grátis na PlayFiver.';
    }

    private static function deleteFreeBonusMessage(int $httpStatus, string $message, array $data = []): string
    {
        if ($httpStatus === 404) {
            return 'Rodada grátis não encontrada na PlayFiver. Ela pode já ter sido usada, expirada ou removida.';
        }

        if ($httpStatus === 500) {
            return 'Erro interno da PlayFiver ao deletar rodada grátis.';
        }

        return $message !== '' ? $message : 'Falha ao deletar rodada grátis na PlayFiver.';
    }

    private static function launchMessage(int $httpStatus, string $message, array $data = []): string
    {
        $text = mb_strtolower($message . ' ' . json_encode($data, JSON_UNESCAPED_UNICODE));

        if ($httpStatus === 403 || str_contains($text, 'ip')) {
            return 'IP do servidor não permitido na PlayFiver.';
        }

        if (str_contains($text, 'agente') || str_contains($text, 'agent')) {
            return 'Agente PlayFiver não existe ou está configurado incorretamente.';
        }

        if (str_contains($text, 'secret')) {
            return 'Secret key da PlayFiver está incorreta ou não existe.';
        }

        if (str_contains($text, 'manutenção') || str_contains($text, 'manutencao') || str_contains($text, 'maintenance')) {
            return 'Jogo, provedor ou PlayFiver em manutenção.';
        }

        if (str_contains($text, 'game_code') || str_contains($text, 'jogo')) {
            return 'Jogo não encontrado ou sem suporte para rodadas grátis na PlayFiver.';
        }

        if ($httpStatus === 500) {
            return 'PlayFiver recusou a validação do jogo. Confira usuário, saldo operacional e modo influencer.';
        }

        return $message !== '' ? $message : 'Não foi possível validar o jogo na PlayFiver.';
    }
}
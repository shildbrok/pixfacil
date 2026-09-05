<?php



namespace App\Http\Controllers\Api\Auth;

use App\Models\Setting;
use App\Models\Wallet;
use App\Traits\Affiliates\AffiliateHistoryTrait;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Helpers\Core as Helper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Exceptions\JWTException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Jobs\SendMetaEvent; 
use App\Support\AdminActionGuard;
use App\Support\MailNotificationGuard;

class AuthController extends Controller
{
    use AffiliateHistoryTrait;

    public function __construct()
    {
        $this->middleware('auth.jwt', [
            'except' => ['login', 'register', 'submitForgetPassword', 'submitResetPassword']
        ]);
    }

    public function verify()
    {
        return response()->json(auth('api')->user());
    }

    public function login(Request $request)
    {
        try {
            $credentials = $request->only(['email','password']);

            if (!$jwtToken = auth('api')->attempt($credentials)) {
                return response()->json(['error' => trans('Check credentials')], 400);
            }


            $user = auth('api')->user();




            if ($user && ((int) ($user->banned ?? 0) === 1
                || ! in_array((string) ($user->status ?? 'active'), ['active', '1'], true))) {
                auth('api')->logout();
                return response()->json(['error' => 'Conta bloqueada.'], 403);
            }


            $sessionToken = Str::random(60);
            $user->session_token = $sessionToken;
            $user->save();


            \App\Services\BetCrm\BetCrmService::sendLogin($user);




            try {
                $eventId = 'login_' . $user->id;

                $fbp = $request->input('fbp');
                $fbc = $request->input('fbc');

                SendMetaEvent::dispatchAfterResponse(
                    'Login',
                    [
                        'email'       => $user->email,
                        'phone'       => $user->phone ?? null,
                        'first_name'  => $user->name ?? null,
                        'external_id' => $user->id,
                        'fbp'         => $fbp,
                        'fbc'         => $fbc,
                    ],
                    [
                        'value'    => 0,
                        'currency' => 'BRL',
                    ],
                    $eventId,
                    [
                        'action_source'    => 'website',
                        'event_source_url' => $request->headers->get('referer', config('app.url') . '/login'),
                        'client_ip'        => $request->ip(),
                        'user_agent'       => $request->userAgent(),
                        'test_event'       => app()->environment(['local', 'development', 'staging']),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Erro ao enviar evento Meta CAPI (login): ' . $e->getMessage(), [
                    'user_id' => $user->id ?? null,
                    'email'   => $user->email ?? null,
                ]);
            }

            return $this->respondWithToken($jwtToken, $sessionToken);

        } catch (JWTException $e) {
            return response()->json(['error' => 'Could not create token'], 400);
        }
    }

    public function register(Request $request)
    {
        try {
            $setting = Helper::getSetting();

            $validator = Validator::make($request->all(), [
                'name'           => 'required|string|max:255',
                'email'          => 'required|email|unique:users|max:255',
                'phone'          => 'required|string|min:10|max:15',
                'password'       => ['required', Rules\Password::min(8)->letters()->mixedCase()->numbers()],
                'cupom'          => 'nullable|string',
                'reference_code' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json($validator->errors(), 400);
            }

            if (!empty($request->cupom)) {
                $cupom = \App\Models\Cupom::where('codigo', $request->cupom)->first();

                if (!$cupom) {
                    return response()->json(['error' => 'Esse cupom não existe'], 400);
                }
                if ($cupom->validade < now()) {
                    return response()->json(['error' => 'Esse cupom já está expirado'], 400);
                }
                if ($cupom->usos >= $cupom->quantidade_uso) {
                    return response()->json([
                        'error' => 'Vários usuários resgataram esse cupom hoje, tente novamente amanhã ou outro cupom'
                    ], 400);
                }
            }

            $userData = $request->only(['name','email','phone']);
            $userData['password']           = $request->password;
            $userData['affiliate_cpa']      = $setting->cpa_value;
            $userData['affiliate_baseline'] = $setting->cpa_baseline;


            $user = User::create($userData);

            if (!empty($request->reference_code)) {
                $ref = User::where('inviter_code', $request->reference_code)->first();
                if ($ref) {
                    $user->update(['inviter' => $ref->id]);
                    $this->saveAffiliateHistory($user);
                }
            }

            $this->createWallet($user);



            if (empty($user->inviter_code)) {
                $user->update(['inviter_code' => $this->generateAffiliateCode()]);
            }




            $bcRef = (string) ($request->input('bc_ref')
                ?: $request->cookie(\App\Http\Middleware\CaptureBetCrmRef::COOKIE)
                ?: '');
            if ($bcRef !== '') {
                $user->forceFill(['betcrm_ref' => $bcRef])->save();
            }
            \App\Services\BetCrm\BetCrmService::sendRegistration($user, $request);
            \App\Services\BetCrm\BetCrmService::sendLead($user, $request);

            if (!empty($request->cupom)) {




                DB::transaction(function () use ($request, $user, $setting) {
                    $cupomLock = \App\Models\Cupom::where('codigo', $request->cupom)
                        ->lockForUpdate()
                        ->first();

                    if (! $cupomLock
                        || $cupomLock->validade < now()
                        || $cupomLock->usos >= $cupomLock->quantidade_uso) {


                        return;
                    }

                    $bonus    = (float) $cupomLock->valor_bonus;
                    $rollover = $bonus * (float) $setting->rollover;

                    $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                    if ($wallet) {
                        $wallet->balance_bonus          = (float) $wallet->balance_bonus + $bonus;
                        $wallet->balance_bonus_rollover = (float) $wallet->balance_bonus_rollover + $rollover;
                        $wallet->save();
                    }

                    $cupomLock->increment('usos');
                });
            }

            $token = auth('api')->attempt($request->only('email','password'));
            if (!$token) {
                return response()->json(['error' => 'Faça login ou cadastre-se para continuar'], 401);
            }


            $userAuth = auth('api')->user();


            $sessionToken = Str::random(60);
            $userAuth->session_token = $sessionToken;
            $userAuth->save();




            try {
                $eventId = 'reg_' . $user->id;


                $fbp = $request->input('fbp');
                $fbc = $request->input('fbc');

                SendMetaEvent::dispatchAfterResponse(
                    'CompleteRegistration',
                    [
                        'email'       => $user->email,
                        'phone'       => $user->phone ?? null,
                        'first_name'  => $user->name ?? null,
                        'external_id' => $user->id,
                        'fbp'         => $fbp,
                        'fbc'         => $fbc,
                    ],
                    [
                        'value'    => 0,
                        'currency' => 'BRL',
                    ],
                    $eventId,
                    [
                        'action_source'    => 'website',
                        'event_source_url' => $request->headers->get('referer', config('app.url') . '/register'),
                        'client_ip'        => $request->ip(),
                        'user_agent'       => $request->userAgent(),
                        'test_event'       => app()->environment(['local', 'development', 'staging']),
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Erro ao enviar evento Meta CAPI (register): ' . $e->getMessage(), [
                    'user_id' => $user->id ?? null,
                    'email'   => $user->email ?? null,
                ]);
            }

            return $this->respondWithToken($token, $sessionToken);

        } catch (\Exception $e) {
            report($e);
            $message = $e instanceof \RuntimeException ? $e->getMessage() : 'Não foi possível concluir a solicitação.';
            return response()->json(['error' => $message], 400);
        }
    }

    private function createWallet($user)
    {
        Wallet::create([
            'user_id'  => $user->id,
            'currency' => 'BRL',
            'symbol'   => 'R$',
            'active'   => 1,
        ]);
    }

    public function me()
    {
        return response()->json(auth('api')->user());
    }

    public function logout(Request $request)
    {

        $user = auth('api')->user();

        if ($user) {

            $user->session_token = null;
            $user->save();
        }

        auth('api')->logout();

        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     * @noinspection PhpUndefinedMethodInspection
     */
    public function refresh()
    {
        $jwtToken = auth('api')->refresh();


        $user = auth('api')->user();


        $sessionToken = Str::random(60);
        $user->session_token = $sessionToken;
        $user->save();

        return $this->respondWithToken($jwtToken, $sessionToken);
    }

    private function generateAffiliateCode()
    {
        do {
            $code = \Helper::generateCode(8);
            $checkCode = User::where('inviter_code', $code)->first();
        } while ($checkCode);

        return $code;
    }

    public function submitForgetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);


        $user = DB::table('users')->where('email', $request->email)->first();
        if (!$user) {
            return response()->json([
                'status'  => true,
                'message' => 'If your email exists in our system, you will receive a password reset link.',
            ], 200);
        }

        $token = Str::random(64);
        $hashedToken = hash('sha256', $token);
        $expiresAt = Carbon::now()->addMinutes(15);

        if (MailNotificationGuard::canSend()) {
            DB::transaction(function () use ($request, $token, $hashedToken, $expiresAt) {

                DB::table('password_reset_tokens')->where('email', $request->email)->delete();

                DB::table('password_reset_tokens')->insert([
                    'email'      => $request->email,
                    'token'      => $hashedToken,
                    'created_at' => Carbon::now(),
                    'expires_at' => $expiresAt
                ]);

                Mail::send('emails.forget-password', [
                    'token'     => $token,
                    'resetLink' => url('/reset-password/' . $token)
                ], function ($message) use ($request) {
                    $message->to($request->email)->subject('Reset Password');
                });
            });
        }

        return response()->json([
            'status'  => true,
            'message' => 'If your email exists in our system, you will receive a password reset link.',
        ], 200);
    }

    public function submitResetPassword(Request $request)
    {
        $request->validate([
            'email'    => 'required|email', 
            'token'    => 'required',
            'password' => ['required', 'confirmed', Rules\Password::min(8)->letters()->mixedCase()->numbers()]
        ]);

        $tokenData = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        $providedTokenHash = hash('sha256', (string) $request->token);

        if (! $tokenData || ! hash_equals((string) $tokenData->token, $providedTokenHash)) {
            return response()->json(['error' => 'Invalid or expired token.'], 400);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $user->password = $request->password;
        $user->save();
        app(AdminActionGuard::class)->invalidateUserSessions($user);

        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json(['status' => true, 'message' => 'Your password has been changed!'], 200);
    }

    protected function respondWithToken(string $jwtToken, string $sessionToken)
    {
        return response()->json([
            'access_token'       => $jwtToken,
            'token_type'         => 'bearer',
            'expires_in'         => auth('api')->factory()->getTTL() * 60,
            'session_token'      => $sessionToken,
            'session_expires_in' => 30 * 60,
            'user'               => auth('api')->user(),
        ]);
    }
}
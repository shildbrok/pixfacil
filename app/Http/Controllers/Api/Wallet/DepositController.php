<?php



namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Traits\Gateways\GeraPixTrait;
use App\Helpers\Core;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Str;

class DepositController extends Controller
{
    use GeraPixTrait;


    public function submitPayment(Request $request)
    {

        $this->updateUserCpfIfNeeded($request->input('cpf'));

        if (auth('api')->check() && (bool) (auth('api')->user()->is_influencer ?? false)) {
            return $this->submitInfluencerDeposit($request);
        }

        // Gateway ativo vem de settings.deposit_gateway; cada um tem sua própria
        // flag {key}_is_enable, como o gerapix_is_enable já funcionava.
        $gateway = \App\Services\Gateways\GatewayManager::forDeposit();

        if (! \App\Services\Gateways\GatewayManager::isEnabled($gateway)) {
            return response()->json(['error' => 'Gateway de depósito desativado.'], 403);
        }

        return $gateway->createDeposit($request);
    }


    public function consultStatusTransactionPix(Request $request)
    {
        return $this->consultStatusTransaction($request);
    }


    protected function consultStatusTransaction(Request $request)
    {



        $userId = auth('api')->id();
        if (!$userId) {
            return response()->json(['status' => 'UNAUTHORIZED'], 401);
        }

        if (auth('api')->check()
            && (bool) (auth('api')->user()->is_influencer ?? false)
            && str_starts_with((string) $request->input('idTransaction'), 'influencer_demo_')) {
            return response()->json(['status' => 'PAID', 'influencer_demo' => true]);
        }

        $transaction = Transaction::query()
            ->where('payment_id', $request->input('idTransaction'))
            ->where('user_id', $userId)
            ->first();

        if ($transaction && (int) $transaction->status === 1) {

            return response()->json(['status' => 'PAID']);
        }

        if ($transaction && (int) $transaction->status === 2) {

            return response()->json(['status' => 'CANCELLED']);
        }

        if ($transaction) {

            return response()->json(['status' => 'PENDING']);
        }


        return response()->json(['status' => 'NOT_FOUND'], 404);
    }


    public function index()
    {
        $deposits = Deposit::whereUserId(auth('api')->id())->paginate();
        return response()->json(['deposits' => $deposits], 200);
    }


    protected function submitInfluencerDeposit(Request $request)
    {
        $setting = Core::getSetting();

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:' . ($setting->min_deposit ?? 0), 'max:' . ($setting->max_deposit ?? 999999999)],
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $user = auth('api')->user();
        $amount = (float) $request->input('amount');

        DB::transaction(function () use ($user, $amount): void {
            $wallet = Wallet::query()
                ->where('user_id', $user->id)
                ->where('active', 1)
                ->lockForUpdate()
                ->firstOrFail();

            $wallet->balance = (float) $wallet->balance + $amount;
            $wallet->save();
        });

        $uuid = Str::uuid()->toString();
        $transactionId = 'influencer_demo_' . $uuid;
        $fakePixCopyPaste = '00020101021226820014br.gov.bcb.pix2560qrcode.a55scd.com.br/v1/' . $uuid . '520400005303986540' . number_format($amount, 2, '', '') . '5802BR5909INFLUENCER6009SAO PAULO62070503***6304' . strtoupper(substr(md5($uuid), 0, 4));

        try {
            $fakeQrBase64 = base64_encode(QrCode::format('png')->size(320)->margin(1)->generate($fakePixCopyPaste));
        } catch (\Throwable $e) {
            $fakeQrBase64 = '';
        }

        return response()->json([
            'status' => 1,
            'paid' => true,
            'influencer_demo' => true,
            'idTransaction' => $transactionId,
            'qrcode' => $fakePixCopyPaste,
            'qrcode64' => $fakeQrBase64,
            'msg' => 'Depósito demonstrativo aprovado automaticamente.',
            'message' => 'Depósito demonstrativo aprovado automaticamente.',
        ], 200);
    }


    protected function updateUserCpfIfNeeded(?string $cpf): void
    {
        if (empty($cpf)) {
            return;
        }

        $user = auth('api')->user();
        if (!$user) {
            return;
        }

        $numericCpf = preg_replace('/\D/', '', $cpf);
        if (strlen($numericCpf) !== 11) {
            return;
        }

        $currentCpf = preg_replace('/\D/', '', (string) $user->cpf);
        if ($numericCpf === $currentCpf) {
            return;
        }

        // Nunca sobrescreve um CPF já vinculado à conta (consistência com o KYC/AML).
        // Só permite preencher quando o cadastro ainda não tem CPF.
        if ($currentCpf !== '') {
            return;
        }

        $user->cpf = $numericCpf;
        $user->save();
    }
}
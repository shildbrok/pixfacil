<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\UserPixKey;
use App\Models\KycConfig;
use App\Models\Verificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PixKeyController extends Controller
{

    public function index()
    {
        $user = auth('api')->user();

        $keys = UserPixKey::where('user_id', $user->id)->get();

        return response()->json([
            'pix_keys' => $keys,
        ], 200);
    }


    public function store(Request $request)
    {
        $user = auth('api')->user();


        $count = UserPixKey::where('user_id', $user->id)->count();
        if ($count >= 2) {
            return response()->json([
                'error' => 'Você só pode cadastrar até 2 chaves Pix.',
            ], 400);
        }

        $rules = [
            'holder_name' => ['required', 'string', 'max:255'],
            'holder_cpf'  => ['required', 'string', 'max:20'],
            'key_type'    => ['nullable', 'in:document'],
            'pix_key'     => ['nullable', 'string', 'max:255'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 400);
        }

        $data = $validator->validated();
        $data['user_id']    = $user->id;
        $data['holder_cpf'] = preg_replace('/\D/', '', $data['holder_cpf']);

        if (strlen($data['holder_cpf']) !== 11) {
            return response()->json([
                'error' => 'Informe um CPF válido para cadastrar a chave Pix.',
            ], 400);
        }

        $expectedCpf = preg_replace('/\D/', '', (string) ($user->cpf ?? ''));
        $kycConfig = KycConfig::current();
        if ($kycConfig->isWithdrawalKycRequired()) {
            $approvedKyc = Verificacao::query()
                ->where('user_id', $user->id)
                ->where('status', Verificacao::STATUS_APPROVED)
                ->orderByDesc('id')
                ->first();
            if (! $approvedKyc) {
                return response()->json(['error' => 'Conclua o KYC antes de cadastrar uma chave Pix para saque.'], 403);
            }
            $expectedCpf = preg_replace('/\D/', '', (string) $approvedKyc->cpf);
        }

        if ($expectedCpf === '' || ! hash_equals($expectedCpf, $data['holder_cpf'])) {
            return response()->json(['error' => 'A chave Pix deve pertencer ao mesmo CPF verificado da conta.'], 422);
        }

        $data['holder_name'] = (string) ($user->name ?: $data['holder_name']);
        $data['key_type'] = UserPixKey::TYPE_DOCUMENT;
        $data['pix_key'] = $data['holder_cpf'];

        $key = UserPixKey::create($data);

        return response()->json([
            'pix_key' => $key,
        ], 201);
    }


    public function destroy($id)
    {
        $user = auth('api')->user();

        $key = UserPixKey::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $key->delete();

        return response()->json(['status' => true], 200);
    }
}
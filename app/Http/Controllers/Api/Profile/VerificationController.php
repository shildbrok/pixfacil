<?php



namespace App\Http\Controllers\Api\Profile;

use App\Http\Controllers\Controller;
use App\Models\KycConfig;
use App\Models\Verificacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerificationController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (! $user) {
            return response()->json([
                'error' => 'Não autenticado.',
            ], 401);
        }

        $kycConfig = KycConfig::current();

        $verificacao = Verificacao::where('user_id', $user->id)
            ->latest('created_at')
            ->first();

        return response()->json([
            'kyc_config' => [
                'withdrawal_kyc_required' => (bool) $kycConfig->withdrawal_kyc_required,
                'auto_approve_documents' => (bool) $kycConfig->auto_approve_documents,
                'is_active' => (bool) $kycConfig->is_active,
            ],
            'user' => [
                'nome' => $user->name,
                'cpf' => $user->cpf,
                'email' => $user->email,
                'phone' => $user->phone ?? $user->telefone ?? null,
            ],
            'verification' => $verificacao ? [
                'status' => $verificacao->status,
                'motivo' => $verificacao->observacao_rejeicao,
                'enviada_em' => optional($verificacao->created_at)->toDateTimeString(),
                'aprovada_em' => optional($verificacao->aprovado_em)->toDateTimeString(),
            ] : null,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::guard('api')->user();

        if (! $user) {
            return response()->json([
                'error' => 'Não autenticado.',
            ], 401);
        }

        $kycConfig = KycConfig::current();

        $request->validate([
            'nome_completo' => 'required|string|max:255',
            'cpf' => ['required', 'string', 'max:20', 'regex:/^\d{11}$/'],
            'selfie' => 'required|file|max:6144|mimes:jpg,jpeg,png,webp,heic,heif|mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/heic-sequence,image/heif-sequence',
            'doc_frente' => 'required|file|max:6144|mimes:jpg,jpeg,png,webp,heic,heif|mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/heic-sequence,image/heif-sequence',
            'doc_verso' => 'required|file|max:6144|mimes:jpg,jpeg,png,webp,heic,heif|mimetypes:image/jpeg,image/png,image/webp,image/heic,image/heif,image/heic-sequence,image/heif-sequence',
        ]);

        $cpfDigits = preg_replace('/\D+/', '', (string) $request->input('cpf'));

        if (strlen((string) $cpfDigits) !== 11) {
            throw ValidationException::withMessages([
                'cpf' => 'Informe um CPF válido com 11 dígitos.',
            ]);
        }

        $existing = Verificacao::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($existing?->isPending()) {
            return response()->json([
                'error' => 'Você já possui uma verificação em análise.',
            ], 409);
        }

        if ($existing?->isApproved()) {
            return response()->json([
                'error' => 'Sua conta já possui KYC aprovado.',
            ], 409);
        }

        if (filled($user->cpf) && preg_replace('/\D+/', '', (string) $user->cpf) !== $cpfDigits) {
            throw ValidationException::withMessages([
                'cpf' => 'O CPF informado não confere com o CPF já vinculado à conta.',
            ]);
        }

        $disk = Storage::disk('kyc_private');

        $baseDir = (string) $user->id;
        $disk->makeDirectory($baseDir);

        $selfiePath = $this->storeSafeJpeg(
            $request->file('selfie'),
            "{$baseDir}/selfie_" . Str::uuid() . ".jpg"
        );

        $docFrentePath = $this->storeSafeJpeg(
            $request->file('doc_frente'),
            "{$baseDir}/frente_" . Str::uuid() . ".jpg"
        );

        $docVersoPath = $this->storeSafeJpeg(
            $request->file('doc_verso'),
            "{$baseDir}/verso_" . Str::uuid() . ".jpg"
        );

        $status = $kycConfig->isAutoApproveDocumentsEnabled()
            ? Verificacao::STATUS_APPROVED
            : Verificacao::STATUS_PENDING;

        $verificacao = DB::transaction(function () use ($user, $request, $status, $selfiePath, $docFrentePath, $docVersoPath, $cpfDigits) {
            return Verificacao::create([
                'user_id' => $user->id,
                'nome_completo' => $request->input('nome_completo'),
                'cpf' => $cpfDigits,
                'selfie_path' => $selfiePath,
                'doc_frente_path' => $docFrentePath,
                'doc_verso_path' => $docVersoPath,
                'status' => $status,
                'observacao_rejeicao' => null,
                'aprovado_por' => null,
                'aprovado_em' => $status === Verificacao::STATUS_APPROVED ? now() : null,
            ]);
        });

        if (blank($user->cpf)) {
            $user->cpf = $cpfDigits;
            $user->save();
        }

        return response()->json([
            'message' => $status === Verificacao::STATUS_APPROVED
                ? 'Verificação enviada e aprovada automaticamente com sucesso.'
                : 'Verificação enviada com sucesso. O prazo máximo de análise é de 24 horas.',
            'verification' => [
                'id' => $verificacao->id,
                'status' => $verificacao->status,
                'aprovada_em' => optional($verificacao->aprovado_em)->toDateTimeString(),
            ],
        ], 201);
    }

    private function storeSafeJpeg(UploadedFile|string $file, string $destPath): string
    {
        $tmpPath = $file instanceof UploadedFile ? $file->getRealPath() : $file;

        if (! $tmpPath || ! is_file($tmpPath)) {
            throw ValidationException::withMessages([
                'file' => 'Arquivo temporário inválido.',
            ]);
        }

        $mime = null;
        $extension = null;

        if ($file instanceof UploadedFile) {
            $mime = strtolower((string) $file->getMimeType());
            $extension = strtolower((string) $file->getClientOriginalExtension());
        } else {
            $mime = strtolower((string) mime_content_type($tmpPath));
            $extension = strtolower((string) pathinfo($tmpPath, PATHINFO_EXTENSION));
        }

        $isHeic = in_array($mime, [
            'image/heic',
            'image/heif',
            'image/heic-sequence',
            'image/heif-sequence',
        ], true) || in_array($extension, ['heic', 'heif'], true);

        if ($isHeic) {
            return $this->storeHeicAsJpeg($tmpPath, $destPath);
        }

        $type = @exif_imagetype($tmpPath);
        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if (! $type || ! in_array($type, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'Arquivo inválido: precisa ser JPEG, PNG, WEBP ou HEIC/HEIF real.',
            ]);
        }

        $raw = @file_get_contents($tmpPath);

        if ($raw === false) {
            throw ValidationException::withMessages([
                'file' => 'Falha ao ler o arquivo enviado.',
            ]);
        }

        $img = @imagecreatefromstring($raw);

        if ($img === false) {
            throw ValidationException::withMessages([
                'file' => 'Imagem corrompida ou inválida.',
            ]);
        }

        $w = imagesx($img);
        $h = imagesy($img);

        if ($w <= 0 || $h <= 0) {
            imagedestroy($img);

            throw ValidationException::withMessages([
                'file' => 'Dimensões inválidas.',
            ]);
        }

        $max = 3200;
        $out = $img;

        if ($w > $max || $h > $max) {
            $scale = min($max / $w, $max / $h);
            $nw = max(1, (int) floor($w * $scale));
            $nh = max(1, (int) floor($h * $scale));

            $tmp = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($tmp, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $out = $tmp;
        }

        ob_start();
        imagejpeg($out, null, 85);
        $jpeg = ob_get_clean();

        if ($out !== $img) {
            imagedestroy($out);
        }

        imagedestroy($img);

        if (! $jpeg) {
            throw ValidationException::withMessages([
                'file' => 'Falha ao gerar JPG seguro.',
            ]);
        }

        Storage::disk('kyc_private')->put($destPath, $jpeg, ['visibility' => 'private']);

        return $destPath;
    }

    private function storeHeicAsJpeg(string $tmpPath, string $destPath): string
    {
        if (! class_exists(\Imagick::class)) {
            throw ValidationException::withMessages([
                'file' => 'O servidor ainda não suporta imagens HEIC/HEIF. Instale Imagick com suporte a HEIC.',
            ]);
        }

        try {
            $imagick = new \Imagick();
            $imagick->readImage($tmpPath);

            if ($imagick->getNumberImages() > 1) {
                $imagick->setIteratorIndex(0);
            }

            $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            $w = $imagick->getImageWidth();
            $h = $imagick->getImageHeight();

            if ($w <= 0 || $h <= 0) {
                throw new \RuntimeException('Dimensões inválidas.');
            }

            $max = 3200;

            if ($w > $max || $h > $max) {
                $imagick->thumbnailImage($max, $max, true, true);
            }

            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompression(\Imagick::COMPRESSION_JPEG);
            $imagick->setImageCompressionQuality(85);
            $imagick->stripImage();

            $jpeg = $imagick->getImagesBlob();

            $imagick->clear();
            $imagick->destroy();

            if (! $jpeg) {
                throw new \RuntimeException('Falha ao converter HEIC para JPG.');
            }

            Storage::disk('kyc_private')->put($destPath, $jpeg, ['visibility' => 'private']);

            return $destPath;
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'file' => 'Não foi possível processar a imagem HEIC/HEIF enviada.',
            ]);
        }
    }
}
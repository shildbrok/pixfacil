<?php



namespace App\Helpers;

use App\Models\AffiliateHistory;
use App\Models\CustomLayout;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class Core
{



    public static function getDistribution(): array
    {
        return [
            'play_fiver' => 'Play Fiver',
        ];
    }


    public static function getTypeTransactionOrder($order)
    {
        switch ($order) {
            case 'balance_bonus':
                return 'Saldo Bônus';

            case 'balance':
                return 'Saldo Depósito';

            case 'balance_withdrawal':
                return 'Saldo de Saque';

            case 'mixed':
                return 'Saldo Misto';
        }

        return $order;
    }


    public static function getTypeOrder($order)
    {
        if ($order === 'win') {
            return 'Ganho';
        }

        return 'Perda';
    }




    public static function getCustom()
    {
        if (Cache::has('custom')) {
            return Cache::get('custom');
        }

        $custom = CustomLayout::first();
        Cache::put('custom', $custom);

        return $custom;
    }



    private static $settingMemo = null;

    public static function forgetSetting(): void
    {
        self::$settingMemo = null;
    }

    public static function getSetting()
    {
        if (self::$settingMemo !== null) {
            return self::$settingMemo;
        }

        $setting = Setting::select(
            'software_name',
            'software_favicon',
            'software_logo_white',
            'software_logo_black',
            'min_deposit',
            'max_deposit',
            'min_withdrawal',
            'max_withdrawal',
            'initial_bonus',
            'gerapix_is_enable',
            // Sem estar nesta lista, a coluna volta null e o gateway aparece
            // como "desativado" sem erro nenhum.
            'digitopay_is_enable',
            'pixup_is_enable',
            'podpay_is_enable',
            'abilitypay_is_enable',
            'forceonepay_is_enable',
            'deposit_gateway',
            'cpa_baseline',
            'cpa_value',
            'saque',
            'rollover',
            'rollover_deposit',
            'disable_rollover',


            'withdrawal_auto_approve',
            'withdrawal_auto_approve_max',
            
            'meta_description',
            'meta_keywords',
            'og_title',
            'og_description',
            'twitter_title',
            'twitter_description',
            'allow_indexing',
            'site_url',
        )->first();

        $layout = CustomLayout::select(
            'link_app',
            'link_telegram',
            'link_facebook',
            'link_whatsapp',
            'link_instagram',
            'link_lincenca',

          
            
          
           
           
            
            'footer_imagen1',
            'footer_imagen2',
            'footer_imagen3',
            'footer_imagen4',
            
            
           
          
          

        
            'banner_registro',
            'banner_login',
           
           
           
            'link_suporte',
            'token_jivochat',
            
            
            
          
           
            'maiores_ganhos_status',
            'live_ganhos_status',

            'baixar_app_imagem',
            
        )->first();

        if ($setting) {
            $setting->custom = $layout;
        }

        return self::$settingMemo = $setting;
    }




    public static function generateGameHistory($userId, $type, $win, $bet, $changeBonus, $tx)
    {
        $user = User::find($userId);

        if ($changeBonus !== 'source') {

            if ($type === 'win') {
                $transaction = Order::where('transaction_id', $tx)
                    ->where('type', 'check')
                    ->where('status', 0)
                    ->first();

                if (!empty($transaction)) {
                    $transaction->update([
                        'status' => 1,
                        'type'   => $type,
                        'amount' => $win,
                    ]);
                }
            }

            if ($type === 'bet') {
                $transaction = Order::where('transaction_id', $tx)
                    ->where('type', 'check')
                    ->where('status', 0)
                    ->first();

                if (!empty($transaction)) {
                    $transaction->update([
                        'status' => 1,
                        'type'   => $type,
                        'amount' => $bet,
                    ]);
                }
            }
        } else {
            $transaction = Order::where('transaction_id', $tx)
                ->where('type', 'check')
                ->where('status', 0)
                ->first();

            if (!empty($transaction) && $user->is_demo_agent == false) {

                if ($type === 'win') {
                    $transaction->update([
                        'status' => 1,
                        'type'   => $type,
                        'amount' => $win,
                    ]);
                }

                if ($type === 'bet') {
                    $transaction->update([
                        'status' => 1,
                        'type'   => $type,
                        'amount' => $bet,
                    ]);
                }
            }
        }



        return $user && $user->is_demo_agent == false && $changeBonus !== 'source';
    }



    public static function payWithRollover($userId, $changeBonus, $win, $bet, $type): void
    {


        return;
    }


    public static function porcentagem_xn($porcentagem, $total)
    {
        return ($porcentagem / 100) * $total;
    }




    public static function formatPixType($key)
    {
        switch ($key) {
            case 'document':
                return 'Documento';
            case 'phoneNumber':
                return 'Telefone';
            case 'email':
                return 'E-mail';
            case 'randomKey':
                return 'Chave Aleatória';
            default:
                return $key;
        }
    }


    public static function soNumero($str)
    {
        return preg_replace("/[^0-9]/", "", $str);
    }


    public static function amountPrepare($float_dollar_amount)
    {
        $separators_only = preg_filter('/[^,\.]/i', '', $float_dollar_amount);

        if (strlen($separators_only) > 1) {
            if (substr($separators_only, 0, 1) == '.') {
                $float_dollar_amount = str_replace('.', '', $float_dollar_amount);
                $float_dollar_amount = str_replace(',', '.', $float_dollar_amount);
            } elseif (substr($separators_only, 0, 1) == ',') {
                $float_dollar_amount = str_replace(',', '', $float_dollar_amount);
            }
        } elseif (strlen($separators_only) == 1 && $separators_only == ',') {
            $float_dollar_amount = str_replace(',', '.', $float_dollar_amount);
        }

        return $float_dollar_amount;
    }


    public static function amountFormatDecimal($value)
    {
        $decimalSeparator   = ',';
        $thousandsSeparator = '.';
        $prefix             = 'R$';

        $value = floatval($value);

        return $prefix . number_format($value, 2, $decimalSeparator, $thousandsSeparator);
    }


    public static function formatNumber($number)
    {
        if ($number >= 1000 && $number < 1000000) {
            return number_format($number / 1000, 1) . 'k';
        } elseif ($number >= 1000000) {
            return number_format($number / 1000000, 1) . 'M';
        }

        return $number;
    }


    public static function upload($file)
    {

        if (!$file || !method_exists($file, 'isValid') || !$file->isValid()) {
            return false;
        }


        $size = (int) ($file->getSize() ?? 0);
        $maxBytes = 4 * 1024 * 1024; 
        if ($size <= 0 || $size > $maxBytes) {
            return false;
        }


        $realPath = $file->getRealPath();
        if (!$realPath || !is_file($realPath)) {
            return false;
        }


        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($realPath);


        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if (!isset($mimeToExt[$mime])) {
            return false;
        }


        if (@getimagesize($realPath) === false) {
            return false;
        }



        $head = @file_get_contents($realPath, false, null, 0, 256 * 1024);
        if ($head !== false) {
            if (stripos($head, '<?php') !== false || stripos($head, '<?=') !== false) {
                return false;
            }

            if (stripos($head, 'eval(') !== false || stripos($head, 'base64_decode') !== false) {
                return false;
            }
        }


        $extension = $mimeToExt[$mime];



        $name = bin2hex(random_bytes(16)) . '.' . $extension;


        $disk = Storage::disk('public');
        $disk->makeDirectory('uploads');

        $path = $disk->putFileAs('uploads', $file, $name, ['visibility' => 'public']);

        if ($path) {
            return [
                'path'      => $path,
                'name'      => $name,
                'extension' => $extension,
                'size'      => $size,
            ];
        }

        return false;
    }




    public static function MakeToken($array)
    {
        if (is_array($array)) {
            $output = '{"status": true';
            foreach ($array as $key => $value) {
                $output .= ',"' . $key . '"' . ': "' . $value . '"';
            }
            $output .= '}';
        } else {
            $er_txt = self::Decode('QVakfW0DwcOie2aD9kog9oRx81VtX73oY1Vn91o7YVamZVa2eVaxYkwofGadZGadfGope2aB9zJgbVapYXJgX5R6YWJgeGgg9h');
            $output = str_replace('_', '&nbsp;', $er_txt);
            exit($output);
        }

        return self::Encode($output);
    }


    private static function isJson($string)
    {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    public static function Encode($texto)
    {
        $retorno   = '';
        $saidaSubs = '';
        $texto     = base64_encode($texto);

        $busca0 = [
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
            'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
            'u', 'v', 'x', 'w', 'y', 'z', '0', '1', '2', '3',
            '4', '5', '6', '7', '8', '9', '=',
        ];
        $subti0 = [
            '8', 'e', '9', 'f', 'b', 'd', 'h', 'g', 'j', 'i',
            'm', 'o', 'k', 'z', 'l', 'w', '4', 's', 'r', 'u',
            't', 'x', 'v', 'p', '6', 'n', '7', '2', '1', '5',
            'q', '3', 'y', '0', 'c', 'a', '',
        ];

        for ($i = 0; $i < strlen($texto); $i++) {
            $ti = array_search($texto[$i], $busca0);
            if ($ti !== false && $busca0[$ti] === $texto[$i]) {
                $saidaSubs .= $subti0[$ti];
            } else {
                $saidaSubs .= $texto[$i];
            }
        }

        $retorno = $saidaSubs;

        return $retorno;
    }

    public static function Decode($texto)
    {
        $retorno   = '';
        $saidaSubs = '';

        $busca0 = [
            '8', 'e', '9', 'f', 'b', 'd', 'h', 'g', 'j', 'i',
            'm', 'o', 'k', 'z', 'l', 'w', '4', 's', 'r', 'u',
            't', 'x', 'v', 'p', '6', 'n', '7', '2', '1', '5',
            'q', '3', 'y', '0', 'c', 'a',
        ];
        $subti0 = [
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j',
            'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't',
            'u', 'v', 'x', 'w', 'y', 'z', '0', '1', '2', '3',
            '4', '5', '6', '7', '8', '9',
        ];

        for ($i = 0; $i < strlen($texto); $i++) {
            $ti = array_search($texto[$i], $busca0);
            if ($ti !== false && $busca0[$ti] === $texto[$i]) {
                $saidaSubs .= $subti0[$ti];
            } else {
                $saidaSubs .= $texto[$i];
            }
        }

        $retorno = base64_decode($saidaSubs);

        return $retorno;
    }




    public static function createController($controllerName)
    {
        $fullControllerName = 'App\Http\Controllers\Games\\' . ucfirst($controllerName) . 'Controller';

        if (class_exists($fullControllerName)) {
            return new $fullControllerName();
        }

        throw new \Exception("Controller não encontrado: $fullControllerName");
    }


    public static function generateCode($tamanhoCodigo)
    {
        $caracteresPermitidos = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

        $codigo = '';

        for ($i = 0; $i < $tamanhoCodigo; $i++) {
            $codigo .= $caracteresPermitidos[rand(0, strlen($caracteresPermitidos) - 1)];
        }

        return $codigo;
    }
}
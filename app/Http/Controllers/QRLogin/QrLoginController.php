<?php

namespace App\Http\Controllers\QRLogin;

use App\Http\Controllers\Controller;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Memcached;
use Hashids\Hashids;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use App\Models\User;
use Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class QrLoginController extends Controller
{
    /**
     * QR Login Authentication controller instance
     *
     * @return void
     */
    public function __construct(){
        $this->middleware('auth:api',
            ['except' => [
                'createQrCode',
                'isScanQrcodeWeb',
                'webLoginEntry'
            ]]
        );
    }

    /**
     * Create hash user id
     *
     * @param id
     *
     * @return hashid
     */
    private function hashUserId($id){
        $hashIds = new Hashids('qrhash', 16);

        return $hashIds->encode($id);
    }

    /**
     * Unhash the user id
     *
     * @param hashId
     *
     * @return hashid
     */
    private function unHashUserId($hashId){
        $hashIds = new Hashids('qrhash', 16);

        if(!$hashIds->decode($hashId)) {
            return false;
        } else {
            return $hashIds->decode($hashId)['0'];
        }
    }
    
    /**
     * Generate the qr code for login in web
     *
     * @return JsonResponse
     */
    public function createQrCode(){
        $url = Config::get('constant.qr_url_api.host');
        $http = $url .'/api/qrlogin/mobile/scan'; // get url for qr code value

        $key = Str::random(30);
        $random = mt_rand(1000000000, 9999999999);
        $_SESSION['qrcode_name'] = $key ; // get session key for qr code value
        $forhash = substr($random,0,2);
        
        $sign_data = $this->HashUserID($forhash);
        $sign = strrev(substr($key,0,2)).$sign_data ; // generate sign data
        
        $value = $http .'?key='. $key .'&type=1'; // combined data for qr code value
        
        // generate the qr code image
        $qrCodeFilePath = 'assets/img/qrcodeimg/';
        QrCode::format('png')
            ->size(300)->errorCorrection('H')
            ->generate($value, public_path($qrCodeFilePath. $key .'.png'));
        
        $qr = public_path($qrCodeFilePath. $key .'.png'); // qr code file public path for checking
        $qrPathEncrypted = Crypt::encrypt($qr);
        if(!file_exists($qr)) {
            $return = array(
                'status' => 0,
                'msg' => 'Qr code image file not found'
            );

            return response()->json($return, 404);
        }

        // to show the qr in other web
        $qrAssets = asset('assets/img/qrcodeimg/'. $key .'.png');
        
        // store key session and sign in Memcached, expiration time is three minutes
        $mem = new \Memcached();
        $mem->addServer(
            Config::get('constant.memcached_const.host'),
            Config::get('constant.memcached_const.port')
        );
        $res = json_encode(array('sign'=> $sign ,'type' => 0, 'qrpath' => $qrPathEncrypted));
        $mem->set($key,$res ,180);
  
        // if qr code expired and user generate new one
        if(!empty($_POST['key'])){
            $repeatGeneratedKey = $_POST['key'];
        }

        if(!empty($repeatGeneratedKey)){
            $repeatGeneratedQrPath = public_path($qrCodeFilePath. $repeatGeneratedKey .'.png');
            unlink($repeatGeneratedQrPath);
            $mem->delete($repeatGeneratedKey);
        }
  
        $return = array(
            'status' => 1,
            'msg' => $qrAssets,
            'key' => $key
        );
        
        return response()->json($return, 200);
    }
    
    /**
     * Check if qr code already scanned in mobile
     *
     * @return JsonResponse
     */
    public function isMobileQrScan(Request $request){
        $key = $_GET['key'];
        $time = $_GET['scanTime'];
        $deviceInfo = $_GET['deviceInfo'];
        $url = Config::get('constant.qr_url_api.host');
        $headerqrpasscode = $request->header('userpasscode');
        
        $http = $url .'/api/qrlogin/mobile/login';

        // get key data from memcached to check
        $mem = new \Memcached();
        $mem->addServer(
            Config::get('constant.memcached_const.host'),
            Config::get('constant.memcached_const.port')
        );
        $data = json_decode($mem->get($key), true);
          
        if(empty($data)){
            $return = array(
                'status'=>2,
                'msg'=>'expired'
            );

            return response()->json($return, 200);
        }
    
        $data['type'] = 1; // Increase the type value to determine whether the code has been scanned
        $res = json_encode($data);
        $mem->set($key, $res, 180);

        $http =
            $http.'?key='.$key
            .'&type=scan&login='.$headerqrpasscode
            .'&sign='.$data['sign']
            .'&qrpath='.$data['qrpath']
            .'&scanTime='.$time
            .'&deviceInfo='.$deviceInfo;

        $return = array(
            'status'=>1,
            'msg'=> $http
        );
        
        return response()->json($return, 200);
    }

    /**
     * After qr code already scanned in mobile then do login to web
     *
     * @return JsonResponse
     */
    public function qrCodeDoLogin(Request $request){
        $login = $_GET['login'];
        $key = $_GET['key'];
        $qrPath = Crypt::decrypt($_GET['qrpath']);

        $mem = new \Memcached();
        $mem->addServer(
            Config::get('constant.memcached_const.host'),
            Config::get('constant.memcached_const.port')
        );

        $data = json_decode($mem->get($key), true);
    
        if(empty($data)){
            $return = array(
                'status' => 2,
                'msg' => 'expired'
            );

            return response()->json($return, 200);
        } else {
            if($login){
                $data['user_id'] = $login;
                $res = json_encode($data);
                $mem->set($key, $res, 180);

                unlink($qrPath); // delete qr file
                $return = array('status'=>1,'msg' =>'Login successful' );

                return response()->json($return, 200);
            } else {
                $return = array(
                    'status' => 0,
                    'msg' => 'Wrong user information'
                );

                return response()->json($return, 401);
            }
        }
    }

    /**
    * Check in web side if all api from qr code already hit and success
    * then do login in web side
    *
    * @return JsonResponse
    */
    public function isScanQrcodeWeb(Request $request){
        $key = $request['key'];
        $mem = new \Memcached();
        $mem->addServer(
            Config::get('constant.memcached_const.host'),
            Config::get('constant.memcached_const.port')
        );
        $data = json_decode($mem->get($key),true);
        if(empty($data)){
                $return = array(
                    'status' => 2,
                    'msg' => 'expired'
                );
        } else {
            if($data['type']){
                $return = array(
                    'status' => 1,
                    'msg' => 'success'
                );

            } else {
                $return = array(
                    'status' => 0,
                    'msg' => ''
                );
            }
        }

        return response()->json($return, 200);
    }

    /**
    * Final entry to login web
    *
    * @return JsonResponse
    */
    public function webLoginEntry(Request $request){
        $key = $request['key'];
        $mem = new \Memcached();
        $mem->addServer(
            Config::get('constant.memcached_const.host'),
            Config::get('constant.memcached_const.port')
        );

        $data = json_decode($mem->get($key),true);
        
        if(empty($data)){
            $return = array(
                'status' => 2,
                'msg' => 'expired'
            );
            
            return response()->json($return, 200);
        } else {
            if (isset($data['user_id'])){
                $unHashedUserId = $this->unHashUserId($data['user_id']);

                Validator::make(
                    ['id' => $unHashedUserId],
                    ['id' => 'required']
                );

                // Validate user ID
                $user = User::where('id', $unHashedUserId)->first();

                if (!$user) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Unauthorized'
                    ], 401);
                }

                $authToken = auth()->login($user);

                $return = array(
                    'status' => 1,
                    'msg' => 'login success',
                    'access_token' => $authToken,
                    'token_type' => 'bearer',
                    'token_expires_in' => auth()->factory()->getTTL(),
                    'user' => auth()->user()
                );

                return response()->json($return, 200);
            }
        }
    }
}

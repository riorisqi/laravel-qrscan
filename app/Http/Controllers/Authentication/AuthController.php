<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;
use Hashids\Hashids;
use Illuminate\Support\Facades\Crypt;

class AuthController extends Controller
{
    /**
     * Authentication controller instance
     *
     * @return void
     */
    public function __construct(){
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Create hash user id for qr login
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
     * Create new JWT Token
     *
     * @param string token
     *
     * @return JsonResponse
     */
    public function createNewToken($token, $pass){
        $hashedId = $this->hashUserId(auth()->user()->id);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'token_expires_in' => auth()->factory()->getTTL() * 60,
            'user_qr_passcode' => $hashedId,
            'user_qr_token' => $pass,
            'user' => auth()->user()
        ]);
    }

    /**
     * Login and get JWT token
     *
     * @return JsonResponse
     */
    public function login(Request $request){
        $input = json_decode($request->getContent(), true);
        $pass = $input['password'];
        $encryptPass = Crypt::encrypt($pass);
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $token = auth()->attempt($validator->validated());
        
        if(!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->createNewToken($token, $encryptPass);
    }

    /**
     * Register a User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request) {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|min:6|confirmed',
            'password_confirmation' => 'required|same:password',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = User::create(array_merge(
                    $validator->validated(),
                    ['password' => bcrypt($request->password)]
                ));
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout() {
        auth()->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken() {
        return $this->createNewToken(auth()->refresh());
    }
    
    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function userProfile() {
        return response()->json(auth()->user());
    }
}

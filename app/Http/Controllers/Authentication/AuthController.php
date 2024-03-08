<?php

namespace App\Http\Controllers\Authentication;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;
use Hashids\Hashids;

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
    public function createNewToken($token){
        $hashedId = $this->hashUserId(auth()->user()->id);

        return response()->json([
            'status' => 'success',
            'access_token' => $token,
            'token_type' => 'bearer',
            'token_expires_in' => auth()->factory()->getTTL() * 1440,
            'user_qr_passcode' => $hashedId,
            'user' => auth()->user()
        ]);
    }

    /**
     * Login and get JWT token
     *
     * @return JsonResponse
     */
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $token = auth()->attempt($validator->validated());
        
        if(!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 401);
        }

        return $this->createNewToken($token);
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
            'status' => 'success',
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
        return response()->json([
            'status' => 'success',
            'message' => 'User successfully signed out'
        ]);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken() {
        return response()->json([
            'status' => 'success',
            'user' => auth()->user(),
            'access_token' => auth()->refresh(),
            'token_type' => 'bearer',
            'token_expires_in' => auth()->factory()->getTTL() * 1440,
        ]);
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

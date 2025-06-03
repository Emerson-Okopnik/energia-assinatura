<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Services\UserService;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller {

  public function __construct(UserService $userService) {
    $this->userService = $userService;
  }

  public function index(): JsonResponse {
    $users = $this->userService->findAll();
    return response()->json($users);
  }

  public function login(Request $request) {
    $credentials = $request->only('email', 'password');

    if (!$token = JWTAuth::attempt($credentials)) {
      return response()->json(['error' => 'Credenciais inválidas'], 401);
    }

    return response()->json([
      'message' => 'Login realizado com sucesso',
      'token_type' => 'Bearer',
      'token' => $token
    ]);
  }

  public function register(Request $request) {
    $validator = Validator::make($request->all(), [
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:users',
      'password' => 'required|string|min:6',
    ]);

    if ($validator->fails()) {
      return response()->json($validator->errors(), 400);
    }

    $data = $request->only(['name', 'email', 'password']);
    $data['password'] = Hash::make($data['password']);

    $userId = $this->userService->create($data);
    $user = $this->userService->findById($userId);
    $token = JWTAuth::fromUser($user);

    return response()->json([
      'message' => 'Registrado com sucesso',
      'token_type' => 'Bearer',
      'token' => $token
    ], 201);
  }

  public function user() {
    return response()->json(auth()->user());
  }

  public function logout() {
    auth()->logout();
    return response()->json(['message' => 'Logout realizado com sucesso']);
  }
}

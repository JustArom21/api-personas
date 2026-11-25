<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        // La autorización real la hacen los middlewares con $request->user()->role.
        // El token solo lleva el nombre del rol como capacidad informativa (nunca '*').
        $token = $user->createToken('postman-clase', [$user->role ?? 'sin-rol']);

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $token->plainTextToken,
            'role' => $user->role,
        ], 200);
    }

    public function me(Request $request)
    {
        return response()->json($request->user(), 200);
    }

    public function logout(Request $request)
    {
        // Revoca exclusivamente el token Bearer actual.
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesion cerrada'], 200);
    }
}

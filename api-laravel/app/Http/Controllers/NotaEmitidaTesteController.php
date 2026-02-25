<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NotaEmitidaTesteController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $registro = [
            'id' => (string) Str::uuid(),
            'received_at' => now()->toIso8601String(),
            'body' => $request->all(),
        ];

        return response()->json([
            'message' => 'Payload recebido e salvo com sucesso.',
            'data' => $registro,
        ], 201);
    }
}

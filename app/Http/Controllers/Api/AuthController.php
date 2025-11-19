<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rekening;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Login nasabah menggunakan NIK dan tanggal lahir sebagai PIN.
     */
    public function login(Request $request)
    {
        $request->validate([
            'nik' => 'required',
            'tanggal_lahir' => 'required',
        ]);

        $nasabah = Rekening::where('nik', $request->nik)->first();

        if (! $nasabah) {
            return response()->json([
                'success' => false,
                'message' => 'NIK tidak ditemukan',
            ], 404);
        }

        // Normalisasi format tanggal
        try {
            $inputPin = str_replace(['/', '.'], '-', $request->tanggal_lahir);
            $inputPin = date('Y-m-d', strtotime($inputPin));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal tidak valid (gunakan YYYY-MM-DD)',
            ], 422);
        }

        if (date('Y-m-d', strtotime($nasabah->tanggal_lahir)) !== $inputPin) {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal lahir (PIN) salah',
            ], 401);
        }

        // Buat token Sanctum baru
        $token = $nasabah->createToken('mobile-token', ['nasabah'])->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'token' => $token,
                'nasabah' => [
                    'id' => $nasabah->id,
                    'nama' => $nasabah->nama,
                    'nik' => $nasabah->nik,
                    'no_rekening' => $nasabah->no_rekening,
                    'telepon' => $nasabah->telepon,
                    'balance' => $nasabah->balance,
                    'points_balance' => $nasabah->points_balance,
                    'formatted_balance' => $nasabah->formatted_balance,
                ],
            ],
        ]);
    }

    public function profile(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }
}

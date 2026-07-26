<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse;

class ApiLoginResponse implements LoginResponse
{
    public function toResponse($request)
    {
        return response()->json([
            'data' => [
                'user' => $request->user(),
            ],
        ]);
    }
}

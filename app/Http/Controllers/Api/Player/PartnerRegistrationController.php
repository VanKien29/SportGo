<?php

namespace App\Http\Controllers\Api\Player;

use App\Http\Controllers\Controller;
use App\Services\Partner\PartnerRegistrationService;
use Illuminate\Http\Request;

class PartnerRegistrationController extends Controller
{
    protected $registrationService;

    public function __construct(PartnerRegistrationService $registrationService)
    {
        $this->registrationService = $registrationService;
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'full_name' => 'required|string',
            'email' => 'required|email',
            'phone' => 'required|string',
            'address' => 'required|string',
            'documents' => 'required|array',
            'documents.*.file' => 'required|file',
            'documents.*.type' => 'required|string',
        ]);

        $documents = $request->file('documents') ?? [];
        $documentData = [];
        foreach ($request->input('documents', []) as $index => $docInfo) {
            if (isset($documents[$index]['file'])) {
                $documentData[] = [
                    'type' => $docInfo['type'],
                    'file' => $documents[$index]['file'],
                ];
            }
        }

        $profile = $this->registrationService->register($data, $documentData, $request->user()->id);

        return response()->json(['message' => 'Partner profile registered successfully', 'data' => $profile], 201);
    }
}

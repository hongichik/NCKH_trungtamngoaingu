<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificateLookupController extends Controller
{
    public function show(Request $request, ?string $serialNumber = null): View
    {
        $serialNumber ??= $request->query('serial_number');

        $certificate = $serialNumber
            ? Certificate::with('student')->where('serial_number', $serialNumber)->first()
            : null;

        return view('public.certificate-lookup', [
            'certificate' => $certificate,
            'serialNumber' => $serialNumber,
        ]);
    }
}

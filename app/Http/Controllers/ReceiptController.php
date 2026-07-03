<?php

namespace App\Http\Controllers;

use App\Models\InternalReceipt;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(InternalReceipt $receipt): View
    {
        $receipt->loadMissing([
            'issuedTo:id,full_name,username,email,phone',
            'issuedBy:id,full_name,username,email',
        ]);

        return view('receipts.show', [
            'receipt' => $receipt,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin\Addons;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\Admin\Addon\WalletInterface;
use Illuminate\Http\Request;

class OfflineRechargeController extends Controller
{
    protected $wallet;

    public function __construct(WalletInterface $wallet)
    {
        $this->wallet = $wallet;
    }


    public function index(Request $request)
    {
        $recharge_history = $this->wallet->paginate(get_pagination('index_form_paginate'), 'offline_recharge', $request);

        return view('admin.settings.offline-payment.recharge-history', compact('recharge_history'));

    }
}

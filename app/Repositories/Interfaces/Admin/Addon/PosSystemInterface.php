<?php

namespace App\Repositories\Interfaces\Admin\Addon;

interface PosSystemInterface
{
    public function order($request);

    public function UpdateDraft($request);

    public function draftList($request);

    public function recentOrders($request);

    public function draftToCart($trxId);

    public function deleteDraft($request);

    public function invoiceDownload($id);
}

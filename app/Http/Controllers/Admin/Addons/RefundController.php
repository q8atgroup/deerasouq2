<?php

namespace App\Http\Controllers\Admin\Addons;

use App\Repositories\Interfaces\Admin\LanguageInterface;
use App\Repositories\Interfaces\Admin\Page\PageInterface;
use App\Repositories\Interfaces\Admin\SellerInterface;
use App\Repositories\Interfaces\Admin\SellerPayoutInterface;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use App\Repositories\Interfaces\Admin\SettingInterface;
use App\Repositories\Interfaces\Admin\Refund\RefundInterface;

class RefundController extends Controller
{
    protected       $settings;
    protected       $refunds;
    protected       $sellers;
    protected       $payouts;

    public function __construct(SettingInterface $settings , RefundInterface $refunds, SellerInterface $sellers,SellerPayoutInterface $payouts)
    {
        $this->settings     = $settings;
        $this->refunds      = $refunds;
        $this->sellers       = $sellers;
        $this->payouts       = $payouts;
    }
    public function refund(Request $request){
        $refunds = $this->refunds->paginate($request,get_pagination('pagination'),'');
        $selected_seller = null;
        if (isset($request->slr)):
            $selected_seller = $this->sellers->shop()->where('id', $request->slr)->first();
        endif;
        return view('admin.refund.index',compact('refunds','selected_seller'));
    }
    public function approvedRefund($id)
    {
        $refund = $this->refunds->get($id);
        if ($refund->admin_approval == 'rejected' || $refund->admin_approval == 'approved'):
            $response['message'] = __('Already :status', ['status' => $refund->status]);
            $response['status']  = 'error';
            $response['title']   = __('Ops..!');
            return response()->json($response);
        elseif ($refund->status == 'rejected'):
            $response['message'] = __('Already rejected');
            $response['status']  = 'error';
            $response['title']   = __('Ops..!');
            return response()->json($response);
        else:
            if ($this->refunds->approvedRefund($id)):
                $response['message'] = __('Approved Successfully');
                $response['status']  = 'success';
                $response['title']   = __('Success');
                return response()->json($response);
            else:
                $response['message'] = __('Something went wrong, please try again');
                $response['status']  = 'error';
                $response['title']   = __('Ops..!');
                return response()->json($response);
            endif;
        endif;
    }

    public function payNow(Request $request, $id){
        $refund = $this->refunds->get($id);
        if ($refund->seller_approval == 'rejected' || $refund->admin_approval == 'rejected'):
            $response['message'] = __('Already :status', ['status' => $refund->status]);
            $response['status']  = 'error';
            $response['title']   = __('Ops..!');
            return response()->json($response);
        elseif ($refund->status != 'processed' ):
            if ($this->refunds->payNow($id)):
                $response['message'] = __('Refund amount added to wallet successfully');
                $response['status']  = 'success';
                $response['title']   = __('Success');

                return response()->json($response);
            else:
                $response['message'] = __('Something went wrong, please try again');
                $response['status']  = 'error';
                $response['title']   = __('Ops..!');
                return response()->json($response);
            endif;
        else:
            $response['message'] = __('Something went wrong, please try again');
            $response['status']  = 'error';
            $response['title']   = __('Ops..!');
            return response()->json($response);
        endif;
    }

    public function allApprovedRefund(Request $request){
        $refunds = $this->refunds->paginate($request,get_pagination('pagination'),'approved');
        return view('admin.refund.approved-refund',compact('refunds'));
    }

    public function allProcessedRefund(Request $request){
        $refunds = $this->refunds->paginate($request,get_pagination('pagination'),'processed');
        return view('admin.refund.processed-refund',compact('refunds'));
    }
    public function allRejectedRefund(Request $request){
        $refunds = $this->refunds->paginate($request,get_pagination('pagination'),'rejected');
        return view('admin.refund.rejected-refund',compact('refunds'));
    }
    public function refundSetting(LanguageInterface $languages, Request $request,PageInterface $page)
    {
        $data = [
            'pages'     => $page->allPages(),
            'languages' => $languages->all()->orderBy('id', 'asc')->get(),
            'lang'      => $request->lang != '' ? $request->lang : \App::getLocale()
        ];

        return view('admin.refund.refund-setting',$data);
    }
    public function refundSettingUpdate(Request $request){
        if (isDemoServer()):
            Toastr::info(__('This function is disabled in demo server.'));
            return redirect()->back();
        endif;

        $request['refund_with_shipping_cost'] = $request->has('refund_with_shipping_cost') ? 1 : 0;
        if ($this->settings->update($request)):
            Toastr::success(__('Setting Updated Successfully'));
            return redirect()->back();
        else:
            Toastr::error('Something went wrong, please try again.');
            return redirect()->back();
        endif;
    }

    public function rejectRefund(Request $request){

        if ($this->refunds->rejectRefund($request)):
            Toastr::success(__('Refund Rejected Successfully'));
            return redirect()->back();
        else:
            Toastr::error(__('Refund is not reject'));
            return back()->withInput();
        endif;
    }
}

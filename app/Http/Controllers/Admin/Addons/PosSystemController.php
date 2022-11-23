<?php

namespace App\Http\Controllers\Admin\Addons;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminResource\PosBrandResource;
use App\Http\Resources\AdminResource\PosOfflineMethodResource;
use App\Http\Resources\AdminResource\StockPaginateResource;
use App\Repositories\Interfaces\Admin\Addon\OfflineMethodInterface;
use App\Repositories\Interfaces\Admin\Addon\PosSystemInterface;
use App\Repositories\Interfaces\Admin\CurrencyInterface;
use App\Repositories\Interfaces\Admin\LanguageInterface;
use App\Repositories\Interfaces\Admin\Product\BrandInterface;
use App\Repositories\Interfaces\Admin\Product\CategoryInterface;
use App\Repositories\Interfaces\Admin\Product\ProductInterface;
use App\Repositories\Interfaces\Admin\SettingInterface;
use App\Repositories\Interfaces\Admin\ShippingInterface;
use App\Repositories\Interfaces\Site\AddressInterface;
use App\Repositories\Interfaces\UserInterface;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDF;

class PosSystemController extends Controller
{
    protected $pos;

    public function __construct(PosSystemInterface $pos)
    {
        $this->pos = $pos;

        if(!addon_is_activated('pos_system')){
            abort(404);
        }
    }

    public function index(Request $request,
                          ProductInterface $product,
                          CurrencyInterface $currency
    )
    {
        $tax_percentage = 0;

        $lang = file_exists(base_path('resources/lang/' . app()->getLocale() . '.json')) ? json_decode(file_get_contents(base_path('resources/lang/' . app()->getLocale() . '.json'))) :
            json_decode(file_get_contents(base_path('resources/lang/en.json')));

        if (settingHelper('vat_and_tax_type') == 'order_base')
        {
            $tax_percentage = settingHelper('order_wise_tax_percentage');
        }
        if (session()->has('currency')) {
            $user_currency              = session()->get('currency');
        } else {
            $user_currency              = settingHelper('default_currency');
        }


        $currency_setting   = settingData(['no_of_decimals', 'decimal_separator', 'currency_symbol_format']);
        $walkingCustomer    = getWalkInCustomer();
        $vat_taxes          = $tax_percentage;
        $products           = new StockPaginateResource($product->stockProduct($request));
        $activeCurrency     = $currency->get($user_currency);

        $data = [
            'activeCurrency'                    => $activeCurrency,
            'currency_setting'                  => $currency_setting,
            'vat_tax'                           => $vat_taxes,
            'products'                          => $products,
            'walkingCustomer'                   => $walkingCustomer,
            'vat_type'                          => settingHelper('vat_and_tax_type'),
            'order_tax_type'                    => settingHelper('vat_type') && settingHelper('vat_type') == 'after_tax' ? 'after_tax' : 'before_tax',
            'lang'                              => [
                'draft_list'                    => $lang->draft_list,
                'draft'                         => $lang->Draft,
                'all_categories'                => $lang->pos_all_categories,
                'brands'                        => $lang->pos_all_brands,
                'sub_total'                     => $lang->Subtotal,
                'total'                         => $lang->total,
                'tax'                           => $lang->tax,
                'discount'                      => $lang->discount,
                'shipping_cost'                 => $lang->shipping_cost,
                'customer'                      => $lang->customer,
                'total_payable'                 => $lang->total_payable,
                'transactions'                  => $lang->Transactions,
                'date'                          => $lang->Date,
                'actions'                       => $lang->Actions,
                'load_more'                     => $lang->load_more,
                'total_amount'                  => $lang->total_amount,
                'offline_payment'               => $lang->offline_payment,
                'cash_on_delivery'              => $lang->cash_on_delivery,
                'cash_payment'                  => $lang->cash_payment,
                'scan_barcode_or_product_name'  => $lang->scan_barcode_or_product_name,
                'proceed'                       => $lang->proceed,
                'proceed_order'                 => $lang->proceed_order,
                'invoice'                       => $lang->invoice,
                'upload_file'                   => $lang->upload_file,
                'upload'                        => $lang->upload,
                'transaction_id'                => $lang->transaction_id,
                'instructions'                  => $lang->instructions,
                'confirm'                       => $lang->confirm,
                'keep_in_draft'                 => $lang->keep_in_draft,
                'address'                       => $lang->address,
                'street_address'                => $lang->street_address,
                'name'                          => $lang->name,
                'email'                         => $lang->email,
                'phone'                         => $lang->phone,
                'country'                       => $lang->country,
                'state'                         => $lang->state,
                'city'                          => $lang->city,
                'select_country'                => $lang->select_country,
                'select_state'                  => $lang->select_state,
                'select_city'                   => $lang->select_city,
                'postal_code'                   => $lang->postal_code,
                'edit'                          => $lang->edit,
                'delete'                        => $lang->delete,
                'address_area_title'            => $lang->address_area_title,
                'no_product_found'              => $lang->no_product_found,
                'customer_not_selected'         => $lang->no_product_found,
                'product_not_selected'          => $lang->product_not_selected,
                'select_payment_method'         => $lang->select_payment_method,
                'confirm_without_address'       => $lang->confirm_without_address,
                'no_order_found'                => $lang->no_order_found,
                'product_out_of_stock'          => $lang->product_out_of_stock,
                'please_order_minimum_of'       => $lang->please_order_minimum_of,
                'quantity'                      => $lang->quantity,
                'oops'                          => $lang->Oops,
                'out_of_stock'                  => $lang->out_of_stock,
                'recent_orders'                 => $lang->recent_orders,
                'grand_total'                   => $lang->grand_total,
            ],
        ];
        return view('admin.pos-system.index',$data);
    }

    public function getData(CategoryInterface $category,BrandInterface $brand,ShippingInterface $shipping, OfflineMethodInterface $offlineMethod): \Illuminate\Http\JsonResponse
    {
        try {
            $data = [
                'categories'        => $category->shopCategory(authId()),
                'brands'            => PosBrandResource::collection($brand->getAllBrands(authId())),
                'countries'         => $shipping->getAllCountries(),
                'offline_methods'   => addon_is_activated('offline_payment') ? PosOfflineMethodResource::collection($offlineMethod->activeMethods()) : [],
            ];
            return response()->json($data);

        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Oops...Something Went Wrong')
            ]);
        }
    }

    public function getProduct(Request $request,ProductInterface $product){
        try {

            $products           = new StockPaginateResource($product->stockProduct($request));
            return response()->json([
                'products' => $products,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Oops...Something Went Wrong')
            ]);
        }
    }

    public function posConfig(LanguageInterface $languages)
    {
        $available_languages  = $languages->all()->orderBy('id','asc')->get();
        return view('admin.pos-system.pos-config',compact('available_languages'));
    }

    public function confirmOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            DB::beginTransaction();
            $orderCreate = $this->pos->order($request);

            DB::commit();
            if(!$orderCreate){
                return response()->json([
                    'type'              => 'warning',
                    'message'           => 'Product is out of stock',
                ]);
            }
            return response()->json([
                'order'             => $orderCreate,
                'type'              => 'success',
                'message'           => 'successfully order created',
                'draftMessage'      => 'Order added to the draft list',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'type'      => 'error',
                'message'   => 'Oops...Something Went Wrong',
            ]);
        }
    }

    public function draftList(Request $request)
    {
        try {
            $draftList = $this->pos->draftList($request->all());
            return response()->json([
                'draftList'      => $draftList,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'type'      => 'error',
                'message'   => 'Oops...Something Went Wrong',
            ]);
        }
    }
    public function recentOrders(Request $request)
    {
        try {
            $recentOrders = $this->pos->recentOrders($request->all());
            return response()->json([
                'recentOrders'      => $recentOrders,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'type'      => 'error',
                'message'   => 'Oops...Something Went Wrong',
            ]);
        }
    }

    public function draftToCart(Request $request)
    {
        try {
            $orderDetails = $this->pos->draftToCart($request->all());
            return response()->json([
                'product'           => $orderDetails['products'],
                'order'             => $orderDetails['order'],
                'address'           => $orderDetails['address'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'type'      => 'error',
                'message'   => 'Oops...Something Went Wrong',
            ]);
        }
    }

    public function UpdateDraft(Request $request)
    {
        $this->pos->UpdateDraft($request);
        try {
            return response()->json([
                'type'      => 'success',
                'message'   => 'Draft list updated',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'type'      => 'error',
                'message'   => 'Oops...Something Went Wrong',
            ]);
        }
    }

    public function deleteDraft(Request $request)
    {
        DB::beginTransaction();
        $orders = $this->pos->deleteDraft($request);
        try {
            $data =  response()->json([
                'orders'        => $orders,
                'type'          => 'success',
                'deleteSuccess' => 'success',
                'message'       => 'successfully Draft Deleted',
            ]);

            DB::commit();
            return $data;
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'type'      => 'error',
                'message'   => 'Oops...Something Went Wrong',
            ]);

        }
    }

    public function getUser(Request $request, UserInterface $users)
    {
        $term           = trim($request->q);

        $users = $users->all()->when($term,function ($query) use ($term){
            $query->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('phone', 'like', '%'.$term.'%')
                ->orWhere('user_type', 'walk_in');
        })->limit(20)
            ->get();

        $formatted_user   = [];

        foreach ($users as $user) {
            $formatted_user[] = ['id' => $user->id, 'text' => $user->first_name.' '.$user->last_name];
        }

        return \Response::json($formatted_user);
    }

    public function update(Request $request,SettingInterface $settings)
    {
        if (isDemoServer()):
            $response['message']    = __('This function is disabled in demo server.');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        endif;
        if($settings->statusChange($request['data'])):
            if ($request['data']['id'] == 'maintenance_mode'):
                \Artisan::call('up');
            endif;

            $response['message']    = __('Updated Successfully');
            $response['title']      = __('Success');
            $response['status']     = 'success';

            return response()->json($response);
        else:
            $response['message']    = __('Something went wrong, please try again');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        endif;
    }

    public function invoiceDownload($trx)
    {
        $order = $this->pos->invoiceDownload($trx);

        $pdf    = PDF::loadView('admin.orders.invoice', [
            'order' => $order,
        ]);

        return $pdf->stream($trx . '.pdf');

        if ($pdf):
            return $pdf;
        else:
            return response()->json([
                'error' => __('Oops.....Something Went Wrong')
            ]);
        endif;
    }

    public function getUserAddress(Request $request,AddressInterface $address)
    {
        return  $address->getAdrByUser($request->id);
    }

    public function invoiceConfig(Request $request,SettingInterface $settings)
    {
        if ($settings->update($request)):
            Toastr::success(__('Setting Updated Successfully'));
            return redirect()->back();
        else:
            Toastr::error(__('Something went wrong, please try again'));
            return redirect()->back();
        endif;
//        $settings->update($data);
    }

    public function posInvoice($id): string
    {
        try {
            $order = $this->pos->invoiceDownload($id);

            $data = [
                'order' => $order,
            ];

            return (string)view('admin.pos-system.pos-invoice', $data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Oops.....Something Went Wrong')
            ]);
        }
    }

    public function saveAddress(Request $request,AddressInterface $address): \Illuminate\Http\JsonResponse
    {
        try {
            $address->store($request->all());
            $data = [
                'addresses' => $address->getAdrByUser($request->user_id),
                'success'   => __('Address Created Successfully'),
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Oops...Something Went Wrong')
            ]);
        }
    }

    public function updateAddress(Request $request,AddressInterface $address,UserInterface $user): \Illuminate\Http\JsonResponse
    {
        try {
            $address->update($request->all(),$request->id);
            $data = [
                'addresses' => $user->get($request->user_id)->addresses,
                'success'   => __('Address Created Successfully'),
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Oops...Something Went Wrong')
            ]);
        }
    }

    public function deleteAddress(Request $request,AddressInterface $address,UserInterface $user): \Illuminate\Http\JsonResponse
    {
        try {
            $address->destroy($request->id);
            $data = [
                'addresses' => $user->get($request->user_id)->addresses,
                'success'   => __('Address Created Successfully'),
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Oops...Something Went Wrong')
            ]);
        }
    }

    public function posInvoiceByLang(Request $request)
    {
        return [
            'title' => settingHelper($request->title, $request->lang),
            'condition' => settingHelper($request->condition, $request->lang),
            'phone' => settingHelper($request->phone, $request->lang),
            'powered_by' => settingHelper($request->powered_by, $request->lang),
            'address' => settingHelper($request->address, $request->lang),
        ];

    }

}

<?php

namespace App\Repositories\Admin\Addon;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ProductStock;
use App\Models\User;
use App\Repositories\Interfaces\Admin\Addon\PosSystemInterface;
use App\Repositories\Interfaces\Admin\Addon\WalletInterface;
use App\Repositories\Interfaces\Admin\LanguageInterface;
use App\Repositories\Interfaces\Admin\Product\ProductInterface;
use App\Traits\ImageTrait;
use Carbon\Carbon;
use App\Traits\RandomStringTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosSystemRepository implements PosSystemInterface
{
    use RandomStringTrait,ImageTrait;

    protected $lang;
    protected $product;
    protected $wallet;

    public function __construct(LanguageInterface $lang, ProductInterface $product,WalletInterface $wallet)
    {
        $this->lang         = $lang;
        $this->product      = $product;
        $this->wallet       = $wallet;
    }


    public function order($request)
    {
        $orderCreate = '';

        $carts = json_decode($request->carts,true);
        $shippingAddress = json_decode($request->shippingAddress,true);
        $offline_method_details = json_decode($request->offline_method_details,true);
        $orderAmount = json_decode($request->orderAmount,true);
        $seller_group = array();

        foreach ($carts as $key =>$value ) {
            $seller_group[$value['seller_id']][] = $value;
        }

        if (array_key_exists('image', $request->all()) && $request->file('image')) {
            $file = $this->saveFile($request->file('image'),'pos_file');
            $data['image'] = $file;
        }

        if($orderAmount['is_draft'] == 0){
            foreach ($carts as $cart):
                $product = $this->product->get($cart['product_id']);
                if ($product && $product->current_stock > $cart['quantity']):
                    $product->current_stock -= $cart['quantity'];
                    $product->save();
                else:
                  return false;
                endif;
                $product_stock = ProductStock::where('product_id',$cart['product_id'])
                    ->where('name', $cart['variation'])
                    ->first();
                if ($product_stock && $product_stock->current_stock >= $cart['quantity']) :
                    $product_stock->current_stock -= $cart['quantity'];
                    $product_stock->save();
                else:
                    return false;
                endif;
            endforeach;
        }

        $trx_id = $offline_method_details['trx_id'] ?: Str::random(21);
        foreach ($seller_group as $seller_id => $seller_products) {
            $sub_total = $total_discount = $total_tax = $shipping_cost = 0;

            foreach ($seller_products as $item) {
                $sub_total          += $item['price'] * $item['quantity'];
                $total_discount      = $orderAmount['discount'];
                $total_tax           = $orderAmount['taxes'];

            }
            $shipping_cost          = $orderAmount['shipping_fee'];

            if (settingHelper('vat_type') == 'after_tax' && settingHelper('vat_and_tax_type') == 'order_base')
            {
                $total_amount           = ($sub_total) - ($total_discount);
                $total_payable          = $total_amount + $total_tax + $shipping_cost;
            }
            else{
                $total_amount           = ($sub_total + $total_tax) - ($total_discount);
                $total_payable          = $total_amount + $shipping_cost;
            }

            if(array_key_exists('trx_id',$orderAmount)){
                OrderDetail::whereHas('order', function ($q) use ($orderAmount){
                    $q->where('trx_id',$orderAmount['trx_id']);
                })->delete();
                Order::where('trx_id',$orderAmount['trx_id'])->delete();
            }
            $user = User::where('id',$orderAmount['user_id'])->first();

            $orderCreate = Order::create([
                'seller_id'                     => $seller_id,
                'created_by'                    => authId(),
                'user_id'                       => $user->id,
                'shipping_address'              => $shippingAddress ?  : [],
                'billing_address'               => $shippingAddress  ?  : [],
                'sub_total'                     => $sub_total,
                'discount'                      => $total_discount,
                'payment_type'                  => $orderAmount['payment_type'] ?: null,
                'payment_status'                => $orderAmount['payment_status'],
                'is_draft'                      => $orderAmount['is_draft'],
                'status'                        => $orderAmount['is_draft'] == 1 ? 0 : 1,
                'offline_method_id'             => $offline_method_details['id'] ?: null,
                'offline_method_file'           => isset($data) && array_key_exists('image', $data) ? $data['image'] : [],
                'payment_details'               => $offline_method_details['payment_details']['name'] ?  $offline_method_details['payment_details'] : [],
                'trx_id'                        => $trx_id,
                'delivery_status'               => $orderAmount['payment_type'] == 'cash_payment' ? 'delivered' : 'pending',
                'total_tax'                     => $total_tax,
                'shipping_cost'                 => $shipping_cost,
                'total_amount'                  => $total_amount,
                'total_payable'                 => $total_payable,
                'code'                          => settingHelper('order_prefix') . '-' . $this->generate_random_string(10, 'number'),
                'date'                          => now(),
            ]);

            foreach ($seller_products as $item) {
                OrderDetail::create([
                    'order_id'          => $orderCreate->id,
                    'product_id'        => $item['product_id'],
                    'variation'         => $item['variation'] ?: null,
                    'price'             => $item['price'],
                    'coupon_discount'   => $orderAmount['coupon_discount'],
                    'shipping_cost'     => $orderAmount['shipping_cost'],
                    'tax'                 => $item['tax'] == null ? 0 : $item['tax'],
//                      'discount'          => $request->orderAmount['discount'] == null ? 0 :$request->orderAmount['discount'],
                    'discount'          => 0,
                    'quantity'          => $item['quantity'],
                ]);
            }
        }

        if ($orderAmount['payment_type'] == 'cash_payment')
        {
            $this->wallet->manageDeliveredOrder($orderCreate);
        }
        $orderCreate['customer_name'] = $user->full_name;
        $orderCreate['order_date'] = Carbon::parse(now())->format('d M  h:m A');
;

        return $orderCreate;
    }

    public function UpdateDraft($request)
    {
        DB::beginTransaction();
        try{
            $carts = json_decode($request->carts,true);
            $shippingAddress = json_decode($request->shippingAddress,true);
            $offline_method_details = json_decode($request->offline_method_details,true);
            $orderAmount = json_decode($request->orderAmount,true);

            $seller_group = array();
            foreach ($carts as $key =>$value ) {
                $seller_group[$value['seller_id']][] = $value;
            }

            if (array_key_exists('image', $request->all()) && $request->file('image')) {
                $file = $this->saveFile($request->file('image'),'pos_file');
                $data['image'] = $file;
            }

            if($orderAmount['is_draft'] == 0){
                foreach ($carts as $cart):
                    $product = $this->product->get($cart['product_id']);
                    if ($product != null):
                        $product->current_stock -= $cart['quantity'];
                        $product->save();
                    endif;
                    $product_stock = ProductStock::where('product_id',$cart['product_id'])
                        ->where('name', $cart['variation'])
                        ->first();
                    if ($product_stock != null) :
                        $product_stock->current_stock -= $cart['quantity'];
                        $product_stock->save();
                    endif;
                endforeach;
            }

            $trx_id = $offline_method_details['trx_id'] ?: Str::random(21);

            foreach ($seller_group as $seller_id => $seller_products) {
                $sub_total = $total_discount = $total_tax = $shipping_cost = 0;

                foreach ($seller_products as $item) {
                    $sub_total          += $item['price'] * $item['quantity'];
                    $total_discount      = $orderAmount['discount'];
                    $total_tax           = $orderAmount['taxes'];

                }
                $shipping_cost          = $orderAmount['shipping_fee'];

                if (settingHelper('vat_type') == 'after_tax' && settingHelper('vat_and_tax_type') == 'order_base')
                {
                    $total_amount           = ($sub_total) - ($total_discount);
                    $total_payable          = $total_amount + $total_tax + $shipping_cost;
                }
                else{
                    $total_amount           = ($sub_total + $total_tax) - ($total_discount);
                    $total_payable          = $total_amount + $shipping_cost;
                }


                if(array_key_exists('trx_id',$orderAmount)){
                    $orders = Order::where('trx_id',$orderAmount['trx_id'])->get();
                    foreach($orders as $order){
                        Order::where('id',$order->id)->delete();
                    }
                }
                $orderCreate = Order::create([
                    'seller_id'                     => $seller_id,
                    'created_by'                    => authId(),
                    'user_id'                       => $orderAmount['user_id'],
                    'shipping_address'              => $shippingAddress != [] ? $shippingAddress : [],
                    'billing_address'               => $shippingAddress != [] ? $shippingAddress : [],
                    'sub_total'                     => $sub_total,
                    'discount'                      => $total_discount,
                    'payment_type'                  => $orderAmount['payment_type'] ?: null,
                    'payment_status'                => $orderAmount['payment_status'],
                    'is_draft'                      => $orderAmount['is_draft'],
                    'status'                        => $orderAmount['is_draft'] == 1 ? 0 : 1,
                    'offline_method_id'             => $offline_method_details['id'] ?: null,
                    'offline_method_file'           => isset($data) && array_key_exists('image', $data) ? $data['image'] : [],
                    'payment_details'               => $offline_method_details['payment_details']['name'] ?  $offline_method_details['payment_details'] : [],
                    'trx_id'                        => $trx_id,
                    'total_tax'                     => $total_tax,
                    'shipping_cost'                 => $shipping_cost,
                    'total_amount'                  => $total_amount,
                    'total_payable'                 => $total_payable,
                    'code'                          => settingHelper('order_prefix') . '-' . $this->generate_random_string(10, 'number'),
                    'date'                          => now(),
                ]);
                foreach($orders as $order){
                    $details = OrderDetail::where('order_id',$order->id)->get();
                    foreach($details as $detail){
                        OrderDetail::where('id',$detail->id)->delete();
                    }
                }
                foreach ($seller_products as $item) {
                    OrderDetail::create([
                        'order_id'          => $orderCreate->id,
                        'product_id'        => $item['product_id'],
                        'variation'         => $item['variation'] ?: null,
                        'price'             => $item['price'],
                        'coupon_discount'   => $orderAmount['coupon_discount'],
                        'shipping_cost'     => $orderAmount['shipping_cost'],
                        'tax'                 => $item['tax'] == null ? 0 : $item['tax'],
//                      'discount'          => $request->orderAmount['discount'] == null ? 0 :$request->orderAmount['discount'],
                        'discount'          => "0",
                        'quantity'          => $item['quantity'],
                    ]);
                }
            }


            DB::commit();
            return $orderCreate;
        }catch(\Exception $e){
            DB::rollback();
            return false;
        }

    }
    public function draftList($request)
    {
        $draftList = Order::with('user:id,first_name,last_name')->groupBy('trx_id')
            ->selectRaw('sum(sub_total) as sub_total, sum(total_amount) as total_amount,sum(total_payable) as total_payable,sum(discount) as discount,
            sum(total_tax) as total_tax,trx_id as trx,date,sum(shipping_cost) as shipping_cost,user_id,id,is_draft,code')
            ->where('created_by', authId())->where('is_draft',1)
            ->when(array_key_exists('user_id',$request),function ($q) use ($request){
                $q->where('user_id',$request['user_id']);
            })
            ->latest()->paginate(16);
        return $draftList;
    }

    public function draftToCart($trxId)
    {
        $allProduct = [];
        $order = Order::with('user:id,first_name,last_name')
            ->where('created_by', authId())->where('trx_id', $trxId)->latest()->first();

        if ($order)
        {
            $shipping_cost = $order->shipping_cost;
            $discount = $order->discount;
            $coupon_discount = $order->coupon_discount;

            $orderDetails = OrderDetail::with('product.productLanguages','order','stocks')->whereHas('order',function($query) use($trxId){
                $query->where('trx_id',$trxId);
            })->get();

            $stocks = ProductStock::whereHas('product.orderDetails', function ($q) use($orderDetails){
                $q->whereIn('id',$orderDetails->pluck('id')->toArray())->where(function($query){
                    $query->whereColumn('order_details.variation','=','product_stocks.name')->orWhereNULL('order_details.variation');
                });
            })->get();

            $products = [];
            foreach ($stocks as $key=> $stock) {
//            $stock = $orderDetail->stocks->where('product_id',$orderDetail->product_id)->first();
                $products['id'] = $stock->id;
                $products['trx'] = $trxId;
                $order_detail = $orderDetails->where('variation',$stock->name)->where('product_id',$stock->product_id)->first();
                if(!$order_detail)
                {
                    $order_detail = $orderDetails->where('variation',NULL)->where('product_id',$stock->product_id)->first();
                }
                $products['product'] = [
                    'price'                  => $order_detail->price,
                    'variation'              => $order_detail->variation ?: 0,
                    'tax'                    => $order_detail->tax,
                    'qty'                    => $order_detail->quantity,
                    'sub_total'              => $order_detail->price * $order_detail->quantity,
                    'seller_id'              => $order_detail->order->seller_id,
                    'product_id'             => $order_detail->product_id,
                    'product_name'           => $order_detail->product->product_name,
                    'has_variant'            => $order_detail->product->has_variant,
                    'discount'               => $order_detail->discount * $order_detail->quantity,
                    'id'                     => $stock->id,
                    'current_stock'          => $order_detail->product->has_variant == 1 ? (int)$stock->current_stock : (int)$order_detail->product->current_stock,
                ];
                $allProduct[] = $products;

            }
            $address    = count($orderDetails) > 0 ?  $orderDetails->first()->order->shipping_address : [];

            return [
                'products'      => $allProduct,
                'order'         => [
                    'sub_total'     => $order->sub_total,
                    'tax'           => $order->total_tax,
                    'shipping_cost' => $shipping_cost,
                    'discount'      => $discount,
                    'coupon_discount'      => $coupon_discount,
                    'total_amount'  => $order->total_amount,
                    'total_payable' => $order->total_payable,
                    'shipping_address' => $order->shipping_address,
                ],
                'address'       => $address
            ];
        }
        else{
            return [
                'products'      => $allProduct,
                'order'         => [
                    'sub_total'     => 0,
                    'tax'           => 0,
                    'shipping_cost' => 0,
                    'discount'      => 0,
                    'coupon_discount'      => 0,
                    'total_amount'  => 0,
                    'total_payable' => 0,
                    'shipping_address' => [],
                ],
                'address'       => []
            ];
        }
    }

    public function deleteDraft($request)
    {
        $orders = Order::where('trx_id',$request->trxId)->get();
        foreach($orders as $order){
            Order::where('id',$order->id)->delete();
        }

        foreach($orders as $order){
            $details = OrderDetail::where('order_id',$order->id)->get();
            foreach($details as $detail){
                OrderDetail::where('id',$detail->id)->delete();
            }
        }
        return $this->draftList($request);
    }

    public function invoiceDownload($id)
    {
        try {
            return Order::with('orderDetails.product')->find($id);

        } catch (\Exception $e) {
            return false;
        }
    }

    public function recentOrders($request)
    {
        $recentOrders = Order::with('user:id,first_name,last_name')->groupBy('trx_id')
            ->selectRaw('sum(sub_total) as sub_total, sum(total_amount) as total_amount,sum(total_payable) as total_payable,sum(discount) as discount,
            sum(total_tax) as total_tax,trx_id as trx,date,sum(shipping_cost) as shipping_cost,user_id,id,is_draft,code')
            ->where('created_by', authId())->where('is_draft',0)
            ->when(array_key_exists('user_id',$request),function ($q) use ($request){
                $q->where('user_id',$request['user_id']);
            })
            ->latest()->paginate(16);
        return $recentOrders;
    }
}

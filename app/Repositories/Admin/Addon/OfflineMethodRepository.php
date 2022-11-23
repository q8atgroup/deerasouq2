<?php

namespace App\Repositories\Admin\Addon;

use App\Models\OfflineMethodLanguage;
use App\Repositories\Interfaces\Admin\Addon\OfflineMethodInterface;
use App\Models\OfflineMethod;
use App\Traits\ImageTrait;
use DB;

class OfflineMethodRepository implements OfflineMethodInterface
{
    use ImageTrait;

    public function get($id)
    {
        return OfflineMethod::find($id);
    }

    public function getByLang($id, $lang)
    {
        if($lang == null):
            $methodByLang = OfflineMethodLanguage::with('offlineMethod')->where('lang', 'en')->where('offline_method_id', $id)->first();
        else:
            $methodByLang = OfflineMethodLanguage::with('offlineMethod')->where('lang', $lang)->where('offline_method_id', $id)->first();
            if (blank($methodByLang)):
                $methodByLang = OfflineMethodLanguage::with('offlineMethod')->where('lang', 'en')->where('offline_method_id', $id)->first();
                $methodByLang['translation_null'] = 'not-found';
            endif;
        endif;

        return $methodByLang;
    }

    public function all()
    {
        return OfflineMethod::latest();
    }

    public function paginate($request, $limit)
    {
        return $this->all()->paginate($limit);
    }

    public function store($request)
    {
        DB::beginTransaction();
        try {
            $method = new OfflineMethod();
            $banks_details = [];
            if ($request->type == 'bank_payment'):
                foreach ($request->bank_name as $key => $bank_name):
                    $bank['bank_name']      = $request->bank_name[$key];
                    $bank['bank_branch']    = $request->bank_branch[$key];
                    $bank['account_holder_name'] = $request->account_holder_name[$key];
                    $bank['account_number'] = $request->account_number[$key];
                    $bank['routing_number'] = $request->routing_number[$key];
                    array_push($banks_details, $bank);
                endforeach;
            endif;
            $method->bank_details   = $banks_details;
            $method->type           = $request->type;

            if ($request->thumbnail != ''):
                $files  = $this->getImage($request->thumbnail);
                if ($files):
                    $method->thumbnail        = $files;
                    $method->thumbnail_id     = $request->thumbnail;
                else:
                    $method->thumbnail        = [];
                endif;
            else:
                $method->thumbnail        = [];
            endif;

            $method->save();

            $request['offline_method_id'] = $method->id;
            if ($request->lang == ''):
                $request['lang']    = 'en';
            endif;

            $this->storeLanguage($request);

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    public function update($request)
    {
//        dd($request->all());
        DB::beginTransaction();
        try {
            $method                 = $this->get($request->offline_method_id);
            $banks_details = [];
            if ($request->type == 'bank_payment'):
                foreach ($request->bank_name as $key => $bank_name):
                    $bank['bank_name']      = $request->bank_name[$key];
                    $bank['bank_branch']    = $request->bank_branch[$key];
                    $bank['account_holder_name'] = $request->account_holder_name[$key];
                    $bank['account_number'] = $request->account_number[$key];
                    $bank['routing_number'] = $request->routing_number[$key];
                    array_push($banks_details, $bank);
                endforeach;
            endif;
            $method->bank_details   = $banks_details;
            $method->type           = $request->type;

            if ($request->thumbnail != ''):
                $files  = $this->getImage($request->thumbnail);
                if ($files):
                    $method->thumbnail        = $files;
                    $method->thumbnail_id     = $request->thumbnail;
                else:
                    $method->thumbnail        = [];
                    $method->thumbnail_id     = null;
                endif;
            else:
                $method->thumbnail        = [];
                $method->thumbnail_id     = null;
            endif;

            $method->save();

            if ($request->offline_method_lang_id == '') :
                $this->storeLanguage($request);
            else:
                $this->updateLanguage($request);
            endif;

            DB::commit();
            return true;

        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    public function statusChange($request)
    {
        DB::beginTransaction();
        try {
            $method            = $this->get($request['id']);
            $method->status    = $request['status'];
            $method->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    //language functions
    public function getLanguage($id)
    {
        return OfflineMethodLanguage::find($id);
    }

    public function storeLanguage($request)
    {
        DB::beginTransaction();
        try {
            $methodLang                      = new  OfflineMethodLanguage();
            $methodLang->offline_method_id   = $request->offline_method_id;
            $methodLang->lang                = $request->lang;
            $methodLang->name                = $request->name;
            $methodLang->instructions        = $request->instructions;
            $methodLang->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }
    }

    public function updateLanguage($request)
    {
        DB::beginTransaction();
        try {
            $methodLang                      = $this->getLanguage($request->offline_method_lang_id);
            $methodLang->offline_method_id   = $request->offline_method_id;
            $methodLang->name                = $request->name;
            $methodLang->instructions        = $request->instructions;
            $methodLang->save();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollback();
            return false;
        }

    }

    public function activeMethods()
    {
        return OfflineMethod::with('currentLanguage')->where('status',1)->get();
    }
}


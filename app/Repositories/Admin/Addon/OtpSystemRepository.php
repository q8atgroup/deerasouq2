<?php

namespace App\Repositories\Admin\Addon;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Cache;
use DB;

class OtpSystemRepository
{

    public function get($id)
    {
        return SmsTemplate::findOrFail($id);
    }

    public function all()
    {
        return SmsTemplate::all();
    }

    public function update($request)
    {
        DB::beginTransaction();
        try {
            $template           = $this->get($request->id);
            $template->sms_body = str_replace("\r\n",'', $request->sms_body);
            $template->save();

            Cache::forget('smsTemplates');

            DB::commit();
            return true;
        } catch (\Exception $e){
            DB::rollback();
            return false;
        }
    }

    public function statusChange($request)
    {
        DB::beginTransaction();
        try {
            $template           = $this->get($request['id']);
            $template->status   = $request['status'];
            $template->save();

            DB::commit();
            return true;
        } catch (\Exception $e){
            DB::rollback();
            return false;
        }

    }
}

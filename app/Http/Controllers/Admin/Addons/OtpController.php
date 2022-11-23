<?php

namespace App\Http\Controllers\Admin\Addons;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Setup\OtpSettingRequest;
use App\Repositories\Admin\Addon\OtpSystemRepository;
use App\Repositories\Interfaces\Admin\LanguageInterface;
use App\Repositories\Interfaces\Admin\SettingInterface;
use App\Traits\SmsSenderTrait;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    use SmsSenderTrait;

    private $lanuages;
    private $settings;
    private $otp;

    public function __construct(LanguageInterface $languages, SettingInterface $settings, OtpSystemRepository $otp)
    {
        $this->lanuages = $languages;
        $this->settings = $settings;
        $this->otp      = $otp;
    }

    public function otpSetting()
    {
        return view('admin.settings.otp.index');
    }

    public function otpSettingUpdate(OtpSettingRequest $request)
    {
        if (isDemoServer()):
            Toastr::info(__('This function is disabled in demo server.'));
            return redirect()->back();
        endif;
        if ($this->settings->update($request)):
            Toastr::success(__('Setting Updated Successfully'));
            return redirect()->back()->withInput();
        else:
            Toastr::error(__('Something went wrong, please try again'));
            return redirect()->back()->withInput();
        endif;
    }

    public function smsTemplates()
    {
        $available_languages = $this->lanuages->all()->orderBy('id','asc')->get();
        $sms_templates       = $this->otp->all();

        return view('admin.settings.otp.sms-templates', compact('available_languages','sms_templates'));
    }

    public function smsTemplateUpdate(Request $request)
    {
        if (isDemoServer()):
            Toastr::info(__('This function is disabled in demo server.'));
            return redirect()->back();
        endif;

        if ($this->otp->update($request)):
            Toastr::success(__('Setting Updated Successfully'));
            return redirect()->back()->withInput();
        else:
            Toastr::error(__('Something went wrong, please try again'));
            return redirect()->back();
        endif;
    }
    public function statusChange(Request $request)
    {
        if (isDemoServer()):
            $response['message']    = __('This function is disabled in demo server.');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        endif;

        if(!checkEmptyProvider($request['data']['id'])):
            $response['message']    = __('You can active this service when you will configure all credentials');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        else:
            try {
                if ($this->settings->statusChange($request['data'])):
                    $response['message']    = __('Updated Successfully');
                    $response['title']      = __('Success');
                    $response['status']     = 'success';
                    $response['data']     = 'success';
                    return response()->json($response);
                endif;
            } catch (\Exception $e){
                $response['message']    = __('Something went wrong, please try again');
                $response['title']      = __('Ops..!');
                $response['status']     = 'error';
                return response()->json($response);
            }
        endif;
    }

    public function templateStatusChange(Request $request){
        if (isDemoServer()):
            $response['message']    = __('This function is disabled in demo server.');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        endif;

        if ($this->otp->statusChange($request['data'])):
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

    public function testPage(Request $request){
        $type = $request->type;
        return view('admin.settings.otp.test-number',compact('type'));
    }
    public function sendNumber(Request $request){
        if (isDemoServer()):
            $response['message']    = __('This function is disabled in demo server.');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        endif;

        if ($this->test($request)):
            $response['message']    = __('Text sms sent successfully');
            $response['title']      = __('Success');
            $response['status']     = 'success';
            return response()->json($response);
        else:
            $response['message']    = __('Unable to send, please check your provider credentials');
            $response['title']      = __('Ops..!');
            $response['status']     = 'error';
            return response()->json($response);
        endif;
    }
}

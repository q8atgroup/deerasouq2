<?php

namespace App\Http\Controllers\Admin\Addons;

use App\Http\Controllers\Controller;
use App\Http\Requests\RewardSystemRequest;
use App\Repositories\Interfaces\Admin\Product\CategoryInterface;
use App\Repositories\Interfaces\Admin\RewardSystemInterface;
use App\Repositories\Interfaces\Admin\SellerInterface;
use App\Repositories\Interfaces\Admin\SettingInterface;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Sentinel;

class RewardSystemController extends Controller
{
    private $reward;
    private $setting;

    public function __construct(RewardSystemInterface $reward,
                                SettingInterface $setting){

        $this->reward           = $reward;
        $this->setting          = $setting;
    }
    public function index(){
        $products           = $this->reward->paginate(get_pagination('index_form_paginate'));
        return view('admin.rewards.set-reward',compact('products'));
    }

    public function userRewards(){
        $reward_users           = $this->reward->rewardUser(get_pagination('pagination'));
        return view('admin.rewards.user-rewards-index',compact('reward_users'));
    }

    public function userRewardView($id){
        $rewards = $this->reward->byUser($id,get_pagination('index_form_paginate'));
        return view('admin.rewards.user-reward-view',compact('rewards'));
    }

    public function rewardConfig(){
        return view('admin.rewards.reward-configuration');
    }

    public function storeRewardConfig(Request $request){
        if (isDemoServer()):
            Toastr::info(__('This function is disabled in demo server.'));
            return redirect()->back();
        endif;

        if($this->setting->update($request)):
            Toastr::success(__('Reward Configured Successfully'));
            return redirect()->back();
        else:
            Toastr::error(__('Something went wrong, please try again'));
            return back()->withInput();
        endif;
    }

    public function setRewardBy(RewardSystemRequest $request){
        if (isDemoServer()):
            Toastr::info(__('This function is disabled in demo server.'));
            return redirect()->back();
        endif;

        if($this->reward->setRewardBy($request)):
            Toastr::success(__('Reward Updated Successfully'));
            return redirect()->back();
        else:
            Toastr::error(__('Something went wrong, please try again'));
            return back()->withInput();
        endif;
    }

    public function updateReward(RewardSystemRequest $request){
        if (isDemoServer()):
            Toastr::info(__('This function is disabled in demo server.'));
            return redirect()->back();
        endif;
        if($this->reward->updateReward($request)):
            Toastr::success(__('Reward Updated Successfully'));
            return redirect()->back();
        else:
            Toastr::error(__('Something went wrong, please try again'));
            return back()->withInput();
        endif;
    }

    public function convertReward(Request $request,RewardSystemInterface $rewardSystem): \Illuminate\Http\JsonResponse
    {
        try {
            $reward = Sentinel::getUser()->reward;
            if ($request->reward > $reward->rewards):
                return response()->json([
                    'error' => __('You don not have enough reward point')
                ]);
            endif;
            $reward = $rewardSystem->convertReward($request->all());
            $data = [
                'user' => $reward->user,
                'reward' => $reward,
                'success' => __('reward_convert'),
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Something went wrong, please try again')
            ]);
        }
    }

    public function rewardHistory(RewardSystemInterface $rewardSystem): \Illuminate\Http\JsonResponse
    {
        try {
            $history = $rewardSystem->rewardHistory();
            $data = [
                'reward' => $history['reward'],
                'reward_details' => $history['reward_details'],
            ];
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('Ops..!')
            ]);
        }
    }


}

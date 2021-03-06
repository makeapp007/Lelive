<?php

namespace App\Http\Controllers;

use App\Models\Playinfo;
use App\Facades\Lecloud;
use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Liveinfo;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:admin');
        view()->share('title','管理员后台');
    }

    public function getIndex()
    {
        $user_count = User::count();
        $act_count = Liveinfo::count();

        return view('admin.config.index',[
            'user_number' => $user_count,
            'act_number' => $act_count,
        ]);
    }

    public function getActivity(){
        $livingUser = Liveinfo::select('id','uid','title','activityId')->get();
        $livingInfo = [];

        foreach ($livingUser as $user){
            $arr['id'] = $user['id'];
            $arr['uid'] = $user['uid'];
            $arr['title'] = $user['title'];
            $arr['activityId'] = $user['activityId'];
            array_push($livingInfo,$arr);
        }

        return view('admin.config.activity',[
            'livingInfo' => $livingInfo,
        ]);
    }

    public function getActivityInfo($id){
        $live_info = Liveinfo::select('uid','title','description','activityId','ctime')->where('uid',$id)->first();
        $user_info = User::find($id);

        if($live_info) {
            $live_status = true;
            $act_info['uid'] = $live_info['uid'];
            $act_info['title'] = $live_info['title'];
            $act_info['description'] = $live_info['description'];
            $act_info['activityId'] = $live_info['activityId'];
            $act_info['ctime'] = date('Y-m-d H:i:s', $live_info['ctime']);
            $act_info['name'] = $user_info['name'];
            $act_info['email'] = $user_info['email'];
        }else{
            $live_status = false;
            $act_info = null;
        }

        return view('admin.config.activityinfo',[
            'live_status' => $live_status,
            'act_info' => $act_info,
        ]);
    }

    public function postStopActivity($id){
        $liveinfo = Liveinfo::select('activityId')->where('uid',$id)->first();
        $activityId = $liveinfo->activityId;
        Lecloud::stopActivity($activityId);
        if(Liveinfo::where('uid',$id)->delete()){
            return redirect()->to('admin/actinfo');
        }else{
            return redirect()->to("admin/actinfo/$id");
        }
    }

    public function getUsers(){
        $users = User::select('id','name','email')->where('status','1')->orderBy('id', 'desc')->simplePaginate(30);

        $all_user_info = [];
        foreach ($users as $user){
            $user_info['id'] = $user['id'];
            $user_info['name'] = $user['name'];
            $user_info['email'] = $user['email'];
            array_push($all_user_info,$user_info);
        }

        $previousPageUrl = $users->previousPageUrl();
        $nextPageUrl = $users->nextPageUrl();

        return view('admin.config.users',[
            'users_info' => $all_user_info,
            'previousPageUrl' => $previousPageUrl,
            'nextPageUrl' => $nextPageUrl
        ]);
    }

    public function postBlockUser($id){
        User::where('id',$id)->update(['status'=>0]);
        return redirect()->to('admin/users');
    }

    public function getBlockedUsers(){
        $users = User::select('id','name','email')->where('status','0')->orderBy('id', 'desc')->paginate(30);

        $all_user_info = [];
        foreach ($users as $user){
            $user_info['id'] = $user['id'];
            $user_info['name'] = $user['name'];
            $user_info['email'] = $user['email'];
            array_push($all_user_info,$user_info);
        }

        $previousPageUrl = $users->previousPageUrl();
        $nextPageUrl = $users->nextPageUrl();

        return view('admin.config.blocked',[
            'users_info' => $all_user_info,
            'previousPageUrl' => $previousPageUrl,
            'nextPageUrl' => $nextPageUrl
        ]);
    }

    public function postUnblockUser($id){
        User::where('id',$id)->update(['status'=>1]);
        return redirect()->to('admin/blocked');
    }

    public function getPlayInfo(Request $request){
        if($request->get('id')) {
            $playInfoId = $request->get('id');
            $playInfo = Playinfo::select('uid','activityId','ctime','videoId','videoUnique')->where('id',$playInfoId)->first();
            if(!$playInfo){
                return redirect()->to('admin/playinfo');
            }
            if(!$playInfo['videoId']){
                $json = Lecloud::getPlayInfo($playInfo['activityId']);
                $arr = json_decode($json,true);
                if($arr['machineInfo']){
                    Playinfo::where('id',$playInfoId)->update([
                        'videoId' => $arr['machineInfo'][0]['videoId'],
                        'videoUnique' => $arr['machineInfo'][0]['videoUnique']
                    ]);
                    $uu = Lecloud::getUU();
                    return view('admin.config.playvideo',[
                        'info' => '',
                        'uu' => $uu,
                        'videoUnique' => $arr['machineInfo'][0]['videoUnique']
                    ]);
                }else{
                    if((time()-$playInfo['ctime'])>86400){
                        Playinfo::where('id',$playInfoId)->delete();
                        return redirect()->to('admin/playinfo');
                    }else{
                        return view('admin.config.playvideo',[
                            'info' => '视频还在录制或者转码中',
                            'videoUnique'=>''
                        ]);
                    }
                }
            }else{
                $uu = Lecloud::getUU();
                return view('admin.config.playvideo',[
                    'info' => '',
                    'uu' => $uu,
                    'videoUnique' => $playInfo['videoUnique']
                ]);
            }
        }else {
            $playInfoList = Playinfo::select('id', 'uid', 'activityId', 'ctime')->orderBy('id', 'desc')->paginate(30);
            $playInfo = [];
            foreach ($playInfoList as $value) {
                $arr['id'] = $value['id'];
                $arr['uid'] = $value['uid'];
                $arr['activityId'] = $value['activityId'];
                $arr['ctime'] = date('Y-m-d H:i:s', $value['ctime']);
                array_push($playInfo, $arr);
            }

            $previousPageUrl = $playInfoList->previousPageUrl();
            $nextPageUrl = $playInfoList->nextPageUrl();

            return view('admin.config.playinfo', [
                'playInfo' => $playInfo,
                'previousPageUrl' => $previousPageUrl,
                'nextPageUrl' => $nextPageUrl
            ]);
        }
    }
}
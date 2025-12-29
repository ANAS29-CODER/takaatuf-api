<?php

namespace App\Http\Controllers\API\Profile;

use App\Http\Controllers\Controller;
use App\Services\ProfileService;
use Illuminate\Http\Request;


class ProfileController extends Controller
{
    //

    protected $profileService;

    public function __construct(ProfileService $profileService)
    {
        $this->profileService = $profileService;
    }

    public function showProfile()
    {
        // هنا يمكنك استرجاع معلومات المستخدم من قاعدة البيانات وعرضها في واجهة المستخدم
        $user = auth()->user();
        return response()->json($user);
    }

    public function updateProfile(Request $request)
{
    $data = $request->validate([
        'name' => 'required|string',
        'city_neighborhood' => 'required|string',
        'wallet_type' => 'nullable|string',
        'wallet_address' => 'nullable|string',
        'paypal_account' => 'nullable|string',
    ]);

    // ✅ اجبري profile_completed true طالما الحقول الأساسية موجودة
    $data['profile_completed'] = true;

    $user = $this->profileService->updateProfile(auth()->id(), $data);

    return response()->json($user);
}
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use App\Services\NotificationService;

class AdminAuthController extends Controller
{
    //test
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (Auth::guard('admin')->attempt($credentials, $request->remember)) {

            $admin = Auth::guard('admin')->user();

            NotificationService::send(
                'admin_login',
                'Admin Login',
                $admin->name . ' logged in successfully.'
            );

            return redirect()->route('admin.dashboard');
        }

        return back()->with('error', 'Invalid credentials');
    }

    public function logout(Request $request)
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login')->with('success', 'Login Successfully');
    }
}

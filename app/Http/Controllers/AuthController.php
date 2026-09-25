<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        // تنظيف بسيط يمنع فشل الدخول بسبب فراغ أو أحرف كبيرة بالبريد
        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => __('auth.credentials')])->onlyInput('email');
        }

        $request->session()->regenerate();

        // الأدمن يوجه مباشرة للوحة الإدارة، والعميل لحسابه
        if (auth()->user()->isAdmin()) {
            return redirect()->intended(url('/admin'))
                ->with('success', __('auth.welcome'));
        }

        return redirect()->intended(route('account.profile'))
            ->with('success', __('auth.welcome'));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        // تطبيع البريد قبل التحقق: الدخول يقارن البريد بحروف صغيرة (انظر login())،
        // فلو سُجّل «Ahmad@Mail.com» لما استطاع صاحبه الدخول أبدًا. التطبيع هنا
        // يجعل فحص unique أيضًا غير حساس لحالة الأحرف فلا يُنشأ حساب مكرر ميت.
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            ...$data,
            'role' => 'customer',
            'status' => 'active',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('success', __('auth.registered'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerate();

        return redirect()->route('home');
    }
}

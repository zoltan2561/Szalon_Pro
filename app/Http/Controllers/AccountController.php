<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function index(): View
    {
        return view('admin.account');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => 'required|string|min:12|max:200|confirmed',
            'password_confirmation' => 'required|string|max:200',
        ]);

        DB::transaction(function () use ($request, $data): void {
            $user = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'A jelenlegi jelszó időközben megváltozott.']);
            }
            $user->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }
        }, 5);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'A jelszavadat megváltoztattuk. Jelentkezz be az új jelszóval.');
    }
}

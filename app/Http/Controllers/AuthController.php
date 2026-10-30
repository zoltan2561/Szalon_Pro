<?php

namespace App\Http\Controllers;

use App\Services\DemoAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function demo(Request $request, DemoAccess $access): RedirectResponse
    {
        $viewer = $access->viewer();
        abort_unless($viewer, 404);
        Auth::login($viewer);
        $this->regenerateSession($request);

        return redirect()->route('admin.today');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($data)) {
            throw ValidationException::withMessages(['email' => 'Az e-mail-cím vagy a jelszó hibás.']);
        }
        if ($request->user()->isDemoViewer() && app(DemoAccess::class)->viewer()?->id !== $request->user()->id) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(404);
        }
        $this->regenerateSession($request);

        return redirect()->intended(route('admin.today'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $destination = $request->user()?->isDemoViewer()
            ? ($request->input('return_to') === 'booking' ? 'booking.form' : 'home')
            : 'login';
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route($destination);
    }

    private function regenerateSession(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put('password_hash_web', Auth::guard()->hashPasswordForCookie($request->user()->getAuthPassword()));
    }
}
